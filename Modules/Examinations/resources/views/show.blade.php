@php
    $examDate = $examination->exam_date;
    $isToday = $examDate && $examDate->isToday();
    $isUpcoming = $examDate && $examDate->isFuture();
    $isPast = $examDate && $examDate->isPast() && !$isToday;
@endphp

<x-layouts::app :title="__('Examination Details')">
    <div class="flex flex-col gap-6 w-full max-w-5xl mx-auto p-4 sm:p-6 lg:p-8">

        {{-- Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 text-xs text-zinc-500 mb-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-zinc-800 dark:hover:text-zinc-200">Dashboard</a>
                    <span>/</span>
                    <a href="{{ route('examinations.index') }}" class="hover:text-zinc-800 dark:hover:text-zinc-200">Examinations</a>
                    <span>/</span>
                    <span class="text-zinc-800 dark:text-zinc-200 font-medium">{{ $examination->title }}</span>
                </div>
                <div class="flex items-center gap-3">
                    <flux:heading size="xl" class="font-bold tracking-tight text-zinc-900 dark:text-white">
                        {{ $examination->title }}
                    </flux:heading>
                    @if ($isToday)
                        <flux:badge color="emerald" size="sm" class="font-semibold">
                            <span class="size-1.5 rounded-full bg-emerald-500 mr-1.5 animate-pulse"></span>
                            Today
                        </flux:badge>
                    @elseif ($isUpcoming)
                        <flux:badge color="sky" size="sm" class="font-medium">
                            Upcoming
                        </flux:badge>
                    @elseif ($isPast)
                        <flux:badge color="zinc" size="sm" class="font-medium">
                            Completed
                        </flux:badge>
                    @endif
                </div>
                <flux:text class="text-zinc-500 text-sm mt-1 flex items-center gap-2">
                    <span>{{ $examination->subject?->name ?? $examination->subject_name }}</span>
                    <span>&bull;</span>
                    <span>{{ $examination->schoolClass?->name ?? $examination->class_name }}</span>
                    @if ($examination->institution)
                        <span>&bull;</span>
                        <span class="text-zinc-400">{{ $examination->institution->name }}</span>
                    @endif
                </flux:text>
            </div>
            
            <div class="flex items-center gap-2.5">
                <flux:button href="{{ route('examinations.index') }}" icon="arrow-left" variant="ghost" class="hover:bg-zinc-100 dark:hover:bg-zinc-800">
                    Back to Exams
                </flux:button>
                
                @can('edit examination')
                    <flux:button href="{{ route('examinations.edit', $examination->id) }}" icon="pencil" variant="primary" class="shadow-sm">
                        Edit Exam
                    </flux:button>
                @endcan

                @can('delete examination')
                    <form action="{{ route('examinations.destroy', $examination->id) }}" method="POST" onsubmit="return confirm('Delete this examination?');" class="inline">
                        @csrf
                        @method('DELETE')
                        <flux:button type="submit" icon="trash" variant="subtle" class="text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10">
                            Delete
                        </flux:button>
                    </form>
                @endcan
            </div>
        </div>

        {{-- Success Banner --}}
        @if (session('success'))
            <div class="rounded-xl border border-emerald-200/50 bg-emerald-50/50 dark:bg-emerald-500/10 dark:border-emerald-500/20 p-4 flex items-start shadow-sm">
                <flux:icon icon="check-circle" class="size-5 text-emerald-500 mr-3 mt-0.5" />
                <div class="text-sm font-medium text-emerald-800 dark:text-emerald-400">
                    {{ session('success') }}
                </div>
            </div>
        @endif

        {{-- Overview Metric Cards --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-kpi-card 
                title="Subject" 
                icon="book-open" 
                color="indigo" 
                :count="$examination->subject?->name ?? $examination->subject_name" 
                subtext="curriculum subject" 
            />
            <x-kpi-card 
                title="Target Class" 
                icon="academic-cap" 
                color="violet" 
                :count="$examination->schoolClass?->name ?? $examination->class_name" 
                subtext="registered stream" 
            />
            <x-kpi-card 
                title="Scheduled Date" 
                icon="calendar" 
                color="sky" 
                :count="$examDate?->format('d M Y') ?? 'TBA'" 
                :subtext="$examination->start_time ? $examination->start_time->format('H:i') . ' - ' . ($examination->end_time?->format('H:i') ?? '') : 'time not set'" 
            />
            <x-kpi-card 
                title="Scoring Rule" 
                icon="chart-bar" 
                color="emerald" 
                :count="$examination->total_marks . ' pts'" 
                :subtext="$examination->passing_marks ? 'pass threshold: ' . $examination->passing_marks . ' pts' : 'no pass mark set'" 
            />
        </div>

        {{-- Assessment Details Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Academic & Term Info --}}
            <div class="bg-white dark:bg-zinc-900 shadow-[0_1px_3px_0_rgba(0,0,0,0.05)] border border-zinc-200 dark:border-zinc-800 rounded-xl p-6 flex flex-col gap-4">
                <div class="flex items-center gap-2.5 pb-3 border-b border-zinc-100 dark:border-zinc-800">
                    <div class="size-8 rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-400 flex items-center justify-center">
                        <flux:icon icon="bookmark-square" class="size-4" />
                    </div>
                    <flux:heading size="sm" class="font-semibold text-zinc-900 dark:text-white">Academic Details</flux:heading>
                </div>
                
                <div class="grid grid-cols-2 gap-4 text-xs">
                    <div>
                        <span class="text-zinc-400 uppercase font-semibold text-[10px]">Academic Term</span>
                        <p class="text-sm font-semibold text-zinc-900 dark:text-white mt-0.5">{{ $examination->term ?? '—' }}</p>
                    </div>
                    <div>
                        <span class="text-zinc-400 uppercase font-semibold text-[10px]">Academic Year</span>
                        <p class="text-sm font-semibold text-zinc-900 dark:text-white mt-0.5">{{ $examination->academic_year ?? '—' }}</p>
                    </div>
                    <div>
                        <span class="text-zinc-400 uppercase font-semibold text-[10px]">Campus</span>
                        <p class="text-sm font-semibold text-zinc-900 dark:text-white mt-0.5">{{ $examination->institution?->name ?? 'Default Campus' }}</p>
                    </div>
                    <div>
                        <span class="text-zinc-400 uppercase font-semibold text-[10px]">Results Recorded</span>
                        <p class="text-sm font-semibold text-zinc-900 dark:text-white mt-0.5">{{ $examination->results()->count() }} students</p>
                    </div>
                </div>
            </div>

            {{-- Schedule & Grading Details --}}
            <div class="bg-white dark:bg-zinc-900 shadow-[0_1px_3px_0_rgba(0,0,0,0.05)] border border-zinc-200 dark:border-zinc-800 rounded-xl p-6 flex flex-col gap-4">
                <div class="flex items-center gap-2.5 pb-3 border-b border-zinc-100 dark:border-zinc-800">
                    <div class="size-8 rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400 flex items-center justify-center">
                        <flux:icon icon="clock" class="size-4" />
                    </div>
                    <flux:heading size="sm" class="font-semibold text-zinc-900 dark:text-white">Timetable & Criteria</flux:heading>
                </div>
                
                <div class="grid grid-cols-2 gap-4 text-xs">
                    <div>
                        <span class="text-zinc-400 uppercase font-semibold text-[10px]">Exam Date</span>
                        <p class="text-sm font-semibold text-zinc-900 dark:text-white mt-0.5">{{ $examDate?->format('l, d M Y') ?? '—' }}</p>
                    </div>
                    <div>
                        <span class="text-zinc-400 uppercase font-semibold text-[10px]">Exam Window</span>
                        <p class="text-sm font-semibold text-zinc-900 dark:text-white mt-0.5">
                            @if ($examination->start_time)
                                {{ $examination->start_time->format('H:i') }} &ndash; {{ $examination->end_time?->format('H:i') }}
                            @else
                                Full Day / Unset
                            @endif
                        </p>
                    </div>
                    <div>
                        <span class="text-zinc-400 uppercase font-semibold text-[10px]">Total Score</span>
                        <p class="text-sm font-bold text-zinc-900 dark:text-white mt-0.5">{{ $examination->total_marks }} Marks</p>
                    </div>
                    <div>
                        <span class="text-zinc-400 uppercase font-semibold text-[10px]">Passing Score</span>
                        <p class="text-sm font-bold text-blue-600 dark:text-blue-400 mt-0.5">{{ $examination->passing_marks ?? '—' }} Marks</p>
                    </div>
                </div>
            </div>

            {{-- Notes Section --}}
            @if ($examination->notes)
                <div class="md:col-span-2 bg-zinc-50 dark:bg-zinc-800/40 rounded-xl border border-zinc-200 dark:border-zinc-800 p-5 flex items-start gap-3">
                    <flux:icon icon="information-circle" class="size-5 text-zinc-400 mt-0.5 shrink-0" />
                    <div>
                        <flux:heading size="sm" class="font-semibold text-zinc-900 dark:text-white">Additional Instructions</flux:heading>
                        <p class="text-xs text-zinc-600 dark:text-zinc-300 whitespace-pre-line leading-relaxed mt-1">{{ $examination->notes }}</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-layouts::app>
