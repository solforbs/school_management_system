{{-- Student KPI Tiles --}}
<div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
    <x-kpi-card title="Attendance Today" icon="clock" color="violet" :count="$stats['attendance_today_count']" href="{{ route('attendance.index') }}" subtext="today's check-ins" />

    <x-kpi-card title="This Month" icon="calendar-days" color="indigo" :count="$stats['attendance_this_month_count']" href="{{ route('attendance.index') }}" subtext="days attended this month" />

    <x-kpi-card title="Fees Outstanding" icon="exclamation-triangle" color="red" count="KES {{ $currency($stats['fees_outstanding']) }}" href="{{ route('feemanagement.index') }}" subtext="pending fee balance" />
</div>

{{-- Recent Fees Card --}}
<flux:card class="hover:shadow-md transition-shadow">
    <div class="flex items-center justify-between mb-4">
        <flux:heading size="lg" class="font-semibold">My Fee Statements</flux:heading>
        @can('view feemanagement')
            <flux:button href="{{ route('feemanagement.index') }}" variant="ghost" size="sm" icon-trailing="chevron-right" class="text-xs">
                View All
            </flux:button>
        @endcan
    </div>
    <flux:table>
        <flux:table.columns>
            <flux:table.column>Title</flux:table.column>
            <flux:table.column>Amount</flux:table.column>
            <flux:table.column>Balance</flux:table.column>
            <flux:table.column>Status</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($stats['recent_fees'] as $fee)
                <flux:table.row class="transition-colors hover:bg-zinc-50/50 dark:hover:bg-zinc-800/20">
                    <flux:table.cell class="font-medium text-xs">{{ $fee->title }}</flux:table.cell>
                    <flux:table.cell class="text-xs">{{ $currency($fee->amount) }}</flux:table.cell>
                    <flux:table.cell class="text-zinc-500 text-xs">{{ $currency($fee->balance) }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="match ($fee->status) {
                            'paid' => 'emerald',
                            'partial' => 'amber',
                            'overdue' => 'red',
                            default => 'zinc',
                        }" class="font-semibold">{{ ucfirst($fee->status) }}</flux:badge>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4">
                        <div class="flex flex-col items-center justify-center py-6">
                            <div class="rounded-full bg-zinc-50 dark:bg-zinc-800 p-2.5 mb-2">
                                <flux:icon icon="banknotes" class="size-5 text-zinc-400" />
                            </div>
                            <p class="text-xs font-medium text-zinc-900 dark:text-zinc-100">No fee records</p>
                            <p class="text-[11px] text-zinc-500 dark:text-zinc-400">You do not have any fee statements currently.</p>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</flux:card>
