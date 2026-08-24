            <div class="mb-4 mt-6 flex items-center justify-between border-b border-zinc-200 pb-2 dark:border-zinc-700">
                <flux:heading size="xl" class="font-bold tracking-tight text-zinc-900 dark:text-white">{{ $stats['institution']?->name }}</flux:heading>
            </div>

            {{-- KPI tiles --}}
            @if (!isset($stats['students_count']))
                {{-- Accountant Financial Metrics Strip --}}
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <x-kpi-card title="Total Invoiced" icon="document-text" color="sky" count="KES {{ $currency($stats['fees_billed']) }}" href="{{ route('feemanagement.index') }}" subtext="gross receivables billed" />
                    <x-kpi-card title="Collected Revenue" icon="check-badge" color="emerald" count="KES {{ $currency($stats['fees_collected']) }}" href="{{ route('feemanagement.index', ['status' => 'paid']) }}" subtext="{{ $stats['fee_collection_rate'] }}% collection velocity" />
                    <x-kpi-card title="Outstanding Arrears" icon="banknotes" color="rose" count="KES {{ $currency($stats['fees_outstanding']) }}" href="{{ route('feemanagement.index', ['status' => 'overdue']) }}">
                        <span class="text-xs font-semibold text-rose-600">{{ $stats['overdue_fees_count'] }} overdue defaulters</span>
                    </x-kpi-card>
                    <x-kpi-card title="Overdue Accounts" icon="exclamation-triangle" color="amber" :count="$stats['overdue_fees_count']" href="{{ route('feemanagement.index', ['status' => 'overdue']) }}" subtext="require collection reminders" />
                </div>
            @else
                {{-- Director All-Round KPI Strip --}}
                <div class="grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-6">
                    <x-kpi-card title="Students" icon="user-group" color="sky" :count="$stats['students_count']" href="{{ route('student.index') }}">
                        <div class="flex items-center flex-wrap gap-1">
                            <span class="px-1.5 py-0.5 rounded-md bg-zinc-100 dark:bg-zinc-800 font-medium">{{ $stats['active_students_count'] }} active</span>
                            @if (end($stats['student_growth']))
                                <span class="text-emerald-600 dark:text-emerald-400 font-medium">&uarr; {{ end($stats['student_growth']) }} /mo</span>
                            @endif
                        </div>
                    </x-kpi-card>

                    <x-kpi-card title="Parents" icon="users" color="indigo" :count="$stats['parents_count']" href="{{ route('parent.index') }}" subtext="linked to a student" />

                    <x-kpi-card title="Collection" icon="banknotes" color="emerald" count="{{ $stats['fee_collection_rate'] }}%" href="{{ route('feemanagement.index') }}" subtext="{{ $currency($stats['fees_outstanding']) }} outst." />

                    <x-kpi-card title="Overdue" icon="exclamation-triangle" color="red" :count="$stats['overdue_fees_count']" href="{{ route('feemanagement.index') }}" subtext="need a reminder" />

                    <x-kpi-card title="Attendance" icon="clock" color="violet" :count="$stats['attendance_today_count']" href="{{ route('attendance.index') }}" subtext="check-ins today" />

                    <x-kpi-card title="Devices" icon="device-phone-mobile" color="rose" :count="$stats['devices_online_count']" countSuffix="/{{ $stats['devices_count'] }}" href="{{ route('devices.index') }}" subtext="online" />
                </div>
            @endif

            {{-- Trends --}}
            <div class="grid grid-cols-1 gap-4 {{ isset($stats['student_growth']) ? 'lg:grid-cols-2' : '' }}">
                @if (isset($stats['student_growth']))
                    <flux:card>
                        <flux:heading size="lg" class="mb-1">Student Enrollment</flux:heading>
                        <flux:text class="mb-4 text-sm text-zinc-500">New students, last 6 months</flux:text>
                        @php
                            $studentSeries = [['name' => 'Students', 'data' => array_values($stats['student_growth'])]];
                            $labels = array_keys($stats['student_growth']);
                        @endphp
                        <x-charts.apex type="area" :series="$studentSeries" :labels="$labels" :colors="['#10b981']" :height="220" />
                    </flux:card>
                @endif

                <flux:card>
                    <div class="flex items-center justify-between mb-1">
                        <flux:heading size="lg">Fee Collection Velocity Trend</flux:heading>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold font-mono bg-emerald-50 text-emerald-700 border border-emerald-200/60">
                            {{ $stats['fee_collection_rate'] }}% Settled
                        </span>
                    </div>
                    <flux:text class="mb-4 text-sm text-zinc-500">Historical comparison: Total Invoiced (Billed) vs. Verified Collections (Paid)</flux:text>
                    @php
                        $feeLabels = array_keys($stats['fee_collection_trend']);
                        $billedData = [];
                        $collectedData = [];
                        foreach($stats['fee_collection_trend'] as $bucket) {
                            $billedData[] = $bucket['billed'] ?? 0;
                            $collectedData[] = $bucket['collected'] ?? 0;
                        }
                        $feeSeries = [
                            ['name' => 'Billed Receivables', 'data' => $billedData],
                            ['name' => 'Collected Revenue', 'data' => $collectedData]
                        ];
                    @endphp
                    <x-charts.apex type="area" :series="$feeSeries" :labels="$feeLabels" :colors="['#0ea5e9', '#10b981']" currencyPrefix="KES " :height="240" />
                </flux:card>
            </div>

            {{-- Distributions --}}
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                @if (isset($stats['enrollment_by_status']))
                    <flux:card>
                        <flux:heading size="lg" class="mb-4">Enrollment Status</flux:heading>
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
                            ->map(fn ($count, $status) => ['label' => ucfirst($status), 'value' => $count, 'color' => $enrollColors[$status] ?? 'indigo'])
                            ->values()
                            ->all()" />
                    </flux:card>
                @endif

                <flux:card>
                    <flux:heading size="lg" class="mb-4">Fee Status</flux:heading>
                    @php
                        $feeStatusMeta = [
                            'paid' => ['label' => 'Paid', 'color' => 'emerald'],
                            'partial' => ['label' => 'Partial', 'color' => 'amber'],
                            'overdue' => ['label' => 'Overdue', 'color' => 'red'],
                            'pending' => ['label' => 'Pending', 'color' => 'zinc'],
                        ];
                    @endphp
                    <x-charts.distribution-bars :data="collect($feeStatusMeta)
                        ->map(fn ($meta, $key) => ['label' => $meta['label'], 'value' => $stats['fees_by_status'][$key] ?? 0, 'color' => $meta['color']])
                        ->values()
                        ->all()" />
                </flux:card>
            </div>

            {{-- Recent activity --}}
            <div class="grid grid-cols-1 gap-4 {{ isset($stats['recent_students']) ? 'lg:grid-cols-2' : '' }}">
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

                @if (isset($stats['recent_students']))
                    <flux:card>
                        <flux:heading size="lg" class="mb-4">Recent Students</flux:heading>
                        <flux:table>
                            <flux:table.columns>
                                <flux:table.column>Name</flux:table.column>
                                <x-sortable-column column="admission_number">Admission No.</x-sortable-column>
                                <x-sortable-column column="enrollment_status">Status</x-sortable-column>
                            </flux:table.columns>
                            <flux:table.rows>
                                @forelse ($stats['recent_students'] as $studentDetails)
                                    <flux:table.row class="transition-colors hover:bg-zinc-50/50 dark:hover:bg-zinc-800/20">
                                        <flux:table.cell>
                                            <div class="flex items-center gap-3">
                                                @php
                                                    $name = $studentDetails->student?->name ?? 'Unknown';
                                                    $initials = collect(explode(' ', $name))->map(fn($w) => strtoupper(substr($w, 0, 1)))->take(2)->join('');
                                                @endphp
                                                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-50 text-xs font-semibold text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400 ring-1 ring-inset ring-emerald-500/20">
                                                    {{ $initials }}
                                                </div>
                                                <span class="font-medium">{{ $name }}</span>
                                            </div>
                                        </flux:table.cell>
                                        <flux:table.cell class="text-zinc-500">{{ $studentDetails->admission_number }}</flux:table.cell>
                                        <flux:table.cell>
                                            <flux:badge :color="$studentDetails->is_active ? 'emerald' : 'zinc'" class="font-semibold">
                                                {{ ucfirst($studentDetails->enrollment_status) }}
                                            </flux:badge>
                                        </flux:table.cell>
                                    </flux:table.row>
                                @empty
                                    <flux:table.row>
                                        <flux:table.cell colspan="3">
                                            <div class="flex flex-col items-center justify-center py-8">
                                                <div class="rounded-full bg-zinc-50 dark:bg-zinc-800 p-3 mb-3">
                                                    <flux:icon icon="users" class="size-6 text-zinc-400" />
                                                </div>
                                                <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">No students yet</p>
                                                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">New enrollments will appear here.</p>
                                            </div>
                                        </flux:table.cell>
                                    </flux:table.row>
                                @endforelse
                            </flux:table.rows>
                        </flux:table>
                    </flux:card>
                @endif
            </div>

            {{-- ===================== PARENT ===================== --}}
