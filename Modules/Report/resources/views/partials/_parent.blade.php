            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                {{-- Children --}}
                <x-kpi-card title="Children" icon="user-group" color="sky" :count="$stats['children_count']" href="{{ route('student.index') }}" subtext="enrolled" />

                {{-- Attendance --}}
                <x-kpi-card title="Attendance Today" icon="clock" color="violet" :count="$stats['attendance_today_count']" href="{{ route('attendance.index') }}" subtext="check-ins" />

                {{-- Fees Outstanding --}}
                <x-kpi-card title="Fees Outstanding" icon="exclamation-triangle" color="red" count="KES {{ $currency($stats['fees_outstanding']) }}" href="{{ route('feemanagement.index') }}" subtext="total balance" />
            </div>

            <flux:card>
                <flux:heading size="lg" class="mb-4">My Children</flux:heading>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Name</flux:table.column>
                        <x-sortable-column column="admission_number">Admission No.</x-sortable-column>
                        <flux:table.column>Institution</flux:table.column>
                        <flux:table.column>Status</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($stats['children'] as $child)
                            <flux:table.row>
                                <flux:table.cell>{{ $child->student?->name }}</flux:table.cell>
                                <flux:table.cell>{{ $child->admission_number }}</flux:table.cell>
                                <flux:table.cell>{{ $child->institution?->name }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge :color="$child->is_active ? 'emerald' : 'zinc'">
                                        {{ ucfirst($child->enrollment_status) }}
                                    </flux:badge>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="4" class="text-center text-zinc-500">No children linked to
                                    your account.</flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </flux:card>

            <flux:card>
                <flux:heading size="lg" class="mb-4">Recent Fees</flux:heading>
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
                                <flux:table.cell>
                                    <div class="flex items-center gap-3">
                                        @php
                                            $name = $fee->student?->name ?? 'Unknown';
                                            $initials = collect(explode(' ', $name))->map(fn($w) => strtoupper(substr($w, 0, 1)))->take(2)->join('');
                                        @endphp
                                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-50 text-xs font-semibold text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-400 ring-1 ring-inset ring-indigo-500/20">
                                            {{ $initials }}
                                        </div>
                                        <span class="font-medium">{{ $name }}</span>
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell class="text-zinc-600 dark:text-zinc-300">{{ $fee->title }}</flux:table.cell>
                                <flux:table.cell class="font-medium">{{ $currency($fee->amount) }}</flux:table.cell>
                                <flux:table.cell class="text-zinc-500">{{ $currency($fee->balance) }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge :color="match ($fee->status) {
                                        'paid' => 'emerald',
                                        'partial' => 'amber',
                                        'overdue' => 'red',
                                        default => 'zinc',
                                    }" class="font-semibold">{{ ucfirst($fee->status) }}</flux:badge>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="5">
                                    <div class="flex flex-col items-center justify-center py-8">
                                        <div class="rounded-full bg-zinc-50 dark:bg-zinc-800 p-3 mb-3">
                                            <flux:icon icon="banknotes" class="size-6 text-zinc-400" />
                                        </div>
                                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">No fee records</p>
                                        <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">There are no recent fees to display.</p>
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </flux:card>

            {{-- ===================== STUDENT ===================== --}}
