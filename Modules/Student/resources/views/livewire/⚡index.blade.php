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
    public string $sort = 'created_at';

    #[Url]
    public string $direction = 'desc';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('view student'), 403);
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'status', 'classId', 'gender'], true)) {
            $this->resetPage();
        }
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
        !empty($this->search) || !empty($this->classId) || !empty($this->status) || !empty($this->gender);
    $selectedClass = $this->classes->firstWhere('id', $this->classId);
    $institution = $this->currentInstitutionModel;
@endphp

<div class="flex flex-col gap-8 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">

    {{-- Top Header Section --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span
                    class="px-2.5 py-0.5 rounded-full text-xs font-bold tracking-wide uppercase bg-blue-50 text-blue-700 border border-blue-200/60">
                    Academic Enrollment
                </span>
                <span class="text-xs text-zinc-400">&bull;</span>
                <span class="text-xs text-zinc-500 font-medium">{{ $institution?->name ?? 'Campus Operations' }}</span>
            </div>
            <flux:heading size="xl" class="font-black tracking-tight text-zinc-900">Student Directory</flux:heading>
            <flux:text class="text-zinc-500 text-sm mt-0.5">
                Manage student admissions, classroom assignments, guardian contacts, and enrollment profiles.
            </flux:text>
        </div>

        <div class="flex items-center gap-3">
            <x-export-buttons base-route="student.export" module="student"
                :params="['search' => $this->search, 'status' => $this->status, 'class_id' => $this->classId, 'gender' => $this->gender]" />
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
        <x-kpi-card title="Total Students" icon="academic-cap" color="sky" :count="$this->totalStudents">
            <div class="flex flex-wrap items-center gap-2 sm:gap-2.5 mt-1.5">
                <a href="{{ route('student.index', array_filter(['search' => $this->search, 'class_id' => $this->classId, 'status' => $this->status, 'gender' => $this->gender === 'male' ? null : 'male'])) }}"
                    wire:navigate.hover
                    class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-blue-50/90 hover:bg-blue-100/90 dark:bg-blue-950/40 dark:hover:bg-blue-900/50 text-blue-700 dark:text-blue-300 text-[11px] font-semibold border border-blue-200/70 dark:border-blue-800/50 transition shadow-2xs {{ $this->gender === 'male' ? 'ring-2 ring-blue-500 font-bold' : '' }}"
                    title="{{ $this->gender === 'male' ? 'Clear Male Filter' : 'Filter by Male Learners' }}">
                    <svg class="size-3 text-blue-600 dark:text-blue-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
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

        <x-kpi-card title="Class Sections" icon="building-library" color="indigo" :count="$this->totalClasses"
            subtext="Configured grade streams" href="{{ route('classes.index') }}" />
    </div>

    {{-- Reusable Single-Row Filter Toolbar --}}
    <x-filter-toolbar action="{{ route('student.index') }}" placeholder="Search student name, admission no, or phone..."
        :search="$this->search" :hasActiveFilters="$hasActiveFilters" resetRoute="{{ route('student.index') }}">
        {{-- Class Filter --}}
        <div style="flex: 0 1 200px;">
            <select name="class_id" onchange="this.form.submit()"
                class="w-full px-3 py-2 bg-zinc-50 border border-zinc-200 rounded-xl text-xs font-medium text-zinc-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
                <option value="">All Classes</option>
                @foreach ($this->classes as $c)
                    <option value="{{ $c->id }}" {{ $this->classId == $c->id ? 'selected' : '' }}>
                        {{ $c->name }} ({{ $c->level }})
                    </option>
                @endforeach
            </select>
        </div>

        {{-- Status Filter --}}
        <div style="flex: 0 1 150px;">
            <select name="status" onchange="this.form.submit()"
                class="w-full px-3 py-2 bg-zinc-50 border border-zinc-200 rounded-xl text-xs font-medium text-zinc-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
                <option value="">All Statuses</option>
                <option value="active" {{ $this->status === 'active' ? 'selected' : '' }}>Active Only</option>
                <option value="inactive" {{ $this->status === 'inactive' ? 'selected' : '' }}>Inactive</option>
                <option value="graduated" {{ $this->status === 'graduated' ? 'selected' : '' }}>Graduated</option>
                <option value="transferred" {{ $this->status === 'transferred' ? 'selected' : '' }}>Transferred
                </option>
                <option value="suspended" {{ $this->status === 'suspended' ? 'selected' : '' }}>Suspended</option>
            </select>
        </div>

        {{-- Gender Filter --}}
        <div style="flex: 0 1 130px;">
            <select name="gender" onchange="this.form.submit()"
                class="w-full px-3 py-2 bg-zinc-50 border border-zinc-200 rounded-xl text-xs font-medium text-zinc-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
                <option value="">All Genders</option>
                <option value="male" {{ $this->gender === 'male' ? 'selected' : '' }}>Male ({{ number_format($this->maleStudents) }})</option>
                <option value="female" {{ $this->gender === 'female' ? 'selected' : '' }}>Female ({{ number_format($this->femaleStudents) }})</option>
            </select>
        </div>

        <x-slot:scopeTags>
            @if ($this->classId && ($selectedClass = $this->classes->firstWhere('id', $this->classId)))
                <a href="{{ route('student.index', ['search' => $this->search, 'status' => $this->status, 'gender' => $this->gender]) }}"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-zinc-100 hover:bg-zinc-200 text-zinc-800 font-semibold transition border border-zinc-200/60"
                    title="Remove Class Filter">
                    <span>Class: <strong>{{ $selectedClass->name }}</strong></span>
                    <span class="text-zinc-400 hover:text-zinc-700 font-bold">×</span>
                </a>
            @endif

            @if ($this->status)
                <a href="{{ route('student.index', ['search' => $this->search, 'class_id' => $this->classId, 'gender' => $this->gender]) }}"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-zinc-100 hover:bg-zinc-200 text-zinc-800 font-semibold transition border border-zinc-200/60"
                    title="Remove Status Filter">
                    <span>Status: <strong>{{ ucfirst($this->status) }}</strong></span>
                    <span class="text-zinc-400 hover:text-zinc-700 font-bold">×</span>
                </a>
            @endif

            @if ($this->gender)
                <a href="{{ route('student.index', ['search' => $this->search, 'class_id' => $this->classId, 'status' => $this->status]) }}"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-zinc-100 hover:bg-zinc-200 text-zinc-800 font-semibold transition border border-zinc-200/60"
                    title="Remove Gender Filter">
                    <span>Gender: <strong>{{ ucfirst($this->gender) }}</strong></span>
                    <span class="text-zinc-400 hover:text-zinc-700 font-bold">×</span>
                </a>
            @endif

            @if ($this->search)
                <a href="{{ route('student.index', ['class_id' => $this->classId, 'status' => $this->status, 'gender' => $this->gender]) }}"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-800 font-semibold transition border border-blue-200/60"
                    title="Remove Search Keyword">
                    <span>Search: <strong>"{{ $this->search }}"</strong></span>
                    <span class="text-blue-500 hover:text-blue-800 font-bold">×</span>
                </a>
            @endif
        </x-slot:scopeTags>
    </x-filter-toolbar>

    {{-- Smart High-Density 4-Column Student Roster Table (Zero Horizontal Scroll) --}}
    <div class="bg-white shadow-sm border border-zinc-200/80 rounded-2xl overflow-hidden flex flex-col">
        @if ($this->students->isNotEmpty())
            <div class="w-full">
                <table class="w-full text-left text-sm table-fixed">
                    <thead
                        class="bg-zinc-50 text-zinc-500 border-b border-zinc-200/80 text-xs font-bold uppercase tracking-wider">
                        <tr>
                            <th class="py-3.5 px-5 w-4/12">Student Profile & Admission</th>
                            <th class="py-3.5 px-5 w-3/12">Class Stream & Gender</th>
                            <th class="py-3.5 px-5 w-3/12">Guardian & Contact</th>
                            <th class="py-3.5 px-5 w-2/12 text-right">Status & Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
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
                            <tr class="hover:bg-zinc-50/60 transition-colors">
                                {{-- Col 1: Student Profile & Admission --}}
                                <td class="py-4 px-5">
                                    <div class="flex items-center gap-3">
                                        @if ($student->profile_photo && Storage::disk('public')->exists($student->profile_photo))
                                            <img src="{{ asset('storage/' . $student->profile_photo) }}"
                                                alt="{{ $user?->name }}"
                                                class="size-10 rounded-xl object-cover border border-zinc-200 shrink-0">
                                        @else
                                            <div
                                                class="size-10 rounded-xl bg-zinc-100 border border-zinc-200 font-bold text-xs flex items-center justify-center text-zinc-800 shrink-0">
                                                {{ $initials }}
                                            </div>
                                        @endif
                                        <div class="min-w-0 flex-1">
                                            <a href="{{ $user ? route('student.show', $user->id) : '#' }}"
                                                wire:navigate.hover
                                                class="font-bold text-zinc-900 hover:text-blue-600 transition truncate block">
                                                {{ $user?->name ?? 'Student' }}
                                            </a>
                                            <div
                                                class="text-xs text-zinc-400 mt-0.5 flex flex-wrap items-center gap-1.5 font-mono">
                                                <span
                                                    class="px-1.5 py-0.5 rounded bg-zinc-100 text-zinc-700 font-semibold border border-zinc-200 text-[11px]">{{ $admNo }}</span>
                                                @if ($student->student_number)
                                                    <span class="text-zinc-300">&bull;</span>
                                                    <span
                                                        class="text-[11px] text-zinc-500">{{ $student->student_number }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                {{-- Col 2: Class Stream & Gender --}}
                                <td class="py-4 px-5 text-xs">
                                    <div class="font-bold text-zinc-900">
                                        {{ $className }}
                                    </div>
                                    <div class="text-[11px] text-zinc-500 mt-1 flex items-center gap-1.5">
                                        @if ($classLevel)
                                            <span
                                                class="px-2 py-0.5 rounded bg-zinc-100 text-zinc-700 font-medium border border-zinc-200/60">{{ $classLevel }}</span>
                                            <span class="text-zinc-300">&bull;</span>
                                        @endif
                                        <span
                                            class="capitalize text-zinc-600 font-medium">{{ $student->gender ?? 'Unspecified' }}</span>
                                    </div>
                                </td>

                                {{-- Col 3: Guardian & Contact --}}
                                <td class="py-4 px-5 text-xs">
                                    <div class="font-medium text-zinc-900 flex items-center gap-1.5">
                                        <flux:icon icon="user" class="size-3.5 text-zinc-400 shrink-0" />
                                        <span class="truncate">{{ $guardian }}</span>
                                    </div>
                                    <div class="text-[11px] text-zinc-500 mt-1 flex items-center gap-1 font-mono">
                                        @if ($guardianPhone)
                                            <flux:icon icon="phone" class="size-3 text-zinc-400 shrink-0" />
                                            <a href="tel:{{ $guardianPhone }}"
                                                class="hover:text-blue-600 transition">{{ $guardianPhone }}</a>
                                        @else
                                            <span class="text-zinc-400">No contact provided</span>
                                        @endif
                                    </div>
                                </td>

                                {{-- Col 4: Status & Actions --}}
                                <td class="py-4 px-5 text-right">
                                    <div class="flex flex-col items-end gap-2">
                                        <span
                                            class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold
                                                {{ $status === 'active'
                                                    ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                                                    : ($status === 'suspended'
                                                        ? 'bg-amber-50 text-amber-700 border border-amber-200'
                                                        : ($status === 'graduated'
                                                            ? 'bg-indigo-50 text-indigo-700 border border-indigo-200'
                                                            : 'bg-rose-50 text-rose-700 border border-rose-200')) }}">
                                            <span
                                                class="size-1.5 rounded-full {{ $status === 'active' ? 'bg-emerald-500' : ($status === 'suspended' ? 'bg-amber-500' : ($status === 'graduated' ? 'bg-indigo-500' : 'bg-rose-500')) }}"></span>
                                            {{ ucfirst($status) }}
                                        </span>

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
