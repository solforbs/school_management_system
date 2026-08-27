<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
// Monday morning, once, and nothing in between.
//
// This cadence *is* the throttle: FeeReminderService keeps no record of who
// it last wrote to, so every run re-notifies every parent who still owes
// anything. Any schedule tighter than the time you'd give someone to act on
// the reminder just sends the same parent the same balance again - and each
// one costs a real SMS. A week matches what the message now asks for.
Schedule::command('feemanagement:send-reminders')->weeklyOn(1, '09:00');

// Not urgent: the command only picks up a report card that has been ready
// for a full day, so running it more often than daily cannot find anything
// a daily run wouldn't.
Schedule::command('reportcards:send-ready')->dailyAt('07:00');

// School day runs 8am-4pm (Timetable module) - give teachers a couple of
// hours' buffer to finish marking attendance before tallying the day.
Schedule::command('lesson:generate-daily-reports')->dailyAt('18:00');
// The timetable only runs Monday-Friday, so Friday evening is "end of week".
Schedule::command('lesson:generate-weekly-reports')->weeklyOn(5, '18:30');

// A terminal stamps every punch with its own clock, so drift files
// attendance at the wrong hour without ever announcing itself. Hourly is
// far more often than any device drifts; it is a tiny queued command, and
// devices that are switched off are caught on reconnect instead.
Schedule::command('zkteco:sync-clocks')->hourly();
