@php
    $studentCount = $schoolClass->students->count();
    $capacity = (int) ($schoolClass->capacity ?? 0);
    $occupancyRate = $capacity > 0 ? round(($studentCount / $capacity) * 100) : 0;
@endphp

<x-layouts::app :title="__('Class Details')">
    <div class="flex flex-col gap-6 w-full max-w-6xl mx-auto p-4 sm:p-6 lg:p-8">

        {{-- Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 text-xs text-zinc-500 mb-1">
                    <a href="{{ route('dashboard') }}" wire:navigate.hover
                        class="hover:text-zinc-800 dark:hover:text-zinc-200">Dashboard</a>
                    <span>/</span>
                    <a href="{{ route('classes.index') }}" wire:navigate.hover
                        class="hover:text-zinc-800 dark:hover:text-zinc-200">Classes</a>
                    <span>/</span>
                    <span class="text-zinc-800 dark:text-zinc-200 font-medium">{{ $schoolClass->name }}</span>
                </div>
                <div class="flex items-center gap-3">
                    <flux:heading size="xl" class="font-bold tracking-tight text-zinc-900 dark:text-white">
                        {{ $schoolClass->name }}
                    </flux:heading>
                    @if ($schoolClass->level)
                        <flux:badge size="sm" color="indigo" class="font-semibold">
                            {{ $schoolClass->level }}
                        </flux:badge>
                    @endif
                </div>
                <flux:text class="text-zinc-500 text-sm mt-1 flex items-center gap-1.5">
                    <flux:icon icon="building-library" class="size-4 text-zinc-400 shrink-0" />
                    {{ $schoolClass->institution?->name }}
                </flux:text>
            </div>

            <div class="flex items-center gap-2.5">
                <flux:button href="{{ route('classes.index') }}" wire:navigate.hover icon="arrow-left" variant="ghost"
                    class="hover:bg-zinc-100 dark:hover:bg-zinc-800">
                    Back to Classes
                </flux:button>
                @can('edit classes')
                    <flux:button href="{{ route('classes.edit', $schoolClass->id) }}" wire:navigate.hover icon="pencil"
                        variant="primary" class="shadow-sm">
                        Edit Class
                    </flux:button>
                @endcan
                @can('delete classes')
                    <form action="{{ route('classes.destroy', $schoolClass->id) }}" method="POST"
                        onsubmit="return confirm('Remove {{ $schoolClass->name }} permanently?');" class="inline">
                        @csrf
                        @method('DELETE')
                        <flux:button type="submit" icon="trash" variant="subtle"
                            class="text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10">
                            Delete
                        </flux:button>
                    </form>
                @endcan
            </div>
        </div>

        {{-- Success Banner --}}
        @if (session('success'))
            <div
                class="rounded-xl border border-emerald-200/50 bg-emerald-50/50 dark:bg-emerald-500/10 dark:border-emerald-500/20 p-4 flex items-start shadow-sm">
                <flux:icon icon="check-circle" class="size-5 text-emerald-500 mr-3 mt-0.5" />
                <div class="text-sm text-emerald-800 dark:text-emerald-400 font-medium">
                    {{ session('success') }}
                </div>
            </div>
        @endif

        {{-- Overview Metric Cards --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-kpi-card title="Class Teacher" icon="user" color="emerald" :count="$schoolClass->classTeacher?->name ?? 'Unassigned'"
                subtext="primary supervisor" />
            <x-kpi-card title="Enrolled Students" icon="users" color="violet" :count="$studentCount"
                subtext="assigned to this stream" />
            <x-kpi-card title="Room Capacity" icon="chart-bar" color="indigo" :count="$capacity ? $studentCount . ' / ' . $capacity : 'Unlimited'" :subtext="$capacity ? $occupancyRate . '% occupancy' : 'no limit configured'" />
        </div>

        {{-- Enrolled Students Roster Card --}}
        <div
            class="bg-white dark:bg-zinc-900 shadow-[0_1px_3px_0_rgba(0,0,0,0.05)] border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden flex flex-col">
            <div
                class="px-6 py-4 border-b border-zinc-100 dark:border-zinc-800 flex items-center justify-between bg-zinc-50/50 dark:bg-zinc-800/20">
                <div>
                    <flux:heading size="md" class="font-semibold text-zinc-900 dark:text-white">Class Roster
                    </flux:heading>
                    <flux:text class="text-xs text-zinc-500">Students enrolled in {{ $schoolClass->name }}</flux:text>
                </div>
                <flux:badge size="sm" color="violet" class="font-semibold">
                    {{ $studentCount }} Students
                </flux:badge>
            </div>

            @if ($schoolClass->students->isEmpty())
                <div class="p-8">
                    <x-empty-state icon="users" color="violet" title="No Students Enrolled"
                        description="There are currently no students assigned to this class stream.">
                        @can('create student')
                            @if (Route::has('student.create'))
                                <flux:button href="{{ route('student.create') }}" icon="plus" variant="primary">
                                    Enroll Student
                                </flux:button>
                            @endif
                        @endcan
                    </x-empty-state>
                </div>
            @else
                <div class="overflow-x-auto">
                    <flux:table class="p-2">
                        <flux:table.columns>
                            <x-sortable-column column="name">Student Name</x-sortable-column>
                            <x-sortable-column column="admission_number">Admission No.</x-sortable-column>
                            <flux:table.column>Gender</flux:table.column>
                            <flux:table.column>Enrollment Status</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($schoolClass->students as $studentDetails)
                                @php
                                    $studentName = $studentDetails->student?->name ?? 'Unknown Student';
                                    $initials = collect(explode(' ', $studentName))
                                        ->map(fn($w) => strtoupper(substr($w, 0, 1)))
                                        ->take(2)
                                        ->join('');
                                @endphp
                                <flux:table.row
                                    class="transition-colors hover:bg-zinc-50/50 dark:hover:bg-zinc-800/20 group">
                                    {{-- Name --}}
                                    <flux:table.cell>
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="size-8 rounded-full bg-violet-50 dark:bg-violet-900/30 text-violet-600 dark:text-violet-400 ring-1 ring-inset ring-violet-500/20 flex items-center justify-center font-bold text-xs shrink-0 transition-transform group-hover:scale-105">
                                                {{ $initials }}
                                            </div>
                                            <span
                                                class="font-medium text-sm text-zinc-900 dark:text-white">{{ $studentName }}</span>
                                        </div>
                                    </flux:table.cell>

                                    {{-- Admission No --}}
                                    <flux:table.cell>
                                        <span
                                            class="font-mono text-xs text-zinc-600 dark:text-zinc-300 bg-zinc-100 dark:bg-zinc-800 px-2 py-0.5 rounded border border-zinc-200/50 dark:border-zinc-700/50">
                                            {{ $studentDetails->admission_number }}
                                        </span>
                                    </flux:table.cell>

                                    {{-- Gender --}}
                                    <flux:table.cell class="text-xs text-zinc-600 dark:text-zinc-400">
                                        {{ $studentDetails->gender ? ucfirst($studentDetails->gender) : '—' }}
                                    </flux:table.cell>

                                    {{-- Status --}}
                                    <flux:table.cell>
                                        <flux:badge :color="$studentDetails->is_active ? 'emerald' : 'zinc'"
                                            size="sm" class="font-medium">
                                            {{ ucfirst($studentDetails->enrollment_status ?? 'Enrolled') }}
                                        </flux:badge>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                </div>
            @endif
        </div>
    </div>
</x-layouts::app>
