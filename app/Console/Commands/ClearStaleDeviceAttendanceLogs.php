<?php

namespace App\Console\Commands;

use App\Models\Devices;
use App\Services\DeviceAttendanceLogRetention;
use App\Services\DeviceAttendanceLogRetentionOutcome;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Wipes a terminal's own onboard attendance log once a week, so its limited
 * flash storage never fills up. Safe to run daily: DeviceAttendanceLogRetention
 * only actually queues a clear once 7 days have passed since the last one,
 * and only for a device currently checking in (a CLEAR LOG command has no
 * "as of" - it executes whenever the device next polls, so queuing it for a
 * long-offline device would risk wiping a backlog it may not have pushed
 * yet; --all overrides that for an operator who knows it's safe).
 *
 * The database already has every record the moment the device pushes it -
 * this only protects the terminal's own storage, never the source of truth.
 */
class ClearStaleDeviceAttendanceLogs extends Command
{
    protected $signature = 'zkteco:clear-old-attendance-logs {--all : Include devices that are not currently checking in}';

    protected $description = 'Clear onboard attendance logs from biometric terminals that have kept more than a week';

    public function handle(DeviceAttendanceLogRetention $retention): int
    {
        $devices = Devices::with('zktecoDevice')
            ->whereNotNull('zkteco_device_id')
            ->get();

        $cleared = 0;
        $skipped = 0;

        foreach ($devices as $device) {
            $outcome = $retention->clearIfDue($device, force: (bool) $this->option('all'));

            if ($outcome === DeviceAttendanceLogRetentionOutcome::Queued) {
                $cleared++;
                $this->line("  cleared {$device->serial_number}");

                continue;
            }

            $skipped++;
            $this->line("  skipped {$device->serial_number} ({$outcome->value})");

            if ($outcome === DeviceAttendanceLogRetentionOutcome::QueueFull) {
                Log::warning('Device log clear skipped, command queue full', [
                    'device' => $device->serial_number,
                ]);
            }
        }

        $this->info(sprintf('Device log retention: %d cleared, %d skipped.', $cleared, $skipped));

        return self::SUCCESS;
    }
}
