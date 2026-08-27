<?php

namespace Modules\ReportCard\Support;

use Modules\Curriculum\Models\Curriculum;

/**
 * The two grading scales Kenyan schools actually use, ready to be loaded
 * into an institution in one click instead of typed in band by band.
 *
 * Bands stop at .99 rather than meeting at a whole number so the table
 * reads the way a school expects ("75 - 79.99, B+"). GradingBandService
 * rounds a percentage to two decimals before looking it up, which is what
 * keeps the sliver between one band's ceiling and the next one's floor
 * from falling through un-graded.
 *
 * Neither is law: both are a starting point a school edits from its own
 * settings page.
 */
class GradingScaleDefaults
{
    /**
     * Option keys for the standard-scale picker. Neither is a bare number:
     * PHP silently casts a numeric string array key to an int, so a plain
     * '844' would come back out of array_keys() as int 844 and fail any
     * strict comparison against the constant.
     */
    public const SCALE_844 = '844:letters';

    public const SCALE_CBC = 'cbc:levels';

    /**
     * CBC's four expectation bands, and what each one says about a learner.
     *
     * These are not a scale of their own: they are what the eight KJSEA
     * levels roll up into, two levels to a band. A report states the band
     * because that is the language a parent reads, and the level because
     * that is what KJSEA records - both off the one set of marks.
     *
     * @var array<string, string>
     */
    public const EXPECTATION_BANDS = [
        'EE' => 'Exceeding Expectations - correctly performs all expected activities',
        'ME' => 'Meeting Expectations - follows instructions and completes most activities',
        'AE' => 'Approaching Expectations - attempts the work but is inconsistent',
        'BE' => 'Below Expectations - major inaccuracies, or unable to complete tasks',
    ];

    /**
     * The standard scales a school can load.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::SCALE_844 => '8-4-4 — A to E',
            self::SCALE_CBC => 'CBC — 4 Bands (EE / ME / AE / BE) & 8 KJSEA Levels',
        ];
    }

    /**
     * 8-4-4: twelve letter grades, each worth the KCSE grade points used
     * to work out a mean grade.
     *
     * @return array<int, array{min_percentage: float, max_percentage: float, grade: string, points: int, remark: string}>
     */
    public static function eightFourFour(): array
    {
        return [
            ['min_percentage' => 80, 'max_percentage' => 100, 'grade' => 'A', 'points' => 12, 'remark' => 'Excellent'],
            ['min_percentage' => 75, 'max_percentage' => 79.99, 'grade' => 'A-', 'points' => 11, 'remark' => 'Very good'],
            ['min_percentage' => 70, 'max_percentage' => 74.99, 'grade' => 'B+', 'points' => 10, 'remark' => 'Very good'],
            ['min_percentage' => 65, 'max_percentage' => 69.99, 'grade' => 'B', 'points' => 9, 'remark' => 'Good'],
            ['min_percentage' => 60, 'max_percentage' => 64.99, 'grade' => 'B-', 'points' => 8, 'remark' => 'Good'],
            ['min_percentage' => 55, 'max_percentage' => 59.99, 'grade' => 'C+', 'points' => 7, 'remark' => 'Above average'],
            ['min_percentage' => 50, 'max_percentage' => 54.99, 'grade' => 'C', 'points' => 6, 'remark' => 'Average'],
            ['min_percentage' => 45, 'max_percentage' => 49.99, 'grade' => 'C-', 'points' => 5, 'remark' => 'Average'],
            ['min_percentage' => 40, 'max_percentage' => 44.99, 'grade' => 'D+', 'points' => 4, 'remark' => 'Below average'],
            ['min_percentage' => 35, 'max_percentage' => 39.99, 'grade' => 'D', 'points' => 3, 'remark' => 'Weak'],
            ['min_percentage' => 30, 'max_percentage' => 34.99, 'grade' => 'D-', 'points' => 2, 'remark' => 'Weak'],
            ['min_percentage' => 0, 'max_percentage' => 29.99, 'grade' => 'E', 'points' => 1, 'remark' => 'Needs serious improvement'],
        ];
    }

    /**
     * CBC, marked on the eight KJSEA achievement levels - which is also
     * marking it on the four bands, since each band is simply its two
     * levels taken together (EE1 + EE2 = EE, and so on down).
     *
     * `points` is the achievement level 8-1 that a KJSEA report states per
     * subject. The band is read off the grade rather than stored, so the
     * two can never drift apart.
     *
     * The published ranges are whole numbers running 90-100, 75-89, 58-74
     * and so on, and start at 1 rather than 0. They're written here closing
     * at .99 and opening at 0 so a percentage landing between two of them -
     * 89.4, or an unattempted 0 - still resolves to a level instead of
     * coming back ungraded.
     *
     * @return array<int, array{min_percentage: float, max_percentage: float, grade: string, points: int, remark: string}>
     */
    public static function cbc(): array
    {
        return [
            ['min_percentage' => 90, 'max_percentage' => 100, 'grade' => 'EE1', 'points' => 8, 'remark' => 'Exceptional'],
            ['min_percentage' => 75, 'max_percentage' => 89.99, 'grade' => 'EE2', 'points' => 7, 'remark' => 'Very good'],
            ['min_percentage' => 58, 'max_percentage' => 74.99, 'grade' => 'ME1', 'points' => 6, 'remark' => 'Good'],
            ['min_percentage' => 41, 'max_percentage' => 57.99, 'grade' => 'ME2', 'points' => 5, 'remark' => 'Fair'],
            ['min_percentage' => 31, 'max_percentage' => 40.99, 'grade' => 'AE1', 'points' => 4, 'remark' => 'Needs improvement'],
            ['min_percentage' => 21, 'max_percentage' => 30.99, 'grade' => 'AE2', 'points' => 3, 'remark' => 'Below average'],
            ['min_percentage' => 11, 'max_percentage' => 20.99, 'grade' => 'BE1', 'points' => 2, 'remark' => 'Well below average'],
            ['min_percentage' => 0, 'max_percentage' => 10.99, 'grade' => 'BE2', 'points' => 1, 'remark' => 'Minimal'],
        ];
    }

    /**
     * The four-band letters a grade rolls up into: EE1 and EE2 are both EE.
     * Null on 8-4-4, whose A-E letters aren't expectation bands and mustn't
     * be read as though they were.
     */
    public static function bandFor(?string $grade): ?string
    {
        if ($grade === null) {
            return null;
        }

        $letters = strtoupper(preg_replace('/[^A-Za-z]/', '', $grade) ?? '');

        return array_key_exists($letters, self::EXPECTATION_BANDS) ? $letters : null;
    }

    /**
     * What a band says about a learner, for the key printed on a report.
     */
    public static function bandDescription(?string $band): ?string
    {
        return $band === null ? null : (self::EXPECTATION_BANDS[$band] ?? null);
    }

    /**
     * The bands behind one of the picker's options.
     *
     * @return array<int, array{min_percentage: float, max_percentage: float, grade: string, points: int, remark: string}>
     */
    public static function forKey(string $key): array
    {
        return $key === self::SCALE_CBC ? self::cbc() : self::eightFourFour();
    }

    public static function labelForKey(string $key): string
    {
        return self::options()[$key] ?? self::options()[self::SCALE_844];
    }

    /**
     * Which option a curriculum grades on - the one to preselect so the
     * common case is a single click.
     */
    public static function keyFor(?Curriculum $curriculum): string
    {
        return $curriculum?->isCbc() ? self::SCALE_CBC : self::SCALE_844;
    }

    /**
     * The default scale for a curriculum, chosen by the system it runs on.
     *
     * @return array<int, array{min_percentage: float, max_percentage: float, grade: string, points: int, remark: string}>
     */
    public static function for(Curriculum $curriculum): array
    {
        return self::forSystem((string) $curriculum->system);
    }

    /**
     * @return array<int, array{min_percentage: float, max_percentage: float, grade: string, points: int, remark: string}>
     */
    public static function forSystem(string $system): array
    {
        return $system === 'cbc' ? self::cbc() : self::eightFourFour();
    }

    /**
     * How to name a scale in the confirmation the settings page shows once
     * it has been loaded.
     */
    public static function labelFor(string $system): string
    {
        return self::labelForKey($system === 'cbc' ? self::SCALE_CBC : self::SCALE_844);
    }
}
