<?php

namespace App\Console\Commands;

use App\Jobs\SendParentNotificationJob;
use App\Models\ParentNotification;
use Illuminate\Console\Command;

/**
 * Picks up parent notices whose scheduled time has arrived and queues each
 * one for delivery. A "send now" notice is dispatched straight from the
 * screen and never waits for this.
 */
class SendDueParentNotifications extends Command
{
    protected $signature = 'notifications:send-due';

    protected $description = 'Queue any scheduled parent notifications whose time has come';

    public function handle(): int
    {
        $due = ParentNotification::query()
            ->where('status', 'pending')
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '<=', now())
            ->get();

        foreach ($due as $notification) {
            SendParentNotificationJob::dispatch($notification->id);
            $this->info("Queued parent notification #{$notification->id} ({$notification->name}).");
        }

        $this->info($due->isEmpty() ? 'Nothing due.' : $due->count().' notification(s) queued.');

        return self::SUCCESS;
    }
}
