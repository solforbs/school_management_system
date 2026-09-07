<?php

use App\Services\ZKTecoUserSyncService;
use Athwari\LaravelZktecoAdms\Models\ZktecoDevice;
use Athwari\LaravelZktecoAdms\Services\CommandManager;
use Athwari\LaravelZktecoAdms\Services\DeviceManager;

/**
 * Every device user gets a keypad password: whatever the caller supplied
 * (digits only, <= 6), or the default when they gave nothing usable.
 */
function syncServiceCapturing(?string &$captured): ZKTecoUserSyncService
{
    $deviceManager = Mockery::mock(DeviceManager::class);
    $deviceManager->shouldReceive('getDevice')->andReturn(new ZktecoDevice(['serial_number' => 'SN1']));

    $commandManager = Mockery::mock(CommandManager::class);
    $commandManager->shouldReceive('queueCommand')
        ->andReturnUsing(function ($serial, $command) use (&$captured) {
            $captured = $command;

            return true;
        });

    return new ZKTecoUserSyncService($deviceManager, $commandManager);
}

test('a user synced with no password gets Passwd=1234', function () {
    $service = syncServiceCapturing($command);

    $service->addUserToDevice('SN1', [
        'pin' => '7644',
        'name' => 'Julian Isaac',
        'privilege' => 0,
        'card' => '',
        'password' => '',
    ]);

    expect($command)->toContain('PIN=7644')
        ->and($command)->toContain('Passwd='.ZKTecoUserSyncService::DEFAULT_DEVICE_PASSWORD)
        ->and($command)->toContain('Passwd=1234');
});

test('the password key being absent entirely still yields the default', function () {
    $service = syncServiceCapturing($command);

    $service->addUserToDevice('SN1', [
        'pin' => '10',
        'name' => 'No Password Key',
    ]);

    expect($command)->toContain('Passwd=1234');
});

test('a caller-supplied numeric password is kept, trimmed to six digits', function () {
    $service = syncServiceCapturing($command);

    $service->addUserToDevice('SN1', [
        'pin' => '11',
        'name' => 'Has Admission No',
        'password' => 'ADM-2026-000123',
    ]);

    // Digits of "ADM-2026-000123" -> "2026000123" -> first 6 -> "202600".
    expect($command)->toContain('Passwd=202600')
        ->and($command)->not->toContain('Passwd=1234');
});

test('a caller-supplied password with no digits falls back to the default', function () {
    $service = syncServiceCapturing($command);

    $service->addUserToDevice('SN1', [
        'pin' => '12',
        'name' => 'Letters Only',
        'password' => 'abc-def',
    ]);

    expect($command)->toContain('Passwd=1234');
});
