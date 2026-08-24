            {{-- KPI tiles --}}
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-6">
                
                <x-kpi-card title="Institutions" icon="building-office-2" color="indigo" :count="$stats['institutions_count']" href="{{ route('institution.index') }}">
                    <div class="flex items-center flex-wrap gap-1">
                        <span class="px-1.5 py-0.5 rounded-md bg-zinc-100 dark:bg-zinc-800 font-medium">{{ $stats['active_institutions_count'] }} active</span>
                        @if (end($stats['institution_growth']))
                            <span class="text-emerald-600 dark:text-emerald-400 font-medium">&uarr; {{ end($stats['institution_growth']) }} /mo</span>
                        @endif
                    </div>
                </x-kpi-card>

                <x-kpi-card title="Students" icon="user-group" color="sky" :count="$stats['students_count']" href="{{ route('student.index') }}">
                    <div class="flex items-center flex-wrap gap-1">
                        <span class="px-1.5 py-0.5 rounded-md bg-zinc-100 dark:bg-zinc-800 font-medium">{{ $stats['active_students_count'] }} active</span>
                        @if (end($stats['student_growth']))
                            <span class="text-emerald-600 dark:text-emerald-400 font-medium">&uarr; {{ end($stats['student_growth']) }} /mo</span>
                        @endif
                    </div>
                </x-kpi-card>

                <x-kpi-card title="Collection" icon="banknotes" color="emerald" count="{{ $stats['fee_collection_rate'] }}%" href="{{ route('feemanagement.index') }}" subtext="KES {{ $currency($stats['fees_outstanding']) }} outst." />

                <x-kpi-card title="Attendance" icon="clock" color="violet" :count="$stats['attendance_today_count']" href="{{ route('attendance.index') }}" subtext="check-ins today" />

                <x-kpi-card title="Approvals" icon="clipboard-document-check" color="amber" :count="$stats['pending_approvals_count']" href="{{ route('institution.index') }}" subtext="waiting review" />

                <x-kpi-card title="Devices" icon="device-phone-mobile" color="rose" :count="$stats['devices_online_count']" countSuffix="/{{ $stats['devices_count'] }}" href="{{ route('devices.index') }}" subtext="online" />
            </div>

            {{-- Trends --}}
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <flux:card>
                    <flux:heading size="lg" class="mb-1">Platform Growth</flux:heading>
                    <flux:text class="mb-4 text-sm text-zinc-500">New institutions and students across all campuses, last 6 months</flux:text>
                    <div class="mb-3 flex items-center gap-4 text-xs text-zinc-500 dark:text-zinc-400">
                        <span class="flex items-center gap-1.5"><span class="size-2 rounded-full bg-indigo-500"></span> Institutions</span>
                        <span class="flex items-center gap-1.5"><span class="size-2 rounded-full bg-emerald-500"></span> Students</span>
                    </div>
                    <div class="grid grid-cols-2 gap-6">
                        @php
                            $instSeries = [['name' => 'Institutions', 'data' => array_values($stats['institution_growth'])]];
                            $studentSeries = [['name' => 'Students', 'data' => array_values($stats['student_growth'])]];
                            $labels = array_keys($stats['institution_growth']);
                        @endphp
                        <x-charts.apex type="area" :series="$instSeries" :labels="$labels" :colors="['#4f46e5']" :height="140" />
                        <x-charts.apex type="area" :series="$studentSeries" :labels="$labels" :colors="['#10b981']" :height="140" />
                    </div>
                </flux:card>

                <flux:card>
                    <div class="flex items-center justify-between mb-1">
                        <flux:heading size="lg">Global Fee Collection Velocity</flux:heading>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold font-mono bg-emerald-50 text-emerald-700 border border-emerald-200/60">
                            {{ $stats['fee_collection_rate'] }}% Settled
                        </span>
                    </div>
                    <flux:text class="mb-4 text-sm text-zinc-500">System-wide billed vs. collected revenue over the last 6 months</flux:text>
                    @php
                        $feeLabels = array_keys($stats['fee_collection_trend']);
                        $billedData = [];
                        $collectedData = [];
                        foreach($stats['fee_collection_trend'] as $bucket) {
                            $billedData[] = $bucket['billed'] ?? 0;
                            $collectedData[] = $bucket['collected'] ?? 0;
                        }
                        $feeSeries = [
                            ['name' => 'Billed Invoiced', 'data' => $billedData],
                            ['name' => 'Collected Cash', 'data' => $collectedData]
                        ];
                    @endphp
                    <x-charts.apex type="area" :series="$feeSeries" :labels="$feeLabels" :colors="['#0ea5e9', '#10b981']" currencyPrefix="KES " :height="220" />
                </flux:card>
            </div>

            {{-- Distributions --}}
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <flux:card>
                    <flux:heading size="lg" class="mb-4">Institutions by Type</flux:heading>
                    <x-charts.distribution-bars :data="collect($stats['institutions_by_type'])
                        ->map(fn ($count, $type) => ['label' => $type, 'value' => $count, 'color' => 'indigo'])
                        ->values()
                        ->all()" />
                </flux:card>

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

                <flux:card>
                    <flux:heading size="lg" class="mb-4">Users by Role</flux:heading>
                    @php
                        $roleColors = ['indigo', 'sky', 'violet', 'amber', 'rose', 'emerald'];
                    @endphp
                    <x-charts.distribution-bars :data="collect($stats['users_by_role'])
                        ->map(fn ($count, $role) => ['label' => $role, 'value' => $count])
                        ->values()
                        ->map(fn ($item, $i) => $item + ['color' => $roleColors[$i % count($roleColors)]])
                        ->all()" />
                </flux:card>
            </div>

            {{-- Needs attention --}}
            @if ($stats['pending_approvals_count'] > 0)
                <flux:card>
                    <div class="mb-4 flex items-center justify-between">
                        <div>
                            <flux:heading size="lg">Needs Your Attention</flux:heading>
                            <flux:text class="text-sm text-zinc-500">Institutions waiting to be reviewed, oldest first</flux:text>
                        </div>
                        <flux:badge color="amber">{{ $stats['pending_approvals_count'] }} pending</flux:badge>
                    </div>
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>Name</flux:table.column>
                            <flux:table.column>Owner</flux:table.column>
                            <flux:table.column>Type</flux:table.column>
                            <flux:table.column>Submitted</flux:table.column>
                            <flux:table.column>Actions</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($stats['pending_institutions'] as $institution)
                                <flux:table.row>
                                    <flux:table.cell>{{ $institution->name }}</flux:table.cell>
                                    <flux:table.cell>{{ $institution->owner?->name }}</flux:table.cell>
                                    <flux:table.cell>{{ $institution->type }}</flux:table.cell>
                                    <flux:table.cell>{{ $institution->created_at?->diffForHumans() }}</flux:table.cell>
                                    <flux:table.cell>
                                        <flux:button href="{{ route('institution.show', $institution->id) }}" icon="eye"
                                            variant="primary" color="emerald">review</flux:button>
                                        <form action="{{ route('institution.approve', $institution->id) }}" method="POST" class="inline">
                                            @csrf
                                            <flux:button type="submit" icon="check" variant="primary" color="blue">approve</flux:button>
                                        </form>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                </flux:card>
            @endif

            {{-- Recent activity --}}
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <flux:card class="lg:col-span-2">
                    <flux:heading size="lg" class="mb-4">Recent Institutions</flux:heading>
                    <flux:table>
                        <flux:table.columns>
                            <x-sortable-column column="name">Name</x-sortable-column>
                            <flux:table.column>Owner</flux:table.column>
                            <flux:table.column>Status</flux:table.column>
                            <x-sortable-column column="created_at">Created</x-sortable-column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @forelse ($stats['recent_institutions'] as $institution)
                                <flux:table.row>
                                    <flux:table.cell>{{ $institution->name }}</flux:table.cell>
                                    <flux:table.cell>{{ $institution->owner?->name }}</flux:table.cell>
                                    <flux:table.cell>
                                        <flux:badge :color="$institution->is_active ? 'emerald' : 'zinc'">
                                            {{ ucfirst($institution->status ?? 'unknown') }}
                                        </flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell>{{ $institution->created_at?->format('d M Y') }}</flux:table.cell>
                                </flux:table.row>
                            @empty
                                <flux:table.row>
                                    <flux:table.cell colspan="4" class="text-center text-zinc-500">No institutions yet.
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforelse
                        </flux:table.rows>
                    </flux:table>
                </flux:card>

                <flux:card>
                    <div class="mb-4 flex items-center justify-between">
                        <flux:heading size="lg">Recent Messages</flux:heading>
                        <flux:button href="{{ route('messages.index') }}" size="sm" variant="ghost">View all</flux:button>
                    </div>
                    <div class="space-y-3">
                        @forelse ($stats['recent_contact_messages'] as $message)
                            <a href="{{ route('messages.show', $message) }}"
                                class="block rounded-lg border border-zinc-100 p-3 text-sm hover:bg-zinc-50 dark:border-white/5 dark:hover:bg-white/5">
                                <div class="flex items-center justify-between">
                                    <span class="font-medium text-zinc-900 dark:text-white">{{ $message->name }}</span>
                                    <span class="text-xs text-zinc-400">{{ $message->created_at->diffForHumans() }}</span>
                                </div>
                                <p class="mt-1 truncate text-zinc-500 dark:text-zinc-400">{{ $message->message }}</p>
                            </a>
                        @empty
                            <flux:text class="text-zinc-500">No messages yet.</flux:text>
                        @endforelse
                    </div>
                </flux:card>
            </div>

            {{-- ===================== DIRECTOR / ACCOUNTANT: INSTITUTION-SCOPED ===================== --}}
