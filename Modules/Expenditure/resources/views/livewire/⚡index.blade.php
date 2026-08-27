<?php

use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Expenditure\Models\Expenditure;
use Modules\Expenditure\Models\ExpenditureCategory;

new #[Title('Expenditure')] class extends Component
{
    use WithPagination;

    /**
     * Columns a viewer is allowed to sort by - anything else falls back to
     * the default rather than reaching an arbitrary column.
     *
     * @var string[]
     */
    public const SORTABLE = ['spent_on', 'title', 'amount', 'status'];

    #[Url]
    public string $search = '';

    #[Url(as: 'category_id')]
    public string $categoryId = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public string $sort = 'spent_on';

    #[Url]
    public string $direction = 'desc';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('view expenditure'), 403);
    }

    /**
     * Any change to a filter puts the viewer back on page one - page 4 of
     * the old result set is meaningless against the new one.
     */
    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'categoryId', 'status', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function sortBy(string $column): void
    {
        if (! in_array($column, self::SORTABLE, true)) {
            return;
        }

        if ($this->sort === $column) {
            $this->direction = $this->direction === 'asc' ? 'desc' : 'asc';

            return;
        }

        $this->sort = $column;
        $this->direction = 'asc';
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'categoryId', 'status', 'from', 'to']);
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        abort_unless(auth()->user()->can('delete expenditure'), 403);

        // Resolved through the scoped query, so an id from another school
        // is a 404 rather than a deletion.
        $this->scoped()->findOrFail($id)->delete();

        Flux::toast(text: 'Expenditure removed.', variant: 'success');
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

    #[Computed]
    public function expenditures()
    {
        $sort = in_array($this->sort, self::SORTABLE, true) ? $this->sort : 'spent_on';
        $direction = $this->direction === 'asc' ? 'asc' : 'desc';

        return $this->filtered()
            ->with(['category', 'recordedBy'])
            ->orderBy($sort, $direction)
            ->paginate(15);
    }

    /**
     * Totals describe the filtered set, not the page - an Accountant
     * filtering to "Utilities, this term" wants that subtotal, not the
     * fifteen rows that happen to fit on screen.
     *
     * @return array{total: float, settled: float}
     */
    #[Computed]
    public function totals(): array
    {
        $totals = $this->filtered()
            ->selectRaw('SUM(amount) as total, SUM(CASE WHEN status = ? THEN amount ELSE 0 END) as settled', ['paid'])
            ->first();

        return [
            'total' => (float) ($totals->total ?? 0),
            'settled' => (float) ($totals->settled ?? 0),
        ];
    }

    /**
     * Spend per category over the filtered period: "where is the money
     * going?", asked in the one place an Accountant is already looking.
     */
    #[Computed]
    public function byCategory(): Collection
    {
        return $this->scoped()
            ->when($this->from !== '', fn ($query) => $query->whereDate('spent_on', '>=', $this->from))
            ->when($this->to !== '', fn ($query) => $query->whereDate('spent_on', '<=', $this->to))
            ->where('status', '!=', 'cancelled')
            ->selectRaw('expenditure_category_id, SUM(amount) as total')
            ->groupBy('expenditure_category_id')
            ->orderByDesc('total')
            ->with('category')
            ->get();
    }

    #[Computed]
    public function hasFilters(): bool
    {
        return filled($this->search) || filled($this->categoryId) || filled($this->status)
            || filled($this->from) || filled($this->to);
    }

    /**
     * Every spend this viewer may see, before any filtering.
     */
    private function scoped()
    {
        $query = Expenditure::query();

        if (! isAdmin()) {
            $query->where('institution_id', currentInstitution()?->id ?? 0);
        }

        return $query;
    }

    private function filtered()
    {
        return $this->scoped()
            ->when($this->search !== '', function ($query) {
                $term = '%'.$this->search.'%';

                $query->where(fn ($q) => $q->where('title', 'like', $term)
                    ->orWhere('payee', 'like', $term)
                    ->orWhere('reference', 'like', $term));
            })
            ->when($this->categoryId !== '', fn ($query) => $query->where('expenditure_category_id', $this->categoryId))
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->from !== '', fn ($query) => $query->whereDate('spent_on', '>=', $this->from))
            ->when($this->to !== '', fn ($query) => $query->whereDate('spent_on', '<=', $this->to));
    }
}; ?>

<div class="p-4">
    <div class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <flux:card class="space-y-1">
            <flux:text class="text-zinc-500">Total Recorded</flux:text>
            <flux:heading size="xl">{{ number_format($this->totals['total'], 2) }}</flux:heading>
        </flux:card>
        <flux:card class="space-y-1">
            <flux:text class="text-zinc-500">Paid Out</flux:text>
            <flux:heading size="xl">{{ number_format($this->totals['settled'], 2) }}</flux:heading>
        </flux:card>
        <flux:card class="space-y-1">
            <flux:text class="text-zinc-500">Not Yet Paid</flux:text>
            <flux:heading size="xl">
                {{ number_format($this->totals['total'] - $this->totals['settled'], 2) }}
            </flux:heading>
        </flux:card>
    </div>

    @if ($this->byCategory->isNotEmpty())
        <flux:card class="mb-4">
            <flux:heading class="mb-3">Where the money went</flux:heading>
            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                @foreach ($this->byCategory as $row)
                    <div class="rounded-md border border-zinc-200 px-3 py-2 dark:border-zinc-700">
                        <flux:text class="text-xs text-zinc-500">
                            {{ $row->category?->name ?? 'Uncategorised' }}
                        </flux:text>
                        <flux:heading size="lg">{{ number_format((float) $row->total, 2) }}</flux:heading>
                    </div>
                @endforeach
            </div>
        </flux:card>
    @endif

    <div class="mb-2 flex flex-row flex-wrap items-end justify-between gap-3">
        <div class="flex gap-2">
            @can('create expenditure')
                <flux:button href="{{ route('expenditure.create') }}" icon="plus" wire:navigate>
                    Record Expenditure
                </flux:button>
            @endcan
            <flux:button href="{{ route('expenditure.categories.index') }}" icon="tag" variant="ghost" wire:navigate>
                Categories
            </flux:button>
        </div>

        <div class="flex flex-wrap items-end gap-2">
            <flux:input type="search" icon="magnifying-glass" placeholder="Search item, payee or reference"
                wire:model.live.debounce.400ms="search" class="w-64" />
            <flux:select wire:model.live="categoryId" label="Category">
                <flux:select.option value="">All</flux:select.option>
                @foreach ($this->categories as $category)
                    <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="status" label="Status">
                <flux:select.option value="">All</flux:select.option>
                @foreach (\Modules\Expenditure\Models\Expenditure::STATUSES as $option)
                    <flux:select.option value="{{ $option }}">{{ ucfirst($option) }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input type="date" wire:model.live="from" label="From" />
            <flux:input type="date" wire:model.live="to" label="To" />
            @if ($this->hasFilters)
                <flux:button type="button" variant="ghost" icon="x-mark" wire:click="clearFilters">
                    Clear
                </flux:button>
            @endif
        </div>
    </div>

    <flux:card>
        <flux:table wire:loading.class="opacity-60">
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sort === 'spent_on'" :direction="$direction"
                    wire:click="sortBy('spent_on')">Date</flux:table.column>
                <flux:table.column sortable :sorted="$sort === 'title'" :direction="$direction"
                    wire:click="sortBy('title')">Item</flux:table.column>
                <flux:table.column>Category</flux:table.column>
                <flux:table.column>Payee</flux:table.column>
                <flux:table.column sortable :sorted="$sort === 'amount'" :direction="$direction"
                    wire:click="sortBy('amount')">Amount</flux:table.column>
                <flux:table.column>Method</flux:table.column>
                <flux:table.column sortable :sorted="$sort === 'status'" :direction="$direction"
                    wire:click="sortBy('status')">Status</flux:table.column>
                <flux:table.column>Actions</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->expenditures as $expenditure)
                    <flux:table.row :key="$expenditure->id">
                        <flux:table.cell>{{ $expenditure->spent_on?->format('d M Y') }}</flux:table.cell>
                        <flux:table.cell>{{ $expenditure->title }}</flux:table.cell>
                        <flux:table.cell>{{ $expenditure->category?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $expenditure->payee ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($expenditure->amount, 2) }}</flux:table.cell>
                        <flux:table.cell>
                            {{ ucfirst(str_replace('_', ' ', $expenditure->payment_method)) }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge
                                :color="match ($expenditure->status) {
                                    'paid' => 'emerald',
                                    'approved' => 'blue',
                                    'cancelled' => 'red',
                                    default => 'amber',
                                }">
                                {{ ucfirst($expenditure->status) }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button href="{{ route('expenditure.show', $expenditure->id) }}" icon="eye"
                                variant="primary" color="emerald" wire:navigate>view</flux:button>
                            @can('edit expenditure')
                                <flux:button href="{{ route('expenditure.edit', $expenditure->id) }}" icon="pencil"
                                    variant="primary" color="yellow" wire:navigate>edit</flux:button>
                            @endcan
                            @can('delete expenditure')
                                <flux:button type="button" icon="trash" variant="primary" color="red"
                                    wire:click="delete({{ $expenditure->id }})"
                                    wire:confirm="Remove this expenditure?">delete</flux:button>
                            @endcan
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="text-center text-gray-500">
                            {{ $this->hasFilters ? 'No expenditure matches these filters.' : 'No expenditure recorded yet.' }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <div class="mt-4">
            {{ $this->expenditures->links() }}
        </div>
    </flux:card>
</div>
