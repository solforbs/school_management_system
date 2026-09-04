<?php

namespace Modules\FeeManagement\Console\Commands;

use App\Services\FeeReminderService;
use Illuminate\Console\Command;
use Modules\FeeManagement\Models\Fee;

class SendFeeReminders extends Command
{
    protected $signature = 'feemanagement:send-reminders';

    protected $description = 'Remind parents by email and SMS of any outstanding fee balances, platform-wide';

    public function handle(FeeReminderService $reminderService): int
    {
        $result = $reminderService->sendForDefaulters(Fee::query());

        $this->info("Queued reminders for {$result['parents_queued']} parent(s) - {$result['emails_queued']} email(s), {$result['sms_queued']} SMS.");

        return self::SUCCESS;
    }
}
