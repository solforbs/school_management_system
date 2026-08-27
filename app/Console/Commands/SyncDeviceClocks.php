<?php

namespace App\Console\Commands;

use App\Models\Devices;
use App\Services\ClockSyncOutcome;
use App\Services\DeviceClockSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Keep every connected terminal's clock on the server's.
 *
 * A drifting clock does not announce itself - the device stamps punches
 * with its own time, so attendance quietly files at the wrong hour and
 * nobody notices until a report looks strange.
 *
 * Only devices currently checking in are synced. A clock command carries a
 * fixed moment rather than "now", so queueing one for a terminal that is
 * switched off would just park a stale time waiting to be applied hours
 * later. Those are picked up on the next run after they start checking in
 * again; --all overrides that when you want to reach everything.
 */
class SyncDeviceClocks extends Command
{
    protected $signature = 'zkteco:sync-clocks {--all : Include devices that are not currently checking in}';

    protected $description = 'Queue a clock sync for connected biometric terminals';

    public function handle(DeviceClockSync $clock): int
    {
        $devices = Devices::with('zktecoDevice')
            ->where('is_active', true)
            ->whereNotNull('zkteco_device_id')
            ->get();

        $synced = 0;
        $skipped = 0;

        foreach ($devices as $device) {
            if (! $this->option('all') && ! $device->zktecoDevice?->isOnline()) {
                $skipped++;

                continue;
            }

            $outcome = $clock->sync($device);

            if ($outcome === ClockSyncOutcome::Queued) {
                $synced++;
                $this->line("  queued  {$device->serial_number}");

                continue;
            }

            $skipped++;
            $this->line("  skipped {$device->serial_number} ({$outcome->value})");

            if ($outcome === ClockSyncOutcome::QueueFull) {
                Log::warning('Clock sync skipped, device command queue full', [
                    'device' => $device->serial_number,
                ]);
            }
        }

        $this->info(sprintf(
            'Clock sync: %d queued, %d skipped, at %s (%s).',
            $synced,
            $skipped,
            $clock->currentServerTime()->format('Y-m-d H:i:s'),
            $clock->timezone(),
        ));

        return self::SUCCESS;
    }
}
