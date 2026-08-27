<?php

namespace App\Services;

enum ClockSyncOutcome: string
{
    /** A fresh clock is queued and waiting for the terminal's next poll. */
    case Queued = 'queued';

    /** No physical unit has ever checked in under this serial number. */
    case NeverConnected = 'never_connected';

    /** The device's command queue is full; it has stopped collecting. */
    case QueueFull = 'queue_full';
}
