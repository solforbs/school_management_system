<?php

use App\Services\AnalyticsService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The analytics dashboard. Read-only, so there is nothing to submit - it is
 * a Livewire page purely so it takes part in the same SPA navigation and
 * toast handling as every other module screen.
 */
new #[Title('Financial & Operational Reports')] class extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()->can('view report'), 403);
    }

    /**
     * Computed rather than a public property: the analytics payload is
     * built fresh each render and never needs to serialize into component state.
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function stats(): array
    {
        return app(AnalyticsService::class)->forUser(auth()->user());
    }
}; ?>

@php
    $stats = $this->stats;
    $currency = fn($v) => fmod((float)$v, 1) !== 0.0 ? number_format((float) $v, 2) : number_format((float) $v);
    $pct = fn($num, $den) => $den > 0 ? min(100, round(($num / $den) * 100)) : 0;
@endphp

<div class="flex flex-col gap-6 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">

    {{-- Admin-only; renders nothing for anyone else, or when deploy.enabled is off. --}}
    <livewire:pull-updates />

    {{-- Top Header Section --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold tracking-wide uppercase bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border border-zinc-200/80 dark:border-zinc-700">
                    {{ ucfirst(auth()->user()->getRoleNames()->first() ?? 'Institutional') }} Intelligence
                </span>
                <span class="text-xs text-zinc-400">&bull;</span>
                <span class="text-xs text-zinc-500 font-medium">{{ $stats['institution']?->name ?? currentInstitution()?->name ?? 'Campus Analytics' }}</span>
            </div>
            <flux:heading size="xl" class="font-black tracking-tight text-zinc-900 dark:text-white">Financial Reports &amp; Analytics</flux:heading>
            <flux:text class="text-zinc-500 text-sm mt-0.5">
                Real-time revenue tracking, historical cash flow analysis, and audited accounting summaries.
            </flux:text>
        </div>

        @can('export report')
            <form method="GET" action="{{ route('report.export') }}" x-data="{ period: 'all_time' }"
                class="flex flex-wrap items-end gap-2">
                @if (auth()->user()->hasRole('Admin') || auth()->user()->hasRole('Director'))
                    <div class="min-w-[9.5rem]">
                        <flux:select name="period" size="sm" label="Period" x-model="period">
                            <flux:select.option value="all_time">All time</flux:select.option>
                            <flux:select.option value="this_month">This month</flux:select.option>
                            <flux:select.option value="this_year">This year</flux:select.option>
                            <flux:select.option value="custom">Custom range</flux:select.option>
                        </flux:select>
                    </div>
                    <template x-if="period === 'custom'">
                        <div class="flex items-end gap-2">
                            <flux:input type="date" name="from" size="sm" label="From" />
                            <flux:input type="date" name="to" size="sm" label="To" />
                        </div>
                    </template>
                @endif

                <flux:button type="submit" icon="arrow-down-tray" variant="primary" class="shadow-sm font-semibold">
                    @if (auth()->user()->hasRole('Admin') || auth()->user()->hasRole('Director'))
                        Export Workbook (Excel)
                    @else
                        Export Fee Ledger (CSV)
                    @endif
                </flux:button>
            </form>
        @endcan
    </div>

    {{-- Role Scoped Partials --}}
    @if ($stats['scope'] === 'system')
        @include('report::partials._system')
    @elseif ($stats['scope'] === 'institution')
        @include('report::partials._institution')
    @elseif ($stats['scope'] === 'parent')
        @include('report::partials._parent')
    @elseif ($stats['scope'] === 'student')
        @include('report::partials._student')
    @elseif ($stats['scope'] === 'teacher')
        @include('report::partials._teacher')
    @else
        <flux:callout variant="warning" icon="information-circle">
            <flux:callout.heading>No analytics available</flux:callout.heading>
            <flux:callout.text>Your role does not have a configured analytics view yet.</flux:callout.text>
        </flux:callout>
    @endif

</div>
