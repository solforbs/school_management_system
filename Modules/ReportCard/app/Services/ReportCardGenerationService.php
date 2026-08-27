<?php

namespace Modules\ReportCard\Services;

use Modules\Classes\Models\SchoolClass;
use Modules\ReportCard\Models\ReportCard;
use Modules\ReportCard\Support\TermParser;
use Modules\Result\Models\Result;
use Modules\Student\Models\StudentDetails;

/**
 * Building a class's report cards on demand.
 *
 * ReportCardCompletionService already raises a report card by itself, but
 * only once a learner has a mark for every required subject. That is the
 * right rule for sending parents a link unprompted; it is the wrong rule
 * for a teacher who wants to print what the class has so far, or who marks
 * a subject the school never listed as compulsory. This is the manual
 * path: it reports on whatever marks exist for the term.
 */
class ReportCardGenerationService
{
    public function __construct(private ReportCardPdfService $pdfService) {}

    /**
     * Generate (or refresh) a report card for every learner in a class for
     * one term, and render each to PDF.
     *
     * Existing report cards are updated rather than duplicated, so running
     * this again after correcting a mark re-renders with the new figure
     * instead of leaving a stale PDF behind.
     *
     * @return array{generated: int, skipped: int, students: int}
     */
    public function forClass(SchoolClass $schoolClass, string $term, int $academicYear): array
    {
        $students = StudentDetails::where('class_id', $schoolClass->id)
            ->where('institution_id', $schoolClass->institution_id)
            ->where('enrollment_status', 'active')
            ->get();

        $generated = 0;
        $skipped = 0;

        foreach ($students as $studentDetails) {
            // A learner with no marks at all this term has nothing to
            // report on. Raising an empty report card for them would only
            // put a blank PDF in front of a parent.
            if (! $this->hasResults((int) $studentDetails->student_id, $term, $academicYear)) {
                $skipped++;

                continue;
            }

            $reportCard = ReportCard::updateOrCreate(
                [
                    'student_id' => $studentDetails->student_id,
                    'term' => $term,
                    'academic_year' => $academicYear,
                ],
                [
                    'institution_id' => $schoolClass->institution_id,
                    'class_id' => $schoolClass->id,
                    'term_number' => TermParser::number($term),
                    'status' => 'ready',
                    'completed_at' => now(),
                ],
            );

            $this->pdfService->generate($reportCard);

            $generated++;
        }

        return [
            'generated' => $generated,
            'skipped' => $skipped,
            'students' => $students->count(),
        ];
    }

    private function hasResults(int $studentId, string $term, int $academicYear): bool
    {
        return Result::where('student_id', $studentId)
            ->whereHas('examination', fn ($query) => $query
                ->where('term', $term)
                ->where('academic_year', $academicYear))
            ->exists();
    }
}
