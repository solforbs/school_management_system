{{-- Teacher Analytics Partial --}}
<div class="flex flex-col gap-6">
    @if (!$stats['institution'])
        <flux:callout variant="warning" icon="information-circle">
            <flux:callout.heading>Not yet assigned to a school</flux:callout.heading>
            <flux:callout.text>Ask your Director to add you to a campus roster.</flux:callout.text>
        </flux:callout>
    @else
        {{-- 1. Executive KPI Strip --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {{-- Campus Card --}}
            <x-kpi-card title="Campus" icon="building-office-2" color="indigo" :count="$stats['institution']->name" subtext="currently assigned" />

            {{-- Students Taught --}}
            <x-kpi-card title="Learners Taught" icon="user-group" color="sky" :count="$stats['students_count']" href="{{ route('student.index') }}">
                <div class="flex flex-wrap items-center gap-2 mt-1.5">
                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-blue-50/90 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 text-[11px] font-semibold border border-blue-200/70 dark:border-blue-800/50">
                        <svg class="size-3 text-blue-600 dark:text-blue-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <circle cx="10" cy="14" r="5" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5L20 4m0 0h-4.5m4.5 0v4.5" />
                        </svg>
                        <span>Male: <strong class="font-bold text-zinc-900 dark:text-white">{{ number_format($stats['male_students_count'] ?? 0) }}</strong></span>
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-pink-50/90 dark:bg-pink-950/40 text-pink-700 dark:text-pink-300 text-[11px] font-semibold border border-pink-200/70 dark:border-pink-800/50">
                        <svg class="size-3 text-pink-600 dark:text-pink-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <circle cx="12" cy="9" r="5" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 14v7m-3-3.5h6" />
                        </svg>
                        <span>Female: <strong class="font-bold text-zinc-900 dark:text-white">{{ number_format($stats['female_students_count'] ?? 0) }}</strong></span>
                    </span>
                </div>
            </x-kpi-card>

            {{-- Attendance Today: Roll-call rate + Biometric status --}}
            <x-kpi-card
                title="Attendance Today"
                icon="clock"
                :color="$stats['rollcall_attendance_rate'] !== null ? 'emerald' : ($stats['biometric_scans_today_count'] > 0 ? 'sky' : 'amber')"
                :count="$stats['rollcall_attendance_rate'] !== null ? ($stats['rollcall_attendance_rate'] . '%') : ($stats['biometric_scans_today_count'] > 0 ? $stats['biometric_scans_today_count'] : '0%')"
                href="{{ route('attendance.index') }}"
            >
                <div class="flex flex-col gap-1 mt-1.5">
                    @if ($stats['rollcall_attendance_rate'] !== null)
                        <div class="text-[11px] font-semibold text-zinc-700 dark:text-zinc-300 flex items-center gap-1">
                            <span class="size-1.5 rounded-full bg-emerald-500 shrink-0"></span>
                            <span>Manual: <strong class="text-emerald-700 dark:text-emerald-400 font-bold">{{ $stats['rollcall_attendance_rate'] }}%</strong> ({{ $stats['rollcall_present_count'] }}/{{ $stats['rollcall_total_marked'] }} Present)</span>
                        </div>
                        <div class="text-[10.5px] font-medium {{ $stats['biometric_scans_today_count'] > 0 ? 'text-sky-600 dark:text-sky-400' : 'text-zinc-500 dark:text-zinc-400' }}">
                            @if ($stats['biometric_scans_today_count'] > 0)
                                ⚡ {{ $stats['biometric_scans_today_count'] }} biometric gate scans
                            @else
                                Biometric check-in not done today
                            @endif
                        </div>
                    @else
                        <div class="text-[11px] font-semibold text-amber-700 dark:text-amber-400 flex items-center gap-1">
                            <span class="size-1.5 rounded-full bg-amber-500 shrink-0"></span>
                            <span>Roll-call pending today</span>
                        </div>
                        <div class="text-[10.5px] font-medium text-zinc-500 dark:text-zinc-400">
                            @if ($stats['biometric_scans_today_count'] > 0)
                                ⚡ {{ $stats['biometric_scans_today_count'] }} biometric gate check-ins recorded
                            @else
                                Biometric check-in not done (0 check-ins)
                            @endif
                        </div>
                    @endif
                </div>
            </x-kpi-card>

            {{-- Homeroom Class / Workload Card --}}
            @if ($stats['homeroom_class'])
                <x-kpi-card
                    title="Homeroom Class"
                    icon="academic-cap"
                    color="violet"
                    :count="$stats['homeroom_class']->name"
                    href="{{ route('attendance.register', ['classId' => $stats['homeroom_class']->id]) }}"
                >
                    <div class="flex flex-col gap-1 mt-1.5">
                        @if ($stats['homeroom_stats'] && $stats['homeroom_stats']['is_completed'])
                            <div class="text-[11px] font-semibold text-emerald-700 dark:text-emerald-400 flex items-center gap-1">
                                <span class="size-1.5 rounded-full bg-emerald-500 shrink-0"></span>
                                <span>Roll-Call Saved ({{ $stats['homeroom_stats']['attendance_rate'] ?? 0 }}% Present)</span>
                            </div>
                            <div class="text-[10.5px] text-zinc-500 dark:text-zinc-400">
                                {{ $stats['homeroom_stats']['present_count'] }}/{{ $stats['homeroom_stats']['total_students'] }} learners present today
                            </div>
                        @else
                            <div class="text-[11px] font-semibold text-amber-700 dark:text-amber-400 flex items-center gap-1">
                                <span class="size-1.5 rounded-full bg-amber-500 shrink-0"></span>
                                <span>Today's Roll-Call Pending</span>
                            </div>
                            <div class="text-[10.5px] text-zinc-500 dark:text-zinc-400">
                                {{ $stats['homeroom_stats']['total_students'] ?? $stats['homeroom_class']->students_count }} learners enrolled
                            </div>
                        @endif
                    </div>
                </x-kpi-card>
            @else
                <x-kpi-card
                    title="Teaching Workload"
                    icon="book-open"
                    color="purple"
                    :count="$stats['teaching_allocations']->count() . ' Subjects'"
                    href="{{ route('attendance.index') }}"
                    :subtext="'across ' . ($stats['classes_total_count'] ?? 1) . ' classes'"
                />
            @endif
        </div>

        {{-- 2. Attendance Intelligence & Diagnostics Grid (2 Columns) --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            {{-- Left Column: Roll-Call vs Biometric Gate Sync & 5-Day Trend (7 Cols) --}}
            <div class="lg:col-span-7 flex flex-col gap-5">
                <div class="rounded-2xl border border-zinc-200/80 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    {{-- Header --}}
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-zinc-100 dark:border-zinc-800">
                        <div>
                            <flux:heading size="lg" class="font-bold text-zinc-900 dark:text-white flex items-center gap-2">
                                <flux:icon icon="chart-bar" class="size-5 text-blue-600 dark:text-blue-400" />
                                <span>Attendance Intelligence &amp; Gate Sync</span>
                            </flux:heading>
                            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                                Comparing classroom roll-call register with entrance biometric hardware scans.
                            </flux:text>
                        </div>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold {{ $stats['rollcall_completed'] ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800' : 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300 border border-amber-200 dark:border-amber-800' }}">
                            <span class="size-1.5 rounded-full {{ $stats['rollcall_completed'] ? 'bg-emerald-500' : 'bg-amber-500 animate-pulse' }}"></span>
                            <span>{{ $stats['rollcall_completed'] ? 'Roll-Call Recorded' : 'Roll-Call Pending' }}</span>
                        </span>
                    </div>

                    {{-- Comparison Cards --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-5">
                        {{-- Roll-Call Panel --}}
                        <div class="p-4 rounded-xl border {{ $stats['rollcall_completed'] ? 'border-emerald-200/80 bg-emerald-50/40 dark:border-emerald-900/50 dark:bg-emerald-950/20' : 'border-zinc-200/80 bg-zinc-50/60 dark:border-zinc-800 dark:bg-zinc-800/40' }} flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Classroom Roll-Call</span>
                                    @if ($stats['rollcall_completed'])
                                        <flux:icon icon="check-circle" class="size-4 text-emerald-600 dark:text-emerald-400" />
                                    @else
                                        <flux:icon icon="clock" class="size-4 text-amber-500" />
                                    @endif
                                </div>
                                <div class="mt-2 flex items-baseline gap-2">
                                    <span class="text-3xl font-black text-zinc-900 dark:text-white">
                                        {{ $stats['rollcall_attendance_rate'] !== null ? $stats['rollcall_attendance_rate'] . '%' : '—' }}
                                    </span>
                                    <span class="text-xs font-medium text-zinc-500">attendance rate</span>
                                </div>
                                <div class="mt-3 grid grid-cols-3 gap-2 text-center text-xs">
                                    <div class="p-1.5 rounded-lg bg-white/80 dark:bg-zinc-900/60 border border-zinc-200/60 dark:border-zinc-800">
                                        <div class="font-bold text-emerald-600 dark:text-emerald-400">{{ $stats['rollcall_present_count'] }}</div>
                                        <div class="text-[10px] text-zinc-500 uppercase">Present</div>
                                    </div>
                                    <div class="p-1.5 rounded-lg bg-white/80 dark:bg-zinc-900/60 border border-zinc-200/60 dark:border-zinc-800">
                                        <div class="font-bold text-rose-600 dark:text-rose-400">{{ $stats['rollcall_absent_count'] }}</div>
                                        <div class="text-[10px] text-zinc-500 uppercase">Absent</div>
                                    </div>
                                    <div class="p-1.5 rounded-lg bg-white/80 dark:bg-zinc-900/60 border border-zinc-200/60 dark:border-zinc-800">
                                        <div class="font-bold text-amber-600 dark:text-amber-400">{{ $stats['rollcall_late_count'] }}</div>
                                        <div class="text-[10px] text-zinc-500 uppercase">Late</div>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3 pt-2 border-t border-zinc-200/60 dark:border-zinc-800/60 text-[11px] text-zinc-500">
                                @if ($stats['rollcall_saved_at'])
                                    Last saved at <strong class="text-zinc-700 dark:text-zinc-300 font-semibold">{{ $stats['rollcall_saved_at'] }}</strong>
                                @else
                                    No roll-call saved for today yet
                                @endif
                            </div>
                        </div>

                        {{-- Biometric Scanner Panel --}}
                        <div class="p-4 rounded-xl border {{ $stats['biometric_scans_today_count'] > 0 ? 'border-sky-200/80 bg-sky-50/40 dark:border-sky-900/50 dark:bg-sky-950/20' : 'border-zinc-200/80 bg-zinc-50/60 dark:border-zinc-800 dark:bg-zinc-800/40' }} flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Biometric Gate Sync</span>
                                    <flux:icon icon="signal" class="size-4 {{ $stats['biometric_scans_today_count'] > 0 ? 'text-sky-600 dark:text-sky-400' : 'text-zinc-400' }}" />
                                </div>
                                <div class="mt-2 flex items-baseline gap-2">
                                    <span class="text-3xl font-black text-zinc-900 dark:text-white">
                                        {{ $stats['biometric_scans_today_count'] }}
                                    </span>
                                    <span class="text-xs font-medium text-zinc-500">gate scans</span>
                                </div>
                                <div class="mt-3 p-2.5 rounded-lg bg-white/80 dark:bg-zinc-900/60 border border-zinc-200/60 dark:border-zinc-800 text-xs">
                                    @if ($stats['biometric_scans_today_count'] > 0)
                                        <p class="text-sky-800 dark:text-sky-300 font-medium">
                                            ⚡ Entrance check-ins active and synchronized with classroom register.
                                        </p>
                                    @else
                                        <p class="text-zinc-600 dark:text-zinc-400">
                                            Biometric check-in not done today. Devices may be offline or students have not checked in at gate.
                                        </p>
                                    @endif
                                </div>
                            </div>
                            <div class="mt-3 pt-2 border-t border-zinc-200/60 dark:border-zinc-800/60 text-[11px] text-zinc-500">
                                Scanner Status: <strong class="{{ $stats['biometric_scans_today_count'] > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-600 dark:text-zinc-400' }}">{{ $stats['biometric_scans_today_count'] > 0 ? 'Active Logs' : 'Zero Logs Today' }}</strong>
                            </div>
                        </div>
                    </div>

                    {{-- 5-Day Attendance Turnout Trend --}}
                    @if (!empty($stats['weekly_attendance_trend']))
                        <div class="mt-6 pt-5 border-t border-zinc-100 dark:border-zinc-800">
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-xs font-bold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">5-Day Weekday Attendance Trend</span>
                                <span class="text-xs text-zinc-400">Across your assigned classes</span>
                            </div>
                            <div class="grid grid-cols-5 gap-2">
                                @foreach ($stats['weekly_attendance_trend'] as $day)
                                    <div class="flex flex-col items-center p-2 rounded-xl {{ $day['is_today'] ? 'bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800' : 'bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200/60 dark:border-zinc-800' }}">
                                        <span class="text-[11px] font-bold {{ $day['is_today'] ? 'text-blue-600 dark:text-blue-400' : 'text-zinc-600 dark:text-zinc-400' }}">{{ $day['day_name'] }}</span>
                                        <div class="w-full bg-zinc-200 dark:bg-zinc-700 h-16 rounded-md my-1.5 flex flex-col justify-end p-0.5 overflow-hidden">
                                            @if ($day['rate'] !== null)
                                                <div
                                                    class="w-full rounded-sm {{ $day['rate'] >= 85 ? 'bg-emerald-500' : ($day['rate'] >= 70 ? 'bg-amber-500' : 'bg-rose-500') }} transition-all"
                                                    style="height: {{ max(10, min(100, $day['rate'])) }}%;"
                                                    title="{{ $day['rate'] }}% ({{ $day['present'] }}/{{ $day['total_marked'] }})"
                                                ></div>
                                            @else
                                                <div class="w-full h-1 bg-zinc-300 dark:bg-zinc-600 rounded-sm"></div>
                                            @endif
                                        </div>
                                        <span class="text-xs font-extrabold text-zinc-900 dark:text-white">
                                            {{ $day['rate'] !== null ? $day['rate'] . '%' : '—' }}
                                        </span>
                                        <span class="text-[9.5px] text-zinc-400">{{ $day['total_marked'] > 0 ? $day['present'] . '/' . $day['total_marked'] : 'No roll' }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Action Footer --}}
                    <div class="mt-5 pt-4 border-t border-zinc-100 dark:border-zinc-800 flex flex-wrap items-center justify-between gap-3">
                        <div class="text-xs text-zinc-500 dark:text-zinc-400">
                            Classes completed: <strong class="text-zinc-800 dark:text-zinc-200">{{ $stats['classes_completed_count'] }} of {{ $stats['classes_total_count'] }}</strong>
                        </div>
                        <div class="flex items-center gap-2">
                            <flux:button href="{{ route('attendance.index') }}" wire:navigate.hover size="sm" variant="subtle" icon="clock">
                                Biometric Stream
                            </flux:button>
                            <flux:button href="{{ route('attendance.register') }}" wire:navigate.hover size="sm" variant="filled" icon="clipboard-document-check">
                                Open Roll-Call Register
                            </flux:button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right Column: Absentee Roster & Parent Contact Hub (5 Cols) --}}
            <div class="lg:col-span-5 flex flex-col gap-5">
                <div class="rounded-2xl border border-zinc-200/80 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex items-center justify-between pb-3 border-b border-zinc-100 dark:border-zinc-800">
                        <div>
                            <flux:heading size="lg" class="font-bold text-zinc-900 dark:text-white flex items-center gap-2">
                                <flux:icon icon="exclamation-circle" class="size-5 text-rose-500" />
                                <span>Absent Learners Today</span>
                            </flux:heading>
                            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                                Reach out directly to parents/guardians.
                            </flux:text>
                        </div>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold {{ count($stats['absent_students_today']) > 0 ? 'bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300 border border-rose-200 dark:border-rose-800' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800' }}">
                            {{ count($stats['absent_students_today']) }} Absent
                        </span>
                    </div>

                    <div class="mt-4">
                        @if ($stats['absent_students_today']->isNotEmpty())
                            <div class="space-y-3 max-h-[460px] overflow-y-auto pr-1">
                                @foreach ($stats['absent_students_today'] as $absent)
                                    <div class="p-3 rounded-xl border border-zinc-200/80 bg-zinc-50/70 hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-800/40 dark:hover:bg-zinc-800 transition-colors">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="min-w-0">
                                                <div class="font-bold text-sm text-zinc-900 dark:text-white truncate">
                                                    {{ $absent['student_name'] }}
                                                </div>
                                                <div class="text-xs text-zinc-500 dark:text-zinc-400 flex items-center gap-1.5 mt-0.5">
                                                    <span class="font-mono">{{ $absent['admission_number'] }}</span>
                                                    <span>&bull;</span>
                                                    <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $absent['class_name'] }}</span>
                                                </div>
                                            </div>
                                            <div>
                                                @if ($absent['parent_notified'])
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                                        ✓ Notified
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                                        ⚠️ Unnotified
                                                    </span>
                                                @endif
                                            </div>
                                        </div>

                                        @if ($absent['notes'])
                                            <div class="mt-2 text-xs italic text-zinc-600 dark:text-zinc-400 bg-white/70 dark:bg-zinc-900/60 p-1.5 rounded-md border border-zinc-200/50 dark:border-zinc-800">
                                                "{{ $absent['notes'] }}"
                                            </div>
                                        @endif

                                        <div class="mt-3 pt-2.5 border-t border-zinc-200/60 dark:border-zinc-800/80 flex items-center justify-between text-xs">
                                            <div class="truncate text-zinc-600 dark:text-zinc-400 font-medium">
                                                {{ $absent['parent_name'] }}
                                            </div>
                                            <div class="flex items-center gap-1 shrink-0">
                                                @if ($absent['parent_phone'])
                                                    <a
                                                        href="tel:{{ $absent['parent_phone'] }}"
                                                        class="inline-flex items-center justify-center size-7 rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800"
                                                        title="Call {{ $absent['parent_name'] }} ({{ $absent['parent_phone'] }})"
                                                    >
                                                        <flux:icon icon="phone" class="size-3.5" />
                                                    </a>
                                                @endif
                                                @if ($absent['parent_email'])
                                                    <a
                                                        href="mailto:{{ $absent['parent_email'] }}?subject=Attendance%20Alert:%20{{ urlencode($absent['student_name']) }}%20Absent%20Today&body=Dear%20Parent,%0A%0AWe%20noticed%20that%20{{ urlencode($absent['student_name']) }}%20was%20marked%20absent%20during%20today's%20roll-call.%20Please%20let%20us%20know%20if%20there%20is%20any%20assistance%20needed."
                                                        class="inline-flex items-center justify-center size-7 rounded-lg bg-blue-50 text-blue-700 hover:bg-blue-100 dark:bg-blue-950/60 dark:text-blue-300 border border-blue-200 dark:border-blue-800"
                                                        title="Email {{ $absent['parent_email'] }}"
                                                    >
                                                        <flux:icon icon="envelope" class="size-3.5" />
                                                    </a>
                                                @endif
                                                <a
                                                    href="{{ route('attendance.register', ['classId' => $absent['class_id']]) }}"
                                                    wire:navigate.hover
                                                    class="inline-flex items-center justify-center size-7 rounded-lg bg-zinc-100 text-zinc-700 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300"
                                                    title="Open Class Register"
                                                >
                                                    <flux:icon icon="arrow-top-right-on-square" class="size-3.5" />
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            @if ($stats['rollcall_completed'])
                                <div class="py-8 text-center flex flex-col items-center">
                                    <div class="size-12 rounded-full bg-emerald-50 dark:bg-emerald-950/50 flex items-center justify-center text-emerald-600 dark:text-emerald-400 mb-3 border border-emerald-200/80 dark:border-emerald-800">
                                        <flux:icon icon="check" class="size-6" />
                                    </div>
                                    <h4 class="text-sm font-bold text-zinc-900 dark:text-white">100% Attendance Today!</h4>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400 max-w-xs mt-1">
                                        All learners in your classes have been marked present. No absentees recorded.
                                    </p>
                                </div>
                            @else
                                <div class="py-8 text-center flex flex-col items-center">
                                    <div class="size-12 rounded-full bg-amber-50 dark:bg-amber-950/50 flex items-center justify-center text-amber-600 dark:text-amber-400 mb-3 border border-amber-200/80 dark:border-amber-800">
                                        <flux:icon icon="clipboard-document-check" class="size-6" />
                                    </div>
                                    <h4 class="text-sm font-bold text-zinc-900 dark:text-white">Roll-Call Not Recorded Yet</h4>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400 max-w-xs mt-1 mb-3">
                                        Take today's classroom roll-call to track absentee learners and notify guardians.
                                    </p>
                                    <flux:button href="{{ route('attendance.register') }}" wire:navigate.hover size="sm" variant="filled" icon="clipboard-document-check">
                                        Take Attendance Now
                                    </flux:button>
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- 3. Homeroom Class Pastoral Card (if designated Class Teacher) --}}
        @if ($stats['homeroom_class'])
            <div class="rounded-2xl border border-violet-200/80 bg-gradient-to-r from-violet-50/50 via-white to-violet-50/30 p-5 shadow-sm dark:border-violet-900/60 dark:bg-zinc-900">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-start gap-3.5">
                        <div class="size-11 rounded-xl bg-violet-600 text-white flex items-center justify-center shrink-0 shadow-sm">
                            <flux:icon icon="academic-cap" class="size-6" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded-full text-[10.5px] font-bold uppercase tracking-wider bg-violet-100 text-violet-800 dark:bg-violet-950/80 dark:text-violet-300 border border-violet-200 dark:border-violet-800">
                                    Designated Class Teacher
                                </span>
                                <span class="text-xs text-zinc-400">&bull;</span>
                                <span class="text-xs font-semibold text-zinc-600 dark:text-zinc-400">{{ $stats['homeroom_class']->curriculum?->name ?? 'Curriculum' }}</span>
                            </div>
                            <h3 class="text-lg font-black text-zinc-900 dark:text-white mt-1">
                                {{ $stats['homeroom_class']->name }}
                            </h3>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                                You hold homeroom pastoral responsibility for {{ $stats['homeroom_stats']['total_students'] ?? $stats['homeroom_class']->students_count }} enrolled learners.
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex items-center gap-3 px-4 py-2 rounded-xl bg-white dark:bg-zinc-800 border border-zinc-200/80 dark:border-zinc-700 shadow-xs text-xs">
                            <div>
                                <span class="text-zinc-400 text-[10px] uppercase font-bold block">Status</span>
                                <span class="font-bold {{ $stats['homeroom_stats'] && $stats['homeroom_stats']['is_completed'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">
                                    {{ $stats['homeroom_stats'] && $stats['homeroom_stats']['is_completed'] ? 'Saved' : 'Pending' }}
                                </span>
                            </div>
                            <div class="w-px h-6 bg-zinc-200 dark:bg-zinc-700"></div>
                            <div>
                                <span class="text-zinc-400 text-[10px] uppercase font-bold block">Present</span>
                                <span class="font-bold text-zinc-900 dark:text-white">
                                    {{ $stats['homeroom_stats']['present_count'] ?? 0 }} / {{ $stats['homeroom_stats']['total_students'] ?? $stats['homeroom_class']->students_count }}
                                </span>
                            </div>
                        </div>

                        <flux:button href="{{ route('attendance.register', ['classId' => $stats['homeroom_class']->id]) }}" wire:navigate.hover variant="filled" class="font-semibold shadow-sm justify-center">
                            Open Homeroom Register
                        </flux:button>
                    </div>
                </div>
            </div>
        @endif

        {{-- 4. My Teaching Allocations & Workload Grid --}}
        <div class="rounded-2xl border border-zinc-200/80 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-zinc-100 dark:border-zinc-800">
                <div>
                    <flux:heading size="lg" class="font-bold text-zinc-900 dark:text-white flex items-center gap-2">
                        <flux:icon icon="book-open" class="size-5 text-indigo-600 dark:text-indigo-400" />
                        <span>My Teaching Allocations &amp; Subject Marksheets</span>
                    </flux:heading>
                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                        Direct links to CBC competency evaluations, marksheet entry, and class registers.
                    </flux:text>
                </div>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                    {{ $stats['teaching_allocations']->count() }} Learning Areas Assigned
                </span>
            </div>

            @if ($stats['teaching_allocations']->isNotEmpty())
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-5">
                    @foreach ($stats['teaching_allocations'] as $allocation)
                        <div class="p-4 rounded-xl border border-zinc-200/80 bg-zinc-50/50 hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-800/30 dark:hover:bg-zinc-800/60 transition-all flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between gap-2 mb-2">
                                    <span class="px-2 py-0.5 rounded-md text-[10.5px] font-bold uppercase tracking-wider bg-zinc-200/80 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200">
                                        {{ $allocation->schoolClass?->name ?? 'Class' }}
                                    </span>
                                    <span class="text-[10px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300">
                                        {{ $allocation->schoolClass?->curriculum?->name ?? 'Curriculum' }}
                                    </span>
                                </div>
                                <h4 class="font-bold text-base text-zinc-900 dark:text-white">
                                    {{ $allocation->subject?->name ?? 'Subject' }}
                                </h4>
                                @if ($allocation->subject?->code)
                                    <span class="text-xs font-mono text-zinc-400">Code: {{ $allocation->subject->code }}</span>
                                @endif
                            </div>

                            <div class="mt-4 pt-3 border-t border-zinc-200/60 dark:border-zinc-700/60 flex flex-wrap items-center gap-2">
                                @if (Route::has('assessment.band.entry'))
                                    <flux:button href="{{ route('assessment.band.entry') }}" wire:navigate.hover size="xs" variant="filled">
                                        CBC Bands
                                    </flux:button>
                                @endif
                                @if (Route::has('result.entry.classes'))
                                    <flux:button href="{{ route('result.entry.classes') }}" wire:navigate.hover size="xs" variant="outline">
                                        Enter Marks
                                    </flux:button>
                                @endif
                                <flux:button href="{{ route('attendance.register', ['classId' => $allocation->class_id]) }}" wire:navigate.hover size="xs" variant="subtle" icon="clipboard-document-check">
                                    Register
                                </flux:button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="py-8 text-center text-xs text-zinc-500">
                    No specific teaching subject allocations recorded yet. You can still access homeroom and institution classes.
                </div>
            @endif
        </div>

        {{-- 5. Recent Classroom Assessments & Sittings --}}
        @if (!empty($stats['recent_assessments']) && count($stats['recent_assessments']) > 0)
            <div class="rounded-2xl border border-zinc-200/80 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex items-center justify-between pb-3 border-b border-zinc-100 dark:border-zinc-800">
                    <div>
                        <flux:heading size="lg" class="font-bold text-zinc-900 dark:text-white flex items-center gap-2">
                            <flux:icon icon="clipboard-document-list" class="size-5 text-emerald-600 dark:text-emerald-400" />
                            <span>Recent Assessments &amp; Sittings</span>
                        </flux:heading>
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                            Continuous formative assessments and summative exam sittings for your classes.
                        </flux:text>
                    </div>
                    @if (Route::has('assessment.formative'))
                        <flux:button href="{{ route('assessment.formative') }}" wire:navigate.hover size="sm" variant="filled" icon="plus">
                            New Formative Task
                        </flux:button>
                    @endif
                </div>

                <div class="mt-4 divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($stats['recent_assessments'] as $assessment)
                        <div class="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-sm text-zinc-900 dark:text-white">{{ $assessment['title'] }}</span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300">
                                        {{ $assessment['category_label'] }}
                                    </span>
                                </div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400 flex items-center gap-2 mt-0.5">
                                    <span>{{ $assessment['class_name'] }}</span>
                                    @if ($assessment['term'])
                                        <span>&bull;</span>
                                        <span>{{ $assessment['term'] }}</span>
                                    @endif
                                    <span>&bull;</span>
                                    <span>Created {{ $assessment['created_at'] }}</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                @if (Route::has('assessment.band.entry'))
                                    <flux:button href="{{ route('assessment.band.entry') }}" wire:navigate.hover size="xs" variant="outline">
                                        Grade Bands
                                    </flux:button>
                                @endif
                                @if (Route::has('result.entry.exams'))
                                    <flux:button href="{{ route('result.entry.exams') }}" wire:navigate.hover size="xs" variant="subtle">
                                        Enter Results
                                    </flux:button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endif
</div>
