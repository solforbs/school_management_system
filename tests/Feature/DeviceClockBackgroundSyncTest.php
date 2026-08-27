<?php

use App\Models\Devices;
use App\Models\User;
use App\Services\ClockSyncOutcome;
use App\Services\DeviceClockSync;
use Athwari\LaravelZktecoAdms\Enums\CommandStatus;
use Athwari\LaravelZktecoAdms\Models\ZktecoDevice;
use Athwari\LaravelZktecoAdms\Models\ZktecoDeviceCommand;
use Athwari\LaravelZktecoAdms\Services\CommandManager;
use Carbon\Carbon;
use Modules\Institution\Models\Institution;

/*
 * A terminal stamps every punch with its own clock, so drift files
 * attendance at the wrong hour without ever announcing itself. Two things
 * keep it honest: the hourly schedule and the manual Set Time button, both
 * going through one service so they cannot disagree with each other.
 */

function makeDevice(string $serial, ?int $lastSeenMinutesAgo = 1): Devices
{
    $institution = Institution::create([
        'user_id' => User::factory()->create()->id,
        'name' => 'Imaara Secondary School',
        'code' => 'INST-'.uniqid(),
        'type' => 'School',
        'is_active' => true,
        'status' => 'active',
        'is_approved' => true,
    ]);

    $device = Devices::create([
        'serial_number' => $serial,
        'institution_id' => $institution->id,
        'is_active' => true,
    ]);

    $zkteco = ZktecoDevice::create([
        'serial_number' => $serial,
        'name' => 'K40 Pro',
        'status' => 'online',
        'last_activity_at' => $lastSeenMinutesAgo === null ? null : now()->subMinutes($lastSeenMinutesAgo),
        'timezone' => 'UTC',
        'options' => [],
    ]);

    $device->update(['zkteco_device_id' => $zkteco->id]);

    return $device->fresh();
}

it('queues a clock for a terminal that is checking in', function () {
    config()->set('app.timezone', 'Africa/Nairobi');
    makeDevice('SN-ONLINE');

    $this->artisan('zkteco:sync-clocks')->assertSuccessful();

    expect(ZktecoDeviceCommand::count())->toBe(1)
        ->and(ZktecoDeviceCommand::first()->command_content)->toStartWith('SET OPTION DateTime=');
});

/*
 * A clock command carries a fixed moment. Queueing one for a terminal that
 * is switched off just parks a stale time waiting to be applied hours
 * later - worse than not syncing at all. They are picked up on the next
 * run after they start checking in again.
 */
it('leaves a terminal that is not checking in alone', function () {
    makeDevice('SN-OFFLINE', lastSeenMinutesAgo: 240);

    $this->artisan('zkteco:sync-clocks')->assertSuccessful();

    expect(ZktecoDeviceCommand::count())->toBe(0);
});

it('includes the offline ones when asked explicitly', function () {
    makeDevice('SN-OFFLINE', lastSeenMinutesAgo: 240);

    $this->artisan('zkteco:sync-clocks', ['--all' => true])->assertSuccessful();

    expect(ZktecoDeviceCommand::count())->toBe(1);
});

it('never lets a stale clock pile up behind a fresh one', function () {
    config()->set('app.timezone', 'Africa/Nairobi');
    $device = makeDevice('SN-ONLINE');

    Carbon::setTestNow(Carbon::parse('2026-08-20 07:00:00', 'Africa/Nairobi'));
    $this->artisan('zkteco:sync-clocks');

    Carbon::setTestNow(Carbon::parse('2026-08-20 08:00:00', 'Africa/Nairobi'));
    $this->artisan('zkteco:sync-clocks');

    Carbon::setTestNow(Carbon::parse('2026-08-20 09:00:00', 'Africa/Nairobi'));
    $this->artisan('zkteco:sync-clocks');

    // One command, carrying the newest time - not three, and not 07:00.
    expect(ZktecoDeviceCommand::count())->toBe(1);

    expect(ZktecoDeviceCommand::first()->command_content)
        ->toBe('SET OPTION DateTime='.app(CommandManager::class)
            ->encodeDeviceTime(Carbon::parse('2026-08-20 09:00:00', 'Africa/Nairobi')));

    Carbon::setTestNow();
});

/*
 * A command already handed to the device must not be deleted - it is on its
 * way and will be acknowledged. Only undelivered ones are replaceable.
 */
it('does not disturb a clock already handed to the device', function () {
    $device = makeDevice('SN-ONLINE');

    $this->artisan('zkteco:sync-clocks');
    $this->get('/iclock/getrequest?SN=SN-ONLINE');   // device collects it

    expect(ZktecoDeviceCommand::first()->status)->toBe(CommandStatus::Sent);

    $this->artisan('zkteco:sync-clocks');

    // The collected one survives; a fresh one joins it.
    expect(ZktecoDeviceCommand::count())->toBe(2)
        ->and(ZktecoDeviceCommand::where('status', CommandStatus::Sent)->count())->toBe(1);
});

/*
 * A terminal that has been off comes back into scope on the next hourly
 * run, because dialling in is what marks it online again. Its own RTC keeps
 * time across a reboot, so an hour's wait costs at most an hour of drift -
 * seconds, on hardware like this.
 *
 * Syncing on the reconnect itself would be tighter, but DeviceConnected is
 * never dispatched: DeviceManager::registerDevice() stamps last_activity_at
 * before updateActivity() reads it to detect the transition. That bug also
 * means SyncStudentsToDeviceListener has never run. Fixing it is a separate
 * decision - it would start queueing one command per student against a
 * 100-command cap - so this deliberately leans on the schedule instead.
 */
it('picks up a returning terminal on the next scheduled run', function () {
    config()->set('app.timezone', 'Africa/Nairobi');
    makeDevice('SN-RETURNING', lastSeenMinutesAgo: 600);

    // Too quiet to sync while it is away.
    $this->artisan('zkteco:sync-clocks');
    expect(ZktecoDeviceCommand::count())->toBe(0);

    // It dials in, which marks it online.
    $this->get('/iclock/cdata?SN=SN-RETURNING&options=all');

    $this->artisan('zkteco:sync-clocks');
    expect(ZktecoDeviceCommand::where('command_content', 'like', 'SET OPTION DateTime=%')->count())
        ->toBe(1);
});

it('stamps the device timezone so punches are read as they were set', function () {
    config()->set('app.timezone', 'Africa/Nairobi');
    $device = makeDevice('SN-ONLINE');

    expect($device->zktecoDevice->timezone)->toBe('UTC');

    app(DeviceClockSync::class)->sync($device);

    expect($device->zktecoDevice->fresh()->timezone)->toBe('Africa/Nairobi');
});

it('reports a device that has never connected instead of failing', function () {
    $institution = Institution::create([
        'user_id' => User::factory()->create()->id,
        'name' => 'Imaara Secondary School', 'code' => 'INST-'.uniqid(),
        'type' => 'School', 'is_active' => true, 'status' => 'active', 'is_approved' => true,
    ]);

    $device = Devices::create([
        'serial_number' => 'SN-GHOST',
        'institution_id' => $institution->id,
        'is_active' => true,
    ]);

    expect(app(DeviceClockSync::class)->sync($device))
        ->toBe(ClockSyncOutcome::NeverConnected);
});
