{{-- Teacher Analytics Partial --}}
<div>
    @if (!$stats['institution'])
        <flux:callout variant="warning" icon="information-circle">
            <flux:callout.heading>Not yet assigned to a school</flux:callout.heading>
            <flux:callout.text>Ask your Director to add you to a campus roster.</flux:callout.text>
        </flux:callout>
    @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-kpi-card title="School" icon="building-office-2" color="indigo" :count="$stats['institution']->name" subtext="currently assigned" />
            <x-kpi-card title="Students" icon="user-group" color="sky" :count="$stats['students_count']" href="{{ route('student.index') }}">
                <div class="flex flex-wrap items-center gap-2 sm:gap-2.5 mt-1.5">
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
            <x-kpi-card title="Attendance Today" icon="clock" color="emerald" :count="$stats['attendance_today_count']" href="{{ route('attendance.index') }}" subtext="biometric check-ins" />
        </div>
    @endif
</div>
