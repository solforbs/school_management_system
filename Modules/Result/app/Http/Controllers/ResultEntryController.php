<?php

namespace Modules\Result\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Classes\Models\SchoolClass;
use Modules\Examinations\Models\Examination;
use Modules\Institution\Models\Institution;
use Modules\ReportCard\Services\ReportCardCompletionService;
use Modules\Result\Models\Result;
use Modules\Result\Services\ResultAccessService;
use Modules\Result\Services\ResultGrader;
use Modules\Student\Models\StudentDetails;

/**
 * The marks sheet: one examination, every student in the class, one screen.
 *
 * This is how a subject teacher actually works - the Form 2 maths teacher
 * marks the whole class's papers in one sitting and enters them in one go,
 * rather than opening the single-result form forty times. A class teacher
 * uses the same screen for any subject in their own class.
 */
class ResultEntryController extends Controller
{
    /**
     * Save the whole sheet: create what's new, correct what's changed,
     * leave blank rows alone.
     */
    public function store(Request $request)
    {
        abort_unless(Auth::user()->can('create result'), 403);

        $validated = $request->validate([
            'examination_id' => 'required|exists:examinations,id',
            'class_id' => 'nullable|exists:classes,id',
            'subject_id' => 'nullable|exists:subjects,id',
            'marks' => 'required|array',
            'marks.*' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|array',
            'remarks.*' => 'nullable|string|max:1000',
        ]);

        $examination = $this->scopedExaminations()->firstWhere('id', (int) $validated['examination_id']);
        abort_unless($examination, 403);

        $institution = Institution::find($examination->institution_id);
        abort_unless($institution, 422, 'This examination has no institution.');

        // The class and subject come from the sheet; a paper the Director
        // pinned to one class/subject supplies them as the fallback.
        $classId = (int) ($validated['class_id'] ?? $examination->class_id);
        $subjectId = isset($validated['subject_id']) || $examination->subject_id !== null
            ? (int) ($validated['subject_id'] ?? $examination->subject_id)
            : null;

        abort_unless($classId > 0, 422, 'Pick the class these marks are for.');

        // Re-verify server-side that this viewer actually owns these marks -
        // the dropdowns only ever offer their own classes/subjects, but a
        // crafted request could carry anything.
        if (Auth::user()->hasRole('Teacher')) {
            abort_unless(ResultAccessService::canGrade(Auth::user(), $classId, $subjectId), 403);
        }

        $schoolClass = SchoolClass::find($classId);
        abort_unless($schoolClass && $schoolClass->institution_id === $institution->id, 403);

        $students = $this->studentsIn($classId);
        $studentIds = $students->pluck('student_id')->map(fn ($id) => (int) $id);

        $marks = collect($validated['marks'])
            ->reject(fn ($value) => $value === null || $value === '')
            ->mapWithKeys(fn ($value, $studentId) => [(int) $studentId => (float) $value]);

        if ($marks->isEmpty()) {
            return redirect()
                ->route('result.entry.create', ['examination_id' => $examination->id])
                ->with('error', 'No marks were entered, so nothing was saved.');
        }

        $overMax = $marks->filter(fn ($value) => $examination->total_marks && $value > $examination->total_marks);

        if ($overMax->isNotEmpty()) {
            return redirect()
                ->route('result.entry.create', ['examination_id' => $examination->id])
                ->withInput()
                ->with('error', "Marks can't exceed the examination's total of {$examination->total_marks}. ".$overMax->count().' entry(ies) were over it, so nothing was saved.');
        }

        // Every id must belong to the class being marked - a hand-crafted
        // form could otherwise carry a student from another class entirely.
        $strays = $marks->keys()->reject(fn ($studentId) => $studentIds->contains($studentId));
        abort_unless($strays->isEmpty(), 403);

        $remarks = collect($request->input('remarks', []));
        $existing = Result::where('examination_id', $examination->id)
            ->when($subjectId !== null, fn ($q) => $q->where('subject_id', $subjectId))
            ->whereIn('student_id', $marks->keys())
            ->get()
            ->keyBy('student_id');

        // Amending a mark that's already recorded is an edit, not a
        // creation, and is gated as one.
        if ($existing->isNotEmpty()) {
            abort_unless(
                Auth::user()->can('edit result') || Auth::user()->can('update result'),
                403,
                'Some of these students already have a mark for this examination, and you may not amend recorded results.'
            );
        }

        DB::transaction(function () use ($marks, $remarks, $examination, $institution, $classId, $subjectId, $schoolClass) {
            foreach ($marks as $studentId => $value) {
                Result::updateOrCreate(
                    ['examination_id' => $examination->id, 'student_id' => $studentId, 'subject_id' => $subjectId],
                    [
                        'institution_id' => $institution->id,
                        'class_id' => $classId,
                        'marks_obtained' => $value,
                        'grade' => ResultGrader::grade($examination, $value, $institution, $schoolClass),
                        'remarks' => $remarks->get($studentId) ?: null,
                        'recorded_by' => Auth::id(),
                    ]
                );
            }
        });

        $this->checkReportCardCompletion($examination, $marks->keys(), $subjectId);

        return redirect()
            ->route('result.entry.create', array_filter([
                'examination_id' => $examination->id,
                'class_id' => $classId,
                'subject_id' => $subjectId,
            ]))
            ->with('success', $marks->count().' result(s) saved for '.($schoolClass?->name ?? $examination->title).'.');
    }

    /**
     * A saved mark can complete a student's set for the term, which is what
     * makes their report card ready to send.
     *
     * @param  Collection<int, int>  $studentIds
     */
    private function checkReportCardCompletion(Examination $examination, $studentIds, ?int $subjectId = null): void
    {
        if (! $examination->term || ! $examination->academic_year) {
            return;
        }

        $service = app(ReportCardCompletionService::class);

        Result::whereIn('student_id', $studentIds)
            ->where('examination_id', $examination->id)
            ->when($subjectId !== null, fn ($q) => $q->where('subject_id', $subjectId))
            ->with('student')
            ->get()
            ->each(function ($result) use ($service, $examination) {
                if ($result->student) {
                    $service->handle($result->student, $examination->term, $examination->academic_year);
                }
            });
    }

    /**
     * The class being marked, roll-call order.
     */
    private function studentsIn(int $classId)
    {
        if ($classId <= 0) {
            return collect();
        }

        return StudentDetails::with('student')
            ->where('class_id', $classId)
            ->get()
            ->sortBy(fn ($details) => [$details->admission_number, $details->student?->name])
            ->values();
    }

    /**
     * Examinations this viewer may enter marks for. A sitting is
     * institution-wide, so a Teacher sees every sitting at their school -
     * what they may actually grade within it is enforced per class/subject
     * at save time via ResultAccessService::canGrade().
     */
    private function scopedExaminations()
    {
        $user = Auth::user();

        $query = Examination::with(['subject', 'schoolClass']);

        if ($user->hasRole('Teacher')) {
            $query->where('institution_id', $user->teacherUserDetails?->institution_id ?? 0);
        } elseif (! isAdmin()) {
            $query->where('institution_id', currentInstitution()?->id ?? 0);
        }

        return $query->orderByDesc('exam_date')->get();
    }
}
