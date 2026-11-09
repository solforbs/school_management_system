<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Attendance\Models\Attendance;
use Modules\Attendance\Services\MisfiledAttendanceFinder;

/**
 * Lists roll-call rows that may have been saved into the wrong class by
 * the register's old class-switch bug. Read-only - deciding what to remove
 * is left to a person who knows the school.
 */
class AuditMisfiledAttendance extends Command
{
    protected $signature = 'attendance:audit-misfiled
                            {--institution= : Limit the report to one institution ID}
                            {--limit=50 : Detail rows to print per section (0 = all)}';

    protected $description = 'Report attendance rows saved against a class the learner was not in. Changes nothing.';

    public function handle(MisfiledAttendanceFinder $finder): int
    {
        $institutionId = $this->option('institution') ? (int) $this->option('institution') : null;
        $limit = (int) $this->option('limit');

        $found = $finder->find($institutionId);
        $conflicts = $found['same_day_conflicts'];
        $otherClassRows = $found['other_class_rows'];

        $this->info('Read-only report - no attendance has been changed.');
        $this->newLine();

        $this->line('<options=bold>1. Same learner, same day, recorded in more than one class</> (the bug\'s fingerprint): '.$conflicts->count());
        if ($conflicts->isNotEmpty()) {
            $rows = $conflicts->flatMap(fn (array $conflict) => $conflict['rows']->map(fn (Attendance $row) => [
                $conflict['date'],
                $row->student->name ?? '#'.$row->student_id,
                $row->schoolClass->name ?? '#'.$row->school_class_id,
                $row->status,
                (int) $row->school_class_id === $conflict['current_class_id'] ? 'own class' : 'LIKELY WRONG',
                $row->recorder->name ?? '-',
                $row->id,
            ]));
            $this->table(['Date', 'Learner', 'Recorded in', 'Status', 'Verdict', 'Recorded by', 'Row ID'], $this->limited($rows->all(), $limit));
        }

        $this->newLine();
        $this->line('<options=bold>2. Recorded in a class the learner is not in now</> (the bug, or a learner who moved class): '.$otherClassRows->count());
        if ($otherClassRows->isNotEmpty()) {
            $rows = $otherClassRows->map(fn (Attendance $row) => [
                $row->date->toDateString(),
                $row->student->name ?? '#'.$row->student_id,
                $row->schoolClass->name ?? '#'.$row->school_class_id,
                $row->status,
                $row->recorder->name ?? '-',
                $row->id,
            ]);
            $this->table(['Date', 'Learner', 'Recorded in', 'Status', 'Recorded by', 'Row ID'], $this->limited($rows->all(), $limit));
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @return array<int, array<int, mixed>>
     */
    private function limited(array $rows, int $limit): array
    {
        if ($limit > 0 && count($rows) > $limit) {
            $this->warn('Showing the first '.$limit.' of '.count($rows).' rows (use --limit=0 for all).');

            return array_slice($rows, 0, $limit);
        }

        return $rows;
    }
}
