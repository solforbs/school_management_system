<?php

namespace App\Services;

use App\Models\Devices;
use Athwari\LaravelZktecoAdms\Enums\CommandStatus;
use Athwari\LaravelZktecoAdms\Exceptions\CommandQueueFullException;
use Athwari\LaravelZktecoAdms\Models\ZktecoDeviceCommand;
use Athwari\LaravelZktecoAdms\Services\CommandManager;
use Carbon\Carbon;

/**
 * Keeps a terminal's clock on the server's.
 *
 * The device stamps every punch with its own clock, so a clock that has
 * drifted does not announce itself - it quietly files attendance at the
 * wrong time. This is the single place that decides what "sync the clock"
 * means, shared by the Set Time button, the hourly schedule, and the
 * listener that fires when a device reconnects.
 */
class DeviceClockSync
{
    public function __construct(private readonly CommandManager $commands) {}

    public function sync(Devices $device): ClockSyncOutcome
    {
        $zktecoDevice = $device->zktecoDevice;

        if (! $zktecoDevice) {
            return ClockSyncOutcome::NeverConnected;
        }

        // A queued clock is a fixed moment, not "now" - one that has sat
        // unclaimed while the device was off would set it to a stale time
        // on delivery, which is worse than never syncing at all. Discard
        // anything undelivered and queue the current moment instead.
        $zktecoDevice->deviceCommands()
            ->where('status', CommandStatus::Pending)
            ->where('command_content', 'like', 'SET OPTION DateTime=%')
            ->delete();

        $timezone = $this->timezone();
        $now = Carbon::now($timezone);

        try {
            $this->commands->sendSetTimeCommand($device->serial_number, $now);
        } catch (CommandQueueFullException) {
            return ClockSyncOutcome::QueueFull;
        }

        // Stamp the timezone the clock was just set in, so the punches the
        // device sends back are read in the same one. This column is written
        // once at registration and never updated otherwise, which on a
        // production install usually means it is still sitting at UTC.
        if ($zktecoDevice->timezone !== $timezone) {
            $zktecoDevice->update(['timezone' => $timezone]);
        }

        return ClockSyncOutcome::Queued;
    }

    /**
     * The moment a sync would set, for showing back to whoever asked.
     */
    public function currentServerTime(): Carbon
    {
        return Carbon::now($this->timezone());
    }

    /**
     * The server's own timezone - the thing the terminal is being matched to.
     */
    public function timezone(): string
    {
        return (string) config('app.timezone', 'UTC');
    }

    /**
     * Whether a clock is already waiting to be collected.
     */
    public function hasPendingSync(Devices $device): bool
    {
        if (! $device->zkteco_device_id) {
            return false;
        }

        return ZktecoDeviceCommand::where('device_id', $device->zkteco_device_id)
            ->where('status', CommandStatus::Pending)
            ->where('command_content', 'like', 'SET OPTION DateTime=%')
            ->exists();
    }
}
