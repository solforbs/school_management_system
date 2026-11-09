<?php

namespace Modules\Attendance\Services;

use Illuminate\Support\Collection;
use Modules\Attendance\Models\Attendance;
use Modules\Student\Models\StudentDetails;

/**
 * Finds roll-call rows saved against a class the learner was not in.
 *
 * Before the register was keyed by class, switching class kept the
 * previous class's ticks in the browser and saved them into the next
 * class. That left a second row for the same learner and date under the
 * wrong class. Read-only: nothing here changes data.
 */
class MisfiledAttendanceFinder
{
    /**
     * @return array{
     *     same_day_conflicts: Collection<int, array{student_id: int, date: string, current_class_id: int|null, rows: Collection<int, Attendance>}>,
     *     other_class_rows: Collection<int, Attendance>
     * }
     */
    public function find(?int $institutionId = null): array
    {
        $rows = Attendance::query()
            ->when($institutionId, fn ($q, $id) => $q->where('institution_id', $id))
            ->with(['student:id,name', 'schoolClass:id,name', 'recorder:id,name'])
            ->orderBy('date')
            ->get();

        $currentClassIds = $this->currentClassIds($rows);

        // The bug's fingerprint: one learner, one day, rows in two classes.
        $sameDayConflicts = $rows
            ->groupBy(fn (Attendance $row) => $row->student_id.'|'.$row->date->toDateString())
            ->filter(fn (Collection $group) => $group->pluck('school_class_id')->unique()->count() > 1)
            ->map(fn (Collection $group) => [
                'student_id' => (int) $group->first()->student_id,
                'date' => $group->first()->date->toDateString(),
                'current_class_id' => $currentClassIds[$group->first()->student_id] ?? null,
                'rows' => $group->values(),
            ])
            ->values();

        $conflictRowIds = $sameDayConflicts->flatMap(fn (array $conflict) => $conflict['rows']->pluck('id'))->all();

        // Weaker signal: a single row under a class the learner is not in
        // now. Could be the bug, or a learner who has since moved class -
        // the app keeps no class history to tell the two apart.
        $otherClassRows = $rows
            ->reject(fn (Attendance $row) => in_array($row->id, $conflictRowIds, true))
            ->filter(function (Attendance $row) use ($currentClassIds) {
                $current = $currentClassIds[$row->student_id] ?? null;

                return $current !== null && $current !== (int) $row->school_class_id;
            })
            ->values();

        return [
            'same_day_conflicts' => $sameDayConflicts,
            'other_class_rows' => $otherClassRows,
        ];
    }

    /**
     * @param  Collection<int, Attendance>  $rows
     * @return array<int, int|null>
     */
    private function currentClassIds(Collection $rows): array
    {
        return StudentDetails::query()
            ->whereIn('student_id', $rows->pluck('student_id')->unique())
            ->pluck('class_id', 'student_id')
            ->map(fn ($classId) => $classId !== null ? (int) $classId : null)
            ->all();
    }
}
