{{-- Director / Institution Scoped Analytics Partial --}}
<div>
    <div class="mb-4 mt-2 flex items-center justify-between border-b border-zinc-200/80 pb-2 dark:border-zinc-800">
        <flux:heading size="xl" class="font-black tracking-tight text-zinc-900 dark:text-white">
            {{ $stats['institution']?->name ?? 'Campus Analytics' }}</flux:heading>
    </div>

    {{-- KPI tiles --}}
    @if (!isset($stats['students_count']))
        {{-- Accountant Financial Metrics Strip --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-kpi-card title="Total Invoiced" icon="document-text" color="sky"
                count="KES {{ $currency($stats['fees_billed']) }}" href="{{ route('feemanagement.index') }}"
                subtext="gross receivables billed" />
            <x-kpi-card title="Collected Revenue" icon="check-badge" color="emerald"
                count="KES {{ $currency($stats['fees_collected']) }}"
                href="{{ route('feemanagement.index', ['status' => 'paid']) }}"
                subtext="{{ $stats['fee_collection_rate'] }}% collection velocity" />
            <x-kpi-card title="Outstanding Arrears" icon="banknotes" color="rose"
                count="KES {{ $currency($stats['fees_outstanding']) }}"
                href="{{ route('feemanagement.index', ['status' => 'overdue']) }}">
                <span class="text-xs font-semibold text-rose-600 block">{{ $stats['overdue_fees_count'] }} overdue
                    defaulters</span>
            </x-kpi-card>
            <x-kpi-card title="Overdue Accounts" icon="exclamation-triangle" color="amber" :count="$stats['overdue_fees_count']"
                href="{{ route('feemanagement.index', ['status' => 'overdue']) }}"
                subtext="require collection reminders" />
        </div>
    @else
        {{-- Director All-Round KPI Strip --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <x-kpi-card title="Students" icon="user-group" color="sky" :count="$stats['students_count']"
                href="{{ route('student.index') }}">
                <div class="flex flex-wrap items-center gap-2 sm:gap-2.5 mt-1.5">
                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-blue-50/90 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 text-[11px] font-semibold border border-blue-200/70 dark:border-blue-800/50">
                        <svg class="size-3 text-blue-600 dark:text-blue-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <circle cx="10" cy="14" r="5" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5L20 4m0 0h-4.5m4.5 0v4.5" />
                        </svg>
                        <span>Male: <strong class="font-bold text-zinc-900 dark:text-white">{{ number_format($stats['male_students_count'] ?? 0) }}</strong></span>
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-pink-50/90 dark:bg-pink-950/40 text-pink-700 dark:text-pink-300 text-[11px] font-semibold border border-pink-200/70 dark:border-pink-800/50">
                        <svg class="size-3 text-pink-600 dark:text-pink-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <circle cx="12" cy="9" r="5" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 14v7m-3-3.5h6" />
                        </svg>
                        <span>Female: <strong class="font-bold text-zinc-900 dark:text-white">{{ number_format($stats['female_students_count'] ?? 0) }}</strong></span>
                    </span>
                </div>
            </x-kpi-card>

            <x-kpi-card title="Parents" icon="users" color="indigo" :count="$stats['parents_count']"
                href="{{ route('parent.index') }}" subtext="linked guardian accounts" />

            <x-kpi-card title="Fee Collection" icon="banknotes" color="emerald"
                count="{{ $stats['fee_collection_rate'] }}%" href="{{ route('feemanagement.index') }}">
                <span class="text-xs font-semibold text-rose-600 block">KES {{ $currency($stats['fees_outstanding']) }}
                    outstanding</span>
            </x-kpi-card>

            <x-kpi-card title="Overdue Fees" icon="exclamation-triangle" color="rose" :count="$stats['overdue_fees_count']"
                href="{{ route('feemanagement.index', ['status' => 'overdue']) }}"
                subtext="require collection reminder" />

            <x-kpi-card title="Attendance Today" icon="clock" color="teal" :count="$stats['attendance_today_count']"
                href="{{ route('attendance.index') }}" subtext="biometric check-ins today" />

            <x-kpi-card title="Biometric Devices" icon="device-phone-mobile" color="violet" :count="$stats['devices_online_count']"
                countSuffix="/{{ $stats['devices_count'] }}" href="{{ route('devices.index') }}"
                subtext="devices online" />
        </div>
    @endif

    {{-- Interactive Animated Trends (ApexCharts) --}}
    <div class="grid grid-cols-1 gap-5 mt-6 {{ isset($stats['student_growth']) ? 'lg:grid-cols-2' : '' }}">
        @if (isset($stats['student_growth']))
            <flux:card class="space-y-3">
                <div class="flex items-center justify-between">
                    <div>
                        <flux:heading size="lg" class="font-bold">Student Enrollment</flux:heading>
                        <flux:text class="text-xs text-zinc-400">New students registered, last 6 months</flux:text>
                    </div>
                    <span
                        class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400 border border-emerald-200/60">
                        Active Growth
                    </span>
                </div>
                @php
                    $studentSeries = [['name' => 'Students', 'data' => array_values($stats['student_growth'])]];
                    $labels = array_keys($stats['student_growth']);
                @endphp
                <x-charts.apex type="area" :series="$studentSeries" :labels="$labels" :colors="['#10b981']" :height="220" />
            </flux:card>
        @endif

        <flux:card class="space-y-3">
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="lg" class="font-bold">Fee Collection Velocity Trend</flux:heading>
                    <flux:text class="text-xs text-zinc-400">Historical comparison: Total Invoiced (Billed) vs. Verified
                        Collections (Paid)</flux:text>
                </div>
                <span
                    class="px-2.5 py-0.5 rounded-full text-xs font-bold font-mono bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400 border border-emerald-200/60">
                    {{ $stats['fee_collection_rate'] }}% Settled
                </span>
            </div>
            @php
                $feeLabels = array_keys($stats['fee_collection_trend']);
                $billedData = [];
                $collectedData = [];
                foreach ($stats['fee_collection_trend'] as $bucket) {
                    $billedData[] = $bucket['billed'] ?? 0;
                    $collectedData[] = $bucket['collected'] ?? 0;
                }
                $feeSeries = [
                    ['name' => 'Total Invoiced (Billed)', 'data' => $billedData],
                    ['name' => 'Revenue Collected (Paid)', 'data' => $collectedData],
                ];
            @endphp
            <x-charts.apex type="area" :series="$feeSeries" :labels="$feeLabels" :colors="['#6366f1', '#10b981']" :height="220"
                currency-prefix="KES " />
        </flux:card>
    </div>

    {{-- Distributions --}}
    <div class="grid grid-cols-1 gap-5 mt-6 lg:grid-cols-2">
        @if (isset($stats['enrollment_by_status']))
            <flux:card class="space-y-3">
                <flux:heading size="lg" class="font-bold">Enrollment Status Breakdown</flux:heading>
                @php
                    $enrollColors = [
                        'active' => 'emerald',
                        'transferred' => 'sky',
                        'graduated' => 'indigo',
                        'dropped' => 'zinc',
                        'suspended' => 'amber',
                        'expelled' => 'red',
                        'withdrawn' => 'zinc',
                    ];
                @endphp
                <x-charts.distribution-bars :data="collect($stats['enrollment_by_status'])
                    ->map(
                        fn($count, $status) => [
                            'label' => ucfirst($status),
                            'value' => $count,
                            'color' => $enrollColors[$status] ?? 'indigo',
                        ],
                    )
                    ->values()
                    ->all()" />
            </flux:card>
        @endif

        <flux:card class="space-y-3">
            <flux:heading size="lg" class="font-bold">Fee Status Breakdown</flux:heading>
            @php
                $feeStatusMeta = [
                    'paid' => ['label' => 'Paid', 'color' => 'emerald'],
                    'partial' => ['label' => 'Partial', 'color' => 'amber'],
                    'overdue' => ['label' => 'Overdue', 'color' => 'red'],
                    'pending' => ['label' => 'Pending', 'color' => 'zinc'],
                ];
            @endphp
            <x-charts.distribution-bars :data="collect($feeStatusMeta)
                ->map(
                    fn($meta, $key) => [
                        'label' => $meta['label'],
                        'value' => $stats['fees_by_status'][$key] ?? 0,
                        'color' => $meta['color'],
                    ],
                )
                ->values()
                ->all()" />
        </flux:card>
    </div>

    {{-- Recent activity tables --}}
    <div class="grid grid-cols-1 gap-5 mt-6 {{ isset($stats['recent_students']) ? 'lg:grid-cols-2' : '' }}">
        <flux:card class="space-y-3">
            <div class="flex items-center justify-between pb-2 border-b border-zinc-100 dark:border-zinc-800">
                <flux:heading size="lg" class="font-bold">Recent Fee Invoices</flux:heading>
                <flux:button href="{{ route('feemanagement.index') }}" wire:navigate.hover variant="ghost"
                    size="sm" icon-trailing="chevron-right" class="text-xs font-semibold">View All</flux:button>
            </div>
            <flux:table class="p-2">
                <flux:table.columns>
                    <flux:table.column>Student</flux:table.column>
                    <x-sortable-column column="title">Title</x-sortable-column>
                    <x-sortable-column column="amount">Amount</x-sortable-column>
                    <flux:table.column>Balance</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($stats['recent_fees'] as $fee)
                        <flux:table.row class="transition-colors hover:bg-zinc-50/50 dark:hover:bg-zinc-800/20">
                            <flux:table.cell>
                                <div class="flex items-center gap-2.5">
                                    @php
                                        $name = $fee->student?->name ?? 'Unknown';
                                        $initials = collect(explode(' ', $name))
                                            ->map(fn($w) => strtoupper(substr($w, 0, 1)))
                                            ->take(2)
                                            ->join('');
                                    @endphp
                                    <div
                                        class="flex h-7 w-7 items-center justify-center rounded-lg bg-indigo-50 text-[11px] font-bold text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-400">
                                        {{ $initials }}
                                    </div>
                                    <span
                                        class="font-semibold text-xs sm:text-sm text-zinc-900 dark:text-zinc-100">{{ $name }}</span>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell class="text-zinc-600 dark:text-zinc-300 text-xs">{{ $fee->title }}
                            </flux:table.cell>
                            <flux:table.cell class="font-bold font-mono text-xs">KES {{ $currency($fee->amount) }}
                            </flux:table.cell>
                            <flux:table.cell class="text-zinc-500 font-mono text-xs">KES
                                {{ $currency($fee->balance) }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm"
                                    :color="match ($fee->status) {
                                                                        'paid' => 'emerald',
                                                                        'partial' => 'amber',
                                                                        'overdue' => 'red',
                                                                        default => 'zinc',
                                                                    }"
                                    class="font-semibold text-[11px]">{{ ucfirst($fee->status) }}</flux:badge>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5" class="text-center py-6 text-zinc-500 text-xs">No fee
                                records found.</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>

        @if (isset($stats['recent_students']))
            <flux:card class="space-y-3">
                <div class="flex items-center justify-between pb-2 border-b border-zinc-100 dark:border-zinc-800">
                    <flux:heading size="lg" class="font-bold">Recent Students</flux:heading>
                    <flux:button href="{{ route('student.index') }}" wire:navigate.hover variant="ghost"
                        size="sm" icon-trailing="chevron-right" class="text-xs font-semibold">View All
                    </flux:button>
                </div>
                <flux:table class="p-2">
                    <flux:table.columns>
                        <flux:table.column>Name</flux:table.column>
                        <x-sortable-column column="admission_number">Admission No.</x-sortable-column>
                        <x-sortable-column column="enrollment_status">Status</x-sortable-column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($stats['recent_students'] as $studentDetails)
                            <flux:table.row class="transition-colors hover:bg-zinc-50/50 dark:hover:bg-zinc-800/20">
                                <flux:table.cell>
                                    <div class="flex items-center gap-2.5">
                                        @php
                                            $name = $studentDetails->student?->name ?? 'Unknown';
                                            $initials = collect(explode(' ', $name))
                                                ->map(fn($w) => strtoupper(substr($w, 0, 1)))
                                                ->take(2)
                                                ->join('');
                                        @endphp
                                        <div
                                            class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-50 text-[11px] font-bold text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400">
                                            {{ $initials }}
                                        </div>
                                        <span
                                            class="font-semibold text-xs sm:text-sm text-zinc-900 dark:text-zinc-100">{{ $name }}</span>
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell class="text-zinc-500 text-xs font-mono">
                                    {{ $studentDetails->admission_number }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge size="sm" :color="$studentDetails->is_active ? 'emerald' : 'zinc'"
                                        class="font-semibold text-[11px]">
                                        {{ ucfirst($studentDetails->enrollment_status) }}
                                    </flux:badge>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="3" class="text-center py-6 text-zinc-500 text-xs">No
                                    students enrolled yet.</flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </flux:card>
        @endif
    </div>
</div>
