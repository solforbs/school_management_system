<?php

namespace Modules\Result\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Examinations\Models\Examination;
use Modules\Institution\Models\Institution;
use Modules\Result\Models\Result;
use Modules\Result\Services\ResultGrader;
use Modules\Student\Models\StudentDetails;

/**
 * Records CBC expectation bands for a whole class on a formative task.
 *
 * This is the write endpoint for the band entry sheet. The Volt view
 * (⚡band-entry.blade.php) renders a student list with EE/ME/AE/BE pill
 * pickers; submitting posts here.
 *
 * The contract with ResultGrader::fromBand() is: marks_obtained = null,
 * cbc_band = the band, grade = the band. The existing report card and
 * result display code reads `grade` either way, so nothing breaks.
 */
class BandEntryController extends Controller
{
    public function store(Request $request)
    {
        abort_unless(Auth::user()->can('create result'), 403);

        $validated = $request->validate([
            'examination_id' => 'required|exists:examinations,id',
            'class_id' => 'required|exists:classes,id',
            'subject_id' => 'nullable|exists:subjects,id',
            'bands' => 'required|array',
            'bands.*' => 'nullable|in:'.implode(',', ResultGrader::CBC_ALL_EVALUATIONS),
            'remarks' => 'nullable|array',
            'remarks.*' => 'nullable|string|max:1000',
        ]);

        $examination = $this->scopedExamination((int) $validated['examination_id']);
        abort_unless($examination, 403);

        // Only formative sittings use the band sheet.
        abort_unless(
            $examination->sitting && $examination->sitting->isFormative(),
            422,
            'Band entry is only available for formative / SBA assessments.'
        );

        $institution = Institution::findOrFail($examination->institution_id);
        $classId = (int) $validated['class_id'];
        $subjectId = isset($validated['subject_id']) ? (int) $validated['subject_id'] : $examination->subject_id;

        $rawBands = collect($validated['bands'] ?? []);
        $bands = $rawBands
            ->reject(fn ($v) => $v === null || $v === '')
            ->mapWithKeys(fn ($v, $studentId) => [(int) $studentId => strtoupper($v)]);

        $studentIds = StudentDetails::where('class_id', $classId)->pluck('student_id')->map(fn ($id) => (int) $id);

        // Reject any student not in the class — prevents crafted submissions.
        $strays = $rawBands->keys()->map(fn ($id) => (int) $id)->reject(fn ($id) => $studentIds->contains($id));
        abort_unless($strays->isEmpty(), 403);

        $remarks = collect($request->input('remarks', []));
        $clearedCount = 0;

        DB::transaction(function () use ($bands, $rawBands, $remarks, $examination, $institution, $classId, $subjectId, &$clearedCount) {
            // 1. Save or update selected bands
            foreach ($bands as $studentId => $band) {
                Result::updateOrCreate(
                    [
                        'examination_id' => $examination->id,
                        'student_id' => $studentId,
                        'subject_id' => $subjectId,
                    ],
                    array_merge(
                        ResultGrader::fromBand($band),
                        [
                            'institution_id' => $institution->id,
                            'class_id' => $classId,
                            'remarks' => $remarks->get($studentId) ?: null,
                            'recorded_by' => Auth::id(),
                        ]
                    )
                );
            }

            // 2. Clear any students explicitly submitted as empty (unchecked by teacher)
            $clearedStudentIds = $rawBands
                ->filter(fn ($v) => $v === null || $v === '')
                ->keys()
                ->map(fn ($id) => (int) $id);

            if ($clearedStudentIds->isNotEmpty()) {
                $query = Result::where('examination_id', $examination->id)
                    ->whereIn('student_id', $clearedStudentIds);

                if ($subjectId) {
                    $query->where('subject_id', $subjectId);
                } else {
                    $query->whereNull('subject_id');
                }

                $clearedCount = $query->delete();
            }
        });

        if ($bands->isEmpty() && $clearedCount === 0) {
            return redirect()
                ->route('assessment.band.entry', ['examination_id' => $examination->id, 'class_id' => $classId])
                ->with('error', 'No evaluations were selected, so nothing was saved.');
        }

        $message = match (true) {
            $bands->isNotEmpty() && $clearedCount > 0 => $bands->count().' band(s) recorded, '.$clearedCount.' cleared successfully.',
            $bands->isNotEmpty() => $bands->count().' band(s) recorded successfully.',
            default => $clearedCount.' evaluation(s) cleared successfully.',
        };

        return redirect()
            ->route('assessment.band.entry', [
                'examination_id' => $examination->id,
                'class_id' => $classId,
            ])
            ->with('success', $message);
    }

    private function scopedExamination(int $id): ?Examination
    {
        $query = Examination::with(['sitting', 'subject', 'schoolClass']);

        if (! isAdmin()) {
            $query->where('institution_id', currentInstitutionId() ?? 0);
        }

        return $query->find($id);
    }
}
