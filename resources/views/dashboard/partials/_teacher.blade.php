@if (!$stats['institution'])
    {{-- Modern Unassigned Faculty Onboarding State Card --}}
    <div class="bg-white dark:bg-zinc-900 rounded-3xl p-5 sm:p-8 md:p-12 border border-zinc-200/80 dark:border-zinc-800 shadow-sm relative overflow-hidden text-center max-w-3xl mx-auto">
        <div class="size-16 rounded-2xl bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 flex items-center justify-center mx-auto mb-4 border border-indigo-100 dark:border-indigo-800">
            <flux:icon icon="academic-cap" class="size-8" />
        </div>

        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-400 border border-amber-200/70 mb-3">
            <span class="size-1.5 rounded-full bg-amber-500 animate-ping"></span>
            Profile Linkage Pending
        </span>

        <h3 class="text-lg sm:text-xl font-black tracking-tight text-zinc-900 dark:text-white">Faculty Account Pending Campus Assignment</h3>
        <p class="text-zinc-500 dark:text-zinc-400 text-xs sm:text-sm max-w-lg mx-auto mt-2 mb-6">
            Your instructor account has been created, but it has not yet been linked to an active campus or teaching roster.
        </p>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-left my-6 p-3 sm:p-4 rounded-2xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200/60 dark:border-zinc-800 text-xs">
            <div class="space-y-1">
                <span class="font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-1.5">
                    <span class="size-5 rounded-full bg-indigo-600 text-white flex items-center justify-center text-[10px] font-mono">1</span>
                    Campus Linkage
                </span>
                <p class="text-zinc-400 text-[11px]">Administrator assigns your school campus profile.</p>
            </div>
            <div class="space-y-1">
                <span class="font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-1.5">
                    <span class="size-5 rounded-full bg-indigo-600 text-white flex items-center justify-center text-[10px] font-mono">2</span>
                    Class Allocation
                </span>
                <p class="text-zinc-400 text-[11px]">Assigned as class teacher or subject instructor.</p>
            </div>
            <div class="space-y-1">
                <span class="font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-1.5">
                    <span class="size-5 rounded-full bg-indigo-600 text-white flex items-center justify-center text-[10px] font-mono">3</span>
                    Grading & Attendance
                </span>
                <p class="text-zinc-400 text-[11px]">Instant access to student marks and lesson registers.</p>
            </div>
        </div>

        <div class="flex items-center justify-center gap-3 pt-2">
            <span class="text-xs text-zinc-400">Please reach out to your school Director or Administrator to complete roster assignment.</span>
        </div>
    </div>
@else
    {{-- 1. Homeroom / Class Teacher Workspace (if designated as class teacher) --}}
    @if (!empty($stats['homeroom_class']))
        @php
            $homeroom = $stats['homeroom_class'];
            $isCbcHomeroom = $homeroom->curriculum?->isCbc() ?? false;
        @endphp
        <div class="rounded-2xl bg-white dark:bg-zinc-900 p-5 sm:p-6 border border-zinc-200/90 dark:border-zinc-800 shadow-xs">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="space-y-1.5">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300 border border-amber-200/80 dark:border-amber-800/60">
                            <flux:icon icon="star" class="size-3 text-amber-500" />
                            Homeroom Class Teacher
                        </span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold {{ $isCbcHomeroom ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800' : 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700' }}">
                            {{ $homeroom->curriculum?->name ?? 'Standard' }}
                        </span>
                    </div>
                    <h2 class="text-xl sm:text-2xl font-black tracking-tight text-zinc-900 dark:text-white flex items-center gap-2">
                        <span translate="no">{{ $homeroom->name }}</span>
                        <span class="text-xs font-normal text-zinc-500 dark:text-zinc-400">({{ $homeroom->students_count ?? 0 }} Registered Learners)</span>
                    </h2>
                    <p class="text-xs sm:text-sm text-zinc-500 dark:text-zinc-400 max-w-xl">
                        You are the designated homeroom teacher. You oversee daily roll-call, learner pastoral welfare, and consolidated end-of-term report card remarks.
                    </p>
                </div>

                <div class="flex flex-wrap sm:flex-col gap-2 shrink-0">
                    <flux:button href="{{ route('attendance.register', ['classId' => $homeroom->id]) }}" wire:navigate.hover icon="clock" variant="primary" class="font-semibold text-xs shadow-xs">
                        Mark Class Roll-Call
                    </flux:button>
                    <flux:button href="{{ route('student.index') }}" wire:navigate.hover icon="users" variant="subtle" class="font-semibold text-xs">
                        Class Roster ({{ $homeroom->students_count ?? 0 }})
                    </flux:button>
                    <flux:button href="{{ route('reportcard.index') }}" wire:navigate.hover icon="document-chart-bar" variant="subtle" class="font-semibold text-xs">
                        Term Report Cards
                    </flux:button>
                </div>
            </div>
        </div>
    @endif

    {{-- 2. My Teaching Allocations (Class + Subject Pairs) --}}
    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-sm font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                    <flux:icon icon="book-open" class="size-4 text-zinc-700 dark:text-zinc-300" />
                    My Teaching Allocations
                </h3>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                    Classes and subjects assigned to you. One-click access to open marksheets and record assessments.
                </p>
            </div>
        </div>

        @if (!empty($stats['teaching_allocations']) && $stats['teaching_allocations']->isNotEmpty())
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
                @foreach ($stats['teaching_allocations'] as $alloc)
                    @php
                        $isCbc = $alloc->schoolClass?->curriculum?->isCbc() ?? false;
                        $latestExam = \Modules\Examinations\Models\Examination::where('class_id', $alloc->class_id)
                            ->where('subject_id', $alloc->subject_id)
                            ->latest('id')
                            ->first();
                    @endphp
                    <div class="bg-white dark:bg-zinc-900 rounded-2xl p-4 border border-zinc-200/80 dark:border-zinc-800 shadow-xs hover:border-zinc-400 dark:hover:border-zinc-600 transition flex flex-col justify-between gap-3">
                        <div class="space-y-1">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                                    <span translate="no">{{ $alloc->schoolClass?->name ?? 'Class' }}</span>
                                </span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold {{ $isCbc ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800' : 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300 border border-amber-200 dark:border-amber-800' }}">
                                    {{ $isCbc ? 'CBC' : '8-4-4' }}
                                </span>
                            </div>
                            <h4 class="text-base font-black text-zinc-900 dark:text-zinc-100">
                                {{ $alloc->subject?->name ?? 'Subject' }}
                            </h4>
                            <p class="text-xs text-zinc-400">
                                {{ $isCbc ? 'Competency-Based Assessment (EE-BE bands)' : 'Standard numerical scoring (out of 100)' }}
                            </p>
                        </div>

                        <div class="pt-2 border-t border-zinc-100 dark:border-zinc-800/80 flex flex-wrap items-center gap-2">
                            @if ($isCbc)
                                <flux:button href="{{ route('assessment.formative', ['classId' => $alloc->class_id]) }}" wire:navigate.hover icon="sparkles" variant="primary" class="font-semibold text-xs flex-1">
                                    Record Assessment
                                </flux:button>
                                <flux:button href="{{ $latestExam ? route('assessment.band.entry', ['examination_id' => $latestExam->id]) : route('assessment.formative', ['classId' => $alloc->class_id]) }}" wire:navigate.hover icon="table-cells" variant="subtle" class="font-semibold text-xs">
                                    Marksheet
                                </flux:button>
                            @else
                                <flux:button href="{{ $latestExam ? route('result.entry.create', ['examination_id' => $latestExam->id]) : route('result.entry.exams') }}" wire:navigate.hover icon="table-cells" variant="primary" class="font-semibold text-xs flex-1">
                                    Enter 8-4-4 Marks
                                </flux:button>
                            @endif
                            @can('view student')
                                <flux:button href="{{ route('student.index', ['class_id' => $alloc->class_id]) }}" wire:navigate.hover icon="users" variant="subtle" class="font-semibold text-xs">
                                    Learners
                                </flux:button>
                            @endcan
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="rounded-xl border border-dashed border-zinc-300 dark:border-zinc-700 p-6 text-center text-xs text-zinc-500">
                No teaching subjects assigned yet. Please contact the administrator or director to allocate your subjects.
            </div>
        @endif
    </div>

    {{-- 3. Teacher Operational KPI Tiles --}}
    <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-3 sm:gap-4">
        <x-kpi-card title="Assigned Campus" icon="building-office-2" color="zinc" :count="$stats['institution']->name" subtext="active academic institution" />

        @php
            $teacherMaleCount = $stats['male_students_count'] ?? 0;
            $teacherFemaleCount = $stats['female_students_count'] ?? 0;
        @endphp
        <x-kpi-card title="My Students" icon="academic-cap" color="zinc" :count="$stats['students_count']" href="{{ route('student.index') }}" subtext="enrolled in your assigned classes">
            <div class="flex flex-wrap items-center gap-2 sm:gap-2.5 mt-1.5">
                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-blue-50/90 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 text-[11px] font-semibold border border-blue-200/70 dark:border-blue-800/50">
                    <svg class="size-3 text-blue-600 dark:text-blue-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <circle cx="10" cy="14" r="5" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5L20 4m0 0h-4.5m4.5 0v4.5" />
                    </svg>
                    <span>Male: <strong class="font-bold text-zinc-900 dark:text-white">{{ number_format($teacherMaleCount) }}</strong></span>
                </span>
                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-pink-50/90 dark:bg-pink-950/40 text-pink-700 dark:text-pink-300 text-[11px] font-semibold border border-pink-200/70 dark:border-pink-800/50">
                    <svg class="size-3 text-pink-600 dark:text-pink-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <circle cx="12" cy="9" r="5" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 14v7m-3-3.5h6" />
                    </svg>
                    <span>Female: <strong class="font-bold text-zinc-900 dark:text-white">{{ number_format($teacherFemaleCount) }}</strong></span>
                </span>
            </div>
        </x-kpi-card>

        <x-kpi-card
            title="Attendance Today"
            icon="clock"
            :color="($stats['rollcall_attendance_rate'] ?? null) !== null ? 'emerald' : (($stats['biometric_scans_today_count'] ?? 0) > 0 ? 'sky' : 'amber')"
            :count="($stats['rollcall_attendance_rate'] ?? null) !== null ? ($stats['rollcall_attendance_rate'] . '%') : (($stats['biometric_scans_today_count'] ?? 0) > 0 ? $stats['biometric_scans_today_count'] : '0%')"
            href="{{ route('attendance.index') }}"
        >
            <div class="flex flex-col gap-1 mt-1.5">
                @if (($stats['rollcall_attendance_rate'] ?? null) !== null)
                    <div class="text-[11px] font-semibold text-zinc-700 dark:text-zinc-300 flex items-center gap-1">
                        <span class="size-1.5 rounded-full bg-emerald-500 shrink-0"></span>
                        <span>Manual: <strong class="text-emerald-700 dark:text-emerald-400 font-bold">{{ $stats['rollcall_attendance_rate'] }}%</strong> ({{ $stats['rollcall_present_count'] ?? 0 }}/{{ $stats['rollcall_total_marked'] ?? 0 }} Present)</span>
                    </div>
                    <div class="text-[10.5px] font-medium {{ ($stats['biometric_scans_today_count'] ?? 0) > 0 ? 'text-sky-600 dark:text-sky-400' : 'text-zinc-500 dark:text-zinc-400' }}">
                        @if (($stats['biometric_scans_today_count'] ?? 0) > 0)
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
                        @if (($stats['biometric_scans_today_count'] ?? 0) > 0)
                            ⚡ {{ $stats['biometric_scans_today_count'] }} biometric gate check-ins recorded
                        @else
                            Biometric check-in not done (0 check-ins)
                        @endif
                    </div>
                @endif
            </div>
        </x-kpi-card>
    </div>
@endif
