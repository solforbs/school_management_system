<?php

namespace Modules\Examinations\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Classes\Models\SchoolClass;
use Modules\Examinations\Models\Examination;
use Modules\Examinations\Models\ExamSitting;
use Modules\Result\Services\ResultAccessService;
use Modules\Subject\Models\Subject;

/**
 * Creates a formative assessment sitting (Classroom Assessment or SBA).
 *
 * A formative sitting differs from a summative one in two ways:
 *   1. It has no total_marks — there are no marks, only bands.
 *   2. SaveExamSitting's paper-fanout is skipped — the sitting holds
 *      one lightweight Examination row per subject (the band sheet), not
 *      one per class × subject.
 *
 * After creation the teacher is redirected to the band entry sheet where
 * they pick EE/ME/AE/BE for each student.
 */
class FormativeAssessmentController extends Controller
{
    public function store(Request $request)
    {
        abort_unless(Auth::user()->can('create examination'), 403);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'assessment_category' => 'required|in:classroom_assessment,sba',
            'class_id' => 'required|exists:classes,id',
            'subject_id' => 'required|exists:subjects,id',
            'term' => 'nullable|string|max:100',
            'academic_year' => 'nullable|integer|min:2000|max:2100',
            'assessment_date' => 'required|date',
            'notes' => 'nullable|string|max:1000',
        ]);

        $class = SchoolClass::findOrFail($validated['class_id']);
        $institutionId = $class->institution_id;

        if (! isAdmin()) {
            $userInstitutionId = currentInstitutionId();
            abort_unless($userInstitutionId === null || $userInstitutionId === $institutionId, 403);
        }

        $subject = Subject::where('id', $validated['subject_id'])
            ->where('institution_id', $institutionId)
            ->firstOrFail();

        if (Auth::user()->hasRole('Teacher')) {
            abort_unless(
                ResultAccessService::canGrade(Auth::user(), $class->id, $subject->id),
                403,
                'You are not assigned to record assessments for this class and subject.'
            );
        }

        $sitting = ExamSitting::create([
            'institution_id' => $institutionId,
            'title' => $validated['title'],
            'assessment_category' => $validated['assessment_category'],
            'class_id' => $class->id,
            'term' => $validated['term'] ?? null,
            'academic_year' => $validated['academic_year'] ?? now()->year,
            'notes' => $validated['notes'] ?? null,
        ]);

        // One lightweight examination row — no marks, no schedule.
        // The band entry sheet hangs off this examination.
        $examination = Examination::create([
            'institution_id' => $institutionId,
            'sitting_id' => $sitting->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'title' => $validated['title'],
            'subject_name' => $subject->name,
            'class_name' => $class->name,
            'term' => $validated['term'] ?? null,
            'exam_type' => null,
            'academic_year' => $validated['academic_year'] ?? now()->year,
            'exam_date' => $validated['assessment_date'],
            'total_marks' => null,
            'passing_marks' => null,
        ]);

        return redirect()
            ->route('assessment.band.entry', [
                'examination_id' => $examination->id,
                'class_id' => $class->id,
            ])
            ->with('success', "Assessment '{$sitting->title}' created. Now record bands for each student.");
    }
}
