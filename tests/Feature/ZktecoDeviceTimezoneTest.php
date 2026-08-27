<?php

use Athwari\LaravelZktecoAdms\Models\ZktecoAttendanceLog;
use Athwari\LaravelZktecoAdms\Models\ZktecoDevice;
use Athwari\LaravelZktecoAdms\Services\CommandManager;

/*
 * The terminal keeps its own clock. The only point at which the server can
 * tell it which timezone to hold is the ADMS options exchange it makes on
 * boot, so these tests pin what that reply says - and, just as importantly,
 * what it does not say.
 */

const SN = 'GED7261800014';

function boot(): string
{
    return test()->get('/iclock/cdata?SN='.SN.'&options=all&pushver=2.4.1&language=69')
        ->assertOk()
        ->getContent();
}

it('tells the terminal the server timezone', function () {
    config()->set('app.timezone', 'Africa/Nairobi');

    expect(boot())->toStartWith('GET OPTION FROM: '.SN)
        ->and(boot())->toContain('TimeZone=3');
});

it('follows the server timezone rather than a value of its own', function (string $timezone, string $expected) {
    config()->set('app.timezone', $timezone);

    // The ZKTeco-specific timezone settings stay on something else entirely -
    // the device must track the server, not these.
    config()->set('zkteco-adms.default_timezone', 'UTC');
    config()->set('zkteco-adms.storage_timezone', 'UTC');

    expect(boot())->toContain($expected);
})->with([
    'Nairobi' => ['Africa/Nairobi', 'TimeZone=3'],
    'UTC' => ['UTC', 'TimeZone=0'],
    'Dubai' => ['Asia/Dubai', 'TimeZone=4'],
    'New York' => ['America/New_York', 'TimeZone=-'],
]);

it('lets an explicit offset override it for firmware that wants minutes', function () {
    config()->set('app.timezone', 'Africa/Nairobi');
    config()->set('zkteco-adms.response.timezone_offset', 180);

    expect(boot())->toContain('TimeZone=180');
});

/*
 * A stamp tells the device how much of its history the server already has.
 * Send the wrong one and it withholds a backlog, so this reply must not
 * carry one at all.
 */
it('names nothing that would reconfigure a device already uploading correctly', function () {
    $body = boot();

    // Stamps would rewrite how much history the device thinks the server has.
    expect($body)->not->toContain('ATTLOGStamp')
        ->and($body)->not->toContain('OPERLOGStamp')
        ->and($body)->not->toContain('Stamp=')
        // TransFlag decides which record types get sent at all.
        ->and($body)->not->toContain('TransFlag')
        // TransTimes/TransInterval decide when they get sent.
        ->and($body)->not->toContain('TransTimes')
        ->and($body)->not->toContain('TransInterval');
});

it('carries the timezone and the fields that keep live push as it is', function () {
    config()->set('app.timezone', 'Africa/Nairobi');

    expect(boot())->toContain('TimeZone=3')
        ->and(boot())->toContain('Realtime=1')
        ->and(boot())->toContain('Encrypt=0');
});

it('can be switched off, restoring the plain OK reply', function () {
    config()->set('zkteco-adms.response.send_options_handshake', false);

    expect(boot())->toBe('OK');
});

it('still registers the device and accepts punches after the handshake', function () {
    config()->set('app.timezone', 'Africa/Nairobi');
    config()->set('zkteco-adms.default_timezone', 'Africa/Nairobi');
    config()->set('zkteco-adms.storage_timezone', 'Africa/Nairobi');

    boot();

    $device = ZktecoDevice::where('serial_number', SN)->first();
    expect($device)->not->toBeNull()
        ->and($device->isOnline())->toBeTrue();

    $push = $this->call(
        'POST',
        '/iclock/cdata?SN='.SN.'&table=ATTLOG',
        [], [], [],
        ['CONTENT_TYPE' => 'text/plain'],
        "17\t2026-08-20 07:42:11\t0\t1\t0\n"
    );

    $push->assertOk();
    expect($push->getContent())->toBe('OK: 1');

    $log = ZktecoAttendanceLog::where('device_id', $device->id)->first();
    expect($log->pin)->toBe('17')
        ->and($log->occurred_at->format('Y-m-d H:i'))->toBe('2026-08-20 07:42');
});

/*
 * The options reply must never strand a queued command. This URL delivered
 * commands long before the handshake was added, and some firmware polls
 * here rather than /getrequest - a command that never arrives looks exactly
 * like one the device received and ignored, which is a miserable thing to
 * debug. All three poll paths are covered.
 */
it('hands over a waiting command on every path the device might poll', function (string $url) {
    ZktecoDevice::create([
        'serial_number' => SN, 'name' => 'K40 Pro', 'status' => 'online',
        'last_activity_at' => now(), 'timezone' => 'Africa/Nairobi', 'options' => [],
    ]);

    app(CommandManager::class)->queueCommand(SN, 'SET OPTION DateTime=856078931');

    expect($this->get($url)->getContent())->toContain('SET OPTION DateTime=856078931');
})->with([
    'getrequest' => '/iclock/getrequest?SN='.SN,
    'cdata' => '/iclock/cdata?SN='.SN,
    'cdata with options=all' => '/iclock/cdata?SN='.SN.'&options=all',
]);

it('goes back to answering with the timezone once the queue is empty', function () {
    ZktecoDevice::create([
        'serial_number' => SN, 'name' => 'K40 Pro', 'status' => 'online',
        'last_activity_at' => now(), 'timezone' => 'Africa/Nairobi', 'options' => [],
    ]);

    app(CommandManager::class)->queueCommand(SN, 'SET OPTION DateTime=856078931');

    // First ask drains the command...
    $this->get('/iclock/cdata?SN='.SN.'&options=all');

    // ...the next one carries the options again.
    expect(boot())->toContain('TimeZone=');
});

it('is not blocked forever by a command the device never acknowledged', function () {
    ZktecoDevice::create([
        'serial_number' => SN, 'name' => 'K40 Pro', 'status' => 'online',
        'last_activity_at' => now(), 'timezone' => 'Africa/Nairobi', 'options' => [],
    ]);

    app(CommandManager::class)->queueCommand(SN, 'DATA QUERY USERINFO');

    // Delivered, then never confirmed - it stays 'sent' indefinitely.
    $this->get('/iclock/getrequest?SN='.SN);

    expect(boot())->toContain('TimeZone=');
});
