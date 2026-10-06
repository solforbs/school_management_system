<?php

namespace Modules\ReportCard\Services;

use Illuminate\Support\Collection;
use Modules\Curriculum\Models\Curriculum;
use Modules\Institution\Models\Institution;
use Modules\ReportCard\Models\GradingBand;
use Modules\ReportCard\Models\ReportCard;
use Modules\ReportCard\Support\GradingScaleDefaults;
use Modules\ReportCard\Support\SubjectRow;
use Modules\Result\Models\Result;
use Modules\Result\Services\ResultGrader;

/**
 * Turns a term's raw results into the subject table a report card prints:
 * one row per subject, graded against the scale its class is marked on,
 * plus the totals underneath it.
 *
 * For CBC classes, accommodates both direct formative expectation bands
 * (EE, ME, AE, BE) and numerical summative examinations.
 */
class ReportSheetBuilder
{
    /**
     * @return Collection<int, SubjectRow>
     */
    public function rows(ReportCard $reportCard, Institution $institution): Collection
    {
        $curriculumId = GradingBandService::curriculumIdFor($reportCard->schoolClass, $institution);
        $curriculum = $reportCard->schoolClass?->curriculum
            ?? ($curriculumId ? Curriculum::find($curriculumId) : null);

        if ($curriculum?->isCbc()) {
            return $this->cbcRows($reportCard, $institution, $curriculumId);
        }

        return $this->standardRows($reportCard, $institution, $curriculumId);
    }

    /**
     * Standard 8-4-4 percentage-based row aggregation.
     *
     * @return Collection<int, SubjectRow>
     */
    private function standardRows(ReportCard $reportCard, Institution $institution, ?int $curriculumId): Collection
    {
        $results = Result::where('student_id', $reportCard->student_id)
            ->whereHas('examination', fn ($q) => $q
                ->where('term', $reportCard->term)
                ->where('academic_year', $reportCard->academic_year))
            ->with(['subject', 'examination.subject'])
            ->get();

        $subjects = $this->classSubjects($reportCard);

        foreach ($results as $result) {
            $subjectId = $result->subject_id ?: $result->examination?->subject_id;
            $name = $result->subject?->name
                ?? $result->examination?->subject?->name
                ?? $result->examination?->subject_name;

            if (! $name) {
                continue;
            }

            $key = $subjectId ?: 'name:'.$name;

            $subjects[$key] ??= [
                'name' => $name,
                'code' => $result->subject?->code ?? $result->examination?->subject?->code,
                'learning_area' => $result->subject?->learning_area,
                'strand' => $result->subject?->strand,
                'teacher' => null,
            ];
        }

        $grouped = $results->groupBy(fn ($result) => ($result->subject_id ?: $result->examination?->subject_id) ?: 'name:'.(
            $result->subject?->name ?? $result->examination?->subject?->name ?? $result->examination?->subject_name
        ));

        $rows = collect($subjects)
            ->map(function (array $subject, $key) use ($grouped, $institution, $curriculumId) {
                $subjectResults = $grouped->get($key, collect())
                    ->filter(fn ($result) => $result->examination && $result->examination->total_marks > 0);

                if ($subjectResults->isEmpty()) {
                    return new SubjectRow(
                        name: $subject['name'],
                        code: $subject['code'],
                        marks: null,
                        outOf: null,
                        percentage: null,
                        band: null,
                        teacherInitials: $this->initials($subject['teacher']),
                        directBand: null,
                        learningArea: $subject['learning_area'] ?? null,
                        strand: $subject['strand'] ?? null,
                    );
                }

                $marks = (float) $subjectResults->sum(fn ($result) => (float) $result->marks_obtained);
                $outOf = (float) $subjectResults->sum(fn ($result) => (float) $result->examination->total_marks);
                $percentage = round($marks / $outOf * 100, 2);

                return new SubjectRow(
                    name: $subject['name'],
                    code: $subject['code'],
                    marks: $marks,
                    outOf: $outOf,
                    percentage: $percentage,
                    band: GradingBandService::resolveBand($institution, $percentage, $curriculumId),
                    teacherInitials: $this->initials($subject['teacher']),
                    directBand: null,
                    learningArea: $subject['learning_area'] ?? null,
                    strand: $subject['strand'] ?? null,
                );
            });

        if ($grouped->isNotEmpty() && count($subjects) > 10) {
            $assessedRows = $rows->filter(fn (SubjectRow $row) => $row->isAssessed());
            if ($assessedRows->isNotEmpty()) {
                $rows = $assessedRows;
            }
        }

        return $rows->sortBy(fn (SubjectRow $row) => $row->name)->values();
    }

    /**
     * CBC expectation band aggregation.
     * Groups both formative band entries and summative exams per subject.
     *
     * @return Collection<int, SubjectRow>
     */
    private function cbcRows(ReportCard $reportCard, Institution $institution, ?int $curriculumId): Collection
    {
        $results = Result::where('student_id', $reportCard->student_id)
            ->whereHas('examination', fn ($q) => $q
                ->where('term', $reportCard->term)
                ->where('academic_year', $reportCard->academic_year))
            ->with(['subject', 'examination.subject', 'examination.sitting'])
            ->get();

        $subjects = $this->classSubjects($reportCard);

        foreach ($results as $result) {
            $subjectId = $result->subject_id ?: $result->examination?->subject_id;
            $name = $result->subject?->name
                ?? $result->examination?->subject?->name
                ?? $result->examination?->subject_name;

            if (! $name) {
                continue;
            }

            $key = $subjectId ?: 'name:'.$name;

            $subjects[$key] ??= [
                'name' => $name,
                'code' => $result->subject?->code ?? $result->examination?->subject?->code,
                'learning_area' => $result->subject?->learning_area,
                'strand' => $result->subject?->strand,
                'teacher' => null,
            ];
        }

        $grouped = $results->groupBy(fn ($result) => ($result->subject_id ?: $result->examination?->subject_id) ?: 'name:'.(
            $result->subject?->name ?? $result->examination?->subject?->name ?? $result->examination?->subject_name
        ));

        $rows = collect($subjects)
            ->map(function (array $subject, $key) use ($grouped, $institution, $curriculumId) {
                $subjectResults = $grouped->get($key, collect());

                if ($subjectResults->isEmpty()) {
                    return new SubjectRow(
                        name: $subject['name'],
                        code: $subject['code'],
                        marks: null,
                        outOf: null,
                        percentage: null,
                        band: null,
                        teacherInitials: $this->initials($subject['teacher']),
                        directBand: null,
                        learningArea: $subject['learning_area'] ?? null,
                        strand: $subject['strand'] ?? null,
                    );
                }

                // Collect all bands from formative entries or derived from summative marks
                $bands = $subjectResults
                    ->map(function ($r) use ($institution, $curriculumId) {
                        if ($r->cbc_band) {
                            return strtoupper($r->cbc_band);
                        }
                        if ($r->grade && in_array(strtoupper($r->grade), ResultGrader::CBC_ALL_EVALUATIONS, true)) {
                            return strtoupper($r->grade);
                        }
                        if ($r->marks_obtained !== null && $r->examination?->total_marks > 0) {
                            $pct = ($r->marks_obtained / $r->examination->total_marks) * 100;
                            $band = GradingBandService::resolveBand($institution, $pct, $curriculumId);

                            return $band?->grade;
                        }

                        return null;
                    })
                    ->filter();

                // If numerical marks exist from summative exams
                $numericalResults = $subjectResults->filter(fn ($r) => $r->examination && $r->examination->total_marks > 0 && $r->marks_obtained !== null);
                $marks = $numericalResults->isNotEmpty() ? (float) $numericalResults->sum(fn ($r) => (float) $r->marks_obtained) : null;
                $outOf = $numericalResults->isNotEmpty() ? (float) $numericalResults->sum(fn ($r) => (float) $r->examination->total_marks) : null;
                $percentage = ($marks !== null && $outOf > 0) ? round($marks / $outOf * 100, 2) : null;

                $predominantBand = $this->determinePredominantBand($bands);

                if (! $predominantBand && $percentage !== null) {
                    $resolvedBand = GradingBandService::resolveBand($institution, $percentage, $curriculumId);
                    $predominantBand = $resolvedBand ? GradingScaleDefaults::bandFor($resolvedBand->grade) : null;
                }

                $gradingBand = $percentage !== null
                    ? GradingBandService::resolveBand($institution, $percentage, $curriculumId)
                    : null;

                return new SubjectRow(
                    name: $subject['name'],
                    code: $subject['code'],
                    marks: $marks,
                    outOf: $outOf,
                    percentage: $percentage,
                    band: $gradingBand,
                    teacherInitials: $this->initials($subject['teacher']),
                    directBand: $predominantBand,
                    learningArea: $subject['learning_area'] ?? null,
                    strand: $subject['strand'] ?? null,
                );
            });

        // When a class has an excessive number of subjects assigned (e.g. cross-level or elective catalogs > 10),
        // and the learner has evaluations recorded, filter out unassessed subjects so the report card doesn't
        // overflow with irrelevant secondary/stray subjects.
        if ($grouped->isNotEmpty() && count($subjects) > 10) {
            $assessedRows = $rows->filter(fn (SubjectRow $row) => $row->isAssessed());
            if ($assessedRows->isNotEmpty()) {
                $rows = $assessedRows;
            }
        }

        return $rows->sortBy(fn (SubjectRow $row) => $row->name)->values();
    }

    /**
     * Finds the most common expectation band or sub-level from a collection of evaluations.
     * Ties break in favor of higher achievement points (Level 8 down to 1).
     */
    private function determinePredominantBand(Collection $bands): ?string
    {
        if ($bands->isEmpty()) {
            return null;
        }

        $counts = $bands->countBy();

        return $counts->sort(function ($countA, $countB) {
            return $countB <=> $countA;
        })->keys()->sortByDesc(fn ($b) => ResultGrader::pointsFor($b) ?? 0)->first();
    }

    /**
     * The totals strip under the table: what the learner scored out of what
     * was available, how many subjects that covered, and the mean.
     *
     * @param  Collection<int, SubjectRow>  $rows
     * @return array<string, mixed>
     */
    public function summary(Collection $rows, Institution $institution, ?int $curriculumId): array
    {
        $assessed = $rows->filter(fn (SubjectRow $row) => $row->isAssessed());

        $withPercentage = $assessed->filter(fn (SubjectRow $row) => $row->percentage !== null);

        $meanPercentage = $withPercentage->isNotEmpty()
            ? round($withPercentage->avg(fn (SubjectRow $row) => $row->percentage), 2)
            : null;

        $pointed = $assessed->filter(fn (SubjectRow $row) => $row->points() !== null);

        $meanPoints = $pointed->isNotEmpty()
            ? round($pointed->avg(fn (SubjectRow $row) => $row->points()), 2)
            : null;

        $band = null;
        if ($meanPercentage !== null) {
            $band = GradingBandService::resolveBand($institution, $meanPercentage, $curriculumId);
        } elseif ($meanPoints !== null) {
            $grade = match (true) {
                $meanPoints >= 6.5 => 'EE',
                $meanPoints >= 4.5 => 'ME',
                $meanPoints >= 2.5 => 'AE',
                default => 'BE',
            };
            $band = GradingBandService::resolveBandByGrade($institution, $grade, $curriculumId)
                ?? new GradingBand([
                    'institution_id' => $institution->id,
                    'grade' => $grade,
                    'points' => (int) round($meanPoints),
                    'remark' => match ($grade) {
                        'EE' => 'Exceeding Expectations',
                        'ME' => 'Meeting Expectations',
                        'AE' => 'Approaching Expectations',
                        'BE' => 'Below Expectations',
                    },
                ]);
        }

        return [
            'total_marks' => $assessed->sum(fn (SubjectRow $row) => $row->marks),
            'total_out_of' => $assessed->sum(fn (SubjectRow $row) => $row->outOf),
            'subjects_assessed' => $assessed->count(),
            'subjects_total' => $rows->count(),
            'mean_percentage' => $meanPercentage,
            'mean_points' => $meanPoints,
            'band' => $band,
        ];
    }

    /**
     * The highest points value the scale in use can award.
     *
     * @param  Collection<int, GradingBand>  $scaleBands
     */
    public function pointsCeiling(Collection $scaleBands, ?Curriculum $curriculum = null): int
    {
        $highest = (int) $scaleBands->max('points');

        if ($highest > 0) {
            return $highest;
        }

        return $curriculum?->isCbc() ? 8 : 12;
    }

    /**
     * The subjects a class takes, keyed by subject id, each with the
     * teacher who owns its marks.
     *
     * @return array<int|string, array{name: string, code: ?string, learning_area: ?string, strand: ?string, teacher: ?string}>
     */
    private function classSubjects(ReportCard $reportCard): array
    {
        $schoolClass = $reportCard->schoolClass;

        if (! $schoolClass) {
            return [];
        }

        return $schoolClass->subjectTeachers()
            ->with(['subject', 'teacher'])
            ->get()
            ->filter(fn ($assignment) => $assignment->subject)
            ->mapWithKeys(fn ($assignment) => [
                $assignment->subject_id => [
                    'name' => (string) $assignment->subject->name,
                    'code' => $assignment->subject->code,
                    'learning_area' => $assignment->subject->learning_area,
                    'strand' => $assignment->subject->strand,
                    'teacher' => $assignment->teacher?->name,
                ],
            ])
            ->all();
    }

    /**
     * Initials from teacher name for signature column.
     */
    private function initials(?string $name): ?string
    {
        if (! $name) {
            return null;
        }

        $parts = preg_split('/\s+/', trim($name)) ?: [];

        $letters = collect($parts)
            ->filter()
            ->map(fn (string $part) => strtoupper(substr($part, 0, 1)))
            ->take(2);

        return $letters->isEmpty() ? null : $letters->implode('. ');
    }
}
