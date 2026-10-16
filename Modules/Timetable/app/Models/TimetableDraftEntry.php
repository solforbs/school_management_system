<?php

namespace Modules\Timetable\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Classes\Models\SchoolClass;
use Modules\Institution\Models\Institution;
use Modules\Subject\Models\Subject;

class TimetableDraftEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'draft_id',
        'institution_id',
        'class_id',
        'subject_id',
        'teacher_id',
        'day_of_week',
        'start_time',
        'end_time',
        'period_number',
        'room',
        'is_locked',
        'notes',
    ];

    protected $casts = [
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'is_locked' => 'boolean',
        'period_number' => 'integer',
    ];

    public function draft(): BelongsTo
    {
        return $this->belongsTo(TimetableDraft::class, 'draft_id');
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function getSubjectTitleAttribute(): string
    {
        return $this->subject?->name ?? 'Subject #'.$this->subject_id;
    }

    public function getClassNameAttribute(): string
    {
        return $this->schoolClass?->name ?? 'Class #'.$this->class_id;
    }

    /**
     * Check if a teacher has another entry overlapping this time window within the draft
     */
    public static function checkTeacherConflict(
        int $draftId,
        int $teacherId,
        string $day,
        string $startTime,
        string $endTime,
        ?int $excludeEntryId = null
    ): bool {
        return self::where('draft_id', $draftId)
            ->where('teacher_id', $teacherId)
            ->where('day_of_week', $day)
            ->when($excludeEntryId, fn ($q) => $q->whereKeyNot($excludeEntryId))
            ->where(function ($q) use ($startTime, $endTime) {
                $q->where('start_time', '<', $endTime)
                    ->where('end_time', '>', $startTime);
            })
            ->exists();
    }

    /**
     * Check if a class has another entry overlapping this time window within the draft
     */
    public static function checkClassConflict(
        int $draftId,
        int $classId,
        string $day,
        string $startTime,
        string $endTime,
        ?int $excludeEntryId = null
    ): bool {
        return self::where('draft_id', $draftId)
            ->where('class_id', $classId)
            ->where('day_of_week', $day)
            ->when($excludeEntryId, fn ($q) => $q->whereKeyNot($excludeEntryId))
            ->where(function ($q) use ($startTime, $endTime) {
                $q->where('start_time', '<', $endTime)
                    ->where('end_time', '>', $startTime);
            })
            ->exists();
    }

    public function isDoubleLesson(): bool
    {
        return str_contains((string) $this->notes, '[Double Lesson');
    }

    public function isDoubleLessonPart1(): bool
    {
        return str_contains((string) $this->notes, '[Double Lesson Part 1/2]');
    }

    public function isDoubleLessonPart2(): bool
    {
        return str_contains((string) $this->notes, '[Double Lesson Part 2/2]');
    }
}
