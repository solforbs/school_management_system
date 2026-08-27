<?php

namespace Modules\FeeManagement\Actions;

use Modules\FeeManagement\Models\Fee;
use Modules\Student\Models\StudentDetails;

/**
 * Raising and amending a fee, in one place - shared by the Livewire screen
 * and the controller endpoint.
 */
class SaveFee
{
    /**
     * The student is only chosen when the fee is first raised; moving a fee
     * between students isn't a thing, so the edit form doesn't offer it.
     *
     * @return array<string, string>
     */
    public static function rules(bool $withStudent = true): array
    {
        return array_merge($withStudent ? [
            'student_id' => 'required|exists:student_details,student_id',
        ] : [], [
            'title' => 'required|string|max:255',
            'fee_type' => 'required|string|max:100',
            'amount' => 'required|numeric|min:0',
            'amount_paid' => 'nullable|numeric|min:0',
            'due_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function handle(array $data, ?Fee $fee = null): Fee
    {
        $payload = [
            'title' => $data['title'],
            'fee_type' => $data['fee_type'],
            'amount' => $data['amount'],
            'amount_paid' => $data['amount_paid'] ?? 0,
            'due_date' => $data['due_date'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];

        if ($fee) {
            $fee->update($payload);

            return $fee;
        }

        // Institution and parent are derived from the student so the fee
        // record stays consistent with StudentDetails and can't be
        // mismatched via the form.
        $studentDetails = StudentDetails::where('student_id', $data['student_id'])->firstOrFail();

        return Fee::create($payload + [
            'institution_id' => $studentDetails->institution_id,
            'student_id' => $studentDetails->student_id,
            'parent_id' => $studentDetails->parent_id,
        ]);
    }
}
