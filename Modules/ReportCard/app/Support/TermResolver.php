<?php

namespace Modules\ReportCard\Support;

use Modules\Classes\Models\SchoolClass;
use Modules\Examinations\Models\Examination;

/**
 * Which term a class is currently in, and which one came before it.
 *
 * There is no "current term" setting anywhere in the schema, so it is read
 * off the work the class has actually done: the most recent term it has
 * papers for. That is also the honest answer - a term a class has not sat
 * a single paper in is not a term you can report on.
 */
class TermResolver
{
    /**
     * The term a class is in now, or null when it has no dated papers at
     * all to judge by.
     *
     * @return array{term: string, academic_year: int, term_number: int|null}|null
     */
    public static function currentFor(SchoolClass $schoolClass): ?array
    {
        $terms = Examination::where('class_id', $schoolClass->id)
            ->whereNotNull('term')
            ->whereNotNull('academic_year')
            ->get(['term', 'academic_year', 'exam_date'])
            ->groupBy(fn ($examination) => $examination->term.'|'.$examination->academic_year)
            ->map(fn ($group) => [
                'term' => (string) $group->first()->term,
                'academic_year' => (int) $group->first()->academic_year,
                'term_number' => TermParser::number($group->first()->term),
                // The tie-breaker for terms named in a way TermParser can't
                // number ("Mid-Series", "Opener"): the latest paper sat
                // still says which term ran most recently.
                'latest_paper' => $group->max(fn ($examination) => $examination->exam_date?->timestamp ?? 0),
            ])
            ->sortBy([
                fn ($a, $b) => $a['academic_year'] <=> $b['academic_year'],
                fn ($a, $b) => ($a['term_number'] ?? 0) <=> ($b['term_number'] ?? 0),
                fn ($a, $b) => $a['latest_paper'] <=> $b['latest_paper'],
            ])
            ->values();

        if ($terms->isEmpty()) {
            return null;
        }

        $current = $terms->last();

        return [
            'term' => $current['term'],
            'academic_year' => $current['academic_year'],
            'term_number' => $current['term_number'],
        ];
    }

    /**
     * The term directly before a given one. Term 1 reaches back into the
     * previous academic year's third term rather than reporting that there
     * is nothing before it.
     *
     * @return array{term_number: int, academic_year: int}
     */
    public static function previous(int $termNumber, int $academicYear): array
    {
        return $termNumber <= 1
            ? ['term_number' => 3, 'academic_year' => $academicYear - 1]
            : ['term_number' => $termNumber - 1, 'academic_year' => $academicYear];
    }
}
