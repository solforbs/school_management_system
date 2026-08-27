<?php

namespace Modules\ReportCard\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Modules\Classes\Models\SchoolClass;
use Modules\Curriculum\Models\Curriculum;
use Modules\Institution\Models\Institution;
use Modules\ReportCard\Models\ReportCard;
use Modules\ReportCard\Models\ReportTemplate;
use Modules\ReportCard\Support\InstitutionLogo;
use Modules\ReportCard\Support\ReportCardProse;
use Modules\ReportCard\Support\SubjectRow;

/**
 * A sample report card, rendered through the real template so a school can
 * see what it is configuring before a single parent receives one.
 *
 * The learner is invented; everything around them is not. The bands are the
 * school's own, the wording is its own template, the logo and letterhead
 * are its own - so what the preview shows is what a parent gets, give or
 * take the marks. That is the whole value of it: previewing a mock-up
 * would tell a school nothing about its own settings.
 *
 * Nothing here is saved. Every model is built in memory and thrown away
 * with the response, so previewing can't leave a stray learner or report
 * card behind in the school's records.
 */
class ReportCardPreviewService
{
    /**
     * Marks chosen to land in different bands, so the preview exercises the
     * scale rather than printing the same grade five times - and one
     * subject left unsat, which is how an un-assessed row looks.
     *
     * @var array<int, array{0: string, 1: string, 2: int|null, 3: int|null}>
     */
    private const SAMPLE_SUBJECTS = [
        ['Mathematics', 'MAT', 92, 100],
        ['English', 'ENG', 78, 100],
        ['Kiswahili', 'KIS', 64, 100],
        ['Integrated Science', 'SCI', 45, 100],
        ['Creative Arts', 'CRE', null, null],
    ];

    /**
     * The view data the report card template needs, built for a sample.
     *
     * @return array<string, mixed>
     */
    public function viewData(Institution $institution, ?Curriculum $curriculum): array
    {
        $curriculumId = $curriculum?->id;

        $student = new User(['name' => 'Asha Sample']);
        $schoolClass = new SchoolClass(['name' => 'Sample Class']);

        $reportCard = new ReportCard([
            'institution_id' => $institution->id,
            'term' => 'Sample Term',
            'academic_year' => (int) now()->year,
            'term_number' => 2,
        ]);
        $reportCard->setRelation('schoolClass', $schoolClass);
        $reportCard->setRelation('institution', $institution);

        $scaleBands = GradingBandService::scaleFor($institution, $curriculumId);
        $rows = $this->rows($institution, $curriculumId);

        $builder = new ReportSheetBuilder;
        $summary = $builder->summary($rows, $institution, $curriculumId);

        $meanPercentage = $summary['mean_percentage'];
        $meanGrade = $summary['band']?->grade;

        $template = ReportTemplate::where('institution_id', $institution->id)->first();

        $prose = ReportCardProse::render($template, [
            '{{student_name}}' => $student->name,
            '{{institution_name}}' => $institution->name,
            '{{class_name}}' => $schoolClass->name,
            '{{term}}' => $reportCard->term,
            '{{mean_percentage}}' => $meanPercentage !== null ? number_format($meanPercentage, 2).'%' : '—',
            '{{mean_grade}}' => $meanGrade ?? '—',
        ]);

        return [
            'institution' => $institution,
            'student' => $student,
            'studentDetails' => null,
            'reportCard' => $reportCard,
            'rows' => $rows,
            'summary' => $summary,
            'curriculum' => $curriculum,
            'pointsCeiling' => $builder->pointsCeiling($scaleBands, $curriculum),
            'scaleBands' => $scaleBands,
            'meanPercentage' => $meanPercentage,
            'meanGrade' => $meanGrade,
            'openingHtml' => $prose['opening'],
            'closingHtml' => $prose['closing'],
            'signatoryName' => $template?->signatory_name,
            'signatoryTitle' => $template?->signatory_title,
            'logoDataUri' => InstitutionLogo::dataUri($institution),
            'termHistory' => $this->termHistory($institution, $reportCard, $meanPercentage, $curriculumId),
            // The template prints this banner rather than leaving anyone to
            // work out for themselves that Asha isn't a real learner.
            'previewNotice' => 'SAMPLE — this is a preview of your report card settings, not a real learner.',
        ];
    }

    /**
     * The sample subject rows, graded against the school's real bands. A
     * school that hasn't configured a scale yet gets dashes in the grade
     * column, which is a fair answer to "what will this look like".
     *
     * @return Collection<int, SubjectRow>
     */
    private function rows(Institution $institution, ?int $curriculumId)
    {
        return collect(self::SAMPLE_SUBJECTS)->map(function (array $subject) use ($institution, $curriculumId) {
            [$name, $code, $marks, $outOf] = $subject;

            if ($marks === null) {
                return new SubjectRow($name, $code, null, null, null, null, 'A. S');
            }

            $percentage = round($marks / $outOf * 100, 2);

            return new SubjectRow(
                name: $name,
                code: $code,
                marks: (float) $marks,
                outOf: (float) $outOf,
                percentage: $percentage,
                band: GradingBandService::resolveBand($institution, $percentage, $curriculumId),
                teacherInitials: 'A. S',
            );
        });
    }

    /**
     * A made-up previous term a few points lower, so the preview shows the
     * comparison table a real report would carry rather than hiding a
     * section the school is trying to look at.
     *
     * @return Collection<int, ReportCard>
     */
    private function termHistory(Institution $institution, ReportCard $current, ?float $meanPercentage, ?int $curriculumId)
    {
        if ($meanPercentage === null) {
            return collect([$current]);
        }

        $previousMean = round(max($meanPercentage - 6.5, 0), 2);

        $previous = new ReportCard([
            'term' => 'Previous Term',
            'academic_year' => $current->academic_year,
            'term_number' => 1,
            'mean_percentage' => $previousMean,
            'mean_grade' => GradingBandService::resolve($institution, $previousMean, $curriculumId),
        ]);

        $current->mean_percentage = $meanPercentage;
        $current->mean_grade = GradingBandService::resolve($institution, $meanPercentage, $curriculumId);

        return collect([$previous, $current]);
    }
}
