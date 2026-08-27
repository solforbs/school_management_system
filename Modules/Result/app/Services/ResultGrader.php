<?php

namespace Modules\Result\Services;

use Modules\Classes\Models\SchoolClass;
use Modules\Examinations\Models\Examination;
use Modules\Institution\Models\Institution;
use Modules\ReportCard\Models\GradingBand;
use Modules\ReportCard\Services\GradingBandService;

/**
 * Turns marks into a grade, against whichever scale the class's curriculum
 * runs on - A-E on 8-4-4, EE/ME/AE/BE on CBC.
 *
 * Shared by the single-result form and the class-wide marks sheet so both
 * grade the same way.
 */
class ResultGrader
{
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
}
