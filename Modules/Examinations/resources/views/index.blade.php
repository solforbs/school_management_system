@php
    $canViewSubjects = Route::has('subject.index') && auth()->user()->can('view subject');
    $hasActiveFilters =
        request()->hasAny(['search', 'subject_id', 'class_id', 'timeline']) &&
        (request('search') || request('subject_id') || request('class_id') || request('timeline'));
@endphp

<x-layouts::app :title="__(config('examinations.name'))">
    <div class="flex flex-col gap-6 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">

        {{-- Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2.5">
                    <flux:heading size="xl" class="font-bold tracking-tight text-zinc-900 dark:text-white">
                        Examinations & Assessments
                    </flux:heading>
                    <flux:badge size="sm" color="indigo" class="font-semibold">
                        {{ $totalExams }} {{ Str::plural('Assessment', $totalExams) }}
                    </flux:badge>
                </div>
                <flux:text class="text-zinc-500 text-sm mt-1">
                    Manage examination timetables, assessment scoring criteria, and grading schedules.
                </flux:text>
            </div>

            <div class="flex items-center gap-3">
                @can('create examination')
                    <flux:button href="{{ route('examinations.create') }}" icon="plus" variant="primary"
                        class="shadow-sm hover:shadow-md transition-all">
                        Add Examination
                    </flux:button>
                @endcan
            </div>
        </div>

        {{-- Success Banner --}}
        @if (session('success'))
            <div
                class="rounded-xl border border-emerald-200/50 bg-emerald-50/50 dark:bg-emerald-500/10 dark:border-emerald-500/20 p-4 flex items-start shadow-sm">
                <flux:icon icon="check-circle" class="size-5 text-emerald-500 mr-3 mt-0.5" />
                <div class="text-sm font-medium text-emerald-800 dark:text-emerald-400">
                    {{ session('success') }}
                </div>
            </div>
        @endif

        {{-- Animated KPI Metrics Grid --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-kpi-card title="Total Exams" icon="academic-cap" color="indigo" :count="$totalExams"
                subtext="configured assessments" :href="route('examinations.index')" />
            <x-kpi-card title="Upcoming" icon="calendar-days" color="sky" :count="$upcomingCount"
                subtext="scheduled assessments" :href="route('examinations.index', ['timeline' => 'upcoming'])" />
            <x-kpi-card title="Completed" icon="clipboard-document-check" color="emerald" :count="$pastCount"
                subtext="past assessment dates" :href="route('examinations.index', ['timeline' => 'past'])" />
            <x-kpi-card title="Tested Subjects" icon="book-open" color="violet" :count="$distinctSubjectsCount"
                subtext="unique subject areas" :href="$canViewSubjects ? route('subject.index') : null" />
        </div>

        {{-- Search & Filter Controls --}}
        <form method="GET" action="{{ route('examinations.index') }}"
            class="bg-white dark:bg-zinc-900 shadow-[0_1px_3px_0_rgba(0,0,0,0.05)] border border-zinc-200 dark:border-zinc-800 rounded-xl p-3.5 sm:p-4 flex flex-col md:flex-row items-stretch md:items-center gap-3">
            {{-- Search Bar --}}
            <div class="relative flex-1">
                <flux:input name="search" value="{{ request('search') }}"
                    placeholder="Search by exam title, term, subject, or class..." icon="magnifying-glass"
                    class="w-full" clearable />
            </div>

            {{-- Filter by Subject --}}
            <div class="w-full md:w-48">
                <flux:select name="subject_id" placeholder="All Subjects" onchange="this.form.submit()">
                    <flux:select.option value="">All Subjects</flux:select.option>
                    @foreach ($subjects as $subject)
                        <flux:select.option value="{{ $subject->id }}"
                            :selected="request('subject_id') == $subject->id">
                            {{ $subject->name }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            {{-- Filter by Class --}}
            <div class="w-full md:w-44">
                <flux:select name="class_id" placeholder="All Classes" onchange="this.form.submit()">
                    <flux:select.option value="">All Classes</flux:select.option>
                    @foreach ($classes as $schoolClass)
                        <flux:select.option value="{{ $schoolClass->id }}"
                            :selected="request('class_id') == $schoolClass->id">
                            {{ $schoolClass->name }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            {{-- Filter by Timeline / Status --}}
            <div class="w-full md:w-36">
                <flux:select name="timeline" placeholder="All Dates" onchange="this.form.submit()">
                    <flux:select.option value="">All Dates</flux:select.option>
                    <flux:select.option value="today" :selected="request('timeline') === 'today'">Today
                    </flux:select.option>
                    <flux:select.option value="upcoming" :selected="request('timeline') === 'upcoming'">Upcoming
                    </flux:select.option>
                    <flux:select.option value="past" :selected="request('timeline') === 'past'">Completed
                    </flux:select.option>
                </flux:select>
            </div>

            {{-- Filter Actions --}}
            <div class="flex items-center gap-2">
                <flux:button type="submit" variant="primary" size="sm" class="px-4">
                    Filter
                </flux:button>
                @if ($hasActiveFilters)
                    <flux:button href="{{ route('examinations.index') }}" variant="subtle" size="sm"
                        icon="x-mark" tooltip="Clear all filters">
                        Clear
                    </flux:button>
                @endif
            </div>
        </form>

        {{-- Table Card --}}
        <div
            class="bg-white dark:bg-zinc-900 shadow-[0_1px_3px_0_rgba(0,0,0,0.05)] border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden flex flex-col">
            <div class="overflow-x-auto">
                <flux:table class="p-2">
                    <flux:table.columns>
                        <x-sortable-column column="title">Exam Title & Term</x-sortable-column>
                        <flux:table.column>Subject & Class</flux:table.column>
                        <x-sortable-column column="exam_date">Schedule & Status</x-sortable-column>
                        <x-sortable-column column="total_marks">Scoring</x-sortable-column>
                        <flux:table.column align="end">Actions</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($examinations as $examination)
                            @php
                                $examDate = $examination->exam_date;
                                $isToday = $examDate && $examDate->isToday();
                                $isUpcoming = $examDate && $examDate->isFuture();
                                $isPast = $examDate && $examDate->isPast() && !$isToday;
                            @endphp
                            <flux:table.row
                                class="transition-colors hover:bg-zinc-50/50 dark:hover:bg-zinc-800/20 group">
                                {{-- Exam Title & Term --}}
                                <flux:table.cell>
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="size-8 rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-400 ring-1 ring-inset ring-indigo-500/20 flex items-center justify-center font-bold text-xs shrink-0 transition-transform group-hover:scale-105">
                                            <flux:icon icon="academic-cap" class="size-4" />
                                        </div>
                                        <div class="flex flex-col gap-0.5">
                                            <a href="{{ route('examinations.show', $examination->id) }}"
                                                class="font-semibold text-sm text-zinc-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                                                {{ $examination->title }}
                                            </a>
                                            <div class="flex items-center gap-1.5 text-[11px] text-zinc-500">
                                                @if ($examination->term)
                                                    <flux:badge size="sm" color="zinc"
                                                        class="font-medium text-[10px]">
                                                        {{ $examination->term }}
                                                    </flux:badge>
                                                @endif
                                                @if (isAdmin() && $examination->institution)
                                                    <span class="truncate max-w-[140px] text-zinc-400">&bull;
                                                        {{ $examination->institution->name }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </flux:table.cell>

                                {{-- Subject & Class --}}
                                <flux:table.cell>
                                    <div class="flex flex-col gap-0.5">
                                        <span class="font-medium text-xs text-zinc-900 dark:text-zinc-100">
                                            {{ $examination->subject?->name ?? $examination->subject_name }}
                                        </span>
                                        <div class="flex items-center gap-1 text-[11px] text-zinc-500">
                                            <flux:icon icon="user-group" class="size-3 text-zinc-400 shrink-0" />
                                            <span>{{ $examination->schoolClass?->name ?? $examination->class_name }}</span>
                                        </div>
                                    </div>
                                </flux:table.cell>

                                {{-- Schedule & Status --}}
                                <flux:table.cell>
                                    <div class="flex flex-col gap-1">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-medium text-zinc-900 dark:text-zinc-100">
                                                {{ $examDate?->format('d M Y') ?? 'TBA' }}
                                            </span>
                                            @if ($isToday)
                                                <flux:badge color="emerald" size="sm"
                                                    class="font-semibold text-[10px]">
                                                    <span
                                                        class="size-1.5 rounded-full bg-emerald-500 mr-1 animate-pulse"></span>
                                                    Today
                                                </flux:badge>
                                            @elseif ($isUpcoming)
                                                <flux:badge color="sky" size="sm"
                                                    class="font-medium text-[10px]">
                                                    Upcoming
                                                </flux:badge>
                                            @elseif ($isPast)
                                                <flux:badge color="zinc" size="sm"
                                                    class="font-medium text-[10px]">
                                                    Completed
                                                </flux:badge>
                                            @endif
                                        </div>
                                        @if ($examination->start_time || $examination->end_time)
                                            <div class="flex items-center gap-1 text-[11px] text-zinc-400 font-mono">
                                                <flux:icon icon="clock" class="size-3 shrink-0" />
                                                <span>{{ $examination->start_time?->format('H:i') ?? '--:--' }} &ndash;
                                                    {{ $examination->end_time?->format('H:i') ?? '--:--' }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </flux:table.cell>

                                {{-- Scoring --}}
                                <flux:table.cell>
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono text-xs font-bold text-zinc-900 dark:text-white">
                                            {{ $examination->total_marks }} <span
                                                class="font-normal text-zinc-400 text-[11px]">pts</span>
                                        </span>
                                        @if ($examination->passing_marks)
                                            <flux:badge color="blue" size="sm" class="font-mono text-[10px]">
                                                Pass: {{ $examination->passing_marks }}
                                            </flux:badge>
                                        @endif
                                    </div>
                                </flux:table.cell>

                                {{-- Actions --}}
                                <flux:table.cell align="end">
                                    <div class="flex items-center justify-end gap-1">
                                        <flux:button href="{{ route('examinations.show', $examination->id) }}"
                                            icon="eye" size="sm" variant="subtle"
                                            tooltip="View Examination" />

                                        @can('edit examination')
                                            <flux:button href="{{ route('examinations.edit', $examination->id) }}"
                                                icon="pencil" size="sm" variant="subtle"
                                                tooltip="Edit Examination" />
                                        @endcan

                                        @can('delete examination')
                                            <form action="{{ route('examinations.destroy', $examination->id) }}"
                                                method="POST" class="inline"
                                                onsubmit="return confirm('Remove {{ $examination->title }} permanently?');">
                                                @csrf
                                                @method('DELETE')
                                                <flux:button type="submit" icon="trash" size="sm"
                                                    variant="subtle"
                                                    class="text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10"
                                                    tooltip="Delete Examination" />
                                            </form>
                                        @endcan
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="5">
                                    <x-empty-state icon="academic-cap" color="indigo" title="No Examinations Found"
                                        description="No academic assessments match your current search or filter criteria.">
                                        @if ($hasActiveFilters)
                                            <flux:button href="{{ route('examinations.index') }}" icon="arrow-path"
                                                variant="subtle">
                                                Clear Filters
                                            </flux:button>
                                        @else
                                            @can('create examination')
                                                <flux:button href="{{ route('examinations.create') }}" icon="plus"
                                                    variant="primary">
                                                    Add First Examination
                                                </flux:button>
                                            @endcan
                                        @endif
                                    </x-empty-state>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>

            {{-- Pagination Footer --}}
            @if ($examinations->hasPages() || $examinations->total() > 0)
                <div
                    class="px-6 py-4 border-t border-zinc-100 dark:border-zinc-800 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-zinc-500">
                    <div>
                        Showing <strong
                            class="text-zinc-800 dark:text-zinc-200 font-semibold">{{ $examinations->firstItem() ?? 0 }}</strong>
                        to <strong
                            class="text-zinc-800 dark:text-zinc-200 font-semibold">{{ $examinations->lastItem() ?? 0 }}</strong>
                        of <strong
                            class="text-zinc-800 dark:text-zinc-200 font-semibold">{{ $examinations->total() }}</strong>
                        assessments
                    </div>
                    @if ($examinations->hasPages())
                        <div>
                            {{ $examinations->links() }}
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-layouts::app>
