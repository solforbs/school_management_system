<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Modules\FeeManagement\Models\Fee;
use Modules\FeeManagement\Models\FeePayment;

/**
 * The single place a payment gets applied to a Fee - keeps the
 * FeePayment audit row and Fee.amount_paid (which everything else in the
 * app already reads - dashboards, reminders, the balance/status
 * attributes) in sync, whether the payment was entered manually or via
 * the AI receipt-scanning flow.
 */

/**
 * @property int $student_id
 * @property int $institution_id
 * @property int $fee_id
 * @property float $amount
 * @property string|null $reference
 * @property string|null $payment_method
 * @property string|null $receipt_path
 * @property string|null $source
 * @property string|null $extraction_raw_response
 * @property string|null $recorded_by
 * @property \Illuminate\Support\Carbon|null $paid_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property array $data
 */
class FeePaymentService
{
    public function record(Fee $fee, array $data): FeePayment
    {
        return DB::transaction(function () use ($fee, $data) {
            $payment = FeePayment::create([
                'fee_id' => $fee->id,
                'institution_id' => $fee->institution_id,
                'student_id' => $fee->student_id,
                'amount' => $data['amount'],
                'reference' => $data['reference'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'paid_at' => $data['paid_at'] ?? now(),
                'receipt_path' => $data['receipt_path'] ?? null,
                'source' => $data['source'] ?? 'manual',
                'extraction_raw_response' => $data['extraction_raw_response'] ?? null,
                'recorded_by' => $data['recorded_by'] ?? null,
            ]);

            $fee->increment('amount_paid', $data['amount']);

            return $payment;
        });
    }
}
