<?php

namespace Modules\Expenditure\Actions;

use Modules\Expenditure\Models\Expenditure;
use Modules\Expenditure\Models\ExpenditureCategory;

/**
 * Recording a spend, in one place.
 *
 * The Livewire form and the controller both go through here, so the rules a
 * spend is validated against and the way it's stored can't drift apart
 * depending on which door it came in by.
 */
class SaveExpenditure
{
    /**
     * @return array<string, string>
     */
    public static function rules(): array
    {
        return [
            'expenditure_category_id' => 'nullable|exists:expenditure_categories,id',
            'title' => 'required|string|max:255',
            'payee' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0',
            'spent_on' => 'required|date',
            'payment_method' => 'required|in:'.implode(',', Expenditure::PAYMENT_METHODS),
            'reference' => 'nullable|string|max:255',
            'status' => 'required|in:'.implode(',', Expenditure::STATUSES),
            'notes' => 'nullable|string',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function handle(
        array $data,
        int $institutionId,
        ?Expenditure $expenditure = null,
        ?int $recordedBy = null,
    ): Expenditure {
        // A nullable field that wasn't submitted at all is simply absent
        // from the validated data, so it has to be normalised before it's
        // read or written.
        $categoryId = $data['expenditure_category_id'] ?? null;

        // 'exists' alone would let a crafted request file a spend under
        // another school's category.
        if ($categoryId) {
            $category = ExpenditureCategory::findOrFail($categoryId);
            abort_unless($category->institution_id === $institutionId, 403);
        }

        $payload = [
            'institution_id' => $institutionId,
            'expenditure_category_id' => $categoryId ?: null,
            'title' => $data['title'],
            'payee' => $data['payee'] ?? null,
            'amount' => $data['amount'],
            'spent_on' => $data['spent_on'],
            'payment_method' => $data['payment_method'],
            'reference' => $data['reference'] ?? null,
            'status' => $data['status'],
            'notes' => $data['notes'] ?? null,
            // Marking it paid stamps the moment the money left; moving it
            // back to pending/approved/cancelled clears that stamp.
            'paid_at' => $data['status'] === 'paid' ? ($expenditure?->paid_at ?? now()) : null,
        ];

        if ($expenditure) {
            $expenditure->update($payload);

            return $expenditure;
        }

        return Expenditure::create($payload + ['recorded_by' => $recordedBy]);
    }
}
