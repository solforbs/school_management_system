<?php

namespace Modules\ReportCard\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Modules\ReportCard\Models\ReportCard;
use Modules\ReportCard\Services\ReportCardCompletionService;
use Modules\ReportCard\Services\ReportCardSender;

/**
 * One report card's delivery, queued rather than sent inline so a school
 * with hundreds of report cards ready at once doesn't hit the mail server
 * and SMS gateway all in the same second - see the 'notifications' rate
 * limiter in AppServiceProvider.
 */
class SendReportCardJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(private readonly int $reportCardId) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RateLimited('notifications')];
    }

    public function handle(ReportCardCompletionService $completionService, ReportCardSender $sender): void
    {
        $reportCard = ReportCard::where('status', 'ready')
            ->with(['student', 'institution'])
            ->find($this->reportCardId);

        if (! $reportCard) {
            // Already sent, deleted, or no longer ready by the time its
            // turn came up behind the rate limiter.
            return;
        }

        // Re-checked here rather than trusted from dispatch time: a job can
        // sit behind the rate limiter for a while, and results that changed
        // in the meantime shouldn't still go out unattended.
        if (! $reportCard->student || ! $reportCard->academic_year || ! $completionService->isStillComplete($reportCard->student, $reportCard->term, $reportCard->academic_year)) {
            $reportCard->delete();

            return;
        }

        $sender->send($reportCard);
    }
}
