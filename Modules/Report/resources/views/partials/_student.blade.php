{{-- Student Analytics Partial --}}
<div>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-kpi-card title="Attendance Today" icon="clock" color="emerald" :count="$stats['attendance_today_count']"
            subtext="check-ins recorded" />
        <x-kpi-card title="This Month" icon="calendar-days" color="indigo" :count="$stats['attendance_this_month_count']" subtext="total days present" />
        <x-kpi-card title="Fees Outstanding" icon="banknotes" color="rose"
            count="KES {{ $currency($stats['fees_outstanding']) }}" href="{{ route('feemanagement.index') }}"
            subtext="current balance" />
    </div>

    <flux:card class="space-y-3 mt-6 min-w-0 overflow-hidden">
        <div class="flex items-center justify-between pb-2 border-b border-zinc-100 dark:border-zinc-800">
            <flux:heading size="lg" class="font-bold">Fee Ledger</flux:heading>
            <flux:button href="{{ route('feemanagement.index') }}" wire:navigate.hover variant="ghost" size="sm"
                class="text-xs font-semibold">View All</flux:button>
        </div>
        <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
            @forelse ($stats['recent_fees'] as $fee)
                <div class="flex items-center justify-between gap-3 py-2.5 hover:bg-zinc-50/50 dark:hover:bg-zinc-800/20 px-1 rounded-lg transition-colors">
                    <div class="min-w-0 flex-1">
                        <div class="font-semibold text-xs sm:text-sm text-zinc-900 dark:text-zinc-100 truncate">
                            {{ $fee->title }}
                        </div>
                        <div class="text-[11px] text-zinc-400">
                            {{ $fee->created_at?->format('d M Y') }}
                        </div>
                    </div>
                    <div class="text-right shrink-0">
                        <div class="font-bold font-mono text-xs sm:text-sm text-zinc-900 dark:text-zinc-100 whitespace-nowrap">
                            KES {{ $currency($fee->amount) }}
                        </div>
                        <div class="flex items-center justify-end gap-1.5 mt-0.5">
                            @if ((float) $fee->balance > 0)
                                <span class="text-[10px] font-mono text-rose-600 dark:text-rose-400 font-medium">Bal: {{ $currency($fee->balance) }}</span>
                            @endif
                            <flux:badge size="sm"
                                :color="match ($fee->status) {
                                    'paid' => 'emerald',
                                    'partial' => 'amber',
                                    'overdue' => 'red',
                                    default => 'zinc',
                                }"
                                class="font-semibold text-[10px] uppercase tracking-wider">
                                {{ ucfirst($fee->status) }}
                            </flux:badge>
                        </div>
                    </div>
                </div>
            @empty
                <div class="py-6 text-center text-zinc-500 text-xs">
                    No fee records found.
                </div>
            @endforelse
        </div>
    </flux:card>
</div>
