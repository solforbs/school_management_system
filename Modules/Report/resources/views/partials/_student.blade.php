            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                {{-- Attendance Today --}}
                <x-kpi-card title="Attendance Today" icon="clock" color="violet" :count="$stats['attendance_today_count']" href="{{ route('attendance.index') }}" subtext="check-ins" />

                {{-- Attendance Month --}}
                <x-kpi-card title="This Month" icon="calendar-days" color="indigo" :count="$stats['attendance_this_month_count']" href="{{ route('attendance.index') }}" subtext="days attended" />

                {{-- Fees Outstanding --}}
                <x-kpi-card title="Fees Outstanding" icon="exclamation-triangle" color="red" count="KES {{ $currency($stats['fees_outstanding']) }}" href="{{ route('feemanagement.index') }}" subtext="total balance" />
            </div>

            <flux:card>
                <flux:heading size="lg" class="mb-4">My Fees</flux:heading>
                <flux:table>
                    <flux:table.columns>
                        <x-sortable-column column="title">Title</x-sortable-column>
                        <x-sortable-column column="amount">Amount</x-sortable-column>
                        <flux:table.column>Balance</flux:table.column>
                        <flux:table.column>Status</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($stats['recent_fees'] as $fee)
                            <flux:table.row>
                                <flux:table.cell>{{ $fee->title }}</flux:table.cell>
                                <flux:table.cell>{{ $currency($fee->amount) }}</flux:table.cell>
                                <flux:table.cell>{{ $currency($fee->balance) }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge :color="match ($fee->status) {
                                        'paid' => 'emerald',
                                        'partial' => 'amber',
                                        'overdue' => 'red',
                                        default => 'zinc',
                                    }">{{ ucfirst($fee->status) }}</flux:badge>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="4" class="text-center text-zinc-500">No fee records yet.
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </flux:card>

            {{-- ===================== TEACHER ===================== --}}
