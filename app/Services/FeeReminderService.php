<?php

namespace App\Services;

use App\Jobs\SendFeeReminderJob;
use Illuminate\Database\Eloquent\Builder;
use Modules\FeeManagement\Models\Fee;

/**
 * Queues a consolidated fee-balance reminder (email + SMS where a contact is
 * on file) for every parent with at least one outstanding fee. Grouped by
 * parent so someone with several unpaid fees - possibly across more than
 * one child - gets a single reminder, not one per fee.
 *
 * The actual sending happens in SendFeeReminderJob, one per parent, rate
 * limited on the queue - a school with hundreds of defaulters would otherwise
 * hit the mail server and SMS gateway all in the same second.
 */
class FeeReminderService
{
    /**
     * @param  Builder<Fee>  $query  A Fee query already scoped to whatever the
     *                               caller is allowed to send reminders for.
     * @return array{
     *     parents_queued: int,
     *     emails_queued: int,
     *     sms_queued: int,
     *     skipped_no_contact: int
     * }
     */
    public function sendForDefaulters(Builder $query): array
    {
        $fees = (clone $query)
            ->whereColumn('amount_paid', '<', 'amount')
            ->whereNotNull('parent_id')
            ->with('parent.parent')
            ->get();

        $result = ['parents_queued' => 0, 'emails_queued' => 0, 'sms_queued' => 0, 'skipped_no_contact' => 0];

        foreach ($fees->groupBy('parent_id') as $parentId => $parentFees) {
            $parent = $parentFees->first()->parent;

            if (! $parent) {
                continue;
            }

            $canEmail = (bool) ($parent->email && featureEnabled('email_notifications'));
            $canSms = (bool) ($parent->parent?->parent_phone && featureEnabled('sms'));

            if (! $canEmail && ! $canSms) {
                $result['skipped_no_contact']++;

                continue;
            }

            SendFeeReminderJob::dispatch((int) $parentId, $parentFees->pluck('id')->all());

            $result['parents_queued']++;
            $canEmail && $result['emails_queued']++;
            $canSms && $result['sms_queued']++;
        }

        return $result;
    }
}
