<?php

namespace Modules\ReportCard\Console\Commands;

use Illuminate\Console\Command;
use Modules\ReportCard\Models\ReportCard;
use Modules\ReportCard\Services\ReportCardQueuer;

class SendReadyReportCards extends Command
{
    protected $signature = 'reportcards:send-ready';

    protected $description = 'Queue a one-time download link (email and SMS) for report cards that have been ready for at least a day, giving directors time to correct results before parents see them';

    public function handle(ReportCardQueuer $queuer): int
    {
        $count = $queuer->queueReady(ReportCard::query());

        $this->info("Queued {$count} report card(s) for sending.");

        return self::SUCCESS;
    }
}
