@php
    $currency = fn($v) => number_format((float) $v, 2);
    $pct = fn($num, $den) => $den > 0 ? min(100, round(($num / $den) * 100)) : 0;
@endphp

<x-layouts::app :title="__('Financial & Operational Reports')">
    <div class="flex flex-col gap-6 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">

        {{-- Top Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold tracking-wide uppercase bg-zinc-100 text-zinc-700 border border-zinc-200">
                        {{ ucfirst(auth()->user()->getRoleNames()->first() ?? 'Institutional') }} Intelligence
                    </span>
                    <span class="text-xs text-zinc-400">&bull;</span>
                    <span class="text-xs text-zinc-500 font-medium"><span translate="no">{{ $stats['institution']?->name ?? currentInstitution()?->name ?? 'Campus Analytics' }}</span></span>
                </div>
                @if (auth()->user()->hasRole('Teacher'))
                    <flux:heading size="xl" class="font-black tracking-tight text-zinc-900">Classroom &amp; Academic Analytics</flux:heading>
                    <flux:text class="text-zinc-500 text-sm mt-0.5">
                        Daily roll-call records, student attendance trends, academic performance, and teaching allocations.
                    </flux:text>
                @else
                    <flux:heading size="xl" class="font-black tracking-tight text-zinc-900">Financial Reports &amp; Analytics</flux:heading>
                    <flux:text class="text-zinc-500 text-sm mt-0.5">
                        Real-time revenue tracking, historical cash flow analysis, and audited accounting summaries.
                    </flux:text>
                @endif
            </div>

            <div class="flex items-center gap-3">
                @if (auth()->user()->hasRole('Teacher'))
                    <flux:button href="{{ route('attendance.index') }}" icon="clipboard-document-check" variant="primary" class="shadow-sm font-semibold">
                        Roll-Call Register
                    </flux:button>
                @else
                    @can('export report')
                        <flux:button href="{{ route('report.export') }}" icon="arrow-down-tray" variant="primary" class="shadow-sm font-semibold">
                            @if (auth()->user()->hasRole('Admin') || auth()->user()->hasRole('Director'))
                                Export Full Workbook (Excel)
                            @else
                                Export Fee Ledger (CSV)
                            @endif
                        </flux:button>
                    @endcan
                @endif
            </div>
        </div>

        {{-- ===================== ADMIN: SYSTEM-WIDE ===================== --}}
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
</x-layouts::app>
