{{-- Parent Analytics Partial --}}
<div>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-kpi-card title="Children" icon="user-group" color="sky" :count="$stats['children_count']" subtext="linked to your account" />
        <x-kpi-card title="Attendance Today" icon="clock" color="emerald" :count="$stats['attendance_today_count']"
            href="{{ route('attendance.index') }}" subtext="biometric check-ins" />
        <x-kpi-card title="Fees Outstanding" icon="banknotes" color="rose"
            count="KES {{ $currency($stats['fees_outstanding']) }}" href="{{ route('feemanagement.index') }}"
            subtext="pending balance" />
    </div>

    <div class="grid grid-cols-1 gap-5 mt-6 lg:grid-cols-2 min-w-0">
        <flux:card class="space-y-3 min-w-0 overflow-hidden">
            <flux:heading size="lg" class="font-bold">My Children</flux:heading>
            <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($stats['children'] as $child)
                    @php
                        $name = $child->student?->name ?? 'Unknown';
                        $initials = collect(explode(' ', $name))
                            ->map(fn($w) => strtoupper(substr($w, 0, 1)))
                            ->take(2)
                            ->join('');
                    @endphp
                    <div class="flex items-center justify-between gap-3 py-2.5 hover:bg-zinc-50/50 dark:hover:bg-zinc-800/20 px-1 rounded-lg transition-colors">
                        <div class="flex items-center gap-2.5 min-w-0 flex-1">
                            <div class="flex size-8 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-[11px] font-bold text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-400 border border-indigo-100 dark:border-indigo-800">
                                {{ $initials }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="font-semibold text-xs sm:text-sm text-zinc-900 dark:text-zinc-100 truncate">
                                    {{ $name }}
                                </div>
                                <div class="text-[11px] text-zinc-500 dark:text-zinc-400 truncate">
                                    <span class="font-mono"><span translate="no">{{ $child->admission_number }}</span></span>
                                    @if ($child->institution?->name)
                                        &bull; <span translate="no">{{ $child->institution->name }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="shrink-0 text-right">
                            <flux:badge size="sm" :color="$child->is_active ? 'emerald' : 'zinc'"
                                class="font-semibold text-[11px]">
                                {{ ucfirst($child->enrollment_status) }}
                            </flux:badge>
                        </div>
                    </div>
                @empty
                    <div class="py-6 text-center text-zinc-500 text-xs">
                        No children linked to your account.
                    </div>
                @endforelse
            </div>
        </flux:card>

        <flux:card class="space-y-3 min-w-0 overflow-hidden">
            <div class="flex items-center justify-between pb-2 border-b border-zinc-100 dark:border-zinc-800">
                <flux:heading size="lg" class="font-bold">Recent Fee Invoices</flux:heading>
                <flux:button href="{{ route('feemanagement.index') }}" wire:navigate.hover variant="ghost"
                    size="sm" class="text-xs font-semibold">View All</flux:button>
            </div>
            <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($stats['recent_fees'] as $fee)
                    @php
                        $name = $fee->student?->name ?? 'Unknown';
                        $initials = collect(explode(' ', $name))
                            ->map(fn($w) => strtoupper(substr($w, 0, 1)))
                            ->take(2)
                            ->join('');
                    @endphp
                    <div class="flex items-center justify-between gap-3 py-2.5 hover:bg-zinc-50/50 dark:hover:bg-zinc-800/20 px-1 rounded-lg transition-colors">
                        <div class="flex items-center gap-2.5 min-w-0 flex-1">
                            <div class="flex size-8 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-[11px] font-bold text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-400 border border-indigo-100 dark:border-indigo-800">
                                {{ $initials }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="font-semibold text-xs sm:text-sm text-zinc-900 dark:text-zinc-100 truncate">
                                    {{ $name }}
                                </div>
                                <div class="text-[11px] text-zinc-500 dark:text-zinc-400 truncate">
                                    {{ $fee->title }}
                                </div>
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
</div>
