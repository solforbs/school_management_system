@if (!$stats['institution'])
    {{-- Modern Unassigned Faculty Onboarding State Card --}}
    <div class="bg-white dark:bg-zinc-900 rounded-3xl p-8 sm:p-12 border border-zinc-200/80 dark:border-zinc-800 shadow-sm relative overflow-hidden text-center max-w-3xl mx-auto">
        <div class="size-16 rounded-2xl bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 flex items-center justify-center mx-auto mb-4 border border-indigo-100 dark:border-indigo-800">
            <flux:icon icon="academic-cap" class="size-8" />
        </div>

        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-400 border border-amber-200/70 mb-3">
            <span class="size-1.5 rounded-full bg-amber-500 animate-ping"></span>
            Profile Linkage Pending
        </span>

        <h3 class="text-xl font-black tracking-tight text-zinc-900 dark:text-white">Faculty Account Pending Campus Assignment</h3>
        <p class="text-zinc-500 dark:text-zinc-400 text-xs sm:text-sm max-w-lg mx-auto mt-2 mb-6">
            Your instructor account has been created, but it has not yet been linked to an active campus or teaching roster.
        </p>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-left my-6 p-4 rounded-2xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200/60 dark:border-zinc-800 text-xs">
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
    {{-- Teacher Operational KPI Tiles --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-kpi-card title="Assigned Campus" icon="building-office-2" color="indigo" :count="$stats['institution']->name" subtext="active academic institution" />

        <x-kpi-card title="Enrolled Students" icon="academic-cap" color="sky" :count="$stats['students_count']" href="{{ route('student.index') }}" subtext="learners in institution" />

        <x-kpi-card title="Attendance Today" icon="clock" color="emerald" :count="$stats['attendance_today_count']" href="{{ route('attendance.index') }}" subtext="biometric & lesson check-ins" />
    </div>

    {{-- Quick Action Links for Teachers --}}
    <div class="bg-white dark:bg-zinc-900 rounded-2xl p-5 border border-zinc-200/80 dark:border-zinc-800 shadow-xs space-y-4">
        <div>
            <h3 class="text-sm font-bold text-zinc-900 dark:text-zinc-100">Academic Delivery Hub</h3>
            <p class="text-xs text-zinc-400 mt-0.5">Quick access to daily teaching registers, examination score entries, and class rosters.</p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            @can('view attendance')
                <flux:button href="{{ route('attendance.index') }}" icon="clock" variant="primary" class="font-semibold text-xs shadow-sm">
                    Mark Daily Attendance
                </flux:button>
            @endcan

            @can('view student')
                <flux:button href="{{ route('student.index') }}" icon="users" variant="subtle" class="font-semibold text-xs">
                    View Student Roster
                </flux:button>
            @endcan

            @can('view examination')
                <flux:button href="{{ route('examinations.index') }}" icon="pencil-square" variant="subtle" class="font-semibold text-xs">
                    Examinations & Scoring
                </flux:button>
            @endcan

            @can('view classes')
                <flux:button href="{{ route('classes.index') }}" icon="rectangle-group" variant="ghost" class="font-semibold text-xs">
                    Classes & Streams
                </flux:button>
            @endcan
        </div>
    </div>
@endif
