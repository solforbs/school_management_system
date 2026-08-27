<?php

namespace Modules\ReportCard\Services;

use Illuminate\Support\Collection;
use Modules\Curriculum\Models\Curriculum;
use Modules\Institution\Models\Institution;
use Modules\ReportCard\Models\GradingBand;
use Modules\ReportCard\Models\ReportCard;
use Modules\ReportCard\Support\SubjectRow;
use Modules\Result\Models\Result;

/**
 * Turns a term's raw results into the subject table a report card prints:
 * one row per subject, graded against the scale its class is marked on,
 * plus the totals underneath it.
 *
 * Split out from ReportCardPdfService because it is the part worth reading
 * on its own - the PDF service around it is fetch, render, store.
 */
class ReportSheetBuilder
{
    /**
     * @return Collection<int, SubjectRow>
     */
    public function rows(ReportCard $reportCard, Institution $institution): Collection
    {
        $results = Result::where('student_id', $reportCard->student_id)
            ->whereHas('examination', fn ($q) => $q
                ->where('term', $reportCard->term)
                ->where('academic_year', $reportCard->academic_year))
            ->with('examination.subject')
            ->get();

        $curriculumId = GradingBandService::curriculumIdFor($reportCard->schoolClass, $institution);

        // Every subject the class takes, so one the learner missed still
        // prints as a dashed row rather than vanishing from the report.
        $subjects = $this->classSubjects($reportCard);

        // Results can outlive the subject-teacher assignment they were
        // entered under, so anything with marks is folded in even when the
        // class no longer lists that subject.
        foreach ($results as $result) {
            $subjectId = $result->examination?->subject_id;
            $name = $result->examination?->subject?->name ?? $result->examination?->subject_name;

            if (! $name) {
                continue;
            }

            $key = $subjectId ?: 'name:'.$name;

            $subjects[$key] ??= [
                'name' => $name,
                'code' => $result->examination?->subject?->code,
                'teacher' => null,
            ];
        }

        $grouped = $results->groupBy(fn ($result) => $result->examination?->subject_id ?: 'name:'.(
            $result->examination?->subject?->name ?? $result->examination?->subject_name
        ));

        return collect($subjects)
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
                );
            })
            ->sortBy(fn (SubjectRow $row) => $row->name)
            ->values();
    }

    /**
     * The totals strip under the table: what the learner scored out of what
     * was available, how many subjects that covered, and the mean.
     *
     * The mean percentage averages the subjects rather than dividing total
     * marks by total available, so a subject marked out of 100 doesn't
     * drown out one marked out of 10. Mean points averages the achievement
     * levels themselves, which is the figure a CBC report leads with.
     *
     * @param  Collection<int, SubjectRow>  $rows
     * @return array<string, mixed>
     */
    public function summary(Collection $rows, Institution $institution, ?int $curriculumId): array
    {
        $assessed = $rows->filter(fn (SubjectRow $row) => $row->isAssessed());

        $meanPercentage = $assessed->isNotEmpty()
            ? round($assessed->avg(fn (SubjectRow $row) => $row->percentage), 2)
            : null;

        $pointed = $assessed->filter(fn (SubjectRow $row) => $row->points() !== null);

        return [
            'total_marks' => $assessed->sum(fn (SubjectRow $row) => $row->marks),
            'total_out_of' => $assessed->sum(fn (SubjectRow $row) => $row->outOf),
            'subjects_assessed' => $assessed->count(),
            'subjects_total' => $rows->count(),
            'mean_percentage' => $meanPercentage,
            'mean_points' => $pointed->isNotEmpty()
                ? round($pointed->avg(fn (SubjectRow $row) => $row->points()), 2)
                : null,
            'band' => $meanPercentage !== null
                ? GradingBandService::resolveBand($institution, $meanPercentage, $curriculumId)
                : null,
        ];
    }

    /**
     * The highest points value the scale in use can award. The report
     * prints the mean against it, since "6.5" says nothing without knowing
     * what it is out of.
     *
     * Read off the bands themselves rather than inferred from the
     * curriculum, because the curriculum doesn't always know: a school
     * marking CBC on its school-wide scale has no curriculum attached at
     * all, and would otherwise be told its levels were out of 12. Bands a
     * school has hand-tuned to some other ceiling come out right too.
     *
     * The curriculum is only the fallback for a scale whose bands carry no
     * points at all.
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
     * @return array<int|string, array{name: string, code: ?string, teacher: ?string}>
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
                    'teacher' => $assignment->teacher?->name,
                ],
            ])
            ->all();
    }

    /**
     * "Brian Njoroge" initialled down to "B. N" for the signature column -
     * a full name doesn't fit, and the teacher signs beside it anyway.
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
