@php
    $totalClasses = $classes->count();
    $totalStudents = $classes->sum(fn($c) => $c->students()->count());
    $totalMaleStudents = $classes->sum(fn($c) => $c->students()->where('gender', 'male')->count());
    $totalFemaleStudents = $classes->sum(fn($c) => $c->students()->where('gender', 'female')->count());
    $totalCapacity = $classes->sum(fn($c) => (int) ($c->capacity ?? 0));
    $capacityRate = $totalCapacity > 0 ? round(($totalStudents / $totalCapacity) * 100) : 0;
    $assignedTeachersCount = $classes->filter(fn($c) => (bool) $c->class_teacher_id)->count();

    $canViewStudents = Route::has('student.index') && auth()->user()->can('view student');
    $canViewStaff = Route::has('staff.index') && auth()->user()->can('view staff');
@endphp

<x-layouts::app :title="__(config('classes.name'))">
    <div class="flex flex-col gap-6 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">

        {{-- Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2.5">
                    <flux:heading size="xl" class="font-bold tracking-tight text-zinc-900 dark:text-white">
                        Classes & Streams
                    </flux:heading>
                    <flux:badge size="sm" color="indigo" class="font-semibold">
                        {{ $totalClasses }} Active Streams
                    </flux:badge>
                </div>
                <flux:text class="text-zinc-500 text-sm mt-1">
                    Manage academic levels, monitor classroom capacities, and assign class teachers.
                </flux:text>
            </div>

            <div class="flex items-center gap-3">
                @can('create classes')
                    <flux:button href="{{ route('classes.create') }}" icon="plus" variant="primary"
                        class="shadow-sm hover:shadow-md transition-all">
                        Add Class
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
            <x-kpi-card title="Total Classes" icon="academic-cap" color="indigo" :count="$totalClasses"
                subtext="active classroom streams" :href="route('classes.index')" />
            <x-kpi-card title="Enrolled Students" icon="users" color="violet" :count="$totalStudents"
                :href="$canViewStudents ? route('student.index') : null">
                <div class="flex flex-wrap items-center gap-2 sm:gap-2.5 mt-1.5">
                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-blue-50/90 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 text-[11px] font-semibold border border-blue-200/70 dark:border-blue-800/50">
                        <svg class="size-3 text-blue-600 dark:text-blue-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <circle cx="10" cy="14" r="5" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5L20 4m0 0h-4.5m4.5 0v4.5" />
                        </svg>
                        <span>Male: <strong class="font-bold text-zinc-900 dark:text-white">{{ number_format($totalMaleStudents) }}</strong></span>
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-pink-50/90 dark:bg-pink-950/40 text-pink-700 dark:text-pink-300 text-[11px] font-semibold border border-pink-200/70 dark:border-pink-800/50">
                        <svg class="size-3 text-pink-600 dark:text-pink-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <circle cx="12" cy="9" r="5" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 14v7m-3-3.5h6" />
                        </svg>
                        <span>Female: <strong class="font-bold text-zinc-900 dark:text-white">{{ number_format($totalFemaleStudents) }}</strong></span>
                    </span>
                </div>
            </x-kpi-card>
            <x-kpi-card title="Class Teachers" icon="user-group" color="emerald" :count="$assignedTeachersCount"
                countSuffix="/{{ $totalClasses }}" subtext="supervisors assigned" :href="$canViewStaff ? route('staff.index') : null" />
            <x-kpi-card title="Capacity Rate" icon="chart-bar" color="sky" :count="$capacityRate" countSuffix="%"
                subtext="total seat occupancy" />
        </div>

        {{-- Classes Table Card --}}
        <div
            class="bg-white dark:bg-zinc-900 shadow-[0_1px_3px_0_rgba(0,0,0,0.05)] border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden flex flex-col">
            <div class="overflow-x-auto">
                <flux:table class="p-2">
                    <flux:table.columns>
                        <x-sortable-column column="name">Class Name & Level</x-sortable-column>
                        @if (isAdmin())
                            <flux:table.column>Institution</flux:table.column>
                        @endif
                        <flux:table.column>Class Teacher</flux:table.column>
                        <flux:table.column>Occupancy / Capacity</flux:table.column>
                        <flux:table.column align="end">Actions</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($classes as $schoolClass)
                            @php
                                $studentCount = $schoolClass->students()->count();
                                $capacity = (int) ($schoolClass->capacity ?? 0);
                                $isOverfilled = $capacity > 0 && $studentCount > $capacity;
                                $isFull = $capacity > 0 && $studentCount === $capacity;
                                $teacherName = $schoolClass->classTeacher?->name;
                                $teacherInitials = $teacherName
                                    ? collect(explode(' ', $teacherName))
                                        ->map(fn($w) => strtoupper(substr($w, 0, 1)))
                                        ->take(2)
                                        ->join('')
                                    : '';
                            @endphp
                            <flux:table.row
                                class="transition-colors hover:bg-zinc-50/50 dark:hover:bg-zinc-800/20 group">
                                {{-- Class Name & Level --}}
                                <flux:table.cell>
                                    <div class="flex items-center gap-2.5">
                                        <div
                                            class="size-8 rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-400 ring-1 ring-inset ring-indigo-500/20 flex items-center justify-center font-bold text-xs shrink-0 transition-transform group-hover:scale-105">
                                            <flux:icon icon="academic-cap" class="size-4" />
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <a href="{{ route('classes.show', $schoolClass->id) }}"
                                                class="font-semibold text-sm text-zinc-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                                                {{ $schoolClass->name }}
                                            </a>
                                            @if ($schoolClass->level)
                                                <flux:badge size="sm" color="zinc"
                                                    class="font-medium text-[11px]">
                                                    {{ $schoolClass->level }}
                                                </flux:badge>
                                            @endif
                                        </div>
                                    </div>
                                </flux:table.cell>

                                {{-- Institution / Campus (Admin only) --}}
                                @if (isAdmin())
                                    <flux:table.cell>
                                        <div
                                            class="flex items-center gap-1.5 text-zinc-500 text-xs truncate max-w-[150px]">
                                            <flux:icon icon="building-library"
                                                class="size-3.5 text-zinc-400 shrink-0" />
                                            <span>{{ $schoolClass->institution?->name ?? '—' }}</span>
                                        </div>
                                    </flux:table.cell>
                                @endif

                                {{-- Class Teacher --}}
                                <flux:table.cell>
                                    @if ($schoolClass->classTeacher)
                                        <div class="flex items-center gap-2">
                                            <div
                                                class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-[10px] font-semibold text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400 ring-1 ring-inset ring-emerald-500/20">
                                                {{ $teacherInitials }}
                                            </div>
                                            <span class="text-xs font-medium text-zinc-900 dark:text-zinc-100">
                                                {{ $teacherName }}
                                            </span>
                                        </div>
                                    @else
                                        <flux:badge size="sm" color="amber" class="font-medium text-[11px]">
                                            Unassigned
                                        </flux:badge>
                                    @endif
                                </flux:table.cell>

                                {{-- Occupancy / Capacity Smart Pill --}}
                                <flux:table.cell>
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono text-xs font-semibold text-zinc-900 dark:text-white">
                                            {{ $studentCount }}<span
                                                class="text-zinc-400 font-normal">/{{ $capacity ?: '∞' }}</span>
                                        </span>

                                        @if ($isOverfilled)
                                            <flux:badge color="red" size="sm" class="font-semibold text-[11px]">
                                                Overfilled (+{{ $studentCount - $capacity }})
                                            </flux:badge>
                                        @elseif ($isFull)
                                            <flux:badge color="emerald" size="sm"
                                                class="font-semibold text-[11px]">
                                                Full
                                            </flux:badge>
                                        @elseif ($capacity > 0)
                                            <flux:badge color="sky" size="sm" class="font-medium text-[11px]">
                                                {{ $capacity - $studentCount }} open
                                            </flux:badge>
                                        @else
                                            <flux:badge color="zinc" size="sm" class="font-medium text-[11px]">
                                                No limit
                                            </flux:badge>
                                        @endif
                                    </div>
                                </flux:table.cell>

                                {{-- Actions --}}
                                <flux:table.cell align="end">
                                    <div class="flex items-center justify-end gap-1">
                                        <flux:button href="{{ route('classes.show', $schoolClass->id) }}"
                                            icon="eye" size="sm" variant="subtle"
                                            tooltip="View Class Details" />

                                        @can('edit classes')
                                            <flux:button href="{{ route('classes.edit', $schoolClass->id) }}"
                                                icon="pencil" size="sm" variant="subtle" tooltip="Edit Class" />
                                        @endcan

                                        @can('delete classes')
                                            <form action="{{ route('classes.destroy', $schoolClass->id) }}" method="POST"
                                                class="inline"
                                                onsubmit="return confirm('Remove {{ $schoolClass->name }} permanently?');">
                                                @csrf
                                                @method('DELETE')
                                                <flux:button type="submit" icon="trash" size="sm" variant="subtle"
                                                    class="text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10"
                                                    tooltip="Delete Class" />
                                            </form>
                                        @endcan
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="{{ isAdmin() ? 5 : 4 }}">
                                    <x-empty-state icon="academic-cap" color="indigo" title="No Classes Configured"
                                        description="Create classroom streams and sections to begin assigning teachers and enrolling students.">
                                        @can('create classes')
                                            <flux:button href="{{ route('classes.create') }}" icon="plus"
                                                variant="primary">
                                                Add First Class
                                            </flux:button>
                                        @endcan
                                    </x-empty-state>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>
        </div>
    </div>
</x-layouts::app>
