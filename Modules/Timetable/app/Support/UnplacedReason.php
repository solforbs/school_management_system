<?php

namespace Modules\Timetable\Support;

/**
 * Why a lesson could not be placed. Stored in the draft's conflict summary
 * so the UI can show a specific, actionable reason.
 */
enum UnplacedReason: string
{
    case ClassSlotsFull = 'CLASS_SLOTS_FULL';
    case TeacherBusy = 'TEACHER_BUSY';
    case TeacherDailyLimit = 'TEACHER_DAILY_LIMIT';
    case TeacherConsecutiveLimit = 'TEACHER_CONSECUTIVE_LIMIT';
    case SubjectDailyLimit = 'SUBJECT_DAILY_LIMIT';
    case NoDoublePairAvailable = 'NO_DOUBLE_PAIR_AVAILABLE';
    case LockedConflict = 'LOCKED_CONFLICT';

    /**
     * Higher number = more specific/actionable when several reasons apply.
     */
    public function priority(): int
    {
        return match ($this) {
            self::TeacherBusy => 60,
            self::TeacherDailyLimit => 50,
            self::TeacherConsecutiveLimit => 40,
            self::SubjectDailyLimit => 30,
            self::NoDoublePairAvailable => 20,
            self::LockedConflict => 15,
            self::ClassSlotsFull => 10,
        };
    }
}
