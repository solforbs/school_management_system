<?php

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Classes\Models\SchoolClass;

new #[Title('Class Management')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('view classes'), 403);
    }

    public function delete(int $id): void
    {
        abort_unless(auth()->user()->can('delete classes'), 403);
        $schoolClass = SchoolClass::when(!isAdmin(), fn($q) => $q->where('institution_id', currentInstitutionId()))->findOrFail($id);
        $schoolClass->delete();
    }

    #[Computed]
    public function classes(): Collection
    {
        return SchoolClass::with(['classTeacher', 'institution'])
            ->withCount('students')
            ->when(!isAdmin(), fn($q) => $q->where('institution_id', currentInstitutionId()))
            ->when($this->search !== '', fn($q) => $q->where('name', 'like', '%' . $this->search . '%'))
            ->orderBy('name')
            ->get();
    }
}; ?>

@php
    $classes = $this->classes;
    $totalClasses = $classes->count();
    $totalStudents = $classes->sum('students_count');
    $totalCapacity = $classes->sum(fn($c) => (int) ($c->capacity ?? 0));
    $capacityRate = $totalCapacity > 0 ? round(($totalStudents / $totalCapacity) * 100) : 0;
    $assignedTeachersCount = $classes->filter(fn($c) => (bool) $c->class_teacher_id)->count();

    $canViewStudents = Route::has('student.index') && auth()->user()->can('view student');
    $canViewStaff = Route::has('staff.index') && auth()->user()->can('view staff');
@endphp

<div class="flex flex-col gap-6 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">

    {{-- Header Section --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <flux:heading size="xl" class="font-black tracking-tight text-zinc-900 dark:text-white">Classes &amp;
                    Streams</flux:heading>
                <flux:badge size="sm" color="indigo" class="font-semibold">
                    {{ $totalClasses }} Active Streams
                </flux:badge>
            </div>
            <flux:text class="text-zinc-500 text-sm mt-1">
                Manage academic levels, monitor classroom capacities, and assign class teachers.
            </flux:text>
        </div>

        <div class="flex items-center gap-3">
            <x-export-buttons base-route="classes.export" module="classes" :params="['search' => $this->search]" />
            @can('create classes')
                <flux:button href="{{ route('classes.create') }}" wire:navigate.hover icon="plus" variant="primary"
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
            subtext="assigned to classrooms" :href="$canViewStudents ? route('student.index') : null" />
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
                    <x-sortable-column column="name">Class Name &amp; Level</x-sortable-column>
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
                            $studentCount = $schoolClass->students_count ?? $schoolClass->students()->count();
                            $capacity = (int) ($schoolClass->capacity ?? 0);
                            $isFull = $capacity > 0 && $studentCount >= $capacity;
                            $isOverfilled = $capacity > 0 && $studentCount > $capacity;
                            $teacherName = $schoolClass->classTeacher?->name ?? 'Assigned Teacher';
                            $teacherInitials = collect(explode(' ', $teacherName))
                                ->map(fn($w) => strtoupper(substr($w, 0, 1)))
                                ->take(2)
                                ->join('');
                        @endphp
                        <flux:table.row class="transition-colors hover:bg-zinc-50/50 dark:hover:bg-zinc-800/20 group">
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
                                            <flux:badge size="sm" color="zinc" class="font-medium text-[11px]">
                                                {{ $schoolClass->level }}
                                            </flux:badge>
                                        @endif
                                    </div>
                                </div>
                            </flux:table.cell>

                            {{-- Institution / Campus (Admin only) --}}
                            @if (isAdmin())
                                <flux:table.cell>
                                    <div class="flex items-center gap-1.5 text-zinc-500 text-xs truncate max-w-[150px]">
                                        <flux:icon icon="building-library" class="size-3.5 text-zinc-400 shrink-0" />
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
                                        <flux:badge color="emerald" size="sm" class="font-semibold text-[11px]">
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
                                        wire:navigate.hover icon="eye" size="sm" variant="subtle"
                                        tooltip="View Class Details" />

                                    @can('edit classes')
                                        <flux:button href="{{ route('classes.edit', $schoolClass->id) }}"
                                            wire:navigate.hover icon="pencil" size="sm" variant="subtle"
                                            tooltip="Edit Class" />
                                    @endcan

                                    @can('delete classes')
                                        <x-confirm-form
                                            :action="route('classes.destroy', $schoolClass->id)"
                                            title="Remove {{ $schoolClass->name }}?"
                                            message="This permanently removes the class. Enrolled students lose their class assignment."
                                            confirm="Remove Class"
                                            tooltip="Delete Class" />
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
                                        <flux:button href="{{ route('classes.create') }}" wire:navigate.hover
                                            icon="plus" variant="primary">
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
