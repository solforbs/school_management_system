<?php

namespace App\Observers;

use Athwari\LaravelZktecoAdms\Enums\AttendanceStatus;
use Athwari\LaravelZktecoAdms\Models\ZktecoAttendanceLog;

/**
 * Reclassifies a raw device punch into the school's actual check-in /
 * check-out / overtime schedule, before it ever reaches the database.
 *
 * A ZKTeco terminal reports whatever punch-state the operator last set on
 * its own keypad, which for a school gate is usually just left on one
 * setting and is not a reliable signal of intent. What is reliable is *when*
 * the punch happened, so this rewrites `status` from the time of day (in the
 * system timezone) combined with the raw in/out direction the device did
 * report:
 *
 *   07:00-08:30  -> Check-In   (only for an "in"-family punch)
 *   16:00-18:00  -> Check-Out  (only for an "out"-family punch)
 *   before 07:00 or from 18:00 -> Overtime In/Out, by direction
 *   08:30-16:00  -> left untouched (no rule covers a mid-day punch; forcing
 *                   one would misrepresent a genuine break-in/out)
 *
 * Registered on ZktecoAttendanceLog::creating(), not on the AttendanceReceived
 * event - that event fires after the batch is already persisted and only
 * carries DTOs, not the saved rows, so a listener would need a fragile
 * re-query. Observing `creating` rewrites the attribute in memory before the
 * INSERT, with no extra query and no change to the vendor package.
 */
class ZktecoAttendanceLogObserver
{
    private const IN_FAMILY = [AttendanceStatus::CheckIn, AttendanceStatus::BreakIn, AttendanceStatus::OvertimeIn];

    private const OUT_FAMILY = [AttendanceStatus::CheckOut, AttendanceStatus::BreakOut, AttendanceStatus::OvertimeOut];

    public function creating(ZktecoAttendanceLog $log): void
    {
        if (! $log->occurred_at || ! $log->status instanceof AttendanceStatus) {
            return;
        }

        $time = $log->occurred_at->clone()->setTimezone(config('app.timezone'))->format('H:i');
        $isIn = in_array($log->status, self::IN_FAMILY, true);
        $isOut = in_array($log->status, self::OUT_FAMILY, true);

        $log->status = match (true) {
            $time >= '07:00' && $time < '08:30' && $isIn => AttendanceStatus::CheckIn,
            $time >= '16:00' && $time < '18:00' && $isOut => AttendanceStatus::CheckOut,
            ($time < '07:00' || $time >= '18:00') && $isIn => AttendanceStatus::OvertimeIn,
            ($time < '07:00' || $time >= '18:00') && $isOut => AttendanceStatus::OvertimeOut,
            default => $log->status,
        };
    }
}
