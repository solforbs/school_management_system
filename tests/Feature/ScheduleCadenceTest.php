<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

/**
 * The cron expression a scheduled command is registered with.
 */
function cadenceFor(string $command): ?string
{
    return collect(app(Schedule::class)->events())
        ->first(fn (Event $event) => str_contains($event->command ?? '', $command))
        ?->expression;
}

test('parent-facing reminders go out weekly, never more often', function () {
    // Guarding a real incident rather than a style preference: this was
    // registered as everyMinute(), and FeeReminderService has no per-parent
    // throttle of its own, so every defaulting parent was getting an email
    // and a paid-for SMS every sixty seconds.
    expect(cadenceFor('feemanagement:send-reminders'))->toBe('0 9 * * 1');
});

test('report card delivery runs daily, since nothing becomes sendable sooner', function () {
    // The command ignores anything ready for less than a day, so a tighter
    // schedule can only re-scan the same rows.
    expect(cadenceFor('reportcards:send-ready'))->toBe('0 7 * * *');
});

test('nothing outbound to parents is scheduled every minute', function () {
    $tooOften = collect(app(Schedule::class)->events())
        ->filter(fn (Event $event) => $event->expression === '* * * * *')
        ->map(fn (Event $event) => $event->command)
        ->values()
        ->all();

    expect($tooOften)->toBe([]);
});
