<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
// Monday morning, once, and nothing in between.
//
// This cadence *is* the throttle on how often a parent gets nagged:
// FeeReminderService keeps no record of who it last wrote to, so every run
// re-notifies every parent who still owes anything. Any schedule tighter
// than the time you'd give someone to act on the reminder just sends the
// same parent the same balance again - and each one costs a real SMS. A
// week matches what the message now asks for. (Separately, how fast the
// notifications actually go out once queued is capped by the 'notifications'
// rate limiter in AppServiceProvider, not by this cadence.)
Schedule::command('feemanagement:send-reminders')->weeklyOn(1, '09:00');

// Not urgent: the command only picks up a report card that has been ready
// for a full day, so running it more often than daily cannot find anything
// a daily run wouldn't.
Schedule::command('reportcards:send-ready')->dailyAt('07:00');

// A safety net for environments with no separate always-on queue worker
// process: without this, jobs queued above (and by the manual bulk-send
// buttons) would just sit in the jobs table forever. --stop-when-empty lets
// it exit as soon as there's nothing left to do instead of idling for the
// full five minutes; the 'notifications' rate limiter is what actually
// paces deliveries to ~100/minute, not this cadence.
Schedule::command('queue:work --queue=default --stop-when-empty --max-time=240 --tries=3')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->runInBackground();

// School day runs 8am-4pm (Timetable module) - give teachers a couple of
// hours' buffer to finish marking attendance before tallying the day.
Schedule::command('lesson:generate-daily-reports')->dailyAt('18:00');
// The timetable only runs Monday-Friday, so Friday evening is "end of week".
Schedule::command('lesson:generate-weekly-reports')->weeklyOn(5, '18:30');

// Scheduled parent notices: a "send now" goes straight to the queue from
// the notifications screen, so this only sweeps up the ones set for a
// future date. Every five minutes matches the queue worker's own cadence
// above - a term notice is not to-the-minute business.
Schedule::command('notifications:send-due')->everyFiveMinutes()->withoutOverlapping(10);

// Keeps public/sitemap.xml in sync with the public pages for Google
// Search Console and other crawlers. Hourly is far more often than the
// marketing pages themselves change - the plans page is the only one with
// data behind it, and a stale sitemap for an hour costs nothing since
// crawlers re-fetch it on their own schedule anyway.
Schedule::command('sitemap:generate')->hourly();

// A terminal stamps every punch with its own clock, so drift files
// attendance at the wrong hour without ever announcing itself. Hourly is
// far more often than any device drifts; it is a tiny queued command, and
// devices that are switched off are caught on reconnect instead.
Schedule::command('zkteco:sync-clocks')->hourly();

// The database already has every punch the moment a device pushes it - this
// only protects a terminal's own limited flash storage from filling up.
// Daily is plenty since nothing is actually cleared until 7 days have
// passed since the last clear (DeviceAttendanceLogRetention enforces that).
Schedule::command('zkteco:clear-old-attendance-logs')->dailyAt('02:00');
