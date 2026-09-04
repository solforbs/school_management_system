<?php

use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Institution\Models\Institution;

/**
 * Every payment made to the platform - the onboarding setup fee and every
 * subscription payment, across every institution. Admin-only: a Director
 * has the same history already, scoped to their own school, on the
 * Billing page.
 */
new #[Title('Payments')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $purpose = '';

    #[Url]
    public string $status = '';

    #[Url(as: 'institution')]
    public string $institutionId = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public string $sort = 'newest';

    public function mount(): void
    {
        abort_unless(isAdmin(), 403);
    }

    /**
     * Any filter change starts back on page 1 - otherwise a narrower result
     * set can leave the view stranded past its own last page.
     */
    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'purpose', 'status', 'institutionId', 'from', 'to', 'sort'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'purpose', 'status', 'institutionId', 'from', 'to', 'sort']);
        $this->resetPage();
    }

    /**
     * Every filter but sorting - the set the table and the summary strip
     * both narrow down to, so the totals on screen always describe exactly
     * the rows below them.
     */
    private function filtered(): Builder
    {
        return Payment::query()
            ->when(
                $this->search !== '',
                fn ($q) => $q->where(function ($sub) {
                    $term = '%'.$this->search.'%';

                    $sub->where('reference', 'like', $term)
                        ->orWhere('gateway_reference', 'like', $term)
                        ->orWhereHas('institution', fn ($iq) => $iq->where('name', 'like', $term));
                }),
            )
            ->when($this->purpose !== '', fn ($q) => $q->where('purpose', $this->purpose))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->institutionId !== '', fn ($q) => $q->where('institution_id', $this->institutionId))
            ->when($this->from !== '', fn ($q) => $q->whereDate('created_at', '>=', $this->from))
            ->when($this->to !== '', fn ($q) => $q->whereDate('created_at', '<=', $this->to));
    }

    #[Computed]
    public function payments(): LengthAwarePaginator
    {
        $query = $this->filtered()->with(['institution', 'plan', 'initiatedBy']);

        match ($this->sort) {
            'oldest' => $query->oldest('created_at'),
            'amount_desc' => $query->orderByDesc('amount'),
            'amount_asc' => $query->orderBy('amount'),
            default => $query->latest('created_at'),
        };

        return $query->paginate(20);
    }

    /**
     * Totals for whatever is currently filtered - not the whole table, so
     * these numbers always match what's visible below them.
     *
     * @return array{count: int, total_amount: float, setup_amount: float, subscription_amount: float, pending_amount: float, pending_count: int, failed_count: int}
     */
    #[Computed]
    public function summary(): array
    {
        // filtered() builds a fresh query every call, so each stat below
        // runs its own unclouded copy - none of these narrow one another.
        return [
            'count' => $this->filtered()->count(),
            'total_amount' => (float) $this->filtered()->where('status', 'successful')->sum('amount'),
            'setup_amount' => (float) $this->filtered()->where('status', 'successful')->where('purpose', Payment::PURPOSE_SETUP)->sum('amount'),
            'subscription_amount' => (float) $this->filtered()->where('status', 'successful')->where('purpose', Payment::PURPOSE_SUBSCRIPTION)->sum('amount'),
            'pending_amount' => (float) $this->filtered()->where('status', 'pending')->sum('amount'),
            'pending_count' => $this->filtered()->where('status', 'pending')->count(),
            'failed_count' => $this->filtered()->whereIn('status', ['failed', 'abandoned'])->count(),
        ];
    }

    /**
     * Institutions worth offering in the filter - only ones that have
     * actually paid for something.
     *
     * @return Collection<int, Institution>
     */
    #[Computed]
    public function institutions(): Collection
    {
        return Institution::whereHas('payments')->orderBy('name')->get(['id', 'name']);
    }
}; ?>

@php
    $summary = $this->summary;
    $payments = $this->payments;
    $hasActiveFilters = $search !== '' || $purpose !== '' || $status !== '' || $institutionId !== '' || $from !== '' || $to !== '';
@endphp

<div class="flex flex-col gap-6 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold tracking-wide uppercase bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400 border border-emerald-200/60">
                    Platform Revenue
                </span>
                <span class="text-zinc-300">&bull;</span>
                <span class="text-xs text-zinc-500 font-medium">Every institution, every school</span>
            </div>
            <flux:heading size="xl" class="font-black tracking-tight text-zinc-900 dark:text-white">Payments</flux:heading>
            <flux:text class="text-zinc-500 text-sm mt-0.5">
                Onboarding setup fees and subscription payments across every institution on the platform.
            </flux:text>
        </div>

        <div class="flex items-center gap-3">
            <flux:button href="{{ route('report.export') }}" icon="arrow-down-tray" variant="subtle" class="font-semibold shadow-sm">
                Export Full Workbook
            </flux:button>
        </div>
    </div>

    {{-- KPI strip - reflects whatever is currently filtered below it --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <x-kpi-card title="Total Revenue" icon="banknotes" color="emerald"
            count="KES {{ number_format($summary['total_amount'], 2) }}" subtext="settled payments" />

        <x-kpi-card title="Onboarding Fees" icon="rocket-launch" color="indigo"
            count="KES {{ number_format($summary['setup_amount'], 2) }}" subtext="one-off setup fees" />

        <x-kpi-card title="Subscriptions" icon="arrow-path" color="sky"
            count="KES {{ number_format($summary['subscription_amount'], 2) }}" subtext="plan renewals & upgrades" />

        <x-kpi-card title="Pending / Failed" icon="clock" color="amber"
            count="KES {{ number_format($summary['pending_amount'], 2) }}"
            subtext="{{ $summary['pending_count'] }} pending &bull; {{ $summary['failed_count'] }} failed/abandoned" />
    </div>

    {{-- Filters --}}
    <flux:card class="p-3 sm:p-4">
        <div class="flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-[12rem]">
                <flux:input type="search" icon="magnifying-glass" label="Search" size="sm"
                    placeholder="Institution, reference, gateway ref..." wire:model.live.debounce.400ms="search" />
            </div>

            <div class="min-w-[10rem]">
                <flux:select label="Institution" size="sm" wire:model.live="institutionId">
                    <flux:select.option value="">All institutions</flux:select.option>
                    @foreach ($this->institutions as $institution)
                        <flux:select.option value="{{ $institution->id }}">{{ $institution->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="min-w-[9rem]">
                <flux:select label="Purpose" size="sm" wire:model.live="purpose">
                    <flux:select.option value="">Setup &amp; subscription</flux:select.option>
                    <flux:select.option value="setup">Setup fee</flux:select.option>
                    <flux:select.option value="subscription">Subscription</flux:select.option>
                </flux:select>
            </div>

            <div class="min-w-[9rem]">
                <flux:select label="Status" size="sm" wire:model.live="status">
                    <flux:select.option value="">Any status</flux:select.option>
                    <flux:select.option value="successful">Successful</flux:select.option>
                    <flux:select.option value="pending">Pending</flux:select.option>
                    <flux:select.option value="failed">Failed</flux:select.option>
                    <flux:select.option value="abandoned">Abandoned</flux:select.option>
                </flux:select>
            </div>

            <div class="min-w-[8.5rem]">
                <flux:input type="date" label="From" size="sm" wire:model.live="from" />
            </div>

            <div class="min-w-[8.5rem]">
                <flux:input type="date" label="To" size="sm" wire:model.live="to" />
            </div>

            <div class="min-w-[10rem]">
                <flux:select label="Sort" size="sm" wire:model.live="sort">
                    <flux:select.option value="newest">Newest first</flux:select.option>
                    <flux:select.option value="oldest">Oldest first</flux:select.option>
                    <flux:select.option value="amount_desc">Amount: high to low</flux:select.option>
                    <flux:select.option value="amount_asc">Amount: low to high</flux:select.option>
                </flux:select>
            </div>

            @if ($hasActiveFilters)
                <flux:button type="button" size="sm" variant="ghost" wire:click="resetFilters">
                    Clear filters
                </flux:button>
            @endif
        </div>
    </flux:card>

    {{-- Table --}}
    <div class="bg-white dark:bg-zinc-900 shadow-xs border border-zinc-200/80 dark:border-zinc-800 rounded-2xl overflow-hidden flex flex-col">
        @if ($payments->isNotEmpty())
            <div class="w-full overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-500 border-b border-zinc-200/80 dark:border-zinc-700 text-[11px] font-bold uppercase tracking-wider">
                        <tr>
                            <th class="py-3.5 px-5">Institution</th>
                            <th class="py-3.5 px-5">Purpose &amp; Plan</th>
                            <th class="py-3.5 px-5">Amount</th>
                            <th class="py-3.5 px-5">Channel</th>
                            <th class="py-3.5 px-5">Status</th>
                            <th class="py-3.5 px-5">Reference</th>
                            <th class="py-3.5 px-5 text-right">When</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($payments as $payment)
                            <tr class="hover:bg-zinc-50/60 dark:hover:bg-zinc-800/40 transition-colors">
                                <td class="py-3.5 px-5">
                                    @if ($payment->institution)
                                        <a href="{{ route('institution.show', $payment->institution_id) }}" wire:navigate.hover
                                            class="font-bold text-zinc-900 dark:text-white hover:text-blue-600 transition text-xs sm:text-sm">
                                            {{ $payment->institution->name }}
                                        </a>
                                    @else
                                        <span class="text-xs text-zinc-400 italic">Not yet claimed</span>
                                    @endif
                                    <div class="text-[11px] text-zinc-400 mt-0.5">{{ $payment->initiatedBy?->name ?? '—' }}</div>
                                </td>
                                <td class="py-3.5 px-5">
                                    <flux:badge size="sm" :color="$payment->isSetupFee() ? 'indigo' : 'sky'">
                                        {{ $payment->isSetupFee() ? 'Setup fee' : 'Subscription' }}
                                    </flux:badge>
                                    @if ($payment->plan)
                                        <div class="text-[11px] text-zinc-500 mt-1">{{ $payment->plan->name }}</div>
                                    @endif
                                </td>
                                <td class="py-3.5 px-5">
                                    <span class="font-mono font-bold text-zinc-900 dark:text-white text-sm">
                                        {{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-5">
                                    <span class="text-xs text-zinc-600 dark:text-zinc-300">{{ $payment->channel ? ucfirst($payment->channel) : '—' }}</span>
                                </td>
                                <td class="py-3.5 px-5">
                                    <flux:badge size="sm" :color="match ($payment->status) {
                                        'successful' => 'emerald',
                                        'pending' => 'amber',
                                        'failed' => 'rose',
                                        default => 'zinc',
                                    }">
                                        {{ ucfirst($payment->status) }}
                                    </flux:badge>
                                </td>
                                <td class="py-3.5 px-5">
                                    <span class="font-mono text-[11px] text-zinc-500">{{ $payment->reference }}</span>
                                </td>
                                <td class="py-3.5 px-5 text-right">
                                    <div class="text-xs text-zinc-700 dark:text-zinc-300">
                                        {{ ($payment->paid_at ?? $payment->created_at)?->format('d M Y') }}
                                    </div>
                                    <div class="text-[11px] text-zinc-400">
                                        {{ ($payment->paid_at ?? $payment->created_at)?->format('H:i') }}
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($payments->hasPages())
                <div class="px-5 py-4 border-t border-zinc-100 dark:border-zinc-800">
                    {{ $payments->links() }}
                </div>
            @endif
        @else
            <div class="py-16 text-center">
                <flux:icon icon="banknotes" class="size-12 mx-auto text-zinc-300 mb-3" />
                <h4 class="text-base font-bold text-zinc-900 dark:text-white">No payments found</h4>
                <p class="text-xs text-zinc-500 mt-1">
                    {{ $hasActiveFilters ? 'No payments match your filters.' : 'No payments have been recorded yet.' }}
                </p>
            </div>
        @endif
    </div>
</div>
