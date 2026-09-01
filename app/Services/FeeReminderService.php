<?php

namespace App\Services;

use App\Notifications\FeePaymentReminder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Modules\FeeManagement\Models\Fee;

/**
 * Sends a consolidated fee-balance reminder (email + SMS where a contact is
 * on file) to every parent with at least one outstanding fee. Grouped by
 * parent so someone with several unpaid fees - possibly across more than
 * one child - gets a single reminder, not one per fee.
 */
class FeeReminderService
{
    public function __construct(private readonly SmsService $smsService) {}

    /**
     * @param  Builder<Fee>  $query  A Fee query already scoped to whatever the
     *                               caller is allowed to send reminders for.
     * @return array{
     *     parents_notified: int,
     *     emails_sent: int,
     *     sms_sent: int,
     *     skipped_no_contact: int
     * }
     */
    public function sendForDefaulters(Builder $query): array
    {
        @set_time_limit(180);

        $fees = (clone $query)
            ->whereColumn('amount_paid', '<', 'amount')
            ->whereNotNull('parent_id')
            ->with(['student', 'parent.parent'])
            ->get();

        $result = ['parents_notified' => 0, 'emails_sent' => 0, 'sms_sent' => 0, 'skipped_no_contact' => 0];

        foreach ($fees->groupBy('parent_id') as $parentFees) {
            $parent = $parentFees->first()->parent;

            if (! $parent) {
                continue;
            }

            $notification = new FeePaymentReminder($parentFees);
            $notifiedAny = false;

            if ($parent->email && featureEnabled('email_notifications')) {
                $parent->notify($notification);
                $result['emails_sent']++;
                $notifiedAny = true;
            }

            $phone = $parent->parent?->parent_phone;
            if ($phone && featureEnabled('sms')) {
                $sms = $this->smsService->send((int) preg_replace('/\D/', '', $phone), $notification->toSms());

                // Only count what the gateway actually accepted, so the
                // figure reported back to the user isn't wishful.
                if ($sms['success'] ?? false) {
                    $result['sms_sent']++;
                    $notifiedAny = true;
                } else {
                    Log::warning('Fee reminder SMS failed', [
                        'parent_id' => $parent->id,
                        'error' => $sms['error'] ?? $sms['reason'] ?? null,
                    ]);
                }
            }

            $notifiedAny ? $result['parents_notified']++ : $result['skipped_no_contact']++;
        }

        return $result;
    }
}
