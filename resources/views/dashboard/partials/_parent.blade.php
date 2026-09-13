{{-- Parent KPI Tiles --}}
<div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
    <x-kpi-card title="Children" icon="user-group" color="sky" :count="$stats['children_count']" href="{{ route('student.index') }}"
        subtext="enrolled in school" />

    <x-kpi-card title="Attendance Today" icon="clock" color="violet" :count="$stats['attendance_today_count']"
        href="{{ route('attendance.index') }}" subtext="check-ins recorded" />

    <x-kpi-card title="Fees Outstanding" icon="exclamation-triangle" color="red"
        count="KES {{ $currency($stats['fees_outstanding']) }}" href="{{ route('feemanagement.index') }}"
        subtext="total pending balance" />
</div>

<div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
    {{-- My Children List --}}
    <flux:card class="hover:shadow-md transition-shadow">
        <flux:heading size="lg" class="mb-4 font-semibold">My Children</flux:heading>
        <div class="grid grid-cols-1 gap-3">
            @forelse ($stats['children'] as $child)
                <div
                    class="flex flex-wrap items-center justify-between gap-x-3 gap-y-2 rounded-xl border border-zinc-200 dark:border-zinc-800 p-3.5 bg-zinc-50/50 dark:bg-zinc-900/50 hover:bg-zinc-100/50 dark:hover:bg-zinc-800/50 transition-colors">
                    <div class="flex items-center gap-3 min-w-0">
                        @php
                            $name = $child->student?->name ?? 'Child';
                            $initials = collect(explode(' ', $name))
                                ->map(fn($w) => strtoupper(substr($w, 0, 1)))
                                ->take(2)
                                ->join('');
                        @endphp
                        <div
                            class="flex h-9 w-9 items-center justify-center rounded-full bg-sky-50 text-xs font-bold text-sky-600 dark:bg-sky-500/20 dark:text-sky-400 ring-1 ring-inset ring-sky-500/20">
                            {{ $initials }}
                        </div>
                        <div>
                            <div class="font-medium text-zinc-900 dark:text-white text-sm">{{ $name }}</div>
                            <div class="text-xs text-zinc-500">{{ $child->institution?->name }} · Adm:
                                {{ $child->admission_number }}</div>
                        </div>
                    </div>
                    <flux:badge size="sm" :color="$child->is_active ? 'emerald' : 'zinc'" class="font-semibold">
                        {{ ucfirst($child->enrollment_status) }}
                    </flux:badge>
                </div>
            @empty
                <div class="flex flex-col items-center justify-center py-8">
                    <div class="rounded-full bg-zinc-50 dark:bg-zinc-800 p-3 mb-2">
                        <flux:icon icon="user-group" class="size-6 text-zinc-400" />
                    </div>
                    <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">No children linked</p>
                    <p class="text-xs text-zinc-500 mt-1">Ask the school administrator to link your account to your
                        children.</p>
                </div>
            @endforelse
        </div>
    </flux:card>

    {{-- Recent Fees Table --}}
    <flux:card class="hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between mb-4">
            <flux:heading size="lg" class="font-semibold">Recent Fees</flux:heading>
            @can('view feemanagement')
                <flux:button href="{{ route('feemanagement.index') }}" wire:navigate.hover variant="ghost" size="sm"
                    icon-trailing="chevron-right" class="text-xs">
                    View All
                </flux:button>
            @endcan
        </div>
        <flux:table class="p-2">
            <flux:table.columns>
                <flux:table.column>Child</flux:table.column>
                <flux:table.column>Title</flux:table.column>
                <flux:table.column>Amount</flux:table.column>
                <flux:table.column>Status</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($stats['recent_fees'] as $fee)
                    <flux:table.row class="transition-colors hover:bg-zinc-50/50 dark:hover:bg-zinc-800/20">
                        <flux:table.cell>
                            <span class="font-medium text-xs">{{ $fee->student?->name ?? 'Unknown' }}</span>
                        </flux:table.cell>
                        <flux:table.cell class="text-zinc-600 dark:text-zinc-300 text-xs">{{ $fee->title }}
                        </flux:table.cell>
                        <flux:table.cell class="font-medium text-xs">{{ $currency($fee->amount) }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm"
                                :color="match ($fee->status) {
                                                                'paid' => 'emerald',
                                                                'partial' => 'amber',
                                                                'overdue' => 'red',
                                                                default => 'zinc',
                                                            }"
                                class="font-semibold">{{ ucfirst($fee->status) }}</flux:badge>
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
                                <p class="text-[11px] text-zinc-500 dark:text-zinc-400">There are no fee statements to
                                    show.</p>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
