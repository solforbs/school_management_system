@php
    $totalLogs = $logs ? $logs->total() : 0;
    $devices = $institution ? $institution->devices()->with('zktecoDevice')->get() : collect();
    $deviceCount = $devices->count();
    $onlineDevices = $devices->filter(fn($d) => $d->zktecoDevice?->isOnline())->count();

    $todayStr = now()->toDateString();
    $yesterdayStr = now()->subDay()->toDateString();
    $startOfWeekStr = now()->startOfWeek()->toDateString();
    $endOfWeekStr = now()->endOfWeek()->toDateString();
    $startOfMonthStr = now()->startOfMonth()->toDateString();
    $endOfMonthStr = now()->endOfMonth()->toDateString();

    $isToday = request('from') === $todayStr && request('to') === $todayStr;
    $isYesterday = request('from') === $yesterdayStr && request('to') === $yesterdayStr;
    $isThisWeek = request('from') === $startOfWeekStr && request('to') === $endOfWeekStr;
    $isThisMonth = request('from') === $startOfMonthStr && request('to') === $endOfMonthStr;
    $isAllTime = !request()->filled('from') && !request()->filled('to');
    $isFiltered = request()->filled('search') || request()->filled('from') || request()->filled('to');
@endphp

<x-layouts::app :title="__(config('attendance.name'))">
    <div class="flex flex-col gap-6 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8" x-data
        x-on:keydown.window.slash.prevent="$refs.searchInput?.focus()">

        {{-- Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2.5">
                    <flux:heading size="xl" class="font-bold tracking-tight text-zinc-900 dark:text-white">
                        Attendance Logs
                    </flux:heading>
                    <flux:badge size="sm" color="violet" class="font-semibold">
                        {{ number_format($totalLogs) }} Entries
                    </flux:badge>
                </div>
                <flux:text class="text-zinc-500 text-sm mt-1">
                    Track daily check-ins, biometric verify modes, and real-time student presence.
                </flux:text>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                @can('view attendance')
                    @if (Route::has('attendance.export'))
                        <flux:button href="{{ route('attendance.export', request()->query()) }}" icon="arrow-down-tray"
                            variant="ghost" class="hover:bg-zinc-100 dark:hover:bg-zinc-800">
                            Export Logs
                        </flux:button>
                    @endif
                @endcan

                @can('view sync device')
                    @if (Route::has('sync-devices.index'))
                        <flux:button href="{{ route('sync-devices.index') }}" wire:navigate.hover icon="arrow-path"
                            variant="primary" class="shadow-sm hover:shadow-md transition-all">
                            Sync Devices
                        </flux:button>
                    @endif
                @endcan
            </div>
        </div>

        @if (!$institution)
            <div
                class="bg-amber-50 dark:bg-amber-500/10 border-l-4 border-amber-500 p-4 rounded-r-xl flex items-start shadow-sm">
                <flux:icon icon="exclamation-triangle" class="size-5 text-amber-500 mr-3 mt-0.5 shrink-0" />
                <div>
                    <h3 class="text-sm font-bold text-amber-800 dark:text-amber-400">No active institution found</h3>
                    <p class="text-xs text-amber-700 dark:text-amber-500 mt-1">
                        Attendance records could not be loaded because no institution is currently assigned to your
                        account.
                    </p>
                </div>
            </div>
        @else
            {{-- Animated KPI Metrics --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <x-kpi-card title="Total Logs" icon="document-text" color="violet" :count="$totalLogs"
                    subtext="recorded entries" />
                <x-kpi-card title="Hardware Terminals" icon="device-phone-mobile" color="indigo" :count="$deviceCount"
                    subtext="turnstiles & clocks" />
                <x-kpi-card title="Live Online" icon="signal" color="emerald" :count="$onlineDevices"
                    countSuffix="/{{ $deviceCount }}" subtext="actively reporting" />
            </div>

            {{-- Filter Toolbar with Quick Presets --}}
            <div
                class="bg-white dark:bg-zinc-900 shadow-[0_1px_3px_0_rgba(0,0,0,0.05)] border border-zinc-200 dark:border-zinc-800 rounded-xl p-4 space-y-3.5">
                {{-- Quick Date Preset Pills --}}
                <div class="flex flex-wrap items-center gap-1.5 text-xs">
                    <span class="text-zinc-400 font-medium mr-1 flex items-center gap-1">
                        <flux:icon icon="calendar" class="size-3.5" />
                        Preset:
                    </span>
                    <a href="{{ route('attendance.index', array_filter(['search' => request('search')])) }}"
                        wire:navigate.hover
                        class="px-2.5 py-1 rounded-lg font-medium transition-all {{ $isAllTime ? 'bg-indigo-600 text-white shadow-xs' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700' }}">
                        All Time
                    </a>
                    <a href="{{ route('attendance.index', array_filter(['search' => request('search'), 'from' => $todayStr, 'to' => $todayStr])) }}"
                        wire:navigate.hover
                        class="px-2.5 py-1 rounded-lg font-medium transition-all {{ $isToday ? 'bg-indigo-600 text-white shadow-xs' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700' }}">
                        ⚡ Today
                    </a>
                    <a href="{{ route('attendance.index', array_filter(['search' => request('search'), 'from' => $yesterdayStr, 'to' => $yesterdayStr])) }}"
                        wire:navigate.hover
                        class="px-2.5 py-1 rounded-lg font-medium transition-all {{ $isYesterday ? 'bg-indigo-600 text-white shadow-xs' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700' }}">
                        Yesterday
                    </a>
                    <a href="{{ route('attendance.index', array_filter(['search' => request('search'), 'from' => $startOfWeekStr, 'to' => $endOfWeekStr])) }}"
                        wire:navigate.hover
                        class="px-2.5 py-1 rounded-lg font-medium transition-all {{ $isThisWeek ? 'bg-indigo-600 text-white shadow-xs' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700' }}">
                        This Week
                    </a>
                    <a href="{{ route('attendance.index', array_filter(['search' => request('search'), 'from' => $startOfMonthStr, 'to' => $endOfMonthStr])) }}"
                        wire:navigate.hover
                        class="px-2.5 py-1 rounded-lg font-medium transition-all {{ $isThisMonth ? 'bg-indigo-600 text-white shadow-xs' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700' }}">
                        This Month
                    </a>
                </div>

                {{-- Search & Custom Date Inputs Form --}}
                <form method="GET"
                    class="flex flex-col lg:flex-row items-stretch lg:items-center gap-3 pt-1 border-t border-zinc-100 dark:border-zinc-800">
                    <div class="flex-1 relative">
                        <flux:input x-ref="searchInput" icon="magnifying-glass" type="text" name="search"
                            placeholder="Search by student name, PIN, or admission no... (Press '/' to focus)"
                            value="{{ request('search') }}" />
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <div class="w-full sm:w-40">
                            <flux:input type="date" name="from" value="{{ request('from') }}" />
                        </div>
                        <span class="text-zinc-400 text-xs font-medium px-0.5">to</span>
                        <div class="w-full sm:w-40">
                            <flux:input type="date" name="to" value="{{ request('to') }}" />
                        </div>

                        <div class="flex items-center gap-2 w-full sm:w-auto">
                            <flux:button type="submit" variant="primary" icon="funnel" class="shadow-sm">
                                Filter
                            </flux:button>

                            @if ($isFiltered)
                                <flux:button href="{{ route('attendance.index') }}" wire:navigate.hover variant="ghost"
                                    icon="x-mark">
                                    Clear
                                </flux:button>
                            @endif
                        </div>
                    </div>
                </form>
            </div>

            {{-- Table Card --}}
            <div
                class="bg-white dark:bg-zinc-900 shadow-[0_1px_3px_0_rgba(0,0,0,0.05)] border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden flex flex-col">
                {{-- Live Header Feed Strip --}}
                <div
                    class="px-6 py-3.5 border-b border-zinc-100 dark:border-zinc-800 flex flex-col sm:flex-row sm:items-center justify-between gap-2 bg-zinc-50/50 dark:bg-zinc-800/20">
                    <div class="text-xs font-medium text-zinc-500">
                        Showing <span
                            class="text-zinc-900 dark:text-white font-bold">{{ $logs ? $logs->count() : 0 }}</span> of
                        <span class="text-zinc-900 dark:text-white font-bold">{{ number_format($totalLogs) }}</span>
                        records
                    </div>
                    <div class="flex items-center gap-3">
                        <span
                            class="inline-flex items-center gap-1.5 text-[11px] font-medium text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-500/10 px-2 py-0.5 rounded-full border border-emerald-500/20">
                            <span class="size-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            Live Feed
                        </span>
                        <a href="{{ request()->fullUrl() }}"
                            class="inline-flex items-center gap-1 text-xs font-medium text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200 transition-colors">
                            <flux:icon icon="arrow-path" class="size-3.5" />
                            <span>Refresh</span>
                        </a>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <flux:table class="p-2">
                        <flux:table.columns>
                            <flux:table.column class="w-2/5">Student & Admission</flux:table.column>
                            <flux:table.column>Terminal / Gate</flux:table.column>
                            <x-sortable-column column="status">Status</x-sortable-column>
                            <flux:table.column>Verify Mode</flux:table.column>
                            <x-sortable-column column="occurred_at">Recorded At</x-sortable-column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @forelse ($logs as $log)
                                @php
                                    $studentName = $log['student_name'] ?? 'Unknown';
                                    $initials = collect(explode(' ', $studentName))
                                        ->map(fn($w) => strtoupper(substr($w, 0, 1)))
                                        ->take(2)
                                        ->join('');
                                    $rawDate = $log['occurred_at'] ?? null;
                                    $dateStr = '—';
                                    $timeStr = '';
                                    if ($rawDate && $rawDate !== '—') {
                                        try {
                                            $parsed = \Carbon\Carbon::parse($rawDate);
                                            $dateStr = $parsed->format('M d, Y');
                                            $timeStr = $parsed->format('h:i A');
                                        } catch (\Throwable $e) {
                                            $dateStr = $rawDate;
                                        }
                                    }
                                @endphp
                                <flux:table.row
                                    class="transition-colors hover:bg-zinc-50/50 dark:hover:bg-zinc-800/20 group">
                                    {{-- Student & Admission Stack --}}
                                    <flux:table.cell>
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="size-8 rounded-full bg-violet-50 dark:bg-violet-900/30 text-violet-600 dark:text-violet-400 ring-1 ring-inset ring-violet-500/20 flex items-center justify-center font-bold text-xs transition-transform group-hover:scale-105 shrink-0">
                                                {{ $initials }}
                                            </div>
                                            <div class="flex flex-col">
                                                <span
                                                    class="font-semibold text-sm text-zinc-900 dark:text-white leading-snug">{{ $studentName }}</span>
                                                <span
                                                    class="font-mono text-[11px] text-zinc-500 dark:text-zinc-400 leading-normal">{{ $log['admission_number'] }}</span>
                                            </div>
                                        </div>
                                    </flux:table.cell>

                                    {{-- Device / Gate --}}
                                    <flux:table.cell>
                                        <div class="flex items-center gap-2 text-zinc-600 dark:text-zinc-300 text-xs">
                                            <flux:icon icon="cpu-chip" class="size-4 text-zinc-400 shrink-0" />
                                            <span class="truncate max-w-[220px]">{{ $log['device_name'] }}</span>
                                        </div>
                                    </flux:table.cell>

                                    {{-- Status --}}
                                    <flux:table.cell>
                                        @if ($log['status_color'] === 'emerald')
                                            <flux:badge color="emerald" size="sm" class="font-semibold">
                                                <span
                                                    class="size-1.5 rounded-full bg-emerald-500 mr-1.5 animate-pulse"></span>
                                                {{ $log['status_label'] }}
                                            </flux:badge>
                                        @elseif ($log['status_color'] === 'red')
                                            <flux:badge color="red" size="sm" class="font-semibold">
                                                <span class="size-1.5 rounded-full bg-red-500 mr-1.5"></span>
                                                {{ $log['status_label'] }}
                                            </flux:badge>
                                        @elseif ($log['status_color'] === 'amber')
                                            <flux:badge color="amber" size="sm" class="font-semibold">
                                                <span class="size-1.5 rounded-full bg-amber-500 mr-1.5"></span>
                                                {{ $log['status_label'] }}
                                            </flux:badge>
                                        @elseif ($log['status_color'] === 'sky')
                                            <flux:badge color="sky" size="sm" class="font-semibold">
                                                <span class="size-1.5 rounded-full bg-sky-500 mr-1.5"></span>
                                                {{ $log['status_label'] }}
                                            </flux:badge>
                                        @else
                                            <flux:badge color="zinc" size="sm" class="font-medium">
                                                {{ $log['status_label'] }}
                                            </flux:badge>
                                        @endif
                                    </flux:table.cell>

                                    {{-- Verify Mode --}}
                                    <flux:table.cell>
                                        <div
                                            class="inline-flex items-center gap-1.5 text-xs font-medium text-zinc-600 dark:text-zinc-400 bg-zinc-100 dark:bg-zinc-800 px-2.5 py-1 rounded-md border border-zinc-200/50 dark:border-zinc-700/50">
                                            @if (stripos($log['verify_mode_label'], 'finger') !== false)
                                                <flux:icon icon="finger-print"
                                                    class="size-3.5 text-zinc-500 shrink-0" />
                                            @elseif(stripos($log['verify_mode_label'], 'card') !== false || stripos($log['verify_mode_label'], 'rfid') !== false)
                                                <flux:icon icon="identification"
                                                    class="size-3.5 text-zinc-500 shrink-0" />
                                            @elseif(stripos($log['verify_mode_label'], 'face') !== false)
                                                <flux:icon icon="user" class="size-3.5 text-zinc-500 shrink-0" />
                                            @else
                                                <flux:icon icon="check-badge"
                                                    class="size-3.5 text-zinc-500 shrink-0" />
                                            @endif
                                            <span>{{ $log['verify_mode_label'] }}</span>
                                        </div>
                                    </flux:table.cell>

                                    {{-- Date & Time Stack --}}
                                    <flux:table.cell>
                                        <div class="flex flex-col">
                                            <span
                                                class="font-medium text-xs text-zinc-900 dark:text-zinc-100 leading-snug">{{ $dateStr }}</span>
                                            @if ($timeStr)
                                                <span
                                                    class="text-[11px] text-zinc-400 dark:text-zinc-500 leading-normal">{{ $timeStr }}</span>
                                            @endif
                                        </div>
                                    </flux:table.cell>
                                </flux:table.row>
                            @empty
                                <flux:table.row>
                                    <flux:table.cell colspan="5">
                                        <x-empty-state icon="clock" color="violet" title="No Attendance Logs Found"
                                            description="There are no check-in records matching your active filters. Try adjusting your date range or keyword search.">
                                            @if ($isFiltered)
                                                <flux:button href="{{ route('attendance.index') }}"
                                                    wire:navigate.hover variant="primary" icon="arrow-path">
                                                    Reset All Filters
                                                </flux:button>
                                            @endif
                                        </x-empty-state>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforelse
                        </flux:table.rows>
                    </flux:table>
                </div>

                @if ($logs instanceof \Illuminate\Pagination\AbstractPaginator && $logs->hasPages())
                    <div
                        class="px-6 py-4 border-t border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50">
                        {{ $logs->links() }}
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-layouts::app>
