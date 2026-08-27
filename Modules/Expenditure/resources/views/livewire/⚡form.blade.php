<?php

use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Expenditure\Actions\SaveExpenditure;
use Modules\Expenditure\Models\Expenditure;
use Modules\Expenditure\Models\ExpenditureCategory;

new #[Title('Record Expenditure')] class extends Component
{
    public ?Expenditure $expenditure = null;

    public string $expenditure_category_id = '';

    public string $title = '';

    public string $payee = '';

    public string $amount = '';

    public string $spent_on = '';

    public string $payment_method = 'cash';

    public string $reference = '';

    public string $status = 'pending';

    public string $notes = '';

    /**
     * One component behind both /create and /edit - the only difference is
     * whether a spend was handed in to start from.
     */
    public function mount(?int $expenditureId = null): void
    {
        if ($expenditureId === null) {
            abort_unless(auth()->user()->can('create expenditure'), 403);

            $this->spent_on = now()->format('Y-m-d');

            return;
        }

        abort_unless(auth()->user()->can('edit expenditure'), 403);

        $this->expenditure = $this->scoped()->findOrFail($expenditureId);

        $this->fill([
            'expenditure_category_id' => (string) ($this->expenditure->expenditure_category_id ?? ''),
            'title' => (string) $this->expenditure->title,
            'payee' => (string) $this->expenditure->payee,
            'amount' => (string) $this->expenditure->amount,
            'spent_on' => $this->expenditure->spent_on?->format('Y-m-d') ?? now()->format('Y-m-d'),
            'payment_method' => $this->expenditure->payment_method,
            'reference' => (string) $this->expenditure->reference,
            'status' => $this->expenditure->status,
            'notes' => (string) $this->expenditure->notes,
        ]);
    }

    public function save(): void
    {
        abort_unless(
            auth()->user()->can($this->expenditure ? 'edit expenditure' : 'create expenditure'),
            403
        );

        $validated = $this->validate(SaveExpenditure::rules());

        $saved = SaveExpenditure::handle(
            $validated,
            $this->institutionId(),
            $this->expenditure,
            recordedBy: auth()->id(),
        );

        session()->flash('success', $this->expenditure ? 'Expenditure updated!' : 'Expenditure recorded successfully!');

        $this->redirectRoute('expenditure.show', $saved->id, navigate: true);
    }

    #[Computed]
    public function categories(): Collection
    {
        $query = ExpenditureCategory::query()->where('is_active', true);

        if (! isAdmin()) {
            $query->where('institution_id', currentInstitution()?->id ?? 0);
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Blank means "uncategorised", but an empty string isn't a null
     * foreign key - the rules would reject it.
     */
    protected function prepareForValidation($attributes)
    {
        $attributes['expenditure_category_id'] = $attributes['expenditure_category_id'] === ''
            ? null
            : $attributes['expenditure_category_id'];

        return $attributes;
    }

    private function institutionId(): int
    {
        $institutionId = isAdmin()
            ? ($this->expenditure?->institution_id ?? currentInstitution()?->id)
            : currentInstitution()?->id;

        abort_unless($institutionId, 422, 'No institution selected.');

        return $institutionId;
    }

    private function scoped()
    {
        $query = Expenditure::query();

        if (! isAdmin()) {
            $query->where('institution_id', currentInstitution()?->id ?? 0);
        }

        return $query;
    }
}; ?>

<div class="p-4">
    <div class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div
            class="rounded-t-lg border-b border-gray-200 bg-gray-50 px-6 py-4 dark:border-zinc-700 dark:bg-zinc-800">
            <h4 class="mb-0 text-lg font-semibold text-gray-900 dark:text-white">
                {{ $expenditure ? 'Edit Expenditure' : 'Record Expenditure' }}
            </h4>
        </div>

        <form wire:submit="save">
            <div class="p-6">
                <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
                    <flux:input label="Item" wire:model="title" placeholder="e.g. Term 2 electricity bill" />

                    <flux:select label="Category" wire:model="expenditure_category_id">
                        <flux:select.option value="">Uncategorised</flux:select.option>
                        @foreach ($this->categories as $category)
                            <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:input label="Paid To" wire:model="payee" placeholder="e.g. Kenya Power" />

                    <flux:input type="number" step="0.01" min="0" label="Amount" wire:model="amount" />

                    <flux:input type="date" label="Date" wire:model="spent_on" />

                    <flux:select label="Payment Method" wire:model="payment_method">
                        @foreach (\Modules\Expenditure\Models\Expenditure::PAYMENT_METHODS as $method)
                            <flux:select.option value="{{ $method }}">
                                {{ ucfirst(str_replace('_', ' ', $method)) }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:input label="Reference" wire:model="reference"
                        description="Receipt, cheque or transaction number." />

                    <flux:select label="Status" wire:model="status">
                        @foreach (\Modules\Expenditure\Models\Expenditure::STATUSES as $option)
                            <flux:select.option value="{{ $option }}">{{ ucfirst($option) }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <div class="mb-2 grid grid-cols-1 gap-4">
                    <flux:textarea label="Notes" rows="2" wire:model="notes" />
                </div>

                <p class="text-sm text-gray-500">
                    Marking a spend as paid stamps the date the money left the school.
                </p>
            </div>

            <div
                class="flex justify-end gap-3 rounded-b-lg border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-zinc-700 dark:bg-zinc-800">
                <flux:button href="{{ route('expenditure.index') }}" variant="ghost" wire:navigate>Cancel</flux:button>
                <flux:button variant="primary" type="submit">
                    <span wire:loading.remove wire:target="save">
                        {{ $expenditure ? 'Update Expenditure' : 'Save Expenditure' }}
                    </span>
                    <span wire:loading wire:target="save">Saving…</span>
                </flux:button>
            </div>
        </form>
    </div>
</div>
