<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\FeePaymentReminder;
use App\Services\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\FeeManagement\Models\Fee;

/**
 * One parent's consolidated fee reminder, queued rather than sent inline so
 * FeeReminderService can hand off a whole institution's defaulters at once
 * without hitting the mail server and SMS gateway all in the same second -
 * see the 'notifications' rate limiter in AppServiceProvider.
 */
class SendFeeReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @param  array<int>  $feeIds
     */
    public function __construct(
        private readonly int $parentId,
        private readonly array $feeIds,
    ) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RateLimited('notifications')];
    }

    public function handle(SmsService $smsService): void
    {
        $parent = User::find($this->parentId);

        if (! $parent) {
            return;
        }

        // Re-checked here rather than trusted from dispatch time: a job can
        // sit behind the rate limiter for a while, and a parent who pays in
        // the meantime shouldn't still get nagged once their turn comes up.
        $fees = Fee::whereIn('id', $this->feeIds)
            ->whereColumn('amount_paid', '<', 'amount')
            ->with('student')
            ->get();

        if ($fees->isEmpty()) {
            return;
        }

        $notification = new FeePaymentReminder($fees);

        if ($parent->email && featureEnabled('email_notifications')) {
            $parent->notify($notification);
        }

        $phone = $parent->parent?->parent_phone;
        if ($phone && featureEnabled('sms')) {
            $sms = $smsService->send((int) preg_replace('/\D/', '', $phone), $notification->toSms());

            if (! ($sms['success'] ?? false)) {
                Log::warning('Fee reminder SMS failed', [
                    'parent_id' => $parent->id,
                    'error' => $sms['error'] ?? $sms['reason'] ?? null,
                ]);
            }
        }
    }
}
