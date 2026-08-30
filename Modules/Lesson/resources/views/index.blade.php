@php
    $totalPeriods = count($rows);
    $initialAttended = $rows->filter(fn($r) => ($r['lesson']?->status ?? '') === 'attended')->count();
    
    // Calculate 10-day historical trend for selected class
    $trendHistory = collect();
    $svgPath = '';
    $svgArea = '';
    $svgPoints = [];
    
    if ($selectedClass) {
        $trendHistory = Modules\Lesson\Models\Lesson::where('class_id', $selectedClass->id)
            ->selectRaw('lesson_date, count(*) as total, sum(case when status = "attended" then 1 else 0 end) as attended')
            ->groupBy('lesson_date')
            ->orderBy('lesson_date', 'asc')
            ->limit(10)
            ->get()
            ->map(function($h) {
                $rate = $h->total > 0 ? round(($h->attended / $h->total) * 100) : 0;
                return [
                    'date_label' => $h->lesson_date->format('d M'),
                    'day_name' => $h->lesson_date->format('D'),
                    'date_full' => $h->lesson_date->format('Y-m-d'),
                    'total' => (int) $h->total,
                    'attended' => (int) $h->attended,
                    'rate' => (int) $rate,
                ];
            });

        if ($trendHistory->isNotEmpty()) {
            $width = 900;
            $height = 140;
            $paddingX = 45;
            $paddingTop = 15;
            $paddingBottom = 35;

            $plotWidth = $width - ($paddingX * 2);
            $plotHeight = $height - ($paddingTop + $paddingBottom);
            $count = count($trendHistory);

            foreach ($trendHistory as $i => $d) {
                $x = $paddingX + ($i * ($plotWidth / max(1, $count - 1)));
                $normalizedRate = max(40, min(100, $d['rate']));
                $y = $paddingTop + $plotHeight - ((($normalizedRate - 40) / 60) * $plotHeight);
                $svgPoints[] = [
                    'x' => round($x, 1),
                    'y' => round($y, 1),
                    'rate' => $d['rate'],
                    'date_label' => $d['date_label'],
                    'day_name' => $d['day_name'],
                    'date_full' => $d['date_full'],
                    'attended' => $d['attended'],
                    'total' => $d['total'],
                ];
            }

            // Generate smooth bezier curve path
            $svgPath = "M " . $svgPoints[0]['x'] . " " . $svgPoints[0]['y'];
            for ($i = 0; $i < count($svgPoints) - 1; $i++) {
                $p0 = $svgPoints[$i];
                $p1 = $svgPoints[$i + 1];
                $cx = round(($p0['x'] + $p1['x']) / 2, 1);
                $svgPath .= " C " . $cx . " " . $p0['y'] . ", " . $cx . " " . $p1['y'] . ", " . $p1['x'] . " " . $p1['y'];
            }

            $svgArea = $svgPath . " L " . end($svgPoints)['x'] . " " . ($paddingTop + $plotHeight) . " L " . $svgPoints[0]['x'] . " " . ($paddingTop + $plotHeight) . " Z";
        }
    }

    $avgTrendRate = $trendHistory->isNotEmpty() ? round($trendHistory->avg('rate')) : 100;
    $trendColor = $avgTrendRate >= 85 ? 'emerald' : ($avgTrendRate >= 70 ? 'amber' : 'rose');

    // Quick Date preset definitions
    $todayDate = now()->format('Y-m-d');
    $yesterdayDate = now()->subDay()->format('Y-m-d');
    $thisMondayDate = now()->startOfWeek()->format('Y-m-d');
    $lastFridayDate = now()->subWeek()->endOfWeek()->subDays(2)->format('Y-m-d');

    // Prepare JSON data for Alpine state management
    $initialRowsData = $rows->map(function($r, $i) {
        $entry = $r['entry'];
        $lesson = $r['lesson'];
        return [
            'index' => $i,
            'entry_id' => $entry->id,
            'period_num' => $i + 1,
            'start_time' => $entry->start_time?->format('H:i') ?? '08:00',
            'end_time' => $entry->end_time?->format('H:i') ?? '08:40',
            'subject' => $entry->subject,
            'room' => $entry->room ?? 'Room 101',
            'teacher' => $entry->teacher?->name ?? 'Unassigned',
            'status' => $lesson?->status ?? 'not_attended',
            'remarks' => $lesson?->remarks ?? '',
            'is_open' => false,
        ];
    })->values()->toArray();
@endphp

<x-layouts::app :title="__('Lesson Attendance')">
    <div class="flex flex-col gap-8 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8" x-data="{
        rows: @js($initialRowsData),
        hasChanges: false,
        hoveredPoint: null,
        get total() { return this.rows.length; },
        get attendedCount() { return this.rows.filter(r => r.status === 'attended').length; },
        get engagementRate() { return this.total > 0 ? Math.round((this.attendedCount / this.total) * 100) : 0; },
        setStatus(idx, newStatus) {
            this.rows[idx].status = newStatus;
            this.hasChanges = true;
        },
        setRemarks(idx, text) {
            this.rows[idx].remarks = text;
            this.hasChanges = true;
        },
        markAll(newStatus) {
            this.rows.forEach(r => r.status = newStatus);
            this.hasChanges = true;
        }
    }">
        
        {{-- Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold tracking-wide uppercase bg-blue-50 text-blue-700 border border-blue-200/60">
                        Academic Delivery
                    </span>
                    <span class="text-xs text-zinc-400">&bull;</span>
                    <span class="text-xs text-zinc-500 font-medium">{{ currentInstitution()?->name ?? 'Green Valley Academy' }}</span>
                </div>
                <flux:heading size="xl" class="font-black tracking-tight text-zinc-900">Lesson Attendance</flux:heading>
                <flux:text class="text-zinc-500 text-sm mt-0.5">
                    Track daily timetable lessons, record curriculum progress, and verify subject engagement.
                </flux:text>
            </div>
            
            <div class="flex items-center gap-3">
                <flux:button href="{{ route('lesson.reports.index') }}" icon="chart-bar" variant="subtle" class="font-semibold shadow-sm">
                    Attendance Reports
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

        {{-- Class & Date Filter Toolbar with Quick Presets --}}
        <div class="bg-white rounded-2xl p-4 border border-zinc-200/80 shadow-sm flex flex-col lg:flex-row items-center justify-between gap-4">
            <form action="{{ route('lesson.index') }}" method="GET" class="flex flex-wrap items-center gap-3 w-full lg:w-auto">
                {{-- Class Selector --}}
                <div class="w-full sm:w-64">
                    <flux:select name="class_id" onchange="this.form.submit()" placeholder="Select Class" class="w-full">
                        @foreach ($classes as $class)
                            <flux:select.option value="{{ $class->id }}" :selected="$selectedClass?->id === $class->id">
                                {{ $class->name }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                {{-- Date Picker --}}
                <div class="w-full sm:w-44">
                    <flux:input type="date" name="date" value="{{ $date->format('Y-m-d') }}" onchange="this.form.submit()" class="w-full" />
                </div>
            </form>

            {{-- Quick Date Preset Chips --}}
            <div class="flex flex-wrap items-center gap-1.5 w-full lg:w-auto justify-start lg:justify-end">
                <span class="text-xs font-semibold text-zinc-400 mr-1 hidden sm:inline">Presets:</span>
                <a href="{{ route('lesson.index', ['class_id' => $selectedClass?->id, 'date' => $todayDate]) }}" 
                    class="px-3 py-1.5 rounded-xl text-xs font-semibold transition {{ $date->format('Y-m-d') === $todayDate ? 'bg-zinc-900 text-white shadow-sm' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200' }}">
                    Today
                </a>
                <a href="{{ route('lesson.index', ['class_id' => $selectedClass?->id, 'date' => $yesterdayDate]) }}" 
                    class="px-3 py-1.5 rounded-xl text-xs font-semibold transition {{ $date->format('Y-m-d') === $yesterdayDate ? 'bg-zinc-900 text-white shadow-sm' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200' }}">
                    Yesterday
                </a>
                <a href="{{ route('lesson.index', ['class_id' => $selectedClass?->id, 'date' => $thisMondayDate]) }}" 
                    class="px-3 py-1.5 rounded-xl text-xs font-semibold transition {{ $date->format('Y-m-d') === $thisMondayDate ? 'bg-zinc-900 text-white shadow-sm' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200' }}">
                    This Week
                </a>
                <a href="{{ route('lesson.index', ['class_id' => $selectedClass?->id, 'date' => $lastFridayDate]) }}" 
                    class="px-3 py-1.5 rounded-xl text-xs font-semibold transition {{ $date->format('Y-m-d') === $lastFridayDate ? 'bg-zinc-900 text-white shadow-sm' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200' }}">
                    Last Week
                </a>
            </div>
        </div>

        @if ($selectedClass)
            {{-- Daily Class Health KPI Strip --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                {{-- Card 1: Scheduled Periods --}}
                <div class="bg-white rounded-2xl p-5 border border-zinc-200/80 shadow-sm hover:border-zinc-300 transition duration-150">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-semibold uppercase tracking-wider text-zinc-400">Class Schedule</span>
                        <div class="size-9 rounded-xl bg-zinc-100 flex items-center justify-center text-zinc-700">
                            <flux:icon icon="clock" class="size-4" />
                        </div>
                    </div>
                    <div class="text-2xl font-black text-zinc-900 tracking-tight">
                        <span x-text="total">{{ $totalPeriods }}</span> Periods
                    </div>
                    <div class="text-xs text-zinc-500 mt-2 font-medium">
                        {{ $selectedClass->name }} &bull; {{ $date->format('l, d M Y') }}
                    </div>
                </div>

                {{-- Card 2: Conducted Lessons --}}
                <div class="bg-white rounded-2xl p-5 border border-zinc-200/80 shadow-sm hover:border-zinc-300 transition duration-150">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-semibold uppercase tracking-wider text-emerald-700">Conducted Lessons</span>
                        <div class="size-9 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-600">
                            <flux:icon icon="check-circle" class="size-4" />
                        </div>
                    </div>
                    <div class="text-2xl font-black text-emerald-600 tracking-tight">
                        <span x-text="attendedCount">{{ $initialAttended }}</span> / <span x-text="total">{{ $totalPeriods }}</span> Attended
                    </div>
                    <div class="text-xs text-zinc-500 mt-2 font-medium">
                        Engagement Rate: <strong class="text-zinc-900 font-bold" x-text="engagementRate + '%'">{{ $totalPeriods > 0 ? round(($initialAttended / $totalPeriods) * 100) : 0 }}%</strong>
                    </div>
                </div>

                {{-- Card 3: Batch Shortcuts --}}
                <div class="bg-white rounded-2xl p-5 border border-zinc-200/80 shadow-sm hover:border-zinc-300 transition duration-150 flex flex-col justify-between">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-semibold uppercase tracking-wider text-zinc-400">Quick Actions</span>
                        <div class="size-9 rounded-xl bg-zinc-100 flex items-center justify-center text-zinc-700">
                            <flux:icon icon="bolt" class="size-4" />
                        </div>
                    </div>
                    <div class="flex items-center gap-2 mt-1">
                        @can('edit lesson')
                            <button type="button" @click="markAll('attended')" class="px-3.5 py-1.5 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-xs font-bold transition border border-emerald-200 flex items-center gap-1.5">
                                <span class="size-1.5 rounded-full bg-emerald-600"></span>
                                Mark All Attended
                            </button>
                            <button type="button" @click="markAll('not_attended')" class="px-3.5 py-1.5 rounded-xl bg-zinc-100 hover:bg-zinc-200 text-zinc-700 text-xs font-semibold transition">
                                Reset
                            </button>
                        @else
                            <span class="text-xs text-zinc-500 font-medium">Read-only view</span>
                        @endcan
                    </div>
                </div>
            </div>

            {{-- 📈 Animated Smooth Trend Line & Area Curve Graph --}}
            @if ($trendHistory->isNotEmpty())
                <div class="bg-white rounded-2xl p-6 border border-zinc-200/80 shadow-sm relative overflow-hidden">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-base font-bold text-zinc-900">Attendance Delivery Trend</h3>
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold font-mono {{ $avgTrendRate >= 85 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : ($avgTrendRate >= 70 ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-rose-50 text-rose-700 border border-rose-200') }}">
                                    {{ $avgTrendRate }}% Avg Compliance
                                </span>
                            </div>
                            <p class="text-xs text-zinc-500 mt-0.5">Continuous delivery curve for {{ $selectedClass->name }} across the past 10 class sessions.</p>
                        </div>
                        
                        <div class="flex items-center gap-4 text-xs font-medium text-zinc-500">
                            <span class="flex items-center gap-1.5">
                                <span class="size-2 rounded-full bg-emerald-500"></span> &ge;85% Optimum
                            </span>
                            <span class="flex items-center gap-1.5">
                                <span class="size-2 rounded-full bg-amber-500"></span> 70-84% Moderate
                            </span>
                            <span class="flex items-center gap-1.5">
                                <span class="size-2 rounded-full bg-rose-500"></span> &lt;70% Critical
                            </span>
                        </div>
                    </div>

                    {{-- SVG Smooth Curve Container --}}
                    <div class="relative w-full overflow-x-auto pt-2 pb-1">
                        <svg viewBox="0 0 900 140" class="w-full h-36 overflow-visible">
                            <defs>
                                <linearGradient id="curveGradient" x1="0" y1="0" x2="0" y2="1">
                                    @if ($avgTrendRate >= 85)
                                        <stop offset="0%" stop-color="#10b981" stop-opacity="0.25" />
                                        <stop offset="100%" stop-color="#10b981" stop-opacity="0.0" />
                                    @elseif ($avgTrendRate >= 70)
                                        <stop offset="0%" stop-color="#f59e0b" stop-opacity="0.25" />
                                        <stop offset="100%" stop-color="#f59e0b" stop-opacity="0.0" />
                                    @else
                                        <stop offset="0%" stop-color="#f43f5e" stop-opacity="0.25" />
                                        <stop offset="100%" stop-color="#f43f5e" stop-opacity="0.0" />
                                    @endif
                                </linearGradient>
                            </defs>

                            {{-- Horizontal Reference Guidelines --}}
                            <line x1="45" y1="15" x2="855" y2="15" stroke="#f4f4f5" stroke-dasharray="3 3" stroke-width="1" />
                            <text x="35" y="18" font-size="9" fill="#a1a1aa" text-anchor="end" font-family="monospace">100%</text>

                            <line x1="45" y1="65" x2="855" y2="65" stroke="#f4f4f5" stroke-dasharray="3 3" stroke-width="1" />
                            <text x="35" y="68" font-size="9" fill="#a1a1aa" text-anchor="end" font-family="monospace">75%</text>

                            <line x1="45" y1="105" x2="855" y2="105" stroke="#e4e4e7" stroke-width="1" />
                            <text x="35" y="108" font-size="9" fill="#a1a1aa" text-anchor="end" font-family="monospace">50%</text>

                            {{-- Smooth Area Fill --}}
                            <path d="{{ $svgArea }}" fill="url(#curveGradient)" />

                            {{-- Smooth Line Stroke with Draw Animation --}}
                            <path d="{{ $svgPath }}" fill="none" 
                                class="{{ $avgTrendRate >= 85 ? 'stroke-emerald-500' : ($avgTrendRate >= 70 ? 'stroke-amber-500' : 'stroke-rose-500') }}"
                                stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />

                            {{-- Interactive Data Nodes --}}
                            @foreach ($svgPoints as $p)
                                @php
                                    $isTodayNode = ($p['date_full'] === $date->format('Y-m-d'));
                                    $nodeFillClass = $p['rate'] >= 85 ? '#10b981' : ($p['rate'] >= 70 ? '#f59e0b' : '#f43f5e');
                                @endphp
                                <g class="cursor-pointer group/node"
                                    @mouseenter="hoveredPoint = @js($p)" 
                                    @mouseleave="hoveredPoint = null"
                                    onclick="window.location.href='{{ route('lesson.index', ['class_id' => $selectedClass->id, 'date' => $p['date_full']]) }}'">
                                    
                                    {{-- Outer Pulse Ring if Active Date --}}
                                    @if ($isTodayNode)
                                        <circle cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="8" fill="{{ $nodeFillClass }}" fill-opacity="0.25" class="animate-pulse" />
                                    @endif

                                    {{-- Outer Hover Circle --}}
                                    <circle cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="6" fill="#ffffff" stroke="{{ $nodeFillClass }}" stroke-width="2" class="transition-transform group-hover/node:scale-125" />
                                    
                                    {{-- Inner Center Dot --}}
                                    <circle cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="2.5" fill="{{ $nodeFillClass }}" />

                                    {{-- Top Percentage Label --}}
                                    <text x="{{ $p['x'] }}" y="{{ $p['y'] - 10 }}" font-size="10" font-weight="bold" fill="#18181b" text-anchor="middle">
                                        {{ $p['rate'] }}%
                                    </text>

                                    {{-- Bottom Date Label --}}
                                    <text x="{{ $p['x'] }}" y="122" font-size="10" font-weight="600" fill="#3f3f46" text-anchor="middle">
                                        {{ $p['date_label'] }}
                                    </text>
                                    <text x="{{ $p['x'] }}" y="134" font-size="9" fill="#a1a1aa" text-anchor="middle" font-family="monospace">
                                        {{ $p['day_name'] }}
                                    </text>
                                </g>
                            @endforeach
                        </svg>
                    </div>

                    {{-- Floating Hover Info Tooltip --}}
                    <div class="mt-3 text-xs text-zinc-500 font-medium flex items-center justify-between border-t border-zinc-100 pt-2.5">
                        <span x-show="hoveredPoint">
                            Session: <strong class="text-zinc-900" x-text="hoveredPoint?.date_label + ' (' + hoveredPoint?.day_name + ')'"></strong> &bull; 
                            Conducted: <strong class="text-zinc-900" x-text="hoveredPoint?.attended + ' / ' + hoveredPoint?.total + ' Lessons'"></strong> &bull;
                            Compliance: <strong class="text-emerald-700 font-bold" x-text="hoveredPoint?.rate + '%'"></strong>
                        </span>
                        <span x-show="!hoveredPoint" class="text-zinc-400">
                            Hover over or click any point on the curve to inspect that session's timetable.
                        </span>
                        <span class="text-zinc-400 font-mono text-[11px]">10-Day Window</span>
                    </div>
                </div>
            @endif

            {{-- Main Form for Saving Lesson Attendance --}}
            @if ($rows->isEmpty())
                <div class="bg-white rounded-2xl border border-zinc-200/80 p-16 text-center shadow-sm">
                    <flux:icon icon="calendar" class="size-12 mx-auto text-zinc-300 mb-3" />
                    <h4 class="text-base font-bold text-zinc-900">No Timetable Periods Found</h4>
                    <p class="text-xs text-zinc-500 max-w-sm mx-auto mt-1 mb-4">
                        There are no timetable periods scheduled for {{ $selectedClass->name }} on {{ $date->format('l') }}.
                    </p>
                </div>
            @else
                <form action="{{ route('lesson.store') }}" method="POST" id="attendanceForm">
                    @csrf
                    <input type="hidden" name="class_id" value="{{ $selectedClass->id }}">
                    <input type="hidden" name="date" value="{{ $date->format('Y-m-d') }}">

                    {{-- Responsive Timeline Period Cards List --}}
                    <div class="space-y-4">
                        <template x-for="(row, idx) in rows" :key="row.entry_id">
                            <div class="bg-white rounded-2xl border transition-all duration-150 p-5 shadow-sm hover:border-zinc-300"
                                :class="{
                                    'border-emerald-300 ring-1 ring-emerald-500/20': row.status === 'attended',
                                    'border-amber-300 ring-1 ring-amber-500/20': row.status === 'recovered',
                                    'border-zinc-200/80': row.status === 'not_attended'
                                }">
                                
                                {{-- Hidden Form Inputs for Backend Submission --}}
                                <input type="hidden" :name="`statuses[${idx}][timetable_entry_id]`" :value="row.entry_id">
                                <input type="hidden" :name="`statuses[${idx}][status]`" :value="row.status">
                                <input type="hidden" :name="`statuses[${idx}][remarks]`" :value="row.remarks">

                                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                                    {{-- Left: Period Time, Subject & Appointed Teacher --}}
                                    <div class="flex items-start sm:items-center gap-4">
                                        {{-- Period Badge --}}
                                        <div class="flex flex-col items-center justify-center size-12 rounded-2xl bg-zinc-100 border border-zinc-200 shrink-0 text-center">
                                            <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider">Period</span>
                                            <span class="text-base font-black text-zinc-900" x-text="row.period_num"></span>
                                        </div>

                                        {{-- Subject & Teacher Details --}}
                                        <div>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <h4 class="text-base font-bold text-zinc-900" x-text="row.subject"></h4>
                                                <span class="px-2 py-0.5 rounded-md bg-zinc-100 text-zinc-600 font-mono text-xs font-semibold border border-zinc-200" x-text="`${row.start_time} - ${row.end_time}`"></span>
                                                <span class="px-2 py-0.5 rounded-md bg-zinc-50 text-zinc-500 text-xs font-medium border border-zinc-100" x-text="row.room"></span>
                                            </div>
                                            <div class="flex items-center gap-2 mt-1.5 text-xs text-zinc-500 font-medium">
                                                <div class="size-5 rounded-full bg-zinc-200 text-zinc-700 font-bold text-[10px] flex items-center justify-center" x-text="row.teacher.charAt(0)"></div>
                                                <span class="text-zinc-700 font-semibold" x-text="row.teacher"></span>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Right: Segmented 1-Click Status Switcher --}}
                                    @can('edit lesson')
                                        <div class="flex flex-wrap items-center gap-2 self-start lg:self-center">
                                            <div class="inline-flex p-1 bg-zinc-100 rounded-xl border border-zinc-200">
                                                <button type="button" @click="setStatus(idx, 'attended')"
                                                    :class="row.status === 'attended' ? 'bg-white text-emerald-800 shadow-sm font-bold' : 'text-zinc-600 hover:text-zinc-900 font-medium'"
                                                    class="px-3 py-1.5 rounded-lg text-xs transition duration-150 flex items-center gap-1.5">
                                                    <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                                    Attended
                                                </button>
                                                <button type="button" @click="setStatus(idx, 'not_attended')"
                                                    :class="row.status === 'not_attended' ? 'bg-white text-zinc-900 shadow-sm font-bold' : 'text-zinc-600 hover:text-zinc-900 font-medium'"
                                                    class="px-3 py-1.5 rounded-lg text-xs transition duration-150 flex items-center gap-1.5">
                                                    <span class="size-1.5 rounded-full bg-zinc-400"></span>
                                                    Absent
                                                </button>
                                                <button type="button" @click="setStatus(idx, 'recovered')"
                                                    :class="row.status === 'recovered' ? 'bg-white text-amber-800 shadow-sm font-bold' : 'text-zinc-600 hover:text-zinc-900 font-medium'"
                                                    class="px-3 py-1.5 rounded-lg text-xs transition duration-150 flex items-center gap-1.5">
                                                    <span class="size-1.5 rounded-full bg-amber-500"></span>
                                                    Recovered
                                                </button>
                                            </div>

                                            {{-- Note Toggle Button --}}
                                            <button type="button" @click="row.is_open = !row.is_open"
                                                :class="row.remarks ? 'bg-blue-50 text-blue-700 border-blue-200' : 'bg-zinc-50 text-zinc-600 border-zinc-200 hover:bg-zinc-100'"
                                                class="px-3 py-1.5 rounded-xl border text-xs font-semibold transition flex items-center gap-1.5">
                                                <flux:icon icon="pencil-square" class="size-3.5" />
                                                <span x-text="row.remarks ? 'Edit Note' : 'Add Note'"></span>
                                            </button>
                                        </div>
                                    @else
                                        <div>
                                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold"
                                                :class="{
                                                    'bg-emerald-50 text-emerald-700 border border-emerald-200': row.status === 'attended',
                                                    'bg-amber-50 text-amber-700 border border-amber-200': row.status === 'recovered',
                                                    'bg-zinc-100 text-zinc-600 border border-zinc-200': row.status === 'not_attended'
                                                }">
                                                <span class="size-1.5 rounded-full"
                                                    :class="{
                                                        'bg-emerald-500': row.status === 'attended',
                                                        'bg-amber-500': row.status === 'recovered',
                                                        'bg-zinc-400': row.status === 'not_attended'
                                                    }"></span>
                                                <span x-text="row.status.charAt(0).toUpperCase() + row.status.slice(1)"></span>
                                            </span>
                                        </div>
                                    @endcan
                                </div>

                                {{-- Note Preview / Expandable Remarks Box --}}
                                <div class="mt-3 pt-3 border-t border-zinc-100" x-show="row.remarks || row.is_open">
                                    {{-- Clean Presets Tag Chips --}}
                                    @can('edit lesson')
                                        <div class="flex flex-wrap items-center gap-1.5 mb-2" x-show="row.is_open">
                                            <span class="text-[11px] font-semibold text-zinc-400 mr-1">Presets:</span>
                                            <button type="button" @click="setRemarks(idx, 'Topic covered successfully according to curriculum scheme.')" class="px-2.5 py-1 rounded-lg bg-zinc-100 hover:bg-zinc-200 text-zinc-700 text-[11px] font-medium transition border border-zinc-200">
                                                Topic Covered
                                            </button>
                                            <button type="button" @click="setRemarks(idx, 'Practical lab demonstration and student experiments.')" class="px-2.5 py-1 rounded-lg bg-zinc-100 hover:bg-zinc-200 text-zinc-700 text-[11px] font-medium transition border border-zinc-200">
                                                Practical Lab
                                            </button>
                                            <button type="button" @click="setRemarks(idx, 'Class topical assessment / quiz administered.')" class="px-2.5 py-1 rounded-lg bg-zinc-100 hover:bg-zinc-200 text-zinc-700 text-[11px] font-medium transition border border-zinc-200">
                                                Assessment Quiz
                                            </button>
                                            <button type="button" @click="setRemarks(idx, 'Teacher on official duty. Makeup class scheduled.')" class="px-2.5 py-1 rounded-lg bg-zinc-100 hover:bg-zinc-200 text-zinc-700 text-[11px] font-medium transition border border-zinc-200">
                                                Makeup Required
                                            </button>
                                        </div>

                                        <div x-show="row.is_open">
                                            <textarea x-model="row.remarks" @input="hasChanges = true" rows="2" placeholder="Enter lesson notes, topics covered, or teacher observations..."
                                                class="w-full px-3.5 py-2 rounded-xl text-xs border border-zinc-200 bg-zinc-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-900 transition"></textarea>
                                        </div>
                                    @endcan

                                    <div class="text-xs text-zinc-600 bg-zinc-50 rounded-xl p-2.5 border border-zinc-100 font-medium flex items-center justify-between" x-show="row.remarks && !row.is_open">
                                        <div class="flex items-center gap-2">
                                            <span class="text-zinc-400 font-semibold">Note:</span>
                                            <span x-text="row.remarks"></span>
                                        </div>
                                        @can('edit lesson')
                                            <button type="button" @click="row.is_open = true" class="text-blue-600 text-xs font-semibold hover:underline">Edit</button>
                                        @endcan
                                    </div>
                                </div>

                            </div>
                        </template>
                    </div>

                    {{-- Floating Bottom Save Bar --}}
                    @can('edit lesson')
                        <div class="sticky bottom-6 z-30 mt-6 bg-zinc-900 text-white rounded-2xl p-4 shadow-2xl border border-zinc-800 flex items-center justify-between backdrop-blur-md bg-opacity-95"
                            x-show="hasChanges" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
                            <div class="flex items-center gap-3">
                                <span class="size-2 rounded-full bg-amber-400 animate-pulse"></span>
                                <span class="text-sm font-semibold">Unsaved attendance modifications detected.</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="submit" class="px-5 py-2 rounded-xl bg-white hover:bg-zinc-100 text-zinc-900 text-xs font-bold shadow-sm transition">
                                    Save Daily Attendance
                                </button>
                            </div>
                        </div>

                        {{-- Fallback Regular Save Button --}}
                        <div class="mt-6 flex justify-end" x-show="!hasChanges">
                            <flux:button type="submit" variant="primary" class="font-semibold px-6 shadow-sm">
                                Save Attendance
                            </flux:button>
                        </div>
                    @endcan
                </form>
            @endif

            {{-- Recent Activity Stream --}}
            @if ($recent->isNotEmpty())
                <div class="mt-8">
                    <div class="mb-4">
                        <flux:heading size="lg" class="font-bold text-zinc-900">Recent Lesson Records</flux:heading>
                        <flux:text class="text-xs text-zinc-500 mt-0.5">Historical verification logs for {{ $selectedClass->name }}.</flux:text>
                    </div>
                    
                    <div class="bg-white shadow-sm border border-zinc-200/80 rounded-2xl overflow-hidden flex flex-col">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-zinc-50 text-zinc-500 border-b border-zinc-200/80 text-xs font-bold uppercase tracking-wider">
                                    <tr>
                                        <th class="py-3.5 px-5">Date</th>
                                        <th class="py-3.5 px-5">Subject</th>
                                        <th class="py-3.5 px-5">Status</th>
                                        <th class="py-3.5 px-5 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-100">
                                    @foreach ($recent as $item)
                                        <tr class="hover:bg-zinc-50/60 transition-colors">
                                            <td class="py-3.5 px-5 font-semibold text-zinc-900 text-xs">{{ $item->lesson_date->format('d M Y') }}</td>
                                            <td class="py-3.5 px-5 text-xs text-zinc-700 font-medium">{{ $item->timetableEntry?->subject ?? 'Subject' }}</td>
                                            <td class="py-3.5 px-5">
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold
                                                    {{ $item->status === 'attended' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' :
                                                       ($item->status === 'recovered' ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-zinc-100 text-zinc-600 border border-zinc-200') }}">
                                                    <span class="size-1 rounded-full {{ $item->status === 'attended' ? 'bg-emerald-500' : ($item->status === 'recovered' ? 'bg-amber-500' : 'bg-zinc-400') }}"></span>
                                                    {{ ucfirst(str_replace('_', ' ', $item->status)) }}
                                                </span>
                                            </td>
                                            <td class="py-3.5 px-5 text-right">
                                                <div class="flex items-center justify-end gap-1.5">
                                                    <flux:button href="{{ route('lesson.show', $item->id) }}" size="sm" icon="eye" variant="subtle" tooltip="View" />
                                                    @can('edit lesson')
                                                        <flux:button href="{{ route('lesson.edit', $item->id) }}" size="sm" icon="pencil" variant="subtle" tooltip="Edit" />
                                                    @endcan
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        @endif
    </div>
</x-layouts::app>