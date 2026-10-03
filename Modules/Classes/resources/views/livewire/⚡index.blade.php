<?php

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Attendance\Models\Attendance;
use Modules\Classes\Models\SchoolClass;
use Modules\Result\Services\ResultAccessService;
use Modules\Student\Models\StudentDetails;
use Modules\Subject\Models\SubjectTeacher;

new #[Title('Class Management')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $tab = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('view classes'), 403);

        if ($this->tab === '') {
            $this->tab = $this->isTeacher ? 'my-classes' : 'all';
        }
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        abort_unless(auth()->user()->can('delete classes'), 403);
        $schoolClass = SchoolClass::when(! isAdmin(), fn ($q) => $q->where('institution_id', currentInstitutionId()))->findOrFail($id);
        $schoolClass->delete();
    }

    #[Computed]
    public function isTeacher(): bool
    {
        $user = auth()->user();

        return $user && $user->hasRole('Teacher') && ! $user->hasAnyRole(['SuperAdmin', 'Director', 'Admin']);
    }

    #[Computed]
    public function institutionId(): int
    {
        return (int) (currentInstitutionId()
            ?? auth()->user()?->teacherUserDetails?->institution_id
            ?? auth()->user()?->active_institution_id
            ?? 0);
    }

    /**
     * Homeroom class where this teacher is the designated supervisor.
     */
    #[Computed]
    public function homeroomClass(): ?SchoolClass
    {
        if (! $this->isTeacher) {
            return null;
        }

        return SchoolClass::with(['curriculum', 'institution'])
            ->withCount([
                'students',
                'students as male_students_count' => fn ($q) => $q->where('gender', 'male'),
                'students as female_students_count' => fn ($q) => $q->where('gender', 'female'),
            ])
            ->where('institution_id', $this->institutionId)
            ->where('class_teacher_id', auth()->id())
            ->first();
    }

    /**
     * Today's roll-call attendance summary for the homeroom class (if any).
     */
    #[Computed]
    public function homeroomAttendance(): ?array
    {
        if (! $this->homeroomClass) {
            return null;
        }

        $today = Carbon::today()->toDateString();
        $records = Attendance::where('school_class_id', $this->homeroomClass->id)
            ->whereDate('date', $today)
            ->get();

        $marked = $records->count();
        $present = $records->whereIn('status', ['present', 'late'])->count();
        $absent = $records->where('status', 'absent')->count();

        return [
            'marked' => $marked,
            'present' => $present,
            'absent' => $absent,
            'rate' => $marked > 0 ? round(($present / $marked) * 100) : 0,
            'is_done' => $marked > 0,
        ];
    }

    /**
     * Subject teaching allocations for this teacher with class and subject relations.
     */
    #[Computed]
    public function subjectAllocations(): \Illuminate\Support\Collection
    {
        if (! $this->isTeacher) {
            return collect();
        }

        return SubjectTeacher::where('teacher_id', auth()->id())
            ->whereHas('schoolClass', fn ($q) => $q->where('institution_id', $this->institutionId))
            ->with(['schoolClass.curriculum', 'subject'])
            ->get();
    }

    /**
     * Accessible classes assigned to this teacher (Homeroom + Subject taught + pivot).
     */
    #[Computed]
    public function myClasses(): Collection
    {
        if (! $this->isTeacher) {
            return new Collection();
        }

        $accessibleIds = ResultAccessService::classIds(auth()->user());

        return SchoolClass::with(['classTeacher', 'curriculum', 'institution'])
            ->withCount([
                'students',
                'students as male_students_count' => fn ($q) => $q->where('gender', 'male'),
                'students as female_students_count' => fn ($q) => $q->where('gender', 'female'),
            ])
            ->where('institution_id', $this->institutionId)
            ->whereIn('id', $accessibleIds)
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->orderBy('name')
            ->get();
    }

    /**
     * All classes in the institution (for Admin or teacher reference directory).
     */
    #[Computed]
    public function classes(): Collection
    {
        return SchoolClass::with(['classTeacher', 'curriculum', 'institution'])
            ->withCount([
                'students',
                'students as male_students_count' => fn ($q) => $q->where('gender', 'male'),
                'students as female_students_count' => fn ($q) => $q->where('gender', 'female'),
            ])
            ->when(! isAdmin(), fn ($q) => $q->where('institution_id', $this->institutionId))
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->orderBy('name')
            ->get();
    }
}; ?>

@php
    $isTeacher = $this->isTeacher;
    $allClasses = $this->classes;
    $totalClasses = $allClasses->count();

    if ($isTeacher) {
        $myClasses = $this->myClasses;
        $myClassesCount = $myClasses->count();
        $homeroomClass = $this->homeroomClass;
        $homeroomAttendance = $this->homeroomAttendance;
        $subjectAllocations = $this->subjectAllocations;
        $accessibleClassIds = \Modules\Result\Services\ResultAccessService::classIds(auth()->user());

        $myStudentsQuery = \Modules\Student\Models\StudentDetails::where('institution_id', $this->institutionId)
            ->whereIn('class_id', $accessibleClassIds);
        $myStudentsCount = (clone $myStudentsQuery)->count();
        $myMaleCount = (clone $myStudentsQuery)->where('gender', 'male')->count();
        $myFemaleCount = (clone $myStudentsQuery)->where('gender', 'female')->count();
    } else {
        $totalStudents = $allClasses->sum('students_count');
        $totalMaleStudents = $allClasses->sum('male_students_count');
        $totalFemaleStudents = $allClasses->sum('female_students_count');
        $totalCapacity = $allClasses->sum(fn($c) => (int) ($c->capacity ?? 0));
        $capacityRate = $totalCapacity > 0 ? round(($totalStudents / $totalCapacity) * 100) : 0;
        $assignedTeachersCount = $allClasses->filter(fn($c) => (bool) $c->class_teacher_id)->count();

        $canViewStudents = Route::has('student.index') && auth()->user()->can('view student');
        $canViewStaff = Route::has('staff.index') && auth()->user()->can('view staff');
    }
@endphp

<div class="flex flex-col gap-6 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">

    {{-- Header Section --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <flux:heading size="xl" class="font-black tracking-tight text-zinc-900 dark:text-white">
                    {{ $isTeacher ? 'My Classes & Teaching Streams' : 'Classes & Streams' }}
                </flux:heading>
                <flux:badge size="sm" color="indigo" class="font-semibold">
                    {{ $isTeacher ? $myClassesCount . ' Assigned Streams' : $totalClasses . ' Active Streams' }}
                </flux:badge>
            </div>
            <flux:text class="text-zinc-500 text-sm mt-1">
                @if ($isTeacher)
                    Review your homeroom stream, subject teaching allocations, learner rosters, and marksheets.
                @else
                    Manage academic levels, monitor classroom capacities, and assign class teachers.
                @endif
            </flux:text>
        </div>

        <div class="flex flex-wrap items-center gap-2 sm:gap-3 w-full sm:w-auto">
            @can('export classes')
                <x-export-buttons base-route="classes.export" module="classes" :params="['search' => $this->search]" />
            @endcan
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

    {{-- KPI Metrics Grid --}}
    @if ($isTeacher)
        <div class="grid grid-cols-2 gap-2.5 sm:gap-4 lg:grid-cols-4">
            <x-kpi-card title="My Classes" icon="academic-cap" color="indigo" :count="$myClassesCount"
                subtext="homeroom & taught classes" />

            <x-kpi-card title="My Learners" icon="users" color="violet" :count="$myStudentsCount"
                :href="route('student.index')">
                <div class="flex flex-wrap items-center gap-2 sm:gap-2.5 mt-1.5">
                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-blue-50/90 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 text-[11px] font-semibold border border-blue-200/70 dark:border-blue-800/50">
                        <span>Male: <strong class="font-bold text-zinc-900 dark:text-white">{{ number_format($myMaleCount) }}</strong></span>
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-pink-50/90 dark:bg-pink-950/40 text-pink-700 dark:text-pink-300 text-[11px] font-semibold border border-pink-200/70 dark:border-pink-800/50">
                        <span>Female: <strong class="font-bold text-zinc-900 dark:text-white">{{ number_format($myFemaleCount) }}</strong></span>
                    </span>
                </div>
            </x-kpi-card>

            <x-kpi-card title="Homeroom Class" icon="user-group" color="emerald"
                :count="$homeroomClass ? $homeroomClass->name : 'None Assigned'"
                :subtext="$homeroomClass ? ($homeroomAttendance['is_done'] ? '✓ ' . $homeroomAttendance['rate'] . '% roll-call today' : '⚠️ Roll-call pending today') : 'subject teacher role'"
                :href="$homeroomClass ? route('classes.show', $homeroomClass->id) : null" />

            <x-kpi-card title="Teaching Allocations" icon="book-open" color="sky"
                :count="$subjectAllocations->count()"
                subtext="active subject assignments" />
        </div>
    @else
        <div class="grid grid-cols-2 gap-2.5 sm:gap-4 lg:grid-cols-4">
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
    @endif

    {{-- Teacher Tab Switcher --}}
    @if ($isTeacher)
        <div class="flex items-center justify-between border-b border-zinc-200 dark:border-zinc-800 pb-3 gap-3 flex-wrap">
            <div class="flex items-center gap-2">
                <button type="button" wire:click="setTab('my-classes')"
                    class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-2 {{ $this->tab === 'my-classes' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900 shadow-xs' : 'text-zinc-500 hover:text-zinc-900 dark:hover:text-white hover:bg-zinc-100 dark:hover:bg-zinc-800' }}">
                    <flux:icon icon="academic-cap" class="size-3.5" />
                    <span>My Classes &amp; Streams</span>
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] {{ $this->tab === 'my-classes' ? 'bg-zinc-700 text-zinc-100 dark:bg-zinc-200 dark:text-zinc-800' : 'bg-zinc-200 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300' }}">
                        {{ $myClassesCount }}
                    </span>
                </button>

                <button type="button" wire:click="setTab('all')"
                    class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-2 {{ $this->tab === 'all' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900 shadow-xs' : 'text-zinc-500 hover:text-zinc-900 dark:hover:text-white hover:bg-zinc-100 dark:hover:bg-zinc-800' }}">
                    <flux:icon icon="building-library" class="size-3.5" />
                    <span>All School Streams</span>
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] {{ $this->tab === 'all' ? 'bg-zinc-700 text-zinc-100 dark:bg-zinc-200 dark:text-zinc-800' : 'bg-zinc-200 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300' }}">
                        {{ $totalClasses }}
                    </span>
                </button>
            </div>

            <div class="w-full sm:w-64">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Search streams..." icon="magnifying-glass" size="sm" />
            </div>
        </div>
    @endif

    {{-- Teacher 'My Classes' Tab Content --}}
    @if ($isTeacher && $this->tab === 'my-classes')
        {{-- 1. Homeroom Spotlight Card (if designated class teacher) --}}
        @if ($homeroomClass)
            @php
                $isCbc = $homeroomClass->curriculum?->isCbc() ?? false;
            @endphp
            <div class="relative overflow-hidden rounded-2xl border border-emerald-300/80 dark:border-emerald-800/60 bg-gradient-to-br from-emerald-50/80 via-white to-white dark:from-emerald-950/20 dark:via-zinc-900 dark:to-zinc-900 p-5 sm:p-6 shadow-xs">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-5">
                    <div class="space-y-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-600 text-white shadow-xs">
                                <flux:icon icon="star" class="size-3" />
                                Designated Homeroom Class Teacher
                            </span>
                            @if ($homeroomClass->level)
                                <flux:badge size="sm" color="zinc" class="font-medium text-[11px]">
                                    {{ $homeroomClass->level }}
                                </flux:badge>
                            @endif
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold {{ $isCbc ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300' }}">
                                {{ $homeroomClass->curriculum?->name ?? ($isCbc ? 'CBC' : '8-4-4') }}
                            </span>
                        </div>

                        <div>
                            <h3 class="text-xl sm:text-2xl font-black text-zinc-900 dark:text-white tracking-tight">
                                {{ $homeroomClass->name }}
                            </h3>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                                {{ $homeroomClass->students_count }} Enrolled Learners &middot;
                                <span class="text-blue-600 dark:text-blue-400 font-semibold">{{ $homeroomClass->male_students_count }} Boys</span>,
                                <span class="text-pink-600 dark:text-pink-400 font-semibold">{{ $homeroomClass->female_students_count }} Girls</span>
                            </p>
                        </div>

                        {{-- Attendance Health Status Badge --}}
                        <div class="pt-1">
                            @if ($homeroomAttendance['is_done'])
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-semibold bg-emerald-100/90 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-300/60">
                                    <flux:icon icon="check-circle" class="size-3.5 text-emerald-600" />
                                    Today's Roll-Call: {{ $homeroomAttendance['rate'] }}% Attendance ({{ $homeroomAttendance['present'] }}/{{ $homeroomAttendance['marked'] }} Present)
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-semibold bg-amber-100/90 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-300/60">
                                    <flux:icon icon="exclamation-circle" class="size-3.5 text-amber-600" />
                                    Today's Classroom Roll-Call Pending &middot; Not conducted yet
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Homeroom Quick Actions Toolbar --}}
                    <div class="flex flex-wrap items-center gap-2 sm:gap-2.5">
                        <flux:button href="{{ route('attendance.register', ['classId' => $homeroomClass->id]) }}" wire:navigate.hover variant="primary" icon="clipboard-document-check" class="font-bold text-xs shadow-sm">
                            Roll-Call Register
                        </flux:button>
                        <flux:button href="{{ route('student.index', ['class_id' => $homeroomClass->id]) }}" wire:navigate.hover variant="subtle" icon="users" class="font-semibold text-xs">
                            Learners Roster
                        </flux:button>
                        @if (Route::has('timetable.index'))
                            <flux:button href="{{ route('timetable.index') }}" wire:navigate.hover variant="subtle" icon="calendar-days" class="font-semibold text-xs">
                                Timetable
                            </flux:button>
                        @endif
                        <flux:button href="{{ route('classes.show', $homeroomClass->id) }}" wire:navigate.hover variant="ghost" icon="eye" class="text-xs">
                            Class Dossier
                        </flux:button>
                    </div>
                </div>
            </div>
        @endif

        {{-- 2. Assigned Classes & Teaching Allocations Table --}}
        <div class="bg-white dark:bg-zinc-900 shadow-[0_1px_3px_0_rgba(0,0,0,0.05)] border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden flex flex-col">
            <div class="px-5 py-3.5 border-b border-zinc-100 dark:border-zinc-800 flex items-center justify-between gap-3 bg-zinc-50/50 dark:bg-zinc-800/20">
                <div>
                    <h4 class="font-bold text-sm text-zinc-900 dark:text-white">Teaching Allocations &amp; Assigned Streams</h4>
                    <p class="text-xs text-zinc-500">Classes and subjects allocated to you for classroom instruction, assessment, and grading.</p>
                </div>
                <flux:badge size="sm" color="zinc" class="font-semibold">
                    {{ $myClasses->count() }} Classes
                </flux:badge>
            </div>

            <div class="overflow-x-auto">
                <flux:table class="p-2">
                    <flux:table.columns>
                        <flux:table.column>Class &amp; Level</flux:table.column>
                        <flux:table.column>Your Role</flux:table.column>
                        <flux:table.column>Subjects You Teach</flux:table.column>
                        <flux:table.column>Learners</flux:table.column>
                        <flux:table.column align="end">Quick Actions</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($myClasses as $schoolClass)
                            @php
                                $isHomeroom = (int) $schoolClass->class_teacher_id === (int) auth()->id();
                                $taughtSubjects = $subjectAllocations->where('class_id', $schoolClass->id);
                                $isCbc = $schoolClass->curriculum?->isCbc() ?? false;
                            @endphp
                            <flux:table.row class="transition-colors hover:bg-zinc-50/50 dark:hover:bg-zinc-800/20 group">
                                {{-- Class Name & Level --}}
                                <flux:table.cell>
                                    <div class="flex items-center gap-2.5">
                                        <div class="size-8 rounded-xl {{ $isHomeroom ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400 ring-1 ring-inset ring-emerald-500/20' : 'bg-indigo-50 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-400 ring-1 ring-inset ring-indigo-500/20' }} flex items-center justify-center font-bold text-xs shrink-0 transition-transform group-hover:scale-105">
                                            <flux:icon icon="{{ $isHomeroom ? 'star' : 'academic-cap' }}" class="size-4" />
                                        </div>
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <a href="{{ route('classes.show', $schoolClass->id) }}" class="font-bold text-sm text-zinc-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                                                    {{ $schoolClass->name }}
                                                </a>
                                                <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-bold {{ $isCbc ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-200' : 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 border border-zinc-200' }}">
                                                    {{ $isCbc ? 'CBC' : '8-4-4' }}
                                                </span>
                                            </div>
                                            @if ($schoolClass->level)
                                                <span class="text-[11px] text-zinc-400">{{ $schoolClass->level }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </flux:table.cell>

                                {{-- Your Role --}}
                                <flux:table.cell>
                                    @if ($isHomeroom)
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-300/50">
                                            <flux:icon icon="star" class="size-3" />
                                            Class Teacher
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-indigo-50 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300 border border-indigo-200/60">
                                            Subject Teacher
                                        </span>
                                    @endif
                                </flux:table.cell>

                                {{-- Subjects You Teach --}}
                                <flux:table.cell>
                                    @if ($taughtSubjects->isNotEmpty())
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            @foreach ($taughtSubjects as $alloc)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold bg-violet-50 dark:bg-violet-950/40 text-violet-700 dark:text-violet-300 border border-violet-200/70 dark:border-violet-800/50">
                                                    {{ $alloc->subject?->name ?? 'Subject' }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @elseif ($isHomeroom)
                                        <span class="text-xs text-zinc-400 italic">Homeroom Pastoral Supervision</span>
                                    @else
                                        <span class="text-xs text-zinc-400 italic">—</span>
                                    @endif
                                </flux:table.cell>

                                {{-- Learners --}}
                                <flux:table.cell>
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-xs text-zinc-900 dark:text-white">
                                            {{ $schoolClass->students_count }} Learners
                                        </span>
                                        <span class="text-[11px] text-zinc-400">
                                            ({{ $schoolClass->male_students_count }}M &middot; {{ $schoolClass->female_students_count }}F)
                                        </span>
                                    </div>
                                </flux:table.cell>

                                {{-- Quick Actions --}}
                                <flux:table.cell align="end">
                                    <div class="flex flex-wrap items-center justify-end gap-1.5">
                                        <flux:button href="{{ route('student.index', ['class_id' => $schoolClass->id]) }}" wire:navigate.hover size="xs" variant="subtle" icon="users">
                                            Learners
                                        </flux:button>
                                        @if ($isCbc)
                                            <flux:button href="{{ route('assessment.formative', ['classId' => $schoolClass->id]) }}" wire:navigate.hover size="xs" variant="primary" icon="sparkles">
                                                Record Assessment
                                            </flux:button>
                                        @else
                                            <flux:button href="{{ route('result.entry.classes') }}" wire:navigate.hover size="xs" variant="primary" icon="table-cells">
                                                Enter Marks
                                            </flux:button>
                                        @endif
                                        <flux:button href="{{ route('attendance.register', ['classId' => $schoolClass->id]) }}" wire:navigate.hover size="xs" variant="subtle" icon="clipboard-document-check" tooltip="Attendance Register">
                                            Attendance
                                        </flux:button>
                                        <flux:button href="{{ route('classes.show', $schoolClass->id) }}" wire:navigate.hover size="xs" variant="ghost" icon="eye" tooltip="View Class">
                                        </flux:button>
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="5">
                                    <x-empty-state icon="academic-cap" color="indigo" title="No Classes Assigned"
                                        description="You have not been assigned as a homeroom teacher or subject instructor yet. Please contact the administrator to allocate your classes.">
                                    </x-empty-state>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>
        </div>
    @endif

    {{-- 'All School Streams' Tab (for Teachers) OR Default Admin Table --}}
    @if (! $isTeacher || ($isTeacher && $this->tab === 'all'))
        <div class="bg-white dark:bg-zinc-900 shadow-[0_1px_3px_0_rgba(0,0,0,0.05)] border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden flex flex-col">
            @if ($isTeacher)
                <div class="px-5 py-3.5 border-b border-zinc-100 dark:border-zinc-800 flex items-center justify-between gap-3 bg-zinc-50/50 dark:bg-zinc-800/20">
                    <div>
                        <h4 class="font-bold text-sm text-zinc-900 dark:text-white">All School Streams Directory</h4>
                        <p class="text-xs text-zinc-500">Institution-wide directory of active classes, curriculum systems, and assigned class supervisors.</p>
                    </div>
                    <flux:badge size="sm" color="zinc" class="font-semibold">
                        {{ $totalClasses }} Streams
                    </flux:badge>
                </div>
            @endif

            <div class="overflow-x-auto">
                <flux:table class="p-2">
                    <flux:table.columns>
                        <x-sortable-column column="name">Class Name &amp; Level</x-sortable-column>
                        @if (isAdmin())
                            <flux:table.column>Institution</flux:table.column>
                        @endif
                        <flux:table.column>Curriculum</flux:table.column>
                        <flux:table.column>Class Teacher</flux:table.column>
                        <flux:table.column>{{ $isTeacher ? 'Enrolled Learners' : 'Occupancy / Capacity' }}</flux:table.column>
                        <flux:table.column align="end">Actions</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($allClasses as $schoolClass)
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
                                $isMyClass = $isTeacher && ($accessibleClassIds ?? collect())->contains($schoolClass->id);
                            @endphp
                            <flux:table.row class="transition-colors hover:bg-zinc-50/50 dark:hover:bg-zinc-800/20 group">
                                {{-- Class Name & Level --}}
                                <flux:table.cell>
                                    <div class="flex items-center gap-2.5">
                                        <div class="size-8 rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-400 ring-1 ring-inset ring-indigo-500/20 flex items-center justify-center font-bold text-xs shrink-0 transition-transform group-hover:scale-105">
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
                                            @if ($isMyClass)
                                                <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                                    My Class
                                                </span>
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

                                {{-- Curriculum --}}
                                <flux:table.cell>
                                    <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                                        {{ $schoolClass->curriculum?->name ?? 'Standard' }}
                                    </span>
                                </flux:table.cell>

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
                                                @if ((int) $schoolClass->class_teacher_id === (int) auth()->id())
                                                    <span class="text-[10px] text-emerald-600 font-bold">(You)</span>
                                                @endif
                                            </span>
                                        </div>
                                    @else
                                        <flux:badge size="sm" color="amber" class="font-medium text-[11px]">
                                            Unassigned
                                        </flux:badge>
                                    @endif
                                </flux:table.cell>

                                {{-- Occupancy / Capacity or Learner Count --}}
                                <flux:table.cell>
                                    @if ($isTeacher)
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono text-xs font-semibold text-zinc-900 dark:text-white">
                                                {{ $studentCount }} Learners
                                            </span>
                                            @if ($studentCount > 0)
                                                <span class="text-[11px] text-zinc-400 font-medium">
                                                    ({{ $schoolClass->male_students_count }}M &middot; {{ $schoolClass->female_students_count }}F)
                                                </span>
                                            @endif
                                        </div>
                                    @else
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono text-xs font-semibold text-zinc-900 dark:text-white">
                                                {{ $studentCount }}<span class="text-zinc-400 font-normal">/{{ $capacity ?: '∞' }}</span>
                                            </span>

                                            @if ($studentCount > 0)
                                                <span class="text-[11px] text-zinc-400 dark:text-zinc-500 font-medium">
                                                    ({{ $schoolClass->male_students_count }}M &middot; {{ $schoolClass->female_students_count }}F)
                                                </span>
                                            @endif

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
                                    @endif
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
                                <flux:table.cell colspan="{{ isAdmin() ? 6 : 5 }}">
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
    @endif
</div>
