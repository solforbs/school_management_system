<?php

namespace Modules\Timetable\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Modules\Classes\Models\SchoolClass;
use Modules\Subject\Models\Subject;
use Modules\Timetable\Models\TimetableEntry;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Imports a timetable for a single class from a CSV or XLS/XLSX file and
 * merges it in - only that class's existing entries are replaced, every
 * other class's timetable is left untouched.
 *
 * Expected columns (header row required, case-insensitive, any order):
 * Day | Start Time | End Time | Subject | Teacher Email | Room
 *
 * Rows whose Subject is blank or is "BREAK"/"LUNCH" are treated as
 * non-teaching slots and simply skipped - the grid view already renders
 * gaps for periods with no entry, so nothing needs to be stored for them.
 */
class TimetableImportService
{
    private const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    private const SKIP_SUBJECTS = ['BREAK', 'LUNCH', 'SHORT BREAK', 'LUNCH BREAK', 'TEA BREAK', 'TEA', 'MORNING BREAK'];

    /**
     * @return array{created: int, skipped: int, errors: array<int, string>, warnings: array<int, string>}
     */
    public function import(UploadedFile $file, SchoolClass $class): array
    {
        $subjectIds = Subject::where('institution_id', $class->institution_id)->pluck('id', 'name');

        $rows = $this->readRows($file);

        if (empty($rows)) {
            return ['created' => 0, 'skipped' => 0, 'errors' => ['The file is empty or has no data rows.'], 'warnings' => []];
        }

        $header = array_map(fn ($h) => strtolower(trim((string) $h)), array_shift($rows));
        $columns = $this->mapColumns($header);

        if (! isset($columns['day'], $columns['start_time'], $columns['end_time'], $columns['subject'])) {
            return [
                'created' => 0,
                'skipped' => 0,
                'errors' => ['Missing required columns. Expected: Day, Start Time, End Time, Subject (Teacher Email and Room are optional).'],
                'warnings' => [],
            ];
        }

        $toInsert = [];
        $errors = [];
        $warnings = [];
        $skipped = 0;

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // +1 for header, +1 for 1-based display

            if ($this->isBlankRow($row)) {
                continue;
            }

            $day = $this->normalizeDay((string) ($row[$columns['day']] ?? ''));
            $subject = trim((string) ($row[$columns['subject']] ?? ''));

            if ($subject === '' || in_array(strtoupper($subject), self::SKIP_SUBJECTS, true)) {
                $skipped++;

                continue;
            }

            if (! $day) {
                $errors[] = "Row {$rowNumber}: invalid or missing day.";

                continue;
            }

            $startTime = $this->normalizeTime($row[$columns['start_time']] ?? null);
            $endTime = $this->normalizeTime($row[$columns['end_time']] ?? null);

            if (! $startTime || ! $endTime) {
                $errors[] = "Row {$rowNumber}: invalid start/end time.";

                continue;
            }

            if ($startTime >= $endTime) {
                $errors[] = "Row {$rowNumber}: end time must be after start time.";

                continue;
            }

            $teacherId = null;
            $teacherIdentifier = isset($columns['teacher'])
                ? trim((string) ($row[$columns['teacher']] ?? ''))
                : (isset($columns['teacher_email']) ? trim((string) ($row[$columns['teacher_email']] ?? '')) : '');

            if ($teacherIdentifier !== '') {
                $teacher = User::role('Teacher')
                    ->where(function ($q) use ($teacherIdentifier) {
                        $q->where('email', $teacherIdentifier)
                            ->orWhere('name', $teacherIdentifier)
                            ->orWhereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim($teacherIdentifier))]);
                    })
                    ->whereHas('teacherUserDetails', fn ($q) => $q->where('institution_id', $class->institution_id))
                    ->first();

                if ($teacher) {
                    $teacherId = $teacher->id;
                } else {
                    $warnings[] = "Row {$rowNumber}: no teacher found for '{$teacherIdentifier}' in this institution - left unassigned.";
                }
            }

            if ($teacherId && $this->hasTeacherConflict($teacherId, $day, $startTime, $endTime, $class->id, $toInsert)) {
                $errors[] = "Row {$rowNumber}: teacher is already scheduled for another class on {$day} at {$startTime}-{$endTime}.";

                continue;
            }

            $room = isset($columns['room']) ? trim((string) ($row[$columns['room']] ?? '')) : null;
            $notes = isset($columns['notes']) ? trim((string) ($row[$columns['notes']] ?? '')) : null;

            $toInsert[] = [
                'institution_id' => $class->institution_id,
                'class_id' => $class->id,
                'teacher_id' => $teacherId,
                'class_name' => $class->name,
                'subject' => $subject,
                'subject_id' => $subjectIds[$subject] ?? null,
                'day_of_week' => $day,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'room' => $room !== '' ? $room : null,
                'notes' => $notes !== '' ? $notes : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (empty($toInsert)) {
            return ['created' => 0, 'skipped' => $skipped, 'errors' => $errors ?: ['No valid rows found to import.'], 'warnings' => $warnings];
        }

        DB::transaction(function () use ($class, $toInsert) {
            $existing = TimetableEntry::where('class_id', $class->id)->withCount('lessons')->get();
            $existingBySlot = $existing->keyBy(fn ($e) => $e->day_of_week.'_'.($e->start_time ? $e->start_time->format('H:i') : ''));
            $preservedIds = [];
            $remainingToInsert = [];

            foreach ($toInsert as $row) {
                $slotKey = $row['day_of_week'].'_'.substr((string) $row['start_time'], 0, 5);
                $match = $existingBySlot->get($slotKey);

                if ($match && $match->lessons_count > 0) {
                    $match->update([
                        'teacher_id' => $row['teacher_id'],
                        'subject' => $row['subject'],
                        'subject_id' => $row['subject_id'],
                        'end_time' => $row['end_time'],
                        'room' => $row['room'],
                        'notes' => $row['notes'],
                    ]);
                    $preservedIds[] = $match->id;
                } else {
                    $remainingToInsert[] = $row;
                }
            }

            $staleIds = $existing->pluck('id')->diff($preservedIds)->values()->all();
            if (! empty($staleIds)) {
                TimetableEntry::whereIn('id', $staleIds)->whereDoesntHave('lessons')->delete();
            }

            if (! empty($remainingToInsert)) {
                foreach (array_chunk($remainingToInsert, 100) as $chunk) {
                    TimetableEntry::insert($chunk);
                }
            }
        });

        return ['created' => count($toInsert), 'skipped' => $skipped, 'errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Rejects a teacher being double-booked into an overlapping slot for a
     * different class - checks both rows already queued in this same
     * import batch (all for the class being imported) and existing DB rows
     * for every other class (the class being replaced is excluded since
     * its old entries are about to be deleted anyway).
     *
     * @param  array<int, array<string, mixed>>  $toInsert
     */
    private function hasTeacherConflict(int $teacherId, string $day, string $start, string $end, int $excludeClassId, array $toInsert): bool
    {
        $overlaps = fn (string $aStart, string $aEnd, string $bStart, string $bEnd) => $aStart < $bEnd && $aEnd > $bStart;

        foreach ($toInsert as $row) {
            if ($row['teacher_id'] === $teacherId
                && $row['day_of_week'] === $day
                && $overlaps($row['start_time'], $row['end_time'], $start, $end)) {
                return true;
            }
        }

        return TimetableEntry::where('teacher_id', $teacherId)
            ->where('day_of_week', $day)
            ->where('class_id', '!=', $excludeClassId)
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start)
            ->exists();
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function readRows(UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();

        return $sheet->toArray(null, true, true, false);
    }

    /**
     * @param  array<int, string>  $header
     * @return array<string, int>
     */
    private function mapColumns(array $header): array
    {
        $map = [];

        foreach ($header as $index => $label) {
            $key = match (true) {
                str_contains($label, 'day') => 'day',
                str_contains($label, 'start') => 'start_time',
                str_contains($label, 'end') => 'end_time',
                str_contains($label, 'subject') => 'subject',
                str_contains($label, 'teacher') => 'teacher',
                str_contains($label, 'room') => 'room',
                str_contains($label, 'note') => 'notes',
                default => null,
            };

            if ($key) {
                $map[$key] = $index;
            }
        }

        return $map;
    }

    private function isBlankRow(array $row): bool
    {
        return collect($row)->filter(fn ($v) => trim((string) $v) !== '')->isEmpty();
    }

    private function normalizeDay(string $value): ?string
    {
        $value = trim($value);

        foreach (self::DAYS as $day) {
            if (strcasecmp($value, $day) === 0 || strcasecmp($value, substr($day, 0, 3)) === 0) {
                return $day;
            }
        }

        return null;
    }

    private function normalizeTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Excel time-formatted cells can come through as a numeric day
        // fraction (e.g. 0.34375 for 08:15).
        if (is_numeric($value)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('H:i');
            } catch (\Throwable) {
                return null;
            }
        }

        $value = trim((string) $value);

        foreach (['H:i', 'H:i:s', 'g:i A', 'g:i a'] as $format) {
            $date = \DateTime::createFromFormat($format, $value);
            if ($date !== false) {
                return $date->format('H:i');
            }
        }

        return null;
    }
}
