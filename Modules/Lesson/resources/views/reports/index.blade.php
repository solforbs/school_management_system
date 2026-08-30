@php
    $totalReportsCount = count($reports);
    $totalLessonsAudited = $reports->sum('total_lessons');
    $totalAttended = $reports->sum('attended_count');
    $avgAttendanceRate = $totalLessonsAudited > 0 ? round(($totalAttended / $totalLessonsAudited) * 100, 1) : 0;
@endphp

<x-layouts::app :title="__('Lesson Attendance Reports')">
    <div class="flex flex-col gap-8 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">
        
        {{-- Header with Clear Back Navigation --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1.5">
                    <a href="{{ route('lesson.index') }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-zinc-500 hover:text-zinc-900 transition">
                        <flux:icon icon="arrow-left" class="size-3.5" />
                        <span>Back to Lesson Attendance</span>
                    </a>
                    <span class="text-xs text-zinc-300">&bull;</span>
                    <span class="text-xs text-zinc-500 font-medium">Reports & Analytics</span>
                </div>
                <flux:heading size="xl" class="font-black tracking-tight text-zinc-900">Lesson Reports</flux:heading>
                <flux:text class="text-zinc-500 text-sm mt-0.5">
                    Generate, export, and review curriculum delivery and teacher attendance logs.
                </flux:text>
            </div>
            
            <div class="flex items-center gap-3">
                <flux:button href="{{ route('lesson.index') }}" icon="arrow-left" variant="subtle" class="font-semibold shadow-sm">
                    Mark Attendance
                </flux:button>
            </div>
        </div>

        {{-- Flash Messages --}}
        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50/80 p-4 flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-3">
                    <flux:icon icon="check-circle" class="size-5 text-emerald-600 shrink-0" />
                    <span class="text-sm font-semibold text-emerald-900">{{ session('success') }}</span>
                </div>
            </div>
        @endif

        {{-- Class Selection Filter --}}
        <div class="bg-white rounded-2xl p-4 border border-zinc-200/80 shadow-sm flex flex-col md:flex-row items-center justify-between gap-4">
            <form action="{{ route('lesson.reports.index') }}" method="GET" class="w-full sm:w-80">
                <flux:select name="class_id" onchange="this.form.submit()" placeholder="Select Class to View Reports" class="w-full">
                    @foreach ($classes as $class)
                        <flux:select.option value="{{ $class->id }}" :selected="$selectedClass?->id === $class->id">
                            {{ $class->name }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            </form>
            
            @if ($selectedClass)
                <div class="text-xs text-zinc-500 font-medium self-start md:self-center">
                    Managing archive for <strong class="text-zinc-900 font-bold">{{ $selectedClass->name }}</strong>
                </div>
            @endif
        </div>

        @if (! $selectedClass)
            <div class="bg-white rounded-3xl border border-zinc-200 p-16 text-center shadow-sm">
                <div class="size-14 rounded-2xl bg-zinc-100 flex items-center justify-center text-zinc-500 mx-auto mb-3">
                    <flux:icon icon="rectangle-group" class="size-7" />
                </div>
                <h3 class="text-base font-bold text-zinc-900">Select a Class Stream</h3>
                <p class="text-zinc-500 text-xs max-w-sm mx-auto mt-1">
                    Choose a class from the dropdown above to view its lesson attendance reports.
                </p>
            </div>
        @else
            {{-- Top Metrics Strip --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                {{-- Metric 1: Archived Reports --}}
                <div class="bg-white rounded-2xl p-5 border border-zinc-200/80 shadow-sm hover:border-zinc-300 transition duration-150">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-semibold uppercase tracking-wider text-zinc-400">Reports Archive</span>
                        <div class="size-9 rounded-xl bg-zinc-100 flex items-center justify-center text-zinc-700">
                            <flux:icon icon="document-text" class="size-4" />
                        </div>
                    </div>
                    <div class="text-2xl font-black text-zinc-900 tracking-tight">
                        {{ $totalReportsCount }} Reports
                    </div>
                    <div class="text-xs text-zinc-500 mt-2 font-medium">
                        {{ $selectedClass->name }} verified records
                    </div>
                </div>

                {{-- Metric 2: Audited Periods --}}
                <div class="bg-white rounded-2xl p-5 border border-zinc-200/80 shadow-sm hover:border-zinc-300 transition duration-150">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-semibold uppercase tracking-wider text-zinc-400">Audited Periods</span>
                        <div class="size-9 rounded-xl bg-zinc-100 flex items-center justify-center text-zinc-700">
                            <flux:icon icon="clock" class="size-4" />
                        </div>
                    </div>
                    <div class="text-2xl font-black text-zinc-900 tracking-tight">
                        {{ number_format($totalLessonsAudited) }} Lessons
                    </div>
                    <div class="text-xs text-zinc-500 mt-2 font-medium">
                        {{ number_format($totalAttended) }} successfully conducted
                    </div>
                </div>

                {{-- Metric 3: Delivery Rate --}}
                <div class="bg-white rounded-2xl p-5 border border-zinc-200/80 shadow-sm hover:border-zinc-300 transition duration-150">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-semibold uppercase tracking-wider text-emerald-700">Delivery Rate</span>
                        <div class="size-9 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-600">
                            <flux:icon icon="check-badge" class="size-4" />
                        </div>
                    </div>
                    <div class="text-2xl font-black text-emerald-600 tracking-tight">
                        {{ $avgAttendanceRate }}%
                    </div>
                    <div class="text-xs text-zinc-500 mt-2 font-medium">
                        Average curriculum compliance
                    </div>
                </div>
            </div>

            {{-- Generate New Report Card --}}
            @can('export lesson')
                <div class="bg-white rounded-2xl p-6 border border-zinc-200/80 shadow-sm">
                    <div class="mb-4">
                        <h3 class="text-base font-bold text-zinc-900">Generate New Attendance Report</h3>
                        <p class="text-xs text-zinc-500 mt-0.5">Create a structured daily or weekly audit report for {{ $selectedClass->name }}.</p>
                    </div>
                    
                    <form action="{{ route('lesson.reports.generate') }}" method="POST" class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
                        @csrf
                        <input type="hidden" name="class_id" value="{{ $selectedClass->id }}">

                        <div>
                            <flux:select name="type" label="Report Frequency">
                                <flux:select.option value="daily">Daily Report (Single Day)</flux:select.option>
                                <flux:select.option value="weekly" selected>Weekly Summary (5-Day Week)</flux:select.option>
                            </flux:select>
                        </div>

                        <div>
                            <flux:input type="date" name="date" label="Target Date (or week date)" value="{{ now()->format('Y-m-d') }}" />
                        </div>

                        <div>
                            <flux:button type="submit" variant="primary" class="w-full font-semibold shadow-sm">
                                Generate Report
                            </flux:button>
                        </div>
                    </form>
                </div>
            @endcan

            {{-- Generated Reports Archive Table --}}
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-bold text-zinc-900">Archived Reports</h3>
                        <p class="text-xs text-zinc-500 mt-0.5">Downloadable PDF reports and audit summaries for {{ $selectedClass->name }}.</p>
                    </div>
                </div>

                <div class="bg-white shadow-sm border border-zinc-200/80 rounded-2xl overflow-hidden flex flex-col">
                    @if ($reports->isEmpty())
                        <div class="flex flex-col items-center justify-center py-16">
                            <flux:icon icon="document-chart-bar" class="size-12 text-zinc-300 mb-3" />
                            <h4 class="text-base font-bold text-zinc-900">No Reports Generated Yet</h4>
                            <p class="text-zinc-500 text-xs max-w-sm text-center mt-1">
                                Use the generator above to create your first attendance report for this class.
                            </p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-zinc-50 text-zinc-500 border-b border-zinc-200/80 text-xs font-bold uppercase tracking-wider">
                                    <tr>
                                        <th class="py-3.5 px-5">Report Type</th>
                                        <th class="py-3.5 px-5">Coverage Period</th>
                                        <th class="py-3.5 px-5">Total Lessons</th>
                                        <th class="py-3.5 px-5">Attended</th>
                                        <th class="py-3.5 px-5">Not Attended</th>
                                        <th class="py-3.5 px-5">Recovered</th>
                                        <th class="py-3.5 px-5 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-100">
                                    @foreach ($reports as $report)
                                        @php
                                            $compliancePct = $report->total_lessons > 0 ? round(($report->attended_count / $report->total_lessons) * 100) : 0;
                                        @endphp
                                        <tr class="hover:bg-zinc-50/60 transition-colors">
                                            {{-- Type --}}
                                            <td class="py-4 px-5">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold uppercase font-mono
                                                    {{ $report->isWeekly() ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' : 'bg-zinc-100 text-zinc-700 border border-zinc-200' }}">
                                                    {{ ucfirst($report->type) }}
                                                </span>
                                            </td>

                                            {{-- Period --}}
                                            <td class="py-4 px-5 font-semibold text-zinc-900 text-xs">
                                                {{ $report->period_start->format('d M Y') }}
                                                @if ($report->isWeekly())
                                                    &ndash; {{ $report->period_end->format('d M Y') }}
                                                @endif
                                            </td>

                                            {{-- Total Lessons --}}
                                            <td class="py-4 px-5 text-xs text-zinc-700 font-medium">
                                                {{ $report->total_lessons }} Periods
                                            </td>

                                            {{-- Attended --}}
                                            <td class="py-4 px-5">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                    {{ $report->attended_count }} ({{ $compliancePct }}%)
                                                </span>
                                            </td>

                                            {{-- Not Attended --}}
                                            <td class="py-4 px-5">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold {{ $report->not_attended_count > 0 ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-zinc-100 text-zinc-500' }}">
                                                    {{ $report->not_attended_count }}
                                                </span>
                                            </td>

                                            {{-- Recovered --}}
                                            <td class="py-4 px-5">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold {{ $report->recovered_count > 0 ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-zinc-100 text-zinc-500' }}">
                                                    {{ $report->recovered_count }}
                                                </span>
                                            </td>

                                            {{-- Actions --}}
                                            <td class="py-4 px-5 text-right">
                                                <div class="flex items-center justify-end gap-1.5">
                                                    <flux:button href="{{ route('lesson.reports.show', $report->id) }}" size="sm" icon="eye" variant="subtle" tooltip="View Breakdown" />
                                                    <flux:button href="{{ route('lesson.reports.download', $report->id) }}" size="sm" icon="arrow-down-tray" variant="subtle" tooltip="Download PDF" />
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        @endif

    </div>
</x-layouts::app>