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

<div class="flex flex-col gap-6 w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-0 pb-8 min-w-0 overflow-x-clip" data-analytics-content>

    {{-- Admin-only; only rendered when deploy.enabled is on to avoid phantom empty gaps --}}
    @if (auth()->user()?->hasRole('Admin') && config('deploy.enabled'))
        <livewire:pull-updates />
        <livewire:version-switcher />
    @endif

    {{-- Top Header Section --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 min-w-0">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2 mb-1">
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold tracking-wide uppercase bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border border-zinc-200/80 dark:border-zinc-700">
                    {{ ucfirst(auth()->user()->getRoleNames()->first() ?? 'Institutional') }} Intelligence
                </span>
                <span class="text-xs text-zinc-400">&bull;</span>
                <span class="text-xs text-zinc-500 font-medium truncate" data-institution-name>{{ $stats['institution']?->name ?? currentInstitution()?->name ?? 'Campus Analytics' }}</span>
            </div>
            @if (auth()->user()->hasRole('Teacher'))
                <flux:heading size="xl" class="font-black tracking-tight text-zinc-900 dark:text-white break-words">Classroom &amp; Academic Analytics</flux:heading>
                <flux:text class="text-zinc-500 text-sm mt-0.5">
                    Daily roll-call records, student attendance trends, academic performance, and teaching allocations.
                </flux:text>
            @else
                <flux:heading size="xl" class="font-black tracking-tight text-zinc-900 dark:text-white break-words">Financial Reports &amp; Analytics</flux:heading>
                <flux:text class="text-zinc-500 text-sm mt-0.5">
                    Real-time revenue tracking, historical cash flow analysis, and audited accounting summaries.
                </flux:text>
            @endif
        </div>

        @if (auth()->user()->hasRole('Teacher'))
            <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                <flux:button href="{{ route('attendance.index') }}" wire:navigate.hover icon="clipboard-document-check" variant="filled" class="font-semibold shadow-sm w-full sm:w-auto justify-center">
                    Roll-Call Register
                </flux:button>
                @if (Route::has('assessment.band.entry'))
                    <flux:button href="{{ route('assessment.band.entry') }}" wire:navigate.hover icon="academic-cap" variant="outline" class="font-semibold shadow-sm w-full sm:w-auto justify-center">
                        CBC Band Entry
                    </flux:button>
                @endif
            </div>
        @else
            @can('export report')
                <div class="flex flex-wrap items-end gap-2 w-full sm:w-auto" x-data="{ period: 'all_time' }">
                    {{-- Excel / CSV: raw data for accountants and auditors --}}
                    @if (auth()->user()->hasRole('Admin') || auth()->user()->hasRole('Director'))
                        <form method="GET" action="{{ route('report.export') }}" target="_blank"
                            class="flex flex-wrap items-end gap-2 w-full sm:w-auto">
                            <div class="w-full sm:w-auto min-w-[9.5rem]">
                                <flux:select name="period" size="sm" label="Period" x-model="period">
                                    <flux:select.option value="all_time">All time</flux:select.option>
                                    <flux:select.option value="this_month">This month</flux:select.option>
                                    <flux:select.option value="this_year">This year</flux:select.option>
                                    <flux:select.option value="custom">Custom range</flux:select.option>
                                </flux:select>
                            </div>
                            <template x-if="period === 'custom'">
                                <div class="flex flex-wrap sm:flex-nowrap items-end gap-2 w-full sm:w-auto">
                                    <flux:input type="date" name="from" size="sm" label="From" class="w-full sm:w-auto" />
                                    <flux:input type="date" name="to" size="sm" label="To" class="w-full sm:w-auto" />
                                </div>
                            </template>
                            <flux:button type="submit" icon="table-cells" variant="filled" class="font-semibold shadow-sm w-full sm:w-auto justify-center">
                                Export Excel
                            </flux:button>
                        </form>
                    @else
                        <a href="{{ route('report.export') }}" target="_blank" class="w-full sm:w-auto">
                            <flux:button icon="table-cells" variant="filled" class="font-semibold shadow-sm w-full sm:w-auto justify-center">
                                Export Fee Ledger (CSV)
                            </flux:button>
                        </a>
                    @endif
                </div>
            @endcan
        @endif
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
