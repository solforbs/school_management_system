<?php

namespace Modules\Timetable\Actions;

use Modules\Classes\Models\SchoolClass;
use Modules\Subject\Models\Subject;
use Modules\Timetable\Models\TimetableEntry;

/**
 * Scheduling a lesson slot, in one place - shared by the Livewire screen and
 * the controller endpoint.
 */
class SaveTimetableEntry
{
    /** @var string[] */
    public const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    /**
     * @return array<string, string>
     */
    public static function rules(): array
    {
        return [
            'class_id' => 'required|exists:classes,id',
            'teacher_id' => 'nullable|exists:users,id',
            'subject' => 'required|string|max:255',
            'day_of_week' => 'required|in:'.implode(',', self::DAYS),
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'room' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ];
    }

    /**
     * Whether the chosen teacher is already standing in another room at
     * that time. A slot with no teacher assigned is always fine.
     *
     * @param  array<string, mixed>  $data
     */
    public static function teacherIsDoubleBooked(array $data, ?int $excludeId = null): bool
    {
        if (empty($data['teacher_id'])) {
            return false;
        }

        return TimetableEntry::where('teacher_id', $data['teacher_id'])
            ->where('day_of_week', $data['day_of_week'])
            ->when($excludeId, fn ($query) => $query->whereKeyNot($excludeId))
            ->where(function ($query) use ($data) {
                $query->where('start_time', '<', $data['end_time'])
                    ->where('end_time', '>', $data['start_time']);
            })
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function handle(array $data, int $institutionId, ?TimetableEntry $entry = null): TimetableEntry
    {
        $schoolClass = SchoolClass::find($data['class_id']);

        // 'exists' alone would let a crafted request schedule a slot in
        // another school's class.
        abort_unless($schoolClass && $schoolClass->institution_id === $institutionId, 403);

        $payload = [
            'institution_id' => $institutionId,
            'class_id' => $data['class_id'],
            'teacher_id' => $data['teacher_id'] ?: null,
            'subject' => $data['subject'],
            'day_of_week' => $data['day_of_week'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'room' => $data['room'] ?? null,
            'notes' => $data['notes'] ?? null,

            // Keep the legacy class_name column in sync for anything still
            // reading it directly, though class_id is the source of truth.
            'class_name' => $schoolClass?->name,

            // Best-effort link to the Subject catalog - subject stays free
            // text since not every typed name has a matching catalog entry.
            'subject_id' => Subject::where('institution_id', $institutionId)
                ->where('name', $data['subject'])
                ->value('id'),
        ];

        if ($entry) {
            $entry->update($payload);

            return $entry;
        }

        return TimetableEntry::create($payload);
    }
}
