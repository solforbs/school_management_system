<?php

use App\Models\Devices;
use App\Models\User;
use Athwari\LaravelZktecoAdms\Models\ZktecoDevice;
use Athwari\LaravelZktecoAdms\Models\ZktecoDeviceCommand;
use Athwari\LaravelZktecoAdms\Services\CommandManager;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Modules\Institution\Models\Institution;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function schoolWithDevice(bool $connected = true): array
{
    $director = User::factory()->create();
    $director->assignRole('Director');

    $institution = Institution::create([
        'user_id' => $director->id,
        'name' => 'Imaara Secondary School',
        'code' => 'INST-'.uniqid(),
        'type' => 'School',
        'is_active' => true,
        'status' => 'active',
        'is_approved' => true,
    ]);

    $device = Devices::create([
        'serial_number' => 'GED7261800014',
        'institution_id' => $institution->id,
        'is_active' => true,
    ]);

    if ($connected) {
        $zkteco = ZktecoDevice::create([
            'serial_number' => 'GED7261800014',
            'name' => 'K40 Pro',
            'status' => 'online',
            'last_activity_at' => now(),
            'timezone' => 'UTC',
            'options' => [],
        ]);

        $device->update(['zkteco_device_id' => $zkteco->id]);
    }

    return [$director, $device];
}

// ------------------------------------------------------------------
// The encoding. ZKTeco's calendar treats every month as 31 days, so a
// Unix timestamp will not do - these pin the packing so a refactor
// cannot quietly send a device to the wrong century.
// ------------------------------------------------------------------

it('packs a moment the way the firmware unpacks it', function () {
    $encoded = app(CommandManager::class)->encodeDeviceTime(
        Carbon::parse('2026-08-20 07:42:11')
    );

    // ((26*12*31) + (7*31) + 19) * 86400 + 7*3600 + 42*60 + 11
    expect($encoded)->toBe(856_078_931);
});

it('round-trips back to the wall clock it was given', function (string $moment) {
    $encoded = app(CommandManager::class)->encodeDeviceTime(Carbon::parse($moment));

    // Undo the packing the way the device does.
    $seconds = $encoded % 86400;
    $days = intdiv($encoded, 86400);

    $decoded = sprintf(
        '%04d-%02d-%02d %02d:%02d:%02d',
        2000 + intdiv($days, 12 * 31),
        intdiv($days % (12 * 31), 31) + 1,
        $days % 31 + 1,
        intdiv($seconds, 3600),
        intdiv($seconds % 3600, 60),
        $seconds % 60,
    );

    expect($decoded)->toBe($moment);
})->with([
    '2026-08-20 07:42:11',
    '2026-01-01 00:00:00',
    '2026-12-31 23:59:59',
    '2030-06-15 12:30:45',
]);

// ------------------------------------------------------------------
// The button
// ------------------------------------------------------------------

it('queues the server clock when a Director presses the button', function () {
    config()->set('app.timezone', 'Africa/Nairobi');
    [$director, $device] = schoolWithDevice();

    Carbon::setTestNow(Carbon::parse('2026-08-20 07:42:11', 'Africa/Nairobi'));

    $this->actingAs($director)
        ->post(route('devices.set-time', $device))
        ->assertRedirect()
        ->assertSessionHas('success');

    $command = ZktecoDeviceCommand::first();

    expect($command)->not->toBeNull()
        ->and($command->command_content)->toBe('SET OPTION DateTime=856078931')
        ->and($command->status->value)->toBe('pending');

    Carbon::setTestNow();
});

it('stamps the device timezone to match the clock it was just sent', function () {
    config()->set('app.timezone', 'Africa/Nairobi');
    [$director, $device] = schoolWithDevice();

    // Registered as UTC, which would misread every punch it sends back.
    expect($device->zktecoDevice->timezone)->toBe('UTC');

    $this->actingAs($director)->post(route('devices.set-time', $device));

    expect($device->zktecoDevice->fresh()->timezone)->toBe('Africa/Nairobi');
});

/*
 * Pressing again replaces the waiting clock rather than refusing or
 * stacking. A queued command carries a fixed moment, not "now" - so one
 * that sat unclaimed while the terminal was off would set it to a stale
 * time on delivery. The newest press is the only one worth keeping.
 */
it('replaces a waiting clock rather than stacking a second one', function () {
    config()->set('app.timezone', 'Africa/Nairobi');
    [$director, $device] = schoolWithDevice();

    Carbon::setTestNow(Carbon::parse('2026-08-20 07:42:11', 'Africa/Nairobi'));
    $this->actingAs($director)->post(route('devices.set-time', $device))
        ->assertSessionHas('success');

    // An hour passes with the terminal switched off.
    Carbon::setTestNow(Carbon::parse('2026-08-20 08:42:11', 'Africa/Nairobi'));
    $this->actingAs($director)->post(route('devices.set-time', $device))
        ->assertSessionHas('success');

    expect(ZktecoDeviceCommand::count())->toBe(1)
        ->and(ZktecoDeviceCommand::first()->command_content)
        ->toBe('SET OPTION DateTime=856082531');   // 08:42, not 07:42

    Carbon::setTestNow();
});

it('says so plainly when the terminal has never connected', function () {
    [$director, $device] = schoolWithDevice(connected: false);

    $this->actingAs($director)
        ->post(route('devices.set-time', $device))
        ->assertSessionHas('error');

    expect(ZktecoDeviceCommand::count())->toBe(0);
});

it('refuses a Director reaching for another school\'s device', function () {
    [, $device] = schoolWithDevice();

    $outsider = User::factory()->create();
    $outsider->assignRole('Director');

    Institution::create([
        'user_id' => $outsider->id,
        'name' => 'Riverside Academy',
        'code' => 'INST-'.uniqid(),
        'type' => 'School',
        'is_active' => true,
        'status' => 'active',
        'is_approved' => true,
    ]);

    $this->actingAs($outsider)
        ->post(route('devices.set-time', $device))
        ->assertForbidden();

    expect(ZktecoDeviceCommand::count())->toBe(0);
});

it('turns up on the wire the next time the device polls', function () {
    config()->set('app.timezone', 'Africa/Nairobi');
    [$director, $device] = schoolWithDevice();

    Carbon::setTestNow(Carbon::parse('2026-08-20 07:42:11', 'Africa/Nairobi'));

    $this->actingAs($director)->post(route('devices.set-time', $device));

    $this->get('/iclock/getrequest?SN=GED7261800014')
        ->assertOk()
        ->assertSee('SET OPTION DateTime=856078931');

    Carbon::setTestNow();
});

it('renders the button on the devices page', function () {
    [$director, $device] = schoolWithDevice();

    $this->actingAs($director)
        ->get(route('devices.index'))
        ->assertOk()
        ->assertSee('Set Time')
        ->assertSee(route('devices.set-time', $device));
});
