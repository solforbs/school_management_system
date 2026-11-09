<?php

use App\Models\User;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Classes\Models\SchoolClass;
use Modules\Result\Services\ResultAccessService;
use Modules\Student\Models\StudentDetails;

new #[Title('Student Directory')] class extends Component
{
    use WithPagination;

    /** @var string[] */
    public const SORTABLE = ['admission_number', 'gender', 'phone', 'created_at'];

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url(as: 'class_id')]
    public string $classId = '';

    #[Url]
    public string $gender = '';

    #[Url]
    public string $biometric = '';

    #[Url]
    public string $sort = 'created_at';

    #[Url]
    public string $direction = 'desc';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('view student'), 403);
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'status', 'classId', 'gender', 'biometric'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'status', 'classId', 'gender', 'biometric']);
        $this->resetPage();
    }

    public function sortBy(string $column): void
    {
        if (! in_array($column, self::SORTABLE, true)) {
            return;
        }

        if ($this->sort === $column) {
            $this->direction = $this->direction === 'asc' ? 'desc' : 'asc';

            return;
        }

        $this->sort = $column;
        $this->direction = 'asc';
    }

    public function delete(int $studentId): void
    {
        abort_unless(auth()->user()->can('delete student'), 403);

        $details = $this->scoped()->where('student_id', $studentId)->firstOrFail();
        $student = User::findOrFail($details->student_id);

        $details->delete();
        $student->delete();

        Flux::toast(text: 'Student removed from the institution.', variant: 'success');
    }

    #[Computed]
    public function students()
    {
        $sort = in_array($this->sort, self::SORTABLE, true) ? $this->sort : 'created_at';
        $direction = $this->direction === 'asc' ? 'asc' : 'desc';

        return $this->scoped()
            ->with(['student.roles', 'institution', 'schoolClass'])
            ->when($this->status !== '', fn ($query) => $query->where('enrollment_status', $this->status))
            ->when($this->classId !== '', fn ($query) => $query->where('class_id', $this->classId))
            ->when($this->gender !== '', fn ($query) => $query->where('gender', $this->gender))
            ->when($this->biometric === 'synced', fn ($query) => $query->whereHas('student', fn ($q) => $q->where('zkteco_synced', true)))
            ->when($this->biometric === 'not_synced', fn ($query) => $query->whereHas('student', fn ($q) => $q->where('zkteco_synced', false)))
            ->when($this->search !== '', function ($query) {
                $term = '%'.$this->search.'%';

                $query->where(function ($q) use ($term) {
                    $q->where('admission_number', 'like', $term)->orWhere('student_number', 'like', $term)->orWhere('phone', 'like', $term)->orWhereHas('student', fn ($q2) => $q2->where('name', 'like', $term)->orWhere('email', 'like', $term));
                });
            })
            ->orderBy($sort, $direction)
            ->paginate(15);
    }

    #[Computed]
    public function totalStudents()
    {
        return $this->scoped()->count();
    }

    #[Computed]
    public function maleStudents()
    {
        return $this->scoped()->where('gender', 'male')->count();
    }

    #[Computed]
    public function femaleStudents()
    {
        return $this->scoped()->where('gender', 'female')->count();
    }

    #[Computed]
    public function totalActive()
    {
        return $this->scoped()->where('enrollment_status', 'active')->count();
    }

    #[Computed]
    public function totalUnsynced()
    {
        return $this->scoped()->whereHas('student', fn ($q) => $q->where('zkteco_synced', false))->count();
    }

    #[Computed]
    public function totalSynced()
    {
        return $this->scoped()->whereHas('student', fn ($q) => $q->where('zkteco_synced', true))->count();
    }

    #[Computed]
    public function activeDevices()
    {
        return \App\Models\Devices::where('institution_id', currentInstitutionId() ?? 0)
            ->where('is_active', true)
            ->get();
    }

    public function syncUnsyncedToBiometrics(): void
    {
        abort_unless(auth()->user()->can('edit student') || isAdmin(), 403);

        $institutionId = currentInstitutionId();
        if (! $institutionId) {
            Flux::toast(text: 'No active institution selected.', variant: 'danger');

            return;
        }

        if ($this->activeDevices->isEmpty()) {
            Flux::toast(text: 'No active biometric hardware devices configured for this school.', variant: 'warning');

            return;
        }

        $syncService = app(\App\Services\ZKTecoUserSyncService::class);
        $result = $syncService->bulkSyncUnsyncedForInstitution($institutionId);

        if ($result['synced'] > 0) {
            Flux::toast(
                text: "Successfully queued {$result['synced']} student(s) to {$result['devices_count']} biometric device(s).",
                variant: 'success'
            );
        } elseif ($result['total_students'] === 0) {
            Flux::toast(text: 'All students are already synced to biometric devices.', variant: 'info');
        } else {
            Flux::toast(text: 'Could not queue students to biometric devices. Please verify device connection.', variant: 'warning');
        }
    }

    #[Computed]
    public function totalClasses()
    {
        return $this->scoped()->distinct('class_id')->whereNotNull('class_id')->count('class_id');
    }

    #[Computed]
    public function classes()
    {
        if (auth()->user()->hasRole('Teacher')) {
            $classIds = ResultAccessService::classIds(auth()->user());

            return SchoolClass::where('institution_id', currentInstitutionId() ?? 0)
                ->whereIn('id', $classIds)
                ->orderBy('name')
                ->get();
        }

        return SchoolClass::when(! isAdmin(), fn ($q) => $q->where('institution_id', currentInstitutionId()))
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function currentInstitutionModel()
    {
        return currentInstitution();
    }

    private function scoped()
    {
        $query = StudentDetails::query();

        if (auth()->user()->hasRole('Parent')) {
            return $query->where('parent_id', auth()->id());
        }

        if (auth()->user()->hasRole('Teacher')) {
            $classIds = ResultAccessService::classIds(auth()->user());

            return $query->where('institution_id', currentInstitutionId() ?? 0)
                ->whereIn('class_id', $classIds);
        }

        if (! isAdmin()) {
            return $query->where('institution_id', currentInstitutionId() ?? 0);
        }

        return $query;
    }
}; ?>
@php
    $totalCount = method_exists($this->students, 'total') ? $this->students->total() : count($this->students);
    $hasActiveFilters =
        !empty($this->search) || !empty($this->classId) || !empty($this->status) || !empty($this->gender) || !empty($this->biometric);
    $selectedClass = $this->classes->firstWhere('id', $this->classId);
    $institution = $this->currentInstitutionModel;
@endphp

<div class="flex flex-col gap-8 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">

    {{-- Top Header Section --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span
                    class="px-2.5 py-0.5 rounded-full text-xs font-bold tracking-wide uppercase bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 border border-zinc-200/80 dark:border-zinc-700">
                    Academic Enrollment
                </span>
                <span class="text-xs text-zinc-400">&bull;</span>
                <span class="text-xs text-zinc-500 font-medium"><span translate="no">{{ $institution?->name ?? 'Campus Operations' }}</span></span>
            </div>
            <flux:heading size="xl" class="font-black tracking-tight text-zinc-900">Student Directory</flux:heading>
            <flux:text class="text-zinc-500 text-sm mt-0.5">
                Manage student admissions, classroom assignments, guardian contacts, and enrollment profiles.
            </flux:text>
        </div>

        <div class="flex flex-wrap items-center gap-2 sm:gap-3 w-full sm:w-auto">
            <x-export-buttons base-route="student.export" module="student"
                :params="['search' => $this->search, 'status' => $this->status, 'class_id' => $this->classId, 'gender' => $this->gender, 'biometric' => $this->biometric]" />
            @if ($this->activeDevices->isNotEmpty() && $this->totalUnsynced > 0)
                @can('edit student')
                    <button type="button" wire:click="syncUnsyncedToBiometrics" wire:loading.attr="disabled"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-blue-50 hover:bg-blue-100 dark:bg-blue-950/60 dark:hover:bg-blue-900/60 text-blue-700 dark:text-blue-300 font-semibold text-xs border border-blue-200 dark:border-blue-800 transition shadow-2xs cursor-pointer">
                        <flux:icon icon="finger-print" class="size-3.5 text-blue-600 dark:text-blue-400" />
                        <span wire:loading.remove wire:target="syncUnsyncedToBiometrics">Sync to Biometrics ({{ number_format($this->totalUnsynced) }})</span>
                        <span wire:loading wire:target="syncUnsyncedToBiometrics">Queuing Commands...</span>
                    </button>
                @endcan
            @endif
            @can('create student')
                <flux:button href="{{ route('student.upload') }}" wire:navigate.hover icon="arrow-up-tray" variant="ghost"
                    class="font-semibold shadow-sm px-4">
                    Upload Records
                </flux:button>
                <flux:button href="{{ route('student.create') }}" wire:navigate.hover icon="plus" variant="primary"
                    class="font-semibold shadow-sm px-4">
                    Enroll Student
                </flux:button>
            @endcan
        </div>
    </div>

    {{-- What the plan allows, and how close this school is to it. --}}
    <x-student-limit-banner :institution="$institution" />

    {{-- Flash Messages --}}
    @if (session('success'))
        <div
            class="rounded-2xl border border-emerald-200 bg-emerald-50/80 p-4 flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-3">
                <flux:icon icon="check-circle" class="size-5 text-emerald-600 shrink-0" />
                <span class="text-sm font-semibold text-emerald-900">{{ session('success') }}</span>
            </div>
        </div>
    @endif

    {{-- Consistent Animated KPI Cards (Matching Dashboard Zoom & Hover Effects) --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <x-kpi-card title="Total Students" icon="academic-cap" color="zinc" :count="$this->totalStudents">
            <div class="flex flex-wrap items-center gap-2 sm:gap-2.5 mt-1.5">
                <a href="{{ route('student.index', array_filter(['search' => $this->search, 'class_id' => $this->classId, 'status' => $this->status, 'gender' => $this->gender === 'male' ? null : 'male'])) }}"
                    wire:navigate.hover
                    class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-200 text-[11px] font-semibold border border-zinc-200/80 dark:border-zinc-700 transition shadow-2xs {{ $this->gender === 'male' ? 'ring-2 ring-zinc-900 dark:ring-white font-bold' : '' }}"
                    title="{{ $this->gender === 'male' ? 'Clear Male Filter' : 'Filter by Male Learners' }}">
                    <svg class="size-3 text-zinc-600 dark:text-zinc-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <circle cx="10" cy="14" r="5" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5L20 4m0 0h-4.5m4.5 0v4.5" />
                    </svg>
                    <span>Male: <strong class="font-bold text-zinc-900 dark:text-white">{{ number_format($this->maleStudents) }}</strong></span>
                </a>
                <a href="{{ route('student.index', array_filter(['search' => $this->search, 'class_id' => $this->classId, 'status' => $this->status, 'gender' => $this->gender === 'female' ? null : 'female'])) }}"
                    wire:navigate.hover
                    class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-pink-50/90 hover:bg-pink-100/90 dark:bg-pink-950/40 dark:hover:bg-pink-900/50 text-pink-700 dark:text-pink-300 text-[11px] font-semibold border border-pink-200/70 dark:border-pink-800/50 transition shadow-2xs {{ $this->gender === 'female' ? 'ring-2 ring-pink-500 font-bold' : '' }}"
                    title="{{ $this->gender === 'female' ? 'Clear Female Filter' : 'Filter by Female Learners' }}">
                    <svg class="size-3 text-pink-600 dark:text-pink-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <circle cx="12" cy="9" r="5" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 14v7m-3-3.5h6" />
                    </svg>
                    <span>Female: <strong class="font-bold text-zinc-900 dark:text-white">{{ number_format($this->femaleStudents) }}</strong></span>
                </a>
            </div>
        </x-kpi-card>

        <x-kpi-card title="Active Roster" icon="check-badge" color="emerald" :count="$this->totalActive"
            subtext="Currently attending learners" href="{{ route('student.index', ['status' => 'active']) }}" />

        <x-kpi-card title="Class Sections" icon="building-library" color="zinc" :count="$this->totalClasses"
            subtext="Configured grade streams" href="{{ route('classes.index') }}" />
    </div>

    {{-- Excel-Style Instant Livewire Filter Toolbar (Clean White UI, Zero Line Breaks) --}}
    <div class="bg-white dark:bg-zinc-900 rounded-2xl p-3.5 sm:p-4 border border-zinc-200/80 dark:border-zinc-800 shadow-sm space-y-3">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
            {{-- Instant Debounced Search Bar --}}
            <div class="relative flex-1 min-w-[240px]">
                <div class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 flex items-center text-zinc-400">
                    <flux:icon icon="magnifying-glass" class="size-4" />
                </div>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search student name, admission no, or phone..."
                    class="w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 py-2 pl-9 pr-8 text-xs text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 transition focus:bg-white dark:focus:bg-zinc-900 focus:outline-none focus:ring-2 focus:ring-zinc-900"
                >
                @if ($this->search)
                    <button type="button" wire:click="$set('search', '')"
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 text-xs font-bold p-1 cursor-pointer"
                        title="Clear search">
                        ✕
                    </button>
                @endif
            </div>

            {{-- Compact Dropdowns Filter Strip --}}
            <div class="flex flex-wrap items-center gap-2 sm:gap-2.5">
                {{-- Class Filter --}}
                <select wire:model.live="classId"
                    class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 py-2 px-3 text-xs font-medium text-zinc-800 dark:text-zinc-200 focus:bg-white dark:focus:bg-zinc-900 focus:outline-none focus:ring-2 focus:ring-zinc-900 cursor-pointer transition">
                    <option value="">All Classes</option>
                    @foreach ($this->classes as $c)
                        <option translate="no" value="{{ $c->id }}">{{ $c->name }} ({{ $c->level }})</option>
                    @endforeach
                </select>

                {{-- Status Filter --}}
                <select wire:model.live="status"
                    class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 py-2 px-3 text-xs font-medium text-zinc-800 dark:text-zinc-200 focus:bg-white dark:focus:bg-zinc-900 focus:outline-none focus:ring-2 focus:ring-zinc-900 cursor-pointer transition">
                    <option value="">All Statuses</option>
                    <option value="active">Active Only</option>
                    <option value="inactive">Inactive</option>
                    <option value="graduated">Graduated</option>
                    <option value="transferred">Transferred</option>
                    <option value="suspended">Suspended</option>
                </select>

                {{-- Gender Filter --}}
                <select wire:model.live="gender"
                    class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 py-2 px-3 text-xs font-medium text-zinc-800 dark:text-zinc-200 focus:bg-white dark:focus:bg-zinc-900 focus:outline-none focus:ring-2 focus:ring-zinc-900 cursor-pointer transition">
                    <option value="">All Genders</option>
                    <option value="male">Male ({{ number_format($this->maleStudents) }})</option>
                    <option value="female">Female ({{ number_format($this->femaleStudents) }})</option>
                </select>

                {{-- Biometric Filter --}}
                @if ($this->activeDevices->isNotEmpty())
                    <select wire:model.live="biometric"
                        class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 py-2 px-3 text-xs font-medium text-zinc-800 dark:text-zinc-200 focus:bg-white dark:focus:bg-zinc-900 focus:outline-none focus:ring-2 focus:ring-zinc-900 cursor-pointer transition">
                        <option value="">All Biometric Status</option>
                        <option value="synced">Biometric: Synced ({{ number_format($this->totalSynced) }})</option>
                        <option value="not_synced">Biometric: Not Synced ({{ number_format($this->totalUnsynced) }})</option>
                    </select>
                @endif

                {{-- Reset Button --}}
                @if ($hasActiveFilters)
                    <button type="button" wire:click="resetFilters"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-xs font-semibold text-zinc-700 dark:text-zinc-300 transition cursor-pointer"
                        title="Reset all filters">
                        <flux:icon icon="x-mark" class="size-3.5" />
                        <span>Clear</span>
                    </button>
                @endif
            </div>
        </div>

        {{-- Active Scope Chips --}}
        @if ($hasActiveFilters)
            <div class="flex flex-wrap items-center gap-2 pt-2.5 border-t border-zinc-100 dark:border-zinc-800 text-xs">
                <span class="text-[11px] font-bold text-zinc-400 uppercase tracking-wider mr-1">Active Scope:</span>
                @if ($this->search)
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-zinc-100 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200 font-semibold border border-zinc-200/80 dark:border-zinc-700">
                        <span>Search: <strong>"{{ $this->search }}"</strong></span>
                        <button type="button" wire:click="$set('search', '')" class="text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 font-bold ml-0.5 cursor-pointer">×</button>
                    </span>
                @endif
                @if ($this->classId && ($selectedClass = $this->classes->firstWhere('id', $this->classId)))
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-zinc-100 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200 font-semibold border border-zinc-200/80 dark:border-zinc-700">
                        <span>Class: <strong><span translate="no">{{ $selectedClass->name }}</span></strong></span>
                        <button type="button" wire:click="$set('classId', '')" class="text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 font-bold ml-0.5 cursor-pointer">×</button>
                    </span>
                @endif
                @if ($this->status)
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-zinc-100 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200 font-semibold border border-zinc-200/80 dark:border-zinc-700">
                        <span>Status: <strong>{{ ucfirst($this->status) }}</strong></span>
                        <button type="button" wire:click="$set('status', '')" class="text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 font-bold ml-0.5 cursor-pointer">×</button>
                    </span>
                @endif
                @if ($this->gender)
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-zinc-100 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200 font-semibold border border-zinc-200/80 dark:border-zinc-700">
                        <span>Gender: <strong>{{ ucfirst($this->gender) }}</strong></span>
                        <button type="button" wire:click="$set('gender', '')" class="text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 font-bold ml-0.5 cursor-pointer">×</button>
                    </span>
                @endif
                @if ($this->biometric)
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-zinc-100 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200 font-semibold border border-zinc-200/80 dark:border-zinc-700">
                        <span>Biometric: <strong>{{ $this->biometric === 'synced' ? 'Synced' : 'Not Synced' }}</strong></span>
                        <button type="button" wire:click="$set('biometric', '')" class="text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 font-bold ml-0.5 cursor-pointer">×</button>
                    </span>
                @endif
                <button type="button" wire:click="resetFilters" class="text-xs text-zinc-500 hover:text-zinc-900 dark:hover:text-zinc-100 underline ml-1 cursor-pointer">
                    Clear All
                </button>
            </div>
        @endif
    </div>

    {{-- Responsive Student Directory (Mobile Stacked Cards + Desktop Zero-Overflow Table) --}}
    <div class="bg-white dark:bg-zinc-900 shadow-sm border border-zinc-200/80 dark:border-zinc-800 rounded-2xl overflow-hidden flex flex-col">
        @if ($this->students->isNotEmpty())
            {{-- Mobile Fluid Stacked Cards View (< sm) --}}
            <div class="block sm:hidden divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach ($this->students as $student)
                    @php
                        $user = $student->student ?? ($student->user ?? $student);
                        $initials = collect(explode(' ', $user?->name ?? ''))
                            ->map(fn($w) => strtoupper(substr($w, 0, 1)))
                            ->take(2)
                            ->join('');
                        $admNo = $student->admission_number ?? 'ADM-' . str_pad($student->id, 4, '0', STR_PAD_LEFT);
                        $guardian = $student->guardian_name ?? ($student->parent_name ?? 'Not Listed');
                        $guardianPhone = $student->guardian_phone ?? ($student->parent_phone ?? $student->phone);
                        $status = $student->enrollment_status ?? ($student->is_active ? 'active' : 'inactive');
                        $schoolClass = $student->schoolClass ?? null;
                        $className = $schoolClass?->name ?? 'Unassigned Class';
                        $classLevel = $schoolClass?->level ?? null;
                    @endphp
                    <div class="p-3.5 space-y-2 hover:bg-zinc-50/60 dark:hover:bg-zinc-800/40 transition-colors">
                        {{-- Row 1: Avatar, Name, Admission No & Status Pill --}}
                        <div class="flex items-start justify-between gap-2.5">
                            <div class="flex items-center gap-2.5 min-w-0 flex-1">
                                @if ($student->profile_photo && Storage::disk('public')->exists($student->profile_photo))
                                    <img src="{{ asset('storage/' . $student->profile_photo) }}"
                                        alt="{{ $user?->name }}"
                                        class="size-10 rounded-xl object-cover border border-zinc-200 dark:border-zinc-700 shrink-0">
                                @else
                                    <div class="size-10 rounded-xl bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 font-bold text-xs flex items-center justify-center text-zinc-800 dark:text-zinc-200 shrink-0">
                                        {{ $initials }}
                                    </div>
                                @endif
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <a href="{{ $user ? route('student.show', $user->id) : '#' }}"
                                            wire:navigate.hover
                                            class="font-bold text-zinc-900 dark:text-white hover:text-zinc-600 transition text-sm">
                                            <span translate="no">{{ $user?->name ?? 'Student' }}</span>
                                        </a>
                                        <span class="inline-flex px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 font-mono text-[10px] font-semibold border border-zinc-200 dark:border-zinc-700 whitespace-nowrap shrink-0">
                                            {{ $admNo }}
                                        </span>
                                    </div>
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5 font-medium truncate">
                                        <span class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $className }}</span>
                                        @if ($classLevel)
                                            <span class="text-zinc-400 text-[11px]">({{ $classLevel }})</span>
                                        @endif
                                        <span class="text-zinc-300 dark:text-zinc-600">&bull;</span>
                                        <span class="capitalize">{{ $student->gender ?? 'Learner' }}</span>
                                    </div>
                                </div>
                            </div>

                            {{-- Status Badge & Biometric Indicator --}}
                            <div class="flex flex-col items-end gap-1 shrink-0">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold whitespace-nowrap
                                    {{ $status === 'active'
                                        ? 'bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-400 dark:border-emerald-800/60'
                                        : ($status === 'suspended'
                                            ? 'bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-950/40 dark:text-amber-400 dark:border-amber-800/60'
                                            : ($status === 'graduated'
                                                ? 'bg-zinc-100 text-zinc-700 border border-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:border-zinc-700'
                                                : 'bg-rose-50 text-rose-700 border border-rose-200 dark:bg-rose-950/40 dark:text-rose-400 dark:border-rose-800/60')) }}">
                                    <span class="size-1.5 rounded-full {{ $status === 'active' ? 'bg-emerald-500' : ($status === 'suspended' ? 'bg-amber-500' : ($status === 'graduated' ? 'bg-zinc-500' : 'bg-rose-500')) }}"></span>
                                    {{ ucfirst($status) }}
                                </span>
                                @if ($this->activeDevices->isNotEmpty())
                                    @if ($user?->zkteco_synced)
                                        <span class="inline-flex items-center gap-1 text-[10px] font-medium text-emerald-600 dark:text-emerald-400" title="Synced to biometric device">
                                            <flux:icon icon="finger-print" class="size-2.5 text-emerald-500" />
                                            <span>Synced</span>
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 text-[10px] font-medium text-zinc-400 dark:text-zinc-500" title="Not synced to biometric device">
                                            <flux:icon icon="finger-print" class="size-2.5 text-zinc-400" />
                                            <span>Pending</span>
                                        </span>
                                    @endif
                                @endif
                            </div>
                        </div>

                        {{-- Row 2: Guardian Contact & Quick Action Buttons --}}
                        <div class="flex items-center justify-between gap-2 pt-1 border-t border-zinc-100 dark:border-zinc-800/80 text-xs">
                            <div class="flex items-center gap-2 min-w-0 flex-wrap">
                                <div class="flex items-center gap-1 text-zinc-600 dark:text-zinc-400 truncate text-[11px]">
                                    <flux:icon icon="user" class="size-3 text-zinc-400 shrink-0" />
                                    <span class="truncate max-w-[120px]">{{ $guardian }}</span>
                                </div>
                                @if ($guardianPhone)
                                    <a href="tel:{{ $guardianPhone }}" class="hover:text-zinc-900 transition flex items-center gap-1 text-[11px] font-mono text-zinc-700 dark:text-zinc-300 shrink-0">
                                        <flux:icon icon="phone" class="size-3 text-zinc-400 shrink-0" />
                                        <span>{{ $guardianPhone }}</span>
                                    </a>
                                @endif
                            </div>

                            @if ($user)
                                <div class="flex items-center gap-1 shrink-0">
                                    <flux:button href="{{ route('student.show', $user->id) }}"
                                        wire:navigate.hover size="xs" variant="subtle" title="View Profile">
                                        View
                                    </flux:button>
                                    <x-impersonate-button :user="$user" />
                                    @can('edit student')
                                        <flux:button href="{{ route('student.edit', $user->id) }}"
                                            wire:navigate.hover size="xs" icon="pencil-square" variant="subtle" title="Edit Student" />
                                    @endcan
                                    @can('delete student')
                                        <x-confirm-form
                                            :action="route('student.destroy', $user->id)"
                                            title="Remove {{ $user->name }} from the directory?"
                                            message="This deletes the student's record and login. Recorded results are kept."
                                            confirm="Remove Student"
                                            trigger-class="text-rose-500 hover:text-rose-600 text-xs"
                                            tooltip="Delete" />
                                    @endcan
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Desktop High-Density Zero-Overflow Table (Hidden on Mobile) --}}
            <div class="hidden sm:block w-full overflow-hidden">
                <table class="w-full text-left text-sm table-fixed">
                    <thead
                        class="bg-zinc-50 dark:bg-zinc-800 text-zinc-500 dark:text-zinc-400 border-b border-zinc-200/80 dark:border-zinc-700 text-xs font-bold uppercase tracking-wider">
                        <tr>
                            <th class="py-3.5 px-5 w-4/12">Student Profile & Admission</th>
                            <th class="py-3.5 px-5 w-3/12">Class Stream & Gender</th>
                            <th class="py-3.5 px-5 w-3/12">Guardian & Contact</th>
                            <th class="py-3.5 px-5 w-2/12 text-right">Status & Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($this->students as $student)
                            @php
                                $user = $student->student ?? ($student->user ?? $student);
                                $initials = collect(explode(' ', $user?->name ?? ''))
                                    ->map(fn($w) => strtoupper(substr($w, 0, 1)))
                                    ->take(2)
                                    ->join('');
                                $admNo =
                                    $student->admission_number ?? 'ADM-' . str_pad($student->id, 4, '0', STR_PAD_LEFT);
                                $guardian = $student->guardian_name ?? ($student->parent_name ?? 'Not Listed');
                                $guardianPhone =
                                    $student->guardian_phone ?? ($student->parent_phone ?? $student->phone);
                                $status = $student->enrollment_status ?? ($student->is_active ? 'active' : 'inactive');
                                $schoolClass = $student->schoolClass ?? null;
                                $className = $schoolClass?->name ?? 'Unassigned Class';
                                $classLevel = $schoolClass?->level ?? null;
                            @endphp
                            <tr class="hover:bg-zinc-50/60 dark:hover:bg-zinc-800/40 transition-colors">
                                {{-- Col 1: Student Profile & Admission --}}
                                <td class="py-4 px-5">
                                    <div class="flex items-center gap-3">
                                        @if ($student->profile_photo && Storage::disk('public')->exists($student->profile_photo))
                                            <img src="{{ asset('storage/' . $student->profile_photo) }}"
                                                alt="{{ $user?->name }}"
                                                class="size-10 rounded-xl object-cover border border-zinc-200 dark:border-zinc-700 shrink-0">
                                        @else
                                            <div
                                                class="size-10 rounded-xl bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 font-bold text-xs flex items-center justify-center text-zinc-800 dark:text-zinc-200 shrink-0">
                                                {{ $initials }}
                                            </div>
                                        @endif
                                        <div class="min-w-0 flex-1">
                                            <a href="{{ $user ? route('student.show', $user->id) : '#' }}"
                                                wire:navigate.hover
                                                class="font-bold text-zinc-900 dark:text-white hover:text-zinc-600 transition truncate block">
                                                <span translate="no">{{ $user?->name ?? 'Student' }}</span>
                                            </a>
                                            <div
                                                class="text-xs text-zinc-400 mt-0.5 flex flex-wrap items-center gap-1.5 font-mono">
                                                <span
                                                    class="px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 font-semibold border border-zinc-200 dark:border-zinc-700 text-[11px] whitespace-nowrap">{{ $admNo }}</span>
                                                @if ($student->student_number)
                                                    <span class="text-zinc-300">&bull;</span>
                                                    <span
                                                        class="text-[11px] text-zinc-500 whitespace-nowrap">{{ $student->student_number }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                {{-- Col 2: Class Stream & Gender --}}
                                <td class="py-4 px-5 text-xs">
                                    <div class="font-bold text-zinc-900 dark:text-white truncate">
                                        {{ $className }}
                                    </div>
                                    <div class="text-[11px] text-zinc-500 mt-1 flex items-center gap-1.5 flex-wrap">
                                        @if ($classLevel)
                                            <span
                                                class="px-2 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 font-medium border border-zinc-200/60 dark:border-zinc-700 whitespace-nowrap">{{ $classLevel }}</span>
                                            <span class="text-zinc-300">&bull;</span>
                                        @endif
                                        <span
                                            class="capitalize text-zinc-600 dark:text-zinc-400 font-medium whitespace-nowrap">{{ $student->gender ?? 'Unspecified' }}</span>
                                    </div>
                                </td>

                                {{-- Col 3: Guardian & Contact --}}
                                <td class="py-4 px-5 text-xs">
                                    <div class="font-medium text-zinc-900 dark:text-white flex items-center gap-1.5">
                                        <flux:icon icon="user" class="size-3.5 text-zinc-400 shrink-0" />
                                        <span class="truncate">{{ $guardian }}</span>
                                    </div>
                                    <div class="text-[11px] text-zinc-500 mt-1 flex items-center gap-1 font-mono">
                                        @if ($guardianPhone)
                                            <flux:icon icon="phone" class="size-3 text-zinc-400 shrink-0" />
                                            <a href="tel:{{ $guardianPhone }}"
                                                class="hover:text-zinc-900 transition whitespace-nowrap">{{ $guardianPhone }}</a>
                                        @else
                                            <span class="text-zinc-400">No contact provided</span>
                                        @endif
                                    </div>
                                </td>

                                {{-- Col 4: Status & Actions --}}
                                <td class="py-4 px-5 text-right">
                                    <div class="flex flex-col items-end gap-2">
                                        <div class="flex items-center gap-1.5">
                                            @if ($this->activeDevices->isNotEmpty())
                                                @if ($user?->zkteco_synced)
                                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold text-emerald-700 bg-emerald-50 dark:text-emerald-400 dark:bg-emerald-950/40 border border-emerald-200/80 dark:border-emerald-800/60" title="Synced to biometric device">
                                                        <flux:icon icon="finger-print" class="size-2.5 text-emerald-600 dark:text-emerald-400" />
                                                        <span>Synced</span>
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold text-zinc-500 bg-zinc-100 dark:text-zinc-400 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700" title="Pending biometric device sync">
                                                        <flux:icon icon="finger-print" class="size-2.5 text-zinc-400" />
                                                        <span>Pending</span>
                                                    </span>
                                                @endif
                                            @endif
                                            <span
                                                class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold
                                                    {{ $status === 'active'
                                                        ? 'bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-400 dark:border-emerald-800/60'
                                                        : ($status === 'suspended'
                                                            ? 'bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-950/40 dark:text-amber-400 dark:border-amber-800/60'
                                                            : ($status === 'graduated'
                                                                ? 'bg-zinc-100 text-zinc-700 border border-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:border-zinc-700'
                                                                : 'bg-rose-50 text-rose-700 border border-rose-200 dark:bg-rose-950/40 dark:text-rose-400 dark:border-rose-800/60')) }}">
                                                <span
                                                    class="size-1.5 rounded-full {{ $status === 'active' ? 'bg-emerald-500' : ($status === 'suspended' ? 'bg-amber-500' : ($status === 'graduated' ? 'bg-zinc-500' : 'bg-rose-500')) }}"></span>
                                                {{ ucfirst($status) }}
                                            </span>
                                        </div>

                                        @if ($user)
                                            <div class="flex items-center justify-end gap-1">
                                                <flux:button href="{{ route('student.show', $user->id) }}"
                                                    wire:navigate.hover size="sm" icon="eye"
                                                    variant="subtle" tooltip="View Dossier" />
                                                <x-impersonate-button :user="$user" />
                                                @can('edit student')
                                                    <flux:button href="{{ route('student.edit', $user->id) }}"
                                                        wire:navigate.hover size="sm" icon="pencil-square"
                                                        variant="subtle" tooltip="Edit Profile" />
                                                @endcan
                                                @can('delete student')
                                                    <x-confirm-form
                                                        :action="route('student.destroy', $user->id)"
                                                        title="Remove {{ $user->name }} from the directory?"
                                                        message="This deletes the student's record and login. Recorded results are kept."
                                                        confirm="Remove Student"
                                                        trigger-class="text-rose-500 hover:text-rose-600 hover:bg-rose-50"
                                                        tooltip="Delete" />
                                                @endcan
                                            </div>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Table Footer Summary & Pagination --}}
            <div
                class="border-t border-zinc-200/80 px-6 py-4 bg-zinc-50/50 flex flex-col sm:flex-row justify-between items-center text-xs text-zinc-500 gap-4">
                <div class="flex items-center gap-3">
                    <span>Showing <strong class="text-zinc-800 font-semibold">{{ $this->students->count() }}</strong>
                        of <strong class="text-zinc-800 font-semibold">{{ number_format($totalCount) }}</strong>
                        student records</span>
                    <span class="text-zinc-300">&bull;</span>
                    <span>Page <strong
                            class="text-zinc-800 font-semibold">{{ $this->students->currentPage() }}</strong> of
                        <strong class="text-zinc-800 font-semibold">{{ $this->students->lastPage() }}</strong></span>
                </div>
                @if (method_exists($this->students, 'links') && $this->students->hasPages())
                    <div>
                        {{ $this->students->links() }}
                    </div>
                @endif
            </div>
        @else
            {{-- Empty State --}}
            <div class="flex flex-col items-center justify-center py-20 text-center">
                <div class="size-14 rounded-2xl bg-zinc-100 flex items-center justify-center text-zinc-400 mb-3">
                    <flux:icon icon="academic-cap" class="size-7" />
                </div>
                <h4 class="text-base font-bold text-zinc-900">No Students Found</h4>
                <p class="text-zinc-500 text-xs max-w-sm mx-auto mt-1 mb-4">
                    There are no student enrollments matching your active filter criteria.
                </p>
                @can('create student')
                    <flux:button href="{{ route('student.create') }}" variant="primary" icon="plus" size="sm">
                        Enroll Student
                    </flux:button>
                @endcan
            </div>
        @endif
    </div>
</div>
