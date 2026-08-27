<?php

namespace Modules\ReportCard\Console\Commands;

use Illuminate\Console\Command;
use Modules\ReportCard\Models\ReportCard;
use Modules\ReportCard\Services\ReportCardCompletionService;
use Modules\ReportCard\Services\ReportCardSender;

class SendReadyReportCards extends Command
{
    protected $signature = 'reportcards:send-ready';

    protected $description = 'Send parents a one-time download link (email and SMS) for report cards that have been ready for at least a day, giving directors time to correct results before parents see them';

    public function handle(
        ReportCardCompletionService $completionService,
        ReportCardSender $sender,
    ): int {
        $reportCards = ReportCard::where('status', 'ready')
            ->where('completed_at', '<=', now()->subDay())
            ->with(['student', 'institution'])
            ->get();

        $sent = 0;
        $skipped = 0;

        foreach ($reportCards as $reportCard) {
            // An unattended send holds back a report whose marks no longer
            // add up - unlike the button on the report cards screen, where
            // someone has looked at it and decided.
            if (! $reportCard->student || ! $reportCard->academic_year || ! $completionService->isStillComplete($reportCard->student, $reportCard->term, $reportCard->academic_year)) {
                $reportCard->delete();
                $skipped++;

                continue;
            }

            if (! $sender->send($reportCard)['sent']) {
                $skipped++;

                continue;
            }

            $sent++;
        }

        $this->info("Sent {$sent} report card(s), skipped {$skipped}.");

        return self::SUCCESS;
    }
}
