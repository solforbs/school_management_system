<?php

namespace Modules\Institution\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Classes\Models\SchoolClass;

/**
 * Three weeks of lesson attendance for the demo school - every timetabled
 * period marked, mostly as taught, some missed or made up - plus the
 * weekly lesson reports that roll them up.
 */
class DemoLessonsSeeder extends Seeder
{
    private const WEEKS = 3;

    public function run(): void
    {
        $institution = DemoAccountSeeder::institution();

        if (! $institution) {
            return;
        }

        $entries = DB::table('timetable_entries')
            ->where('institution_id', $institution->id)
            ->get(['id', 'class_id', 'day_of_week']);

        if ($entries->isEmpty()) {
            return;
        }

        $markedBy = $institution->user_id;
        $dayOffset = ['Monday' => 0, 'Tuesday' => 1, 'Wednesday' => 2, 'Thursday' => 3, 'Friday' => 4];
        $weekStarts = collect(range(self::WEEKS, 1))
            ->map(fn (int $back) => Carbon::today()->startOfWeek(Carbon::MONDAY)->subWeeks($back));

        $lessons = [];

        foreach ($weekStarts as $monday) {
            foreach ($entries as $entry) {
                $date = $monday->copy()->addDays($dayOffset[$entry->day_of_week] ?? 0);
                if ($date->gt(Carbon::today())) {
                    continue;
                }

                $roll = rand(1, 100);
                $status = $roll <= 88 ? 'attended' : ($roll <= 95 ? 'not_attended' : 'recovered');

                $lessons[] = [
                    'institution_id' => $institution->id,
                    'class_id' => $entry->class_id,
                    'timetable_entry_id' => $entry->id,
                    'lesson_date' => $date->toDateString(),
                    'status' => $status,
                    'remarks' => $status === 'not_attended' ? 'Teacher on official duty' : null,
                    'marked_by' => $markedBy,
                    'created_at' => $date,
                    'updated_at' => $date,
                ];
            }
        }

        foreach (array_chunk($lessons, 500) as $chunk) {
            DB::table('lessons')->insertOrIgnore($chunk);
        }

        $this->weeklyReports($institution, $weekStarts);
    }

    /**
     * @param  Collection<int, Carbon>  $weekStarts
     */
    private function weeklyReports($institution, $weekStarts): void
    {
        $classes = SchoolClass::where('institution_id', $institution->id)->pluck('id');
        $rows = [];

        foreach ($weekStarts as $monday) {
            $friday = $monday->copy()->addDays(4);

            foreach ($classes as $classId) {
                $lessons = DB::table('lessons')
                    ->where('class_id', $classId)
                    ->whereBetween('lesson_date', [$monday->toDateString(), $friday->toDateString()])
                    ->get(['status']);

                if ($lessons->isEmpty()) {
                    continue;
                }

                $rows[] = [
                    'institution_id' => $institution->id,
                    'class_id' => $classId,
                    'type' => 'weekly',
                    'period_start' => $monday->toDateString(),
                    'period_end' => $friday->toDateString(),
                    'total_lessons' => $lessons->count(),
                    'attended_count' => $lessons->where('status', 'attended')->count(),
                    'not_attended_count' => $lessons->where('status', 'not_attended')->count(),
                    'recovered_count' => $lessons->where('status', 'recovered')->count(),
                    'generated_by' => $institution->user_id,
                    'generated_at' => $friday->copy()->setTime(18, 0),
                    'created_at' => $friday,
                    'updated_at' => $friday,
                ];
            }
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('lesson_reports')->insertOrIgnore($chunk);
        }
    }
}
