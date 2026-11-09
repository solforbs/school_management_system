{{-- Admin / System Scoped Analytics Partial --}}
<div>
    {{-- KPI tiles --}}
    <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-2 xl:grid-cols-3 sm:gap-4 xl:gap-5">
        <x-kpi-card title="Institutions" icon="building-office-2" color="indigo" :count="$stats['institutions_count']"
            href="{{ route('institution.index') }}">
            <div class="flex items-center flex-wrap gap-1.5 mt-0.5">
                <span
                    class="px-2 py-0.5 rounded-md bg-zinc-100 dark:bg-zinc-800 text-xs font-semibold text-zinc-700 dark:text-zinc-300">{{ $stats['active_institutions_count'] }}
                    active</span>
                @if (end($stats['institution_growth']))
                    <span class="text-emerald-600 dark:text-emerald-400 text-xs font-bold">&uarr;
                        +{{ end($stats['institution_growth']) }} /mo</span>
                @endif
            </div>
        </x-kpi-card>

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

        <x-kpi-card title="Fee Collection" icon="banknotes" color="emerald" count="{{ $stats['fee_collection_rate'] }}%"
            href="{{ route('feemanagement.index') }}">
            <span class="text-xs font-semibold text-rose-600 block">KES {{ $currency($stats['fees_outstanding']) }}
                outst.</span>
        </x-kpi-card>

        <x-kpi-card title="Attendance Today" icon="clock" color="teal" :count="$stats['attendance_today_count']"
            href="{{ route('attendance.index') }}" subtext="across all campuses" />

        <x-kpi-card title="Pending Approvals" icon="clipboard-document-check" color="amber" :count="$stats['pending_approvals_count']"
            href="{{ route('institution.index') }}" subtext="institutions awaiting review" />

        <x-kpi-card title="Devices Online" icon="device-phone-mobile" color="rose" :count="$stats['devices_online_count']"
            countSuffix="/{{ $stats['devices_count'] }}" href="{{ route('devices.index') }}"
            subtext="biometric terminals" />
    </div>

    {{-- Interactive Animated Trends (ApexCharts) --}}
    <div class="grid grid-cols-1 gap-5 mt-6 lg:grid-cols-2 min-w-0">
        <flux:card class="space-y-3 min-w-0 overflow-hidden">
            <div class="flex flex-wrap items-start justify-between gap-2 min-w-0">
                <div class="min-w-0">
                    <flux:heading size="lg" class="font-bold">Platform Growth</flux:heading>
                    <flux:text class="text-xs text-zinc-400">New institutions and students across all campuses, last 6
                        months</flux:text>
                </div>
            </div>
            <div class="flex items-center gap-4 text-xs font-semibold text-zinc-600 dark:text-zinc-300">
                <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-indigo-500"></span>
                    Institutions</span>
                <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-emerald-500"></span>
                    Students</span>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 min-w-0">
                @php
                    $instSeries = [['name' => 'Institutions', 'data' => array_values($stats['institution_growth'])]];
                    $studentSeries = [['name' => 'Students', 'data' => array_values($stats['student_growth'])]];
                    $labels = array_keys($stats['institution_growth']);
                @endphp
                <div class="w-full min-w-0 overflow-hidden">
                    <x-charts.apex type="area" :series="$instSeries" :labels="$labels" :colors="['#4f46e5']" :height="140" />
                </div>
                <div class="w-full min-w-0 overflow-hidden">
                    <x-charts.apex type="area" :series="$studentSeries" :labels="$labels" :colors="['#10b981']"
                        :height="140" />
                </div>
            </div>
        </flux:card>

        <flux:card class="space-y-3 min-w-0 overflow-hidden">
            <div class="flex flex-wrap items-start justify-between gap-2 min-w-0">
                <div class="min-w-0">
                    <flux:heading size="lg" class="font-bold">Global Fee Collection Velocity</flux:heading>
                    <flux:text class="text-xs text-zinc-400">Total Billed vs. Collected Revenue across all schools
                    </flux:text>
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
                    ['name' => 'Billed', 'data' => $billedData],
                    ['name' => 'Collected', 'data' => $collectedData],
                ];
            @endphp
            <x-charts.apex type="area" :series="$feeSeries" :labels="$feeLabels" :colors="['#6366f1', '#10b981']" :height="160"
                currency-prefix="KES " />
        </flux:card>
    </div>

    {{-- Platform Revenue --}}
    <div class="mt-6 flex items-center justify-between">
        <div>
            <flux:heading size="lg" class="font-bold">Platform Revenue</flux:heading>
            <flux:text class="text-xs text-zinc-400">Onboarding setup fees and subscription payments, across every institution</flux:text>
        </div>
        <flux:button href="{{ route('admin.payments.index') }}" wire:navigate.hover size="sm" variant="ghost" class="text-xs font-semibold">
            View all payments
        </flux:button>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4 mt-3">
        <x-kpi-card title="Total Revenue" icon="banknotes" color="emerald"
            count="KES {{ $currency($stats['revenue_total']) }}" href="{{ route('admin.payments.index') }}">
            @php
                $delta = null;
                if ($stats['revenue_last_month'] > 0) {
                    $change = $stats['revenue_this_month'] - $stats['revenue_last_month'];
                    $delta = round($change / $stats['revenue_last_month'] * 100);
                }
            @endphp
            <div class="flex items-center flex-wrap gap-1.5 mt-0.5">
                <span class="px-2 py-0.5 rounded-md bg-zinc-100 dark:bg-zinc-800 text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                    KES {{ $currency($stats['revenue_this_month']) }} this month
                </span>
                @if ($delta !== null)
                    <span class="{{ $delta >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }} text-xs font-bold">
                        {{ $delta >= 0 ? '&uarr;' : '&darr;' }} {{ abs($delta) }}%
                    </span>
                @endif
            </div>
        </x-kpi-card>

        <x-kpi-card title="Onboarding Fees" icon="rocket-launch" color="indigo"
            count="KES {{ $currency($stats['revenue_setup']) }}" href="{{ route('admin.payments.index', ['purpose' => 'setup']) }}"
            subtext="one-off setup fees, all time" />

        <x-kpi-card title="Subscriptions" icon="arrow-path" color="sky"
            count="KES {{ $currency($stats['revenue_subscription']) }}" href="{{ route('admin.payments.index', ['purpose' => 'subscription']) }}"
            subtext="plan renewals &amp; upgrades" />

        <x-kpi-card title="Needs Follow-up" icon="exclamation-triangle" color="amber"
            :count="$stats['payments_pending_count']" href="{{ route('admin.payments.index', ['status' => 'pending']) }}">
            <span class="text-xs font-semibold text-rose-600 block">{{ $stats['payments_failed_count'] }} failed in 30 days</span>
        </x-kpi-card>
    </div>

    <div class="grid grid-cols-1 gap-5 mt-5 lg:grid-cols-3">
        <flux:card class="lg:col-span-2 space-y-3">
            <flux:heading size="lg" class="font-bold">Revenue, last 6 months</flux:heading>
            @php
                $revenueSeries = [['name' => 'Revenue', 'data' => array_values($stats['revenue_trend'])]];
                $revenueLabels = array_keys($stats['revenue_trend']);
            @endphp
            <x-charts.apex type="area" :series="$revenueSeries" :labels="$revenueLabels" :colors="['#059669']" :height="220" currency-prefix="KES " />
        </flux:card>

        <flux:card class="space-y-3">
            <div class="flex items-center justify-between pb-2 border-b border-zinc-100 dark:border-zinc-800">
                <flux:heading size="lg" class="font-bold">Recent Payments</flux:heading>
                <flux:button href="{{ route('admin.payments.index') }}" wire:navigate.hover size="sm" variant="ghost" class="text-xs font-semibold">View all</flux:button>
            </div>
            <div class="space-y-2.5">
                @forelse ($stats['recent_payments'] as $payment)
                    <div class="rounded-xl border border-zinc-100 dark:border-zinc-800 p-3">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-bold text-xs text-zinc-900 dark:text-white truncate">
                                <span translate="no">{{ $payment->institution?->name ?? 'Unclaimed' }}</span>
                            </span>
                            <span class="font-mono text-xs font-semibold text-zinc-700 dark:text-zinc-300 shrink-0">
                                KES {{ $currency($payment->amount) }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between gap-2 mt-1">
                            <span class="text-[11px] text-zinc-400">
                                {{ $payment->isSetupFee() ? 'Setup fee' : 'Subscription' }}
                                &bull; {{ $payment->created_at->diffForHumans() }}
                            </span>
                            <flux:badge size="sm" :color="match ($payment->status) {
                                'successful' => 'emerald',
                                'pending' => 'amber',
                                'failed' => 'rose',
                                default => 'zinc',
                            }">{{ ucfirst($payment->status) }}</flux:badge>
                        </div>
                    </div>
                @empty
                    <flux:text class="text-xs text-zinc-400 text-center py-4">No payments recorded yet.</flux:text>
                @endforelse
            </div>
        </flux:card>
    </div>

    {{-- Distributions --}}
    <div class="grid grid-cols-1 gap-5 mt-6 lg:grid-cols-3">
        <flux:card class="space-y-3">
            <flux:heading size="lg" class="font-bold">Institutions by Type</flux:heading>
            <x-charts.distribution-bars :data="collect($stats['institutions_by_type'])
                ->map(fn($count, $type) => ['label' => $type, 'value' => $count, 'color' => 'indigo'])
                ->values()
                ->all()" />
        </flux:card>

        <flux:card class="space-y-3">
            <flux:heading size="lg" class="font-bold">Fee Status</flux:heading>
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

        <flux:card class="space-y-3">
            <flux:heading size="lg" class="font-bold">Users by Role</flux:heading>
            @php
                $roleColors = ['indigo', 'sky', 'violet', 'amber', 'rose', 'emerald'];
            @endphp
            <x-charts.distribution-bars :data="collect($stats['users_by_role'])
                ->map(fn($count, $role) => ['label' => $role, 'value' => $count])
                ->values()
                ->map(fn($item, $i) => $item + ['color' => $roleColors[$i % count($roleColors)]])
                ->all()" />
        </flux:card>
    </div>

    {{-- Needs attention --}}
    @if ($stats['pending_approvals_count'] > 0)
        <flux:card class="space-y-3 mt-6 min-w-0 overflow-hidden">
            <div class="flex items-center justify-between pb-2 border-b border-zinc-100 dark:border-zinc-800">
                <div>
                    <flux:heading size="lg" class="font-bold">Needs Your Attention</flux:heading>
                    <flux:text class="text-xs text-zinc-400">Institutions waiting for admin review</flux:text>
                </div>
                <flux:badge color="amber" class="font-semibold">{{ $stats['pending_approvals_count'] }} pending
                </flux:badge>
            </div>
            <div class="overflow-x-auto w-full">
                <flux:table class="p-2 min-w-[500px] sm:min-w-0">
                    <flux:table.columns>
                        <flux:table.column>Name</flux:table.column>
                        <flux:table.column>Owner</flux:table.column>
                        <flux:table.column>Type</flux:table.column>
                        <flux:table.column>Submitted</flux:table.column>
                        <flux:table.column>Actions</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($stats['pending_institutions'] as $institution)
                            <flux:table.row class="transition-colors hover:bg-zinc-50/50 dark:hover:bg-zinc-800/20">
                                <flux:table.cell class="font-semibold text-xs sm:text-sm"><span translate="no">{{ $institution->name }}</span>
                                </flux:table.cell>
                                <flux:table.cell class="text-xs"><span translate="no">{{ $institution->owner?->name }}</span></flux:table.cell>
                                <flux:table.cell class="text-xs">{{ $institution->type }}</flux:table.cell>
                                <flux:table.cell class="text-xs text-zinc-400">
                                    {{ $institution->created_at?->diffForHumans() }}</flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex items-center gap-2">
                                        <flux:button href="{{ route('institution.show', $institution->id) }}"
                                            wire:navigate.hover icon="eye" size="sm" variant="subtle">Review
                                        </flux:button>
                                        <form action="{{ route('institution.approve', $institution->id) }}" method="POST"
                                            class="inline">
                                            @csrf
                                            <flux:button type="submit" icon="check" size="sm" variant="primary"
                                                color="emerald">Approve</flux:button>
                                        </form>
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        </flux:card>
    @endif

    {{-- Recent activity --}}
    <div class="grid grid-cols-1 gap-5 mt-6 lg:grid-cols-3 min-w-0">
        <flux:card class="lg:col-span-2 space-y-3 min-w-0 overflow-hidden">
            <flux:heading size="lg" class="font-bold">Recent Institutions</flux:heading>
            <div class="overflow-x-auto w-full">
                <flux:table class="p-2 min-w-[420px] sm:min-w-0">
                    <flux:table.columns>
                        <x-sortable-column column="name">Name</x-sortable-column>
                        <flux:table.column>Owner</flux:table.column>
                        <flux:table.column>Status</flux:table.column>
                        <x-sortable-column column="created_at">Created</x-sortable-column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($stats['recent_institutions'] as $institution)
                            <flux:table.row class="transition-colors hover:bg-zinc-50/50 dark:hover:bg-zinc-800/20">
                                <flux:table.cell class="font-semibold text-xs sm:text-sm"><span translate="no">{{ $institution->name }}</span>
                                </flux:table.cell>
                                <flux:table.cell class="text-xs"><span translate="no">{{ $institution->owner?->name }}</span></flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge size="sm" :color="$institution->is_active ? 'emerald' : 'zinc'"
                                        class="font-semibold text-[11px]">
                                        {{ ucfirst($institution->status ?? 'unknown') }}
                                    </flux:badge>
                                </flux:table.cell>
                                <flux:table.cell class="text-xs text-zinc-400 font-mono">
                                    {{ $institution->created_at?->format('d M Y') }}</flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="4" class="text-center py-6 text-zinc-500 text-xs">No
                                    institutions yet.</flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>
        </flux:card>

        <flux:card class="space-y-3 min-w-0 overflow-hidden">
            <div class="flex items-center justify-between pb-2 border-b border-zinc-100 dark:border-zinc-800">
                <flux:heading size="lg" class="font-bold">Recent Messages</flux:heading>
                <flux:button href="{{ route('messages.index') }}" wire:navigate.hover size="sm" variant="ghost"
                    class="text-xs font-semibold">View all</flux:button>
            </div>
            <div class="space-y-2.5">
                @forelse ($stats['recent_contact_messages'] as $message)
                    <a href="{{ route('messages.show', $message) }}" wire:navigate.hover
                        class="block rounded-xl border border-zinc-100 dark:border-zinc-800 p-3 hover:bg-zinc-50/50 dark:hover:bg-zinc-800/20 transition">
                        <div class="flex items-center justify-between">
                            <span
                                class="font-bold text-xs sm:text-sm text-zinc-900 dark:text-white"><span translate="no">{{ $message->name }}</span></span>
                            <span class="text-[11px] text-zinc-400">{{ $message->created_at->diffForHumans() }}</span>
                        </div>
                        <p class="mt-1 truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $message->message }}</p>
                    </a>
                @empty
                    <flux:text class="text-xs text-zinc-400 text-center py-4">No messages yet.</flux:text>
                @endforelse
            </div>
        </flux:card>
    </div>
</div>
