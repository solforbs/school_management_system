<?php

namespace Modules\Result\Services;

use Modules\Classes\Models\SchoolClass;
use Modules\Examinations\Models\Examination;
use Modules\Institution\Models\Institution;
use Modules\ReportCard\Models\GradingBand;
use Modules\ReportCard\Services\GradingBandService;

/**
 * Turns marks into a grade, against whichever scale the class's curriculum
 * runs on - A-E on 8-4-4, EE/ME/AE/BE and EE1-BE2 on CBC.
 *
 * For CBC formative assessments the teacher picks a band or sub-level directly
 * — no marks, no percentage. Use fromBand() for that path.
 *
 * Shared by the single-result form, the band entry sheet, and the class-wide
 * marks sheet so all grade consistently.
 */
class ResultGrader
{
    /**
     * The 4 broad expectation bands (KPSEA / Primary CBC).
     *
     * @var array<int, string>
     */
    public const CBC_BANDS = ['EE', 'ME', 'AE', 'BE'];

    /**
     * The 8 achievement sub-levels (KJSEA / Junior Secondary CBC).
     *
     * @var array<string, array{points: int, band: string, label: string, min_pct: float, max_pct: float}>
     */
    public const CBC_SUB_LEVELS = [
        'EE1' => ['points' => 8, 'band' => 'EE', 'label' => 'Exceptional', 'min_pct' => 90.0, 'max_pct' => 100.0],
        'EE2' => ['points' => 7, 'band' => 'EE', 'label' => 'Very Good', 'min_pct' => 75.0, 'max_pct' => 89.99],
        'ME1' => ['points' => 6, 'band' => 'ME', 'label' => 'Good', 'min_pct' => 58.0, 'max_pct' => 74.99],
        'ME2' => ['points' => 5, 'band' => 'ME', 'label' => 'Fair', 'min_pct' => 41.0, 'max_pct' => 57.99],
        'AE1' => ['points' => 4, 'band' => 'AE', 'label' => 'Needs Improvement', 'min_pct' => 31.0, 'max_pct' => 40.99],
        'AE2' => ['points' => 3, 'band' => 'AE', 'label' => 'Below Average', 'min_pct' => 21.0, 'max_pct' => 30.99],
        'BE1' => ['points' => 2, 'band' => 'BE', 'label' => 'Well Below Average', 'min_pct' => 11.0, 'max_pct' => 20.99],
        'BE2' => ['points' => 1, 'band' => 'BE', 'label' => 'Minimal', 'min_pct' => 1.0, 'max_pct' => 10.99],
    ];

    /**
     * All valid evaluation strings for direct assessment entry.
     *
     * @var array<int, string>
     */
    public const CBC_ALL_EVALUATIONS = [
        'EE1', 'EE2', 'ME1', 'ME2', 'AE1', 'AE2', 'BE1', 'BE2',
        'EE', 'ME', 'AE', 'BE',
    ];

    /**
     * The band a mark falls in, or null when the examination carries no
     * total to work a percentage from, or the school hasn't configured a
     * scale yet.
     */
    public static function band(
        Examination $examination,
        float $marks,
        Institution $institution,
        ?SchoolClass $schoolClass = null,
    ): ?GradingBand {
        if (! $examination->total_marks || $examination->total_marks <= 0) {
            return null;
        }

        $percentage = $marks / $examination->total_marks * 100;
        $schoolClass ??= $examination->schoolClass;

        return GradingBandService::resolveBand(
            $institution,
            $percentage,
            GradingBandService::curriculumIdFor($schoolClass, $institution),
        );
    }

    public static function grade(
        Examination $examination,
        float $marks,
        Institution $institution,
        ?SchoolClass $schoolClass = null,
    ): ?string {
        return self::band($examination, $marks, $institution, $schoolClass)?->grade;
    }

    /**
     * Build the result payload for a directly-entered CBC band or sub-level.
     *
     * Used by BandEntryController for formative / SBA assessments where
     * the teacher picks an evaluation directly — no marks, no percentage.
     * Accepts both the 8 sub-levels (EE1-BE2) and the 4 broad bands (EE-BE).
     *
     * @return array<string, mixed>
     */
    public static function fromBand(string $evaluation): array
    {
        $evaluation = strtoupper(trim($evaluation));

        abort_unless(in_array($evaluation, self::CBC_ALL_EVALUATIONS, true), 422, "'{$evaluation}' is not a valid CBC band or level.");

        return [
            'marks_obtained' => null,
            'cbc_band' => $evaluation,
            'grade' => $evaluation,
        ];
    }

    /**
     * The achievement level points (8 to 1) for a CBC evaluation.
     */
    public static function pointsFor(?string $evaluation): ?int
    {
        if (! $evaluation) {
            return null;
        }

        $evaluation = strtoupper(trim($evaluation));

        if (isset(self::CBC_SUB_LEVELS[$evaluation])) {
            return self::CBC_SUB_LEVELS[$evaluation]['points'];
        }

        return match ($evaluation) {
            'EE' => 8,
            'ME' => 6,
            'AE' => 4,
            'BE' => 2,
            default => null,
        };
    }

    /**
     * The parent expectation band (EE, ME, AE, BE) behind a sub-level.
     */
    public static function parentBand(?string $evaluation): ?string
    {
        if (! $evaluation) {
            return null;
        }

        $evaluation = strtoupper(trim($evaluation));

        if (isset(self::CBC_SUB_LEVELS[$evaluation])) {
            return self::CBC_SUB_LEVELS[$evaluation]['band'];
        }

        return in_array($evaluation, self::CBC_BANDS, true) ? $evaluation : null;
    }

    /**
     * The KNEC 8-level achievement sub-level (EE1 to BE2) corresponding to a percentage mark.
     */
    public static function subLevelForPercentage(float $percentage): string
    {
        foreach (self::CBC_SUB_LEVELS as $code => $info) {
            if ($percentage >= $info['min_pct']) {
                return $code;
            }
        }

        return 'BE2';
    }
}
