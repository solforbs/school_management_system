<?php

namespace Modules\Staff\Actions;

use Illuminate\Support\Carbon;
use Modules\Staff\Models\StaffDetails;
use Modules\Staff\Models\StaffPayment;

/**
 * Recording a payslip, in one place - shared by the Livewire screen and the
 * controller endpoint.
 */
class SaveStaffPayment
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'staff_details_id' => 'required|exists:staff_details,id',
            'period' => ['required', 'date_format:Y-m'],
            'gross_amount' => 'required|numeric|min:0',
            'allowances' => 'nullable|numeric|min:0',
            'deductions' => 'nullable|numeric|min:0',
            'payment_method' => 'required|in:cash,bank_transfer,mobile_money,cheque',
            'reference' => 'nullable|string|max:255',
            'status' => 'required|in:pending,paid,cancelled',
            'notes' => 'nullable|string',
        ];
    }

    /**
     * Whether this staff member already has a payslip for the given month.
     * One per month is enforced here rather than by a unique index -
     * soft-deleted rows would otherwise permanently block re-entering a
     * period.
     */
    public static function periodAlreadyRecorded(StaffDetails $staff, Carbon $period, ?StaffPayment $ignoring = null): bool
    {
        return StaffPayment::where('staff_details_id', $staff->id)
            ->whereDate('period', $period->toDateString())
            ->when($ignoring, fn ($query) => $query->whereKeyNot($ignoring->getKey()))
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function handle(array $data, StaffDetails $staff, ?StaffPayment $payment = null, ?int $recordedBy = null): StaffPayment
    {
        $period = Carbon::parse($data['period'].'-01')->startOfMonth();

        $gross = (float) $data['gross_amount'];
        $allowances = (float) ($data['allowances'] ?? 0);
        $deductions = (float) ($data['deductions'] ?? 0);

        $payload = [
            'staff_details_id' => $staff->id,
            'institution_id' => $staff->institution_id,
            'period' => $period,
            'gross_amount' => $gross,
            'allowances' => $allowances,
            'deductions' => $deductions,
            // Worked out from the parts rather than trusted from the form.
            'net_amount' => StaffPayment::calculateNet($gross, $allowances, $deductions),
            'payment_method' => $data['payment_method'],
            'reference' => $data['reference'] ?? null,
            'status' => $data['status'],
            // Marking it paid stamps the moment it happened; moving it back
            // to pending/cancelled clears that stamp.
            'paid_at' => $data['status'] === 'paid' ? ($payment?->paid_at ?? now()) : null,
            'notes' => $data['notes'] ?? null,
        ];

        if ($payment) {
            $payment->update($payload);

            return $payment;
        }

        return StaffPayment::create($payload + ['recorded_by' => $recordedBy]);
    }
}
