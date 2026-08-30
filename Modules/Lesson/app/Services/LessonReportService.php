<?php

namespace Modules\Lesson\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Modules\Classes\Models\SchoolClass;
use Modules\Lesson\Models\Lesson;
use Modules\Timetable\Models\TimetableEntry;

class LessonReportService
{
    /**
     * Tally attended / not attended / recovered lessons for a class across
     * a date range, day by day. Lesson attendance is marked manually by a
     * teacher (Lesson.status) - this has no relationship to ZKTeco device
     * attendance, it's purely "was this scheduled period taught".
     *
     * A scheduled period with no matching Lesson row is counted as "not
     * attended" (residual), since a row only exists once someone has
     * submitted the day's attendance grid.
     *
     * @return array{total: int, attended: int, notAttended: int, recovered: int, days: array}
     */
    public function compute(SchoolClass $class, CarbonInterface $start, CarbonInterface $end): array
    {
        // Never count periods that haven't happened yet if this is run
        // mid-day/mid-week (e.g. an on-demand report for the current week).
        $end = $end->greaterThan(Carbon::today()) ? Carbon::today() : $end->copy();

        $result = ['total' => 0, 'attended' => 0, 'notAttended' => 0, 'recovered' => 0, 'days' => []];

        if ($start->gt($end)) {
            return $result;
        }

        $entriesByDay = TimetableEntry::with('teacher')
            ->where('class_id', $class->id)
            ->get()
            ->groupBy('day_of_week');

        if ($entriesByDay->isEmpty()) {
            return $result;
        }

        $lessons = Lesson::where('class_id', $class->id)
            ->whereDate('lesson_date', '>=', $start->toDateString())
            ->whereDate('lesson_date', '<=', $end->toDateString())
            ->get()
            ->keyBy(fn (Lesson $lesson) => $lesson->lesson_date->format('Y-m-d').'|'.$lesson->timetable_entry_id);

        // Reassign rather than mutate in place: period_start/period_end
        // come through as CarbonImmutable via Eloquent's date cast, whose
        // addDay() returns a new instance instead of mutating $date.
        for ($date = $start->copy(); $date->lte($end); $date = $date->addDay()) {
            $dayEntries = $entriesByDay->get($date->format('l'), collect())->sortBy('start_time');

            if ($dayEntries->isEmpty()) {
                continue;
            }

            $day = ['date' => $date->copy(), 'total' => 0, 'attended' => 0, 'notAttended' => 0, 'recovered' => 0, 'periods' => []];

            foreach ($dayEntries as $entry) {
                $lesson = $lessons->get($date->format('Y-m-d').'|'.$entry->id);
                $status = $lesson->status ?? 'not_attended';

                $result['total']++;
                $day['total']++;

                if ($status === 'attended') {
                    $result['attended']++;
                    $day['attended']++;
                } elseif ($status === 'recovered') {
                    $result['recovered']++;
                    $day['recovered']++;
                } else {
                    $result['notAttended']++;
                    $day['notAttended']++;
                }

                $day['periods'][] = ['entry' => $entry, 'lesson' => $lesson, 'status' => $status];
            }

            $result['days'][] = $day;
        }

        return $result;
    }
}
