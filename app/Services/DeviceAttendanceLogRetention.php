<?php

namespace App\Services;

use App\Models\Devices;
use Athwari\LaravelZktecoAdms\Enums\CommandStatus;
use Athwari\LaravelZktecoAdms\Exceptions\CommandQueueFullException;
use Athwari\LaravelZktecoAdms\Facades\ZktecoAdms;
use Athwari\LaravelZktecoAdms\Models\ZktecoDevice;
use Athwari\LaravelZktecoAdms\Models\ZktecoDeviceCommand;

/**
 * Keeps a terminal's own onboard flash from filling up.
 *
 * The device pushes every punch to ADMS the moment it happens, so the
 * database already has the full history - only the terminal's limited
 * internal storage is at risk. This is the single place that decides "the
 * device never needs more than a week of its own logs," shared by the
 * scheduled daily sweep and the manual "Clear now" button on the device
 * management page.
 */
class DeviceAttendanceLogRetention
{
    private const RETENTION_DAYS = 7;

    /**
     * Queue a CLEAR LOG command if this device is actually due for one.
     * Used by the scheduled sweep - never clears an offline device (unless
     * $force) or one that isn't yet 7 days past its last clear.
     */
    public function clearIfDue(Devices $device, bool $force = false): DeviceAttendanceLogRetentionOutcome
    {
        $zktecoDevice = $device->zktecoDevice;

        if (! $zktecoDevice) {
            return DeviceAttendanceLogRetentionOutcome::NeverConnected;
        }

        if (! $force && ! $zktecoDevice->isOnline()) {
            return DeviceAttendanceLogRetentionOutcome::Offline;
        }

        if (! $this->isDue($zktecoDevice)) {
            return DeviceAttendanceLogRetentionOutcome::NotDue;
        }

        return $this->queueClear($device, $zktecoDevice);
    }

    /**
     * Queue a CLEAR LOG command right now, regardless of the 7-day cadence -
     * an explicit operator action from the device management page.
     */
    public function clearNow(Devices $device): DeviceAttendanceLogRetentionOutcome
    {
        $zktecoDevice = $device->zktecoDevice;

        if (! $zktecoDevice) {
            return DeviceAttendanceLogRetentionOutcome::NeverConnected;
        }

        return $this->queueClear($device, $zktecoDevice);
    }

    /**
     * Whether 7 days have passed since this device's last clear - or since
     * it registered, if it has never been cleared. A brand-new device is
     * therefore never wiped on its first day.
     */
    public function isDue(ZktecoDevice $zktecoDevice): bool
    {
        $dueSince = $zktecoDevice->attendance_logs_cleared_at ?? $zktecoDevice->created_at;

        return $dueSince === null || $dueSince->diffInDays(now()) >= self::RETENTION_DAYS;
    }

    private function hasPendingClear(ZktecoDevice $zktecoDevice): bool
    {
        return ZktecoDeviceCommand::where('device_id', $zktecoDevice->id)
            ->where('status', CommandStatus::Pending)
            ->where('command_content', 'like', 'CLEAR %')
            ->exists();
    }

    private function queueClear(Devices $device, ZktecoDevice $zktecoDevice): DeviceAttendanceLogRetentionOutcome
    {
        if ($this->hasPendingClear($zktecoDevice)) {
            return DeviceAttendanceLogRetentionOutcome::AlreadyPending;
        }

        try {
            ZktecoAdms::sendClearLogsCommand($device->serial_number);
        } catch (CommandQueueFullException) {
            return DeviceAttendanceLogRetentionOutcome::QueueFull;
        }

        // Stamped at queue-time, not at device acknowledgement - the same
        // tradeoff DeviceClockSync already makes for "SET OPTION DateTime",
        // and CLEAR LOG is idempotent so re-queuing before it actually runs
        // is harmless.
        $zktecoDevice->update(['attendance_logs_cleared_at' => now()]);

        return DeviceAttendanceLogRetentionOutcome::Queued;
    }
}
