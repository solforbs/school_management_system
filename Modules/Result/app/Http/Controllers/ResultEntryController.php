<?php

namespace Modules\Result\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
            'marks' => 'required|array',
            'marks.*' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|array',
            'remarks.*' => 'nullable|string|max:1000',
        ]);

        $examination = $this->scopedExaminations()->firstWhere('id', (int) $validated['examination_id']);
        abort_unless($examination, 403);

        $institution = Institution::find($examination->institution_id);
        abort_unless($institution, 422, 'This examination has no institution.');

        $students = $this->studentsIn($examination);
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

        DB::transaction(function () use ($marks, $remarks, $examination, $institution) {
            foreach ($marks as $studentId => $value) {
                Result::updateOrCreate(
                    ['examination_id' => $examination->id, 'student_id' => $studentId],
                    [
                        'institution_id' => $institution->id,
                        'class_id' => $examination->class_id,
                        'marks_obtained' => $value,
                        'grade' => ResultGrader::grade($examination, $value, $institution, $examination->schoolClass),
                        'remarks' => $remarks->get($studentId) ?: null,
                        'recorded_by' => Auth::id(),
                    ]
                );
            }
        });

        $this->checkReportCardCompletion($examination, $marks->keys());

        return redirect()
            ->route('result.entry.create', ['examination_id' => $examination->id])
            ->with('success', $marks->count().' result(s) saved for '.($examination->subject?->name ?? $examination->title).'.');
    }

    /**
     * A saved mark can complete a student's set for the term, which is what
     * makes their report card ready to send.
     *
     * @param  Collection<int, int>  $studentIds
     */
    private function checkReportCardCompletion(Examination $examination, $studentIds): void
    {
        if (! $examination->term || ! $examination->academic_year) {
            return;
        }

        $service = app(ReportCardCompletionService::class);

        Result::whereIn('student_id', $studentIds)
            ->where('examination_id', $examination->id)
            ->with('student')
            ->get()
            ->each(function ($result) use ($service, $examination) {
                if ($result->student) {
                    $service->handle($result->student, $examination->term, $examination->academic_year);
                }
            });
    }

    /**
     * The class being examined, roll-call order.
     */
    private function studentsIn(Examination $examination)
    {
        if (! $examination->class_id) {
            return collect();
        }

        return StudentDetails::with('student')
            ->where('class_id', $examination->class_id)
            ->get()
            ->sortBy(fn ($details) => [$details->admission_number, $details->student?->name])
            ->values();
    }

    /**
     * Examinations this viewer may enter marks for: a Teacher's own
     * subjects and their class's, everything at the school for a Director.
     */
    private function scopedExaminations()
    {
        $user = Auth::user();

        $query = Examination::with(['subject', 'schoolClass']);

        if ($user->hasRole('Teacher')) {
            $query->where('institution_id', $user->teacherUserDetails?->institution_id ?? 0);
            ResultAccessService::scopeExaminations($query, $user);
        } elseif (! isAdmin()) {
            $query->where('institution_id', currentInstitution()?->id ?? 0);
        }

        return $query->whereNotNull('class_id')->orderByDesc('exam_date')->get();
    }
}
