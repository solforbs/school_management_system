<?php

namespace Modules\Examinations\Services;

use Illuminate\Support\Collection;
use Modules\Classes\Models\SchoolClass;
use Modules\Result\Models\Result;
use Modules\Result\Services\ResultGrader;
use Modules\Student\Models\StudentDetails;

/**
 * Service to aggregate multi-year Competency-Based Assessments (CBA) for Junior School (Grades 7–9).
 *
 * Implements KNEC's Cumulative SBA & Summative assessment framework:
 * - Grade 7 SBA: 20%
 * - Grade 8 SBA: 20%
 * - Grade 9 SBA: 20%
 *   => Total Continuous School-Based Assessment: 60%
 * - KJSEA Summative / National Exam (Grade 9): 40%
 *   => Total Composite Score: 100%
 */
class CbcCumulativeSbaService
{
    public const WEIGHT_G7_SBA = 20.0;

    public const WEIGHT_G8_SBA = 20.0;

    public const WEIGHT_G9_SBA = 20.0;

    public const WEIGHT_KJSEA_SUMMATIVE = 40.0;

    /**
     * Compute the weighted composite score and 8-point achievement level.
     *
     * @return array{
     *     g7_raw_pct: ?float,
     *     g7_weighted: ?float,
     *     g8_raw_pct: ?float,
     *     g8_weighted: ?float,
     *     g9_sba_raw_pct: ?float,
     *     g9_sba_weighted: ?float,
     *     total_sba_weighted: float,
     *     summative_raw_pct: ?float,
     *     summative_weighted: ?float,
     *     composite_percentage: float,
     *     sub_level: string,
     *     band: string,
     *     points: int,
     *     descriptor: string
     * }
     */
    public function computeCumulativeScore(
        ?float $g7Pct = null,
        ?float $g8Pct = null,
        ?float $g9SbaPct = null,
        ?float $summativePct = null,
    ): array {
        $g7Weighted = $g7Pct !== null ? round(($g7Pct / 100.0) * self::WEIGHT_G7_SBA, 2) : 0.0;
        $g8Weighted = $g8Pct !== null ? round(($g8Pct / 100.0) * self::WEIGHT_G8_SBA, 2) : 0.0;
        $g9SbaWeighted = $g9SbaPct !== null ? round(($g9SbaPct / 100.0) * self::WEIGHT_G9_SBA, 2) : 0.0;

        $totalSbaWeighted = round($g7Weighted + $g8Weighted + $g9SbaWeighted, 2);

        $summativeWeighted = $summativePct !== null
            ? round(($summativePct / 100.0) * self::WEIGHT_KJSEA_SUMMATIVE, 2)
            : 0.0;

        $composite = round($totalSbaWeighted + $summativeWeighted, 2);

        $subLevel = ResultGrader::subLevelForPercentage($composite);
        $band = ResultGrader::parentBand($subLevel) ?? 'BE';
        $points = ResultGrader::pointsFor($subLevel) ?? 1;
        $descriptor = ResultGrader::CBC_SUB_LEVELS[$subLevel]['label'] ?? '';

        return [
            'g7_raw_pct' => $g7Pct,
            'g7_weighted' => $g7Weighted,
            'g8_raw_pct' => $g8Pct,
            'g8_weighted' => $g8Weighted,
            'g9_sba_raw_pct' => $g9SbaPct,
            'g9_sba_weighted' => $g9SbaWeighted,
            'total_sba_weighted' => $totalSbaWeighted,
            'summative_raw_pct' => $summativePct,
            'summative_weighted' => $summativeWeighted,
            'composite_percentage' => $composite,
            'sub_level' => $subLevel,
            'band' => $band,
            'points' => $points,
            'descriptor' => $descriptor,
        ];
    }

    /**
     * Compute multi-year cumulative scores for a specific student across all assessed learning areas.
     *
     * @return Collection<string, array<string, mixed>>
     */
    public function getStudentCumulativeLedger(int $studentId, int $institutionId): Collection
    {
        $results = Result::where('student_id', $studentId)
            ->whereHas('examination', fn ($q) => $q->where('institution_id', $institutionId))
            ->with(['examination.schoolClass', 'examination.sitting', 'subject'])
            ->get();

        // Group by subject / learning area
        $bySubject = $results->groupBy(function (Result $r) {
            return $r->subject_id ?: ($r->subject?->name ?? $r->examination?->subject_name ?? 'Unknown');
        });

        $ledger = collect();

        foreach ($bySubject as $key => $subjectResults) {
            $first = $subjectResults->first();
            $subjectName = $first->subject?->name ?? $first->examination?->subject_name ?? 'Subject '.$key;
            $subjectCode = $first->subject?->code;
            $learningArea = $first->subject?->learning_area;

            $g7Pct = $this->extractAveragePercentage($subjectResults, 7, isSba: true);
            $g8Pct = $this->extractAveragePercentage($subjectResults, 8, isSba: true);
            $g9SbaPct = $this->extractAveragePercentage($subjectResults, 9, isSba: true);
            $summativePct = $this->extractAveragePercentage($subjectResults, 9, isSba: false);

            $cumulative = $this->computeCumulativeScore($g7Pct, $g8Pct, $g9SbaPct, $summativePct);

            $ledger->put((string) $key, [
                'subject_id' => $first->subject_id,
                'subject_name' => $subjectName,
                'subject_code' => $subjectCode,
                'learning_area' => $learningArea,
                'cumulative' => $cumulative,
            ]);
        }

        return $ledger->sortBy('subject_name');
    }

    /**
     * Extracts average percentage for a given grade level (7, 8, 9) and assessment mode (SBA vs Summative).
     *
     * @param  Collection<int, Result>  $results
     */
    private function extractAveragePercentage(Collection $results, int $targetGrade, bool $isSba): ?float
    {
        $matching = $results->filter(function (Result $result) use ($targetGrade, $isSba) {
            $exam = $result->examination;
            if (! $exam) {
                return false;
            }

            $class = $exam->schoolClass;
            $gradeDetected = $this->detectGradeLevel($class?->name, $class?->level);

            if ($gradeDetected !== $targetGrade) {
                return false;
            }

            $sitting = $exam->sitting;
            $category = $sitting?->assessment_category ?? 'summative';

            if ($isSba) {
                return in_array($category, ['sba', 'classroom_assessment'], true);
            }

            return in_array($category, ['summative', 'national'], true);
        });

        if ($matching->isEmpty()) {
            return null;
        }

        $percentages = $matching->map(function (Result $r) {
            if ($r->marks_obtained !== null && $r->examination && $r->examination->total_marks > 0) {
                return ($r->marks_obtained / $r->examination->total_marks) * 100.0;
            }

            if ($r->cbc_band) {
                $points = ResultGrader::pointsFor($r->cbc_band);
                if ($points !== null) {
                    // Map points 1-8 to middle percentage of band
                    return ($points / 8.0) * 100.0;
                }
            }

            return null;
        })->filter();

        return $percentages->isNotEmpty() ? round($percentages->avg(), 2) : null;
    }

    /**
     * Helper to detect Grade 7, 8, 9 from class name or level.
     */
    public function detectGradeLevel(?string $name, ?string $level = null): ?int
    {
        $str = ($name ?? '').' '.($level ?? '');

        if (preg_match('/(?:Grade|Class|Yr|Year)\s*([789])/i', $str, $matches)) {
            return (int) $matches[1];
        }

        if (preg_match('/\b([789])\b/', $str, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Export-ready CBA summary payload for a cohort / class.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function buildClassCohortExport(SchoolClass $schoolClass): Collection
    {
        $schoolClass->loadMissing('institution');
        $students = StudentDetails::where('class_id', $schoolClass->id)
            ->with(['student', 'schoolClass'])
            ->get();

        $rows = collect();

        foreach ($students as $studentDetail) {
            $ledger = $this->getStudentCumulativeLedger($studentDetail->student_id, $schoolClass->institution_id);

            $rows->push([
                'student_id' => $studentDetail->student_id,
                'admission_number' => $studentDetail->admission_number,
                'student_name' => $studentDetail->student?->name ?? 'Unknown',
                'nemis_upi' => $studentDetail->student_number,
                'class_name' => $schoolClass->name,
                'subjects_count' => $ledger->count(),
                'ledger' => $ledger,
            ]);
        }

        return $rows;
    }
}
