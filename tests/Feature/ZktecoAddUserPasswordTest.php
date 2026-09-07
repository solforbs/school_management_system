<?php

use App\Models\Devices;
use App\Models\User;
use Athwari\LaravelZktecoAdms\Facades\ZktecoAdms;
use Athwari\LaravelZktecoAdms\Models\ZktecoDevice;
use Athwari\LaravelZktecoAdms\Models\ZktecoDeviceCommand;
use Athwari\LaravelZktecoAdms\Services\CommandManager;
use Database\Seeders\PermissionSeeder;
use Livewire\Livewire;
use Modules\Institution\Models\Institution;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    ZktecoDevice::create([
        'serial_number' => 'GED7261800014',
        'name' => 'K40 Pro',
        'status' => 'online',
        'last_activity_at' => now(),
        'timezone' => 'UTC',
        'options' => [],
    ]);
});

function lastCommand(): string
{
    return ZktecoDeviceCommand::latest('id')->firstOrFail()->command_content;
}

test('adding a device user with no password sends Passwd=1234', function () {
    ZktecoAdms::sendUserAddCommand('GED7261800014', '7644', 'Julian Isaac', 0, '');

    expect(lastCommand())
        ->toContain('PIN=7644')
        ->toContain('Name=Julian Isaac')
        ->toContain('Passwd='.CommandManager::DEFAULT_USER_PASSWORD)
        ->toContain('Passwd=1234');
});

test('a supplied numeric password is used, digits only and capped at six', function () {
    ZktecoAdms::sendUserAddCommand('GED7261800014', '10', 'Jane', 0, '', 'PW-98765-43');

    // digits of "PW-98765-43" -> "9876543" -> first 6 -> "987654"
    expect(lastCommand())->toContain('Passwd=987654')
        ->and(lastCommand())->not->toContain('Passwd=1234');
});

test('a password with no digits falls back to the default', function () {
    ZktecoAdms::sendUserAddCommand('GED7261800014', '11', 'Bob', 0, '', 'abcdef');

    expect(lastCommand())->toContain('Passwd=1234');
});

test('the device manage screen queues an add-user command with the default password', function () {
    // Full stack: the Livewire screen -> facade -> command queue.
    $command = null;

    Livewire::test('pages::devices.manage', ['deviceId' => makeManagedDevice()])
        ->set('newPin', '500')
        ->set('newName', 'Screen User')
        ->set('newPrivilege', '0')
        ->call('addUser')
        ->assertHasNoErrors();

    expect(lastCommand())->toContain('PIN=500')->toContain('Passwd=1234');
});

/** A Devices row linked to the seeded ZktecoDevice, owned by a Director. */
function makeManagedDevice(): int
{
    $director = User::factory()->create();
    $director->assignRole('Director');

    $institution = Institution::create([
        'user_id' => $director->id, 'name' => 'Dev School', 'code' => 'DS-'.uniqid(),
        'type' => 'School', 'is_active' => true, 'status' => 'active', 'is_approved' => true,
    ]);
    $director->update(['active_institution_id' => $institution->id]);
    test()->actingAs($director);

    $zkteco = ZktecoDevice::where('serial_number', 'GED7261800014')->firstOrFail();
    $device = Devices::create([
        'serial_number' => 'GED7261800014',
        'institution_id' => $institution->id,
        'is_active' => true,
        'zkteco_device_id' => $zkteco->id,
    ]);

    return $device->id;
}
