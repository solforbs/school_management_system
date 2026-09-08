<?php

namespace Modules\Examinations\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Where each paper in a sitting lands: Monday to Friday, three fixed
 * sittings a day, so the exam timetable reads as a tidy grid. Shared by the
 * "create a sitting" action and the demo seeder so both spread papers the
 * same way.
 */
class PaperSchedule
{
    /**
     * The three daily sittings, identical every day. The last is a shorter,
     * lower-mark paper.
     *
     * @var list<array{start: string, end: string, marks: int}>
     */
    public const SESSIONS = [
        ['start' => '08:00', 'end' => '09:30', 'marks' => 100],
        ['start' => '10:30', 'end' => '12:00', 'marks' => 100],
        ['start' => '14:00', 'end' => '15:00', 'marks' => 60],
    ];

    /** Monday to Friday. */
    public const DAYS = 5;

    public static function slotsPerDay(): int
    {
        return count(self::SESSIONS);
    }

    /**
     * How many papers fit in one week before the schedule wraps to a
     * second week.
     */
    public static function slotsPerWeek(): int
    {
        return self::DAYS * self::slotsPerDay();
    }

    /**
     * The Monday of next week - anchoring forward keeps a freshly created
     * sitting reading as upcoming whatever day it was set up.
     */
    public static function nextExamWeek(): CarbonInterface
    {
        return Carbon::today()->startOfWeek(Carbon::MONDAY)->addWeek();
    }

    /**
     * The date and sitting time for the paper at the given zero-based slot,
     * counting from `$weekStart`. Slot 0 is Monday's first sitting, slot 3
     * Tuesday's first, and every fifth day the schedule rolls into the next
     * week.
     *
     * @return array{date: CarbonInterface, start: string, end: string, marks: int, passing_marks: int}
     */
    public static function slot(CarbonInterface $weekStart, int $index): array
    {
        $perDay = self::slotsPerDay();
        $session = self::SESSIONS[$index % $perDay];

        $dayOffset = intdiv($index, $perDay);
        $weekendSkips = intdiv($dayOffset, self::DAYS) * 2;

        return [
            'date' => $weekStart->copy()->addDays($dayOffset + $weekendSkips),
            'start' => $session['start'],
            'end' => $session['end'],
            'marks' => $session['marks'],
            'passing_marks' => (int) round($session['marks'] * 0.4),
        ];
    }
}
