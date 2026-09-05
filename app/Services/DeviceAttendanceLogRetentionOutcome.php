<?php

namespace App\Services;

enum DeviceAttendanceLogRetentionOutcome: string
{
    /** A CLEAR LOG command is queued and waiting for the terminal's next poll. */
    case Queued = 'queued';

    /** No physical unit has ever checked in under this serial number. */
    case NeverConnected = 'never_connected';

    /** The device isn't currently checking in - clearing was skipped. */
    case Offline = 'offline';

    /** Fewer than 7 days have passed since the last clear (or registration). */
    case NotDue = 'not_due';

    /** A clear command is already queued/sent and unacknowledged. */
    case AlreadyPending = 'already_pending';

    /** The device's command queue is full; it has stopped collecting. */
    case QueueFull = 'queue_full';
}
