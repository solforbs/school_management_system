<?php

namespace Modules\ReportCard\Services;

use App\Models\User;
use Modules\ReportCard\Models\ReportCard;
use Modules\ReportCard\Support\TermParser;
use Modules\Result\Models\Result;
use Modules\Selections\Models\SubjectSelection;
use Modules\Student\Models\StudentDetails;
use Modules\Subject\Models\Subject;

class ReportCardCompletionService
{
    /**
     * Check whether a student now has a Result for every required subject
     * (compulsory + their own electives) within a given exam term, and if
     * so, mark their report card as "ready" to be sent (the actual send
     * happens later, on a delay - see the SendReadyReportCards command).
     */
    public function handle(User $student, string $term, int $academicYear): void
    {
        $studentDetails = StudentDetails::where('student_id', $student->id)->first();

        if (! $studentDetails) {
            return;
        }

        $requiredSubjectIds = $this->requiredSubjectIds($student, $studentDetails->institution_id);

        // Never mark a report "ready" against an empty requirement set -
        // e.g. no compulsory subjects configured and no electives picked.
        if ($requiredSubjectIds->isEmpty()) {
            return;
        }

        $coveredSubjectIds = $this->coveredSubjectIds($student, $term, $academicYear);

        if ($requiredSubjectIds->diff($coveredSubjectIds)->isNotEmpty()) {
            return;
        }

        ReportCard::firstOrCreate(
            ['student_id' => $student->id, 'term' => $term, 'academic_year' => $academicYear],
            [
                'institution_id' => $studentDetails->institution_id,
                'class_id' => (int) $studentDetails->class_id,
                'term_number' => TermParser::number($term),
                'status' => 'ready',
                'completed_at' => now(),
            ]
        );
    }

    /**
     * Whether the student still has a complete set of results for this
     * term - used to re-verify freshness right before actually sending,
     * since results may have been corrected/removed during the send delay.
     */
    public function isStillComplete(User $student, string $term, int $academicYear): bool
    {
        $studentDetails = StudentDetails::where('student_id', $student->id)->first();

        if (! $studentDetails) {
            return false;
        }

        $requiredSubjectIds = $this->requiredSubjectIds($student, $studentDetails->institution_id);

        if ($requiredSubjectIds->isEmpty()) {
            return false;
        }

        return $requiredSubjectIds->diff($this->coveredSubjectIds($student, $term, $academicYear))->isEmpty();
    }

    /**
     * The subjects this student already has a mark recorded for in the
     * given term. The subject now lives on the result itself; rows from
     * before that column fall back to their examination's subject.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function coveredSubjectIds(User $student, string $term, int $academicYear)
    {
        return Result::where('student_id', $student->id)
            ->whereHas('examination', fn ($q) => $q->where('term', $term)->where('academic_year', $academicYear))
            ->with('examination:id,subject_id')
            ->get()
            ->map(fn ($result) => $result->subject_id ?: $result->examination?->subject_id)
            ->filter()
            ->unique()
            ->values();
    }

    private function requiredSubjectIds(User $student, int $institutionId)
    {
        $compulsoryIds = Subject::where('institution_id', $institutionId)
            ->where('is_compulsory', true)
            ->where('is_active', true)
            ->pluck('id');

        $electiveIds = Subject::whereIn('id',
            SubjectSelection::where('student_id', $student->id)->pluck('subject_id')
        )->where('is_active', true)->pluck('id');

        return $compulsoryIds->merge($electiveIds)->unique();
    }
}
