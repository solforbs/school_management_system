{{-- Parent Analytics Partial --}}
<div>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-kpi-card title="Children" icon="user-group" color="sky" :count="$stats['children_count']" subtext="linked to your account" />
        <x-kpi-card title="Attendance Today" icon="clock" color="emerald" :count="$stats['attendance_today_count']" href="{{ route('attendance.index') }}" subtext="biometric check-ins" />
        <x-kpi-card title="Fees Outstanding" icon="banknotes" color="rose" count="KES {{ $currency($stats['fees_outstanding']) }}" href="{{ route('feemanagement.index') }}" subtext="pending balance" />
    </div>

    <div class="grid grid-cols-1 gap-5 mt-6 lg:grid-cols-2">
        <flux:card class="space-y-3">
            <flux:heading size="lg" class="font-bold">My Children</flux:heading>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Name</flux:table.column>
                    <x-sortable-column column="admission_number">Admission No.</x-sortable-column>
                    <flux:table.column>Institution</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($stats['children'] as $child)
                        <flux:table.row class="transition-colors hover:bg-zinc-50/50 dark:hover:bg-zinc-800/20">
                            <flux:table.cell class="font-semibold text-xs sm:text-sm">{{ $child->student?->name }}</flux:table.cell>
                            <flux:table.cell class="text-xs font-mono text-zinc-500">{{ $child->admission_number }}</flux:table.cell>
                            <flux:table.cell class="text-xs">{{ $child->institution?->name }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$child->is_active ? 'emerald' : 'zinc'" class="font-semibold text-[11px]">
                                    {{ ucfirst($child->enrollment_status) }}
                                </flux:badge>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="4" class="text-center py-6 text-zinc-500 text-xs">No children linked to your account.</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>

        <flux:card class="space-y-3">
            <div class="flex items-center justify-between pb-2 border-b border-zinc-100 dark:border-zinc-800">
                <flux:heading size="lg" class="font-bold">Recent Fee Invoices</flux:heading>
                <flux:button href="{{ route('feemanagement.index') }}" wire:navigate.hover variant="ghost" size="sm" class="text-xs font-semibold">View All</flux:button>
            </div>
            <flux:table>
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
                            <flux:table.cell class="font-semibold text-xs">{{ $fee->student?->name }}</flux:table.cell>
                            <flux:table.cell class="text-xs text-zinc-600 dark:text-zinc-300">{{ $fee->title }}</flux:table.cell>
                            <flux:table.cell class="font-bold font-mono text-xs">KES {{ $currency($fee->amount) }}</flux:table.cell>
                            <flux:table.cell class="text-zinc-500 font-mono text-xs">KES {{ $currency($fee->balance) }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="match ($fee->status) {
                                    'paid' => 'emerald',
                                    'partial' => 'amber',
                                    'overdue' => 'red',
                                    default => 'zinc',
                                }" class="font-semibold text-[11px]">{{ ucfirst($fee->status) }}</flux:badge>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5" class="text-center py-6 text-zinc-500 text-xs">No fee records found.</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    </div>
</div>
