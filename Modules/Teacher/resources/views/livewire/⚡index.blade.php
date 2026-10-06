<?php

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Teacher\Models\TeacherDetails;

new #[Title('Teacher Management')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $department = '';

    #[Url]
    public string $status = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('view teacher'), 403);
    }

    #[Computed]
    public function teachers(): LengthAwarePaginator
    {
        return TeacherDetails::with(['teacher.roles', 'institution'])
            ->when(! isAdmin(), fn ($q) => $q->where('institution_id', currentInstitutionId()))
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';

                $q->where(function ($sub) use ($term) {
                    $sub->whereHas('teacher', fn ($userQ) => $userQ->where('name', 'like', $term)->orWhere('email', 'like', $term))
                        ->orWhere('employee_number', 'like', $term)
                        ->orWhere('phone', 'like', $term);
                });
            })
            ->when($this->department !== '', fn ($q) => $q->where('department', $this->department))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->orderBy('created_at', 'desc')
            ->paginate(15);
    }

    #[Computed]
    public function totalTeachers(): int
    {
        return TeacherDetails::when(! isAdmin(), fn ($q) => $q->where('institution_id', currentInstitutionId()))->count();
    }

    #[Computed]
    public function totalActive(): int
    {
        return TeacherDetails::when(! isAdmin(), fn ($q) => $q->where('institution_id', currentInstitutionId()))
            ->where('status', 'active')
            ->count();
    }

    #[Computed]
    public function totalDepartments(): int
    {
        return count($this->departments);
    }

    #[Computed]
    public function departments(): array
    {
        return TeacherDetails::when(! isAdmin(), fn ($q) => $q->where('institution_id', currentInstitutionId()))
            ->whereNotNull('department')
            ->where('department', '!=', '')
            ->distinct()
            ->pluck('department')
            ->sort()
            ->values()
            ->all();
    }

    public function resendVerification(int $teacherUserId): void
    {
        abort_unless(auth()->user()->can('edit teacher'), 403);

        $teacherUser = User::findOrFail($teacherUserId);
        try {
            $teacherUser->sendEmailVerificationNotification();
            session()->flash('success', "Verification email link sent to {$teacherUser->email}!");
        } catch (Throwable $e) {
            session()->flash('error', "Could not send email to {$teacherUser->email}: {$e->getMessage()}");
        }
    }

    public function verifyTeacher(int $teacherUserId): void
    {
        abort_unless(auth()->user()->can('edit teacher'), 403);

        $teacherUser = User::findOrFail($teacherUserId);
        $teacherUser->forceFill(['email_verified_at' => now()])->save();

        session()->flash('success', "Email for {$teacherUser->name} has been verified! They can now log in immediately.");
    }

    public function deleteTeacher(int $teacherDetailsId): void
    {
        abort_unless(auth()->user()->can('delete teacher'), 403);

        $details = TeacherDetails::findOrFail($teacherDetailsId);
        if (! isAdmin()) {
            abort_unless($details->institution_id === currentInstitutionId(), 403);
        }

        $teacherUser = $details->teacher;
        $name = $teacherUser?->name ?? 'Teacher';

        $details->delete();
        $teacherUser?->delete();

        session()->flash('success', "{$name} removed successfully from the faculty roster.");
    }
}; ?>

@php
    $teachers = $this->teachers;
    $totalCount = method_exists($teachers, 'total') ? $teachers->total() : count($teachers);
    $totalTeachers = $this->totalTeachers;
    $totalActive = $this->totalActive;
    $totalDepartments = $this->totalDepartments;
    $departments = $this->departments;
    $currentSearch = $this->search;
    $currentDept = $this->department;
    $currentStatus = $this->status;
    $hasActiveFilters = !empty($currentSearch) || !empty($currentDept) || !empty($currentStatus);

    // Clean initials calculation stripping honorifics
    $getInitials = function($name) {
        $clean = trim(preg_replace('/^(mr\.|mrs\.|ms\.|dr\.|prof\.)\s+/i', '', $name ?? ''));
        $parts = array_filter(explode(' ', $clean));
        if (count($parts) >= 2) {
            return mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1));
        }
        return mb_strtoupper(mb_substr($clean ?: ($name ?: 'TC'), 0, 2));
    };

    // Color palette per department
    $getDeptStyle = function($dept) {
        $d = strtolower($dept ?? '');
        if (str_contains($d, 'math') || str_contains($d, 'calc')) {
            return ['bg' => 'bg-indigo-50 dark:bg-indigo-950/50', 'text' => 'text-indigo-700 dark:text-indigo-300', 'border' => 'border-indigo-200/80'];
        }
        if (str_contains($d, 'sci') || str_contains($d, 'bio') || str_contains($d, 'chem') || str_contains($d, 'phys')) {
            return ['bg' => 'bg-emerald-50 dark:bg-emerald-950/50', 'text' => 'text-emerald-700 dark:text-emerald-300', 'border' => 'border-emerald-200/80'];
        }
        if (str_contains($d, 'eng') || str_contains($d, 'lit') || str_contains($d, 'lang') || str_contains($d, 'kisw')) {
            return ['bg' => 'bg-amber-50 dark:bg-amber-950/50', 'text' => 'text-amber-800 dark:text-amber-300', 'border' => 'border-amber-200/80'];
        }
        if (str_contains($d, 'hum') || str_contains($d, 'hist') || str_contains($d, 'geo') || str_contains($d, 'cre')) {
            return ['bg' => 'bg-rose-50 dark:bg-rose-950/50', 'text' => 'text-rose-700 dark:text-rose-300', 'border' => 'border-rose-200/80'];
        }
        if (str_contains($d, 'art') || str_contains($d, 'mus') || str_contains($d, 'tech') || str_contains($d, 'comp')) {
            return ['bg' => 'bg-violet-50 dark:bg-violet-950/50', 'text' => 'text-violet-700 dark:text-violet-300', 'border' => 'border-violet-200/80'];
        }
        return ['bg' => 'bg-zinc-100 dark:bg-zinc-800', 'text' => 'text-zinc-700 dark:text-zinc-300', 'border' => 'border-zinc-200'];
    };
@endphp

    <div class="flex flex-col gap-6 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">

        {{-- Top Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold tracking-wide uppercase bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-400 border border-blue-200/60">
                        Faculty Management
                    </span>
                    <span class="text-zinc-300">&bull;</span>
                    <span class="text-xs text-zinc-500 font-medium">{{ currentInstitution()?->name ?? 'Campus Faculty' }}</span>
                </div>
                <flux:heading size="xl" class="font-black tracking-tight text-zinc-900 dark:text-white">Teachers Directory</flux:heading>
                <flux:text class="text-zinc-500 text-sm mt-0.5">
                    Manage instructional staff, academic department assignments, credentials, and faculty dossiers.
                </flux:text>
            </div>

            <div class="flex flex-wrap items-center gap-2 sm:gap-3 w-full sm:w-auto">
                <x-export-buttons base-route="teacher.export" module="teacher"
                    :params="['search' => $currentSearch, 'department' => $currentDept, 'status' => $currentStatus]" />
                @can('create teacher')
                    <flux:button href="{{ route('teacher.create') }}" wire:navigate.hover icon="plus" variant="primary" class="font-semibold shadow-sm px-4">
                        Add Teacher
                    </flux:button>
                @endcan
            </div>
        </div>

        {{-- Flash Messages --}}
        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50/80 p-4 flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-3">
                    <flux:icon icon="check-circle" class="size-5 text-emerald-600 shrink-0" />
                    <span class="text-sm font-semibold text-emerald-900">{{ session('success') }}</span>
                </div>
            </div>
        @endif

        {{-- Animated KPI Cards Strip --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
            <x-kpi-card 
                title="Total Faculty" 
                icon="academic-cap" 
                color="sky" 
                :count="$totalTeachers" 
                subtext="Registered instructional staff" 
            />
            
            <x-kpi-card 
                title="Active Teachers" 
                icon="check-badge" 
                color="emerald" 
                :count="$totalActive" 
                subtext="Currently on active duty" 
                href="{{ route('teacher.index', ['status' => 'active']) }}" 
            />
            
            <x-kpi-card 
                title="Academic Departments" 
                icon="building-library" 
                color="indigo" 
                :count="$totalDepartments" 
                subtext="Configured disciplines" 
            />
        </div>

        {{-- Filter Toolbar --}}
        <div class="bg-white dark:bg-zinc-900 rounded-2xl p-3.5 sm:p-4 border border-zinc-200/80 dark:border-zinc-800 shadow-sm flex flex-col gap-3">
            <form method="GET" action="{{ route('teacher.index') }}" class="flex flex-col sm:flex-row sm:flex-wrap items-stretch sm:items-center gap-2.5">
                <div class="relative flex-1 w-full sm:w-auto sm:basis-60 items-center">
                    <div class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 flex items-center text-zinc-400">
                        <flux:icon icon="magnifying-glass" class="size-4" />
                    </div>
                    <input type="text" name="search" value="{{ $currentSearch }}" placeholder="Search teacher name, ID, or email..." 
                        class="w-full bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs py-2.5 sm:py-2 pl-10 pr-4 text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 focus:bg-white dark:focus:bg-zinc-900 focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
                </div>

                <div class="w-full sm:w-auto sm:flex-[0_1_220px]">
                    <select name="department" onchange="this.form.submit()"
                        class="w-full px-3 py-2.5 sm:py-2 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs font-medium text-zinc-800 dark:text-zinc-200 focus:bg-white dark:focus:bg-zinc-900 focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
                        <option value="">All Academic Departments</option>
                        @foreach ($departments as $dept)
                            <option value="{{ $dept }}" {{ $currentDept === $dept ? 'selected' : '' }}>{{ $dept }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="w-full sm:w-auto sm:flex-[0_1_150px]">
                    <select name="status" onchange="this.form.submit()"
                        class="w-full px-3 py-2.5 sm:py-2 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs font-medium text-zinc-800 dark:text-zinc-200 focus:bg-white dark:focus:bg-zinc-900 focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
                        <option value="">All Statuses</option>
                        <option value="active" {{ $currentStatus === 'active' ? 'selected' : '' }}>Active Only</option>
                        <option value="on_leave" {{ $currentStatus === 'on_leave' ? 'selected' : '' }}>On Leave</option>
                        <option value="suspended" {{ $currentStatus === 'suspended' ? 'selected' : '' }}>Suspended</option>
                        <option value="resigned" {{ $currentStatus === 'resigned' ? 'selected' : '' }}>Resigned</option>
                    </select>
                </div>

                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <button type="submit" class="flex-1 sm:flex-initial text-center justify-center shrink-0 rounded-xl bg-zinc-900 dark:bg-white px-4 py-2.5 sm:py-2 text-xs font-bold text-white dark:text-zinc-900 transition hover:bg-zinc-800 dark:hover:bg-zinc-100 shadow-xs">
                        Filter
                    </button>
                    @if($hasActiveFilters)
                        <a href="{{ route('teacher.index') }}" wire:navigate.hover class="shrink-0 rounded-xl bg-zinc-100 dark:bg-zinc-800 px-3 py-2.5 sm:py-2 text-xs font-semibold text-zinc-600 dark:text-zinc-400 transition hover:bg-zinc-200 dark:hover:bg-zinc-700" title="Reset Filters">
                            ✕
                        </a>
                    @endif
                </div>
            </form>

            @if ($hasActiveFilters)
                <div class="flex flex-wrap items-center gap-2 pt-2.5 border-t border-zinc-100 dark:border-zinc-800 text-xs">
                    <span class="text-[11px] font-bold text-zinc-400 uppercase tracking-wider mr-1">Active Filter Scope:</span>

                    @if ($currentDept)
                        <a href="{{ route('teacher.index', ['search' => $currentSearch, 'status' => $currentStatus]) }}" wire:navigate.hover
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 text-zinc-800 dark:text-zinc-200 font-semibold transition border border-zinc-200/60"
                            title="Remove Department Filter">
                            <span>Dept: <strong>{{ $currentDept }}</strong></span>
                            <span class="text-zinc-400 hover:text-zinc-700 font-bold">✕</span>
                        </a>
                    @endif

                    @if ($currentStatus)
                        <a href="{{ route('teacher.index', ['search' => $currentSearch, 'department' => $currentDept]) }}" wire:navigate.hover
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 text-zinc-800 dark:text-zinc-200 font-semibold transition border border-zinc-200/60"
                            title="Remove Status Filter">
                            <span>Status: <strong>{{ ucfirst(str_replace('_', ' ', $currentStatus)) }}</strong></span>
                            <span class="text-zinc-400 hover:text-zinc-700 font-bold">✕</span>
                        </a>
                    @endif

                    @if ($currentSearch)
                        <a href="{{ route('teacher.index', ['department' => $currentDept, 'status' => $currentStatus]) }}" wire:navigate.hover
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-800 font-semibold transition border border-blue-200/60"
                            title="Remove Search Keyword">
                            <span>Search: <strong>"{{ $currentSearch }}"</strong></span>
                            <span class="text-blue-500 hover:text-blue-800 font-bold">✕</span>
                        </a>
                    @endif

                    <a href="{{ route('teacher.index') }}" wire:navigate.hover class="text-xs font-semibold text-rose-600 hover:text-rose-800 transition ml-auto">
                        Reset All Filters
                    </a>
                </div>
            @endif
        </div>

        {{-- Responsive Teacher Roster (Mobile Cards + Desktop High-Density Table) --}}
        <div class="bg-white dark:bg-zinc-900 shadow-sm border border-zinc-200/80 dark:border-zinc-800 rounded-2xl overflow-hidden flex flex-col">
            @if($teachers->isNotEmpty())
                {{-- Mobile Fluid Stacked Cards View (< sm) --}}
                <div class="block sm:hidden divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($teachers as $teacherDetails)
                        @php
                            $teacherUser = $teacherDetails->teacher;
                            $teacherName = $teacherUser?->name ?? 'Unknown Teacher';
                            $initials = $getInitials($teacherName);
                            $deptStyle = $getDeptStyle($teacherDetails->department ?? '');
                            $status = $teacherDetails->status ?? 'active';
                            $empNo = $teacherDetails->employee_number ?? 'EMP-T-' . str_pad($teacherDetails->id, 3, '0', STR_PAD_LEFT);
                        @endphp
                        <div class="p-3.5 space-y-2 hover:bg-zinc-50/60 dark:hover:bg-zinc-800/40 transition-colors">
                            {{-- Row 1: Avatar, Name, Employee No & Status Pill --}}
                            <div class="flex items-start justify-between gap-2.5">
                                <div class="flex items-center gap-2.5 min-w-0 flex-1">
                                    <div class="size-10 rounded-xl bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300 font-bold text-xs flex items-center justify-center shrink-0 border border-blue-100 dark:border-blue-900">
                                        {{ $initials }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <a href="{{ route('teacher.show', $teacherDetails->teacher_id) }}" wire:navigate.hover class="font-bold text-zinc-900 dark:text-white hover:text-blue-600 transition text-sm">
                                                {{ $teacherName }}
                                            </a>
                                            <span class="inline-flex px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 font-mono text-[10px] font-semibold border border-zinc-200 dark:border-zinc-700 whitespace-nowrap shrink-0">
                                                {{ $empNo }}
                                            </span>
                                        </div>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5 font-medium truncate">
                                            @if($teacherDetails->department)
                                                <span class="font-semibold text-zinc-700 dark:text-zinc-300">{{ $teacherDetails->department }}</span>
                                                <span class="text-zinc-300 dark:text-zinc-600">&bull;</span>
                                            @endif
                                            <span>{{ $teacherDetails->qualification ?? 'Faculty' }}</span>
                                        </div>
                                    </div>
                                </div>

                                {{-- Status & Enroll Badges --}}
                                <div class="flex flex-col items-end gap-1 shrink-0">
                                    @if($status === 'active')
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400 border border-emerald-200/80 whitespace-nowrap shrink-0">
                                            <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                            Active
                                        </span>
                                    @elseif($status === 'on_leave')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-50 text-amber-800 dark:bg-amber-950/50 dark:text-amber-400 border border-amber-200 whitespace-nowrap shrink-0">
                                            On Leave
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 whitespace-nowrap shrink-0">
                                            {{ ucfirst($status) }}
                                        </span>
                                    @endif

                                    @if($teacherDetails->can_enroll_students)
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-green-50 text-green-700 dark:bg-green-950/50 dark:text-green-400 border border-green-200/80 shrink-0">
                                            Enroll Access
                                        </span>
                                    @endif
                                </div>
                            </div>

                            {{-- Row 2: Contact Info & Action Buttons --}}
                            <div class="flex items-center justify-between gap-2 pt-1 border-t border-zinc-100 dark:border-zinc-800/80 text-xs">
                                <div class="flex items-center gap-2.5 min-w-0 flex-wrap">
                                    @if($teacherDetails->phone)
                                        <a href="tel:{{ $teacherDetails->phone }}" class="text-xs font-medium text-zinc-700 dark:text-zinc-300 hover:text-blue-600 transition flex items-center gap-1 shrink-0">
                                            <flux:icon icon="phone" class="size-3 text-zinc-400 shrink-0" />
                                            <span class="font-mono text-[11px]">{{ $teacherDetails->phone }}</span>
                                        </a>
                                    @endif
                                    @if($teacherUser?->email)
                                        <a href="mailto:{{ $teacherUser->email }}" class="text-xs font-medium text-zinc-500 hover:text-blue-600 transition flex items-center gap-1 truncate max-w-[150px]">
                                            <flux:icon icon="envelope" class="size-3 text-zinc-400 shrink-0" />
                                            <span class="truncate text-[11px]">{{ $teacherUser->email }}</span>
                                        </a>
                                    @endif
                                </div>

                                <div class="flex items-center gap-1 shrink-0">
                                    @if($teacherUser && ! $teacherUser->hasVerifiedEmail())
                                        <flux:button wire:click.stop="resendVerification({{ $teacherUser->id }})" size="xs" variant="primary" color="amber" class="text-[10px] px-2 py-0.5" title="Resend email verification link to {{ $teacherUser->email }}">
                                            Resend Link
                                        </flux:button>
                                    @endif
                                    <flux:button href="{{ route('teacher.show', $teacherDetails->teacher_id) }}" wire:navigate.hover size="xs" variant="subtle" title="View Profile">
                                        View
                                    </flux:button>
                                    <x-impersonate-button :user="$teacherDetails->teacher" />
                                    @can('edit teacher')
                                        <flux:button href="{{ route('teacher.edit', $teacherDetails->teacher_id) }}" wire:navigate.hover size="xs" icon="pencil-square" variant="subtle" title="Edit Teacher" />
                                    @endcan
                                    @can('delete teacher')
                                        <x-confirm-form
                                            :action="route('teacher.destroy', $teacherDetails->teacher_id)"
                                            title="Remove {{ $teacherName }} from the faculty?"
                                            message="This removes the teacher's profile and system access."
                                            confirm="Remove Teacher"
                                            trigger-class="text-rose-500 hover:text-rose-600 text-xs"
                                            tooltip="Delete" />
                                    @endcan
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Desktop High-Density 4-Column Table (Hidden on Mobile) --}}
                <div class="hidden sm:block w-full overflow-x-auto">
                    <table class="w-full text-left text-sm table-fixed min-w-[720px]">
                        <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-500 border-b border-zinc-200/80 dark:border-zinc-700 text-xs font-bold uppercase tracking-wider">
                            <tr>
                                <th class="py-3.5 px-5 w-[42%]">Teacher Personnel &amp; ID</th>
                                <th class="py-3.5 px-5 w-[28%]">Department &amp; Qualification</th>
                                <th class="py-3.5 px-5 w-[18%]">Contact</th>
                                <th class="py-3.5 px-5 w-[12%] text-right">Status &amp; Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($teachers as $teacherDetails)
                                @php
                                    $teacherUser = $teacherDetails->teacher;
                                    $teacherName = $teacherUser?->name ?? 'Unknown Teacher';
                                    $initials = $getInitials($teacherName);
                                    $deptStyle = $getDeptStyle($teacherDetails->department ?? '');
                                    $status = $teacherDetails->status ?? 'active';
                                @endphp
                                <tr class="hover:bg-zinc-50/60 dark:hover:bg-zinc-800/40 transition-colors cursor-pointer group" onclick="window.location='{{ route('teacher.show', $teacherDetails->teacher_id) }}'">
                                    <td class="py-4 px-5">
                                        <div style="display: flex; align-items: center; gap: 0.875rem;">
                                            <div style="width: 2.75rem; height: 2.75rem; min-width: 2.75rem; min-height: 2.75rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0;"
                                                class="rounded-xl bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300 font-bold text-xs group-hover:bg-blue-600 group-hover:text-white transition-colors shadow-2xs">
                                                {{ $initials }}
                                            </div>
                                            <div style="min-width: 0; flex: 1;">
                                                <a href="{{ route('teacher.show', $teacherDetails->teacher_id) }}" wire:navigate.hover class="font-bold text-zinc-900 dark:text-white group-hover:text-blue-600 transition truncate block text-sm" onclick="event.stopPropagation();">
                                                    {{ $teacherName }}
                                                </a>
                                                <div style="display: flex; align-items: center; gap: 0.4rem; margin-top: 0.25rem; flex-wrap: nowrap;">
                                                    <span style="white-space: nowrap;" class="px-2 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 font-mono font-semibold border border-zinc-200 dark:border-zinc-700 text-[11px]">
                                                        {{ $teacherDetails->employee_number ?? 'EMP-T-' . str_pad($teacherDetails->id, 3, '0', STR_PAD_LEFT) }}
                                                    </span>
                                                    <span class="text-zinc-300">&bull;</span>
                                                    <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" class="font-sans text-[11px] text-zinc-500">
                                                        {{ $teacherUser?->email ?? 'No email' }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="py-4 px-5">
                                        <div style="display: flex; flex-direction: column; gap: 0.25rem; align-items: flex-start;">
                                            @if($teacherDetails->department)
                                                <span class="px-2.5 py-0.5 rounded-lg text-xs font-semibold border {{ $deptStyle['bg'] }} {{ $deptStyle['text'] }} {{ $deptStyle['border'] }}">
                                                    {{ $teacherDetails->department }}
                                                </span>
                                            @else
                                                <span class="text-xs text-zinc-400 font-medium">No department</span>
                                            @endif
                                            <span class="text-xs text-zinc-500 dark:text-zinc-400 font-medium truncate max-w-[200px]">
                                                {{ $teacherDetails->qualification ?? 'B.Ed. Faculty' }}
                                            </span>
                                        </div>
                                    </td>

                                    <td class="py-4 px-5">
                                        <div style="display: flex; flex-direction: column; gap: 0.2rem;">
                                            <span class="text-xs font-semibold text-zinc-800 dark:text-zinc-200 font-mono">
                                                {{ $teacherDetails->phone ?? '—' }}
                                            </span>
                                            <span class="text-[11px] text-zinc-400">
                                                Joined {{ $teacherDetails->created_at?->format('M Y') ?? 'Recently' }}
                                            </span>
                                        </div>
                                    </td>

                                    <td class="py-4 px-5 text-right" onclick="event.stopPropagation();">
                                        <div style="display: flex; align-items: center; justify-content: flex-end; gap: 0.5rem; flex-wrap: wrap;">
                                            @if($status === 'active')
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400 border border-emerald-200/80">
                                                    <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                                    Active
                                                </span>
                                            @elseif($status === 'on_leave')
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-800 dark:bg-amber-950/50 dark:text-amber-400 border border-amber-200">
                                                    On Leave
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                                                    {{ ucfirst($status) }}
                                                </span>
                                            @endif

                                            @if($teacherDetails->can_enroll_students)
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-green-50 text-green-700 dark:bg-green-950/50 dark:text-green-400 border border-green-200/80">
                                                    <flux:icon icon="check-circle" class="size-3.5" />
                                                    Can Enroll
                                                </span>
                                            @endif

                                            @if($teacherUser && ! $teacherUser->hasVerifiedEmail())
                                                <button type="button" wire:click.stop="resendVerification({{ $teacherUser->id }})" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-400 border border-amber-200 hover:bg-amber-100 transition shadow-2xs cursor-pointer" title="Resend email verification link to {{ $teacherUser->email }}">
                                                    <flux:icon icon="paper-airplane" class="size-3.5 text-amber-600" />
                                                    Resend Link
                                                </button>
                                            @endif

                                            <flux:button href="{{ route('teacher.show', $teacherDetails->teacher_id) }}" wire:navigate.hover size="sm" icon="eye" variant="subtle" title="View Profile" />
                                            <x-impersonate-button :user="$teacherDetails->teacher" />
                                            @can('delete teacher')
                                                <button type="button" wire:click.stop="deleteTeacher({{ $teacherDetails->id }})" wire:confirm="Are you sure you want to remove {{ $teacherName }} from the faculty roster?" class="size-7 inline-flex items-center justify-center rounded-lg text-zinc-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition" title="Remove Teacher">
                                                    <flux:icon icon="trash" class="size-4" />
                                                </button>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if(method_exists($teachers, 'hasPages') && $teachers->hasPages())
                    <div class="px-5 py-4 border-t border-zinc-100 dark:border-zinc-800 flex items-center justify-between">
                        {{ $teachers->links() }}
                    </div>
                @endif
            @else
                <div class="py-16 text-center">
                    <flux:icon icon="user-group" class="size-12 mx-auto text-zinc-300 mb-3" />
                    <h4 class="text-base font-bold text-zinc-900 dark:text-white">No Teachers Found</h4>
                    <p class="text-xs text-zinc-500 mt-1 mb-4">No instructors match your active search or department filters.</p>
                    @if($hasActiveFilters)
                        <a href="{{ route('teacher.index') }}" wire:navigate.hover class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-zinc-100 hover:bg-zinc-200 text-zinc-700 text-xs font-semibold transition">
                            Clear Active Filters
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </div>
