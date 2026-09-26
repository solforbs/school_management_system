@if (!$stats['institution'])
    @can('create institution')
        <flux:card class="text-center py-10 space-y-4">
            <div
                class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 ring-1 ring-inset ring-indigo-500/20">
                <flux:icon icon="building-office-2" class="size-6" />
            </div>
            <div class="space-y-1">
                <flux:heading size="lg" class="font-bold">Set up your school</flux:heading>
                <flux:text class="text-zinc-500 dark:text-zinc-400 max-w-md mx-auto">
                    You don't have an institution yet. Create one to start managing students, parents, fees and attendance.
                </flux:text>
            </div>
            <flux:button href="{{ route('institution.create') }}" wire:navigate.hover variant="primary" icon="plus">
                Add an Institution
            </flux:button>
        </flux:card>
    @else
        <flux:callout variant="warning" icon="information-circle">
            <flux:callout.heading>Not yet assigned to a school</flux:callout.heading>
            <flux:callout.text>Ask your Director to link your account to a staff record.</flux:callout.text>
        </flux:callout>
    @endcan
@elseif(!institutionApproved())
    <flux:callout variant="warning" icon="information-circle">
        <flux:callout.heading>Institution pending approval</flux:callout.heading>
        <flux:callout.text>Your institution is awaiting approval from the system administrator. You will be notified once it is approved.</flux:callout.text>
    </flux:callout>
@else
    @if (!isset($stats['students_count']))
        {{-- Accountant 4-Card Financial KPI Quadrant --}}
        <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-2 xl:grid-cols-4 sm:gap-4 xl:gap-5">
            <x-kpi-card title="Total Invoiced" icon="document-text" color="sky"
                count="KES {{ $currency($stats['fees_billed']) }}" href="{{ route('feemanagement.index') }}"
                subtext="gross receivables billed" />
            <x-kpi-card title="Collected Revenue" icon="check-badge" color="emerald"
                count="KES {{ $currency($stats['fees_collected']) }}"
                href="{{ route('feemanagement.index', ['status' => 'paid']) }}"
                subtext="{{ $pct($stats['fees_collected'], $stats['fees_billed']) }}% revenue settled" />
            <x-kpi-card title="Outstanding Arrears" icon="banknotes" color="rose"
                count="KES {{ $currency($stats['fees_outstanding']) }}"
                href="{{ route('feemanagement.index', ['status' => 'overdue']) }}">
                <span class="text-xs font-semibold text-rose-600">{{ $stats['overdue_fees_count'] }} overdue
                    defaulters</span>
            </x-kpi-card>
            @if (isset($stats['payroll_month_total']))
                <x-kpi-card title="Monthly Payroll" icon="credit-card" color="teal"
                    count="KES {{ $currency($stats['payroll_month_total']) }}"
                    href="{{ route('staff.payments.index') }}"
                    subtext="KES {{ $currency($stats['payroll_month_paid']) }} paid · {{ $stats['payroll_pending_count'] }} pending" />
            @else
                <x-kpi-card title="Staff & Faculty" icon="identification" color="indigo" :count="$stats['staff_count'] ?? 0"
                    href="{{ route('staff.index') }}" subtext="active payroll accounts" />
            @endif
        </div>
    @else
        {{-- Director Operational Executive KPI Tiles (Spacious Responsive Grid) --}}
        <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-2 xl:grid-cols-3 sm:gap-4 xl:gap-5">
            <x-kpi-card title="Enrolled Students" icon="academic-cap" color="sky" :count="$stats['students_count']"
                href="{{ route('student.index') }}" subtext="official learner headcount" />
            <x-kpi-card title="Registered Parents" icon="users" color="indigo" :count="$stats['parents_count']"
                href="{{ route('parent.index') }}" subtext="guardian contact accounts" />
            <x-kpi-card title="Attendance Today" icon="clock" color="emerald" :count="$stats['attendance_today_count']"
                href="{{ route('attendance.index') }}" subtext="biometric check-ins" />
            <x-kpi-card title="Outstanding Fees" icon="banknotes" color="rose"
                count="KES {{ $currency($stats['fees_outstanding']) }}"
                href="{{ route('feemanagement.index', ['status' => 'overdue']) }}">
                <span class="text-xs font-semibold text-rose-600 block">{{ $stats['overdue_fees_count'] }} overdue
                    defaulters</span>
            </x-kpi-card>
            @can('view payroll')
                @if (isset($stats['payroll_month_total']))
                    <x-kpi-card title="Monthly Payroll" icon="credit-card" color="teal"
                        count="KES {{ $currency($stats['payroll_month_total']) }}"
                        href="{{ route('staff.payments.index') }}"
                        subtext="KES {{ $currency($stats['payroll_month_paid']) }} paid · {{ $stats['payroll_pending_count'] }} pending" />
                @endif
            @endcan
            @can('view staff')
                @if (isset($stats['staff_count']))
                    <x-kpi-card title="Staff & Faculty" icon="identification" color="indigo" :count="$stats['staff_count']"
                        href="{{ route('staff.index') }}" subtext="active team members" />
                @endif
            @endcan
        </div>
    @endif

    {{-- Operational Summary Strip --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        {{-- Fee Collection Velocity Meter --}}
        <div
            class="bg-white dark:bg-zinc-900 rounded-2xl p-4 sm:p-5 border border-zinc-200/80 dark:border-zinc-800 shadow-xs space-y-3 hover:border-zinc-300 transition duration-150 lg:col-span-2">
            <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-2">
                <div class="min-w-0">
                    <h3 class="text-sm font-bold text-zinc-900 dark:text-zinc-100">Fee Collection Velocity</h3>
                    <p class="text-xs text-zinc-400 mt-0.5">Real-time revenue settlement across all enrolled students.
                    </p>
                </div>
                <span
                    class="shrink-0 whitespace-nowrap px-2.5 py-0.5 rounded-full text-xs font-bold font-mono bg-emerald-50 text-emerald-700 border border-emerald-200/60 dark:bg-emerald-950/50 dark:text-emerald-400">
                    {{ $pct($stats['fees_collected'], $stats['fees_billed']) }}% Collected
                </span>
            </div>

            <div class="h-2.5 w-full rounded-full bg-zinc-100 dark:bg-zinc-800 overflow-hidden">
                <div class="h-full rounded-full bg-gradient-to-r from-emerald-500 to-teal-400 transition-all duration-700 ease-out"
                    style="width: {{ $pct($stats['fees_collected'], $stats['fees_billed']) }}%"></div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 sm:gap-3 text-xs pt-1 font-medium">
                <div class="flex items-center justify-between sm:block text-zinc-600 dark:text-zinc-300">
                    <span class="text-zinc-400 block sm:inline">Collected:</span>
                    <strong class="text-emerald-600 dark:text-emerald-400 font-bold sm:ml-1">KES
                        {{ $currency($stats['fees_collected']) }}</strong>
                </div>
                <div class="flex items-center justify-between sm:block text-zinc-500">
                    <span class="text-zinc-400 block sm:inline">Total Billed:</span>
                    <strong class="text-zinc-900 dark:text-zinc-100 sm:ml-1">KES
                        {{ $currency($stats['fees_billed']) }}</strong>
                </div>
                <div class="flex items-center justify-between sm:block text-rose-600 font-semibold sm:text-right">
                    <span class="text-rose-500/80 block sm:inline">Outstanding:</span>
                    <strong class="sm:ml-1">KES {{ $currency($stats['fees_outstanding']) }}</strong>
                </div>
            </div>
        </div>

        {{-- Biometric Hardware Heartbeat --}}
        @isset($stats['devices_count'])
            <div
                class="bg-white dark:bg-zinc-900 rounded-2xl p-4 sm:p-5 border border-zinc-200/80 dark:border-zinc-800 shadow-xs space-y-3 hover:border-zinc-300 transition duration-150 flex flex-col justify-between">
                <div class="flex items-center justify-between flex-wrap gap-2">
                    <h3 class="text-sm font-bold text-zinc-900 dark:text-zinc-100">Biometric Devices</h3>
                    <span
                        class="px-2.5 py-0.5 rounded-full text-xs font-bold font-mono shrink-0 {{ ($stats['devices_online_count'] ?? 0) > 0 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200/60' : 'bg-zinc-100 text-zinc-600' }}">
                        {{ $stats['devices_online_count'] ?? 0 }}/{{ $stats['devices_count'] }} Online
                    </span>
                </div>
                <div class="text-xs text-zinc-500">
                    Automatic campus entrance and staff attendance synchronization.
                </div>
                <div class="pt-1">
                    <a href="{{ route('devices.index') }}" wire:navigate.hover
                        class="inline-flex items-center justify-between w-full px-3 py-1.5 rounded-xl bg-zinc-50 dark:bg-zinc-800 hover:bg-zinc-100 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-200 text-xs font-semibold transition border border-zinc-200/60">
                        <span>Manage Device Gateways</span>
                        <flux:icon icon="chevron-right" class="size-3.5 text-zinc-400" />
                    </a>
                </div>
            </div>
        @endisset
    </div>

    {{-- Recent Fees Card --}}
    <div
        class="bg-white dark:bg-zinc-900 rounded-2xl p-4 sm:p-5 border border-zinc-200/80 dark:border-zinc-800 shadow-xs space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 pb-2 border-b border-zinc-100 dark:border-zinc-800">
            <div class="min-w-0">
                <h3 class="text-sm font-bold text-zinc-900 dark:text-zinc-100">Recent Fee Receipts</h3>
                <p class="text-xs text-zinc-400 mt-0.5">Latest transactions and student payment allocations.</p>
            </div>
            @can('view feemanagement')
                <flux:button href="{{ route('feemanagement.index') }}" wire:navigate.hover variant="ghost"
                    size="sm" icon-trailing="chevron-right" class="text-xs font-semibold">
                    View Ledger
                </flux:button>
            @endcan
        </div>
        <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
            @forelse ($stats['recent_fees'] as $fee)
                <div
                    class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 py-2.5 text-sm hover:bg-zinc-50/50 dark:hover:bg-zinc-800/20 px-2 rounded-lg transition-colors">
                    <div class="flex items-center gap-3 min-w-0 flex-1">
                        @php
                            $name = $fee->student?->name ?? 'Unknown';
                            $initials = collect(explode(' ', $name))
                                ->map(fn($w) => strtoupper(substr($w, 0, 1)))
                                ->take(2)
                                ->join('');
                        @endphp
                        <div
                            class="flex size-8 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-xs font-bold text-blue-700 dark:bg-blue-950/50 dark:text-blue-400 border border-blue-100 dark:border-blue-800">
                            {{ $initials }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span
                                    class="font-bold text-zinc-900 dark:text-zinc-100 text-xs sm:text-sm truncate">{{ $name }}</span>
                                <span class="text-zinc-400 text-xs truncate max-w-[180px] sm:max-w-none">— {{ $fee->title }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center justify-between sm:justify-end gap-3 shrink-0 pl-11 sm:pl-0">
                        <span class="font-mono font-bold text-xs text-zinc-900 dark:text-zinc-100 whitespace-nowrap">KES
                            {{ $currency($fee->amount) }}</span>
                        <flux:badge size="sm"
                            :color="match ($fee->status) {
                                                        'paid' => 'emerald',
                                                        'partial' => 'amber',
                                                        'overdue' => 'red',
                                                        default => 'zinc',
                                                    }"
                            class="font-semibold text-[11px]">{{ ucfirst($fee->status) }}</flux:badge>
                    </div>
                </div>
            @empty
                <div class="flex flex-col items-center justify-center py-6">
                    <div class="rounded-full bg-zinc-50 dark:bg-zinc-800 p-2.5 mb-2">
                        <flux:icon icon="banknotes" class="size-5 text-zinc-400" />
                    </div>
                    <p class="text-xs font-medium text-zinc-900 dark:text-zinc-100">No fee records yet.</p>
                </div>
            @endforelse
        </div>
    </div>
@endif
