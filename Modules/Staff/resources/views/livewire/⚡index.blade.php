<?php

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Staff\Models\StaffDetails;

new #[Title('Staff Directory')] class extends Component
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
        abort_unless(auth()->user()->can('view staff'), 403);
    }

    #[Computed]
    public function staff(): LengthAwarePaginator
    {
        return StaffDetails::with(['user', 'institution'])
            ->when(! isAdmin(), fn ($q) => $q->where('institution_id', currentInstitutionId()))
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';

                $q->where(function ($sub) use ($term) {
                    $sub->where('name', 'like', $term)
                        ->orWhere('staff_number', 'like', $term)
                        ->orWhere('job_title', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhereHas('user', fn ($uq) => $uq->where('email', 'like', $term));
                });
            })
            ->when($this->department !== '', fn ($q) => $q->where('department', $this->department))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->orderBy('name')
            ->paginate(15);
    }

    #[Computed]
    public function totalStaff(): int
    {
        return StaffDetails::when(! isAdmin(), fn ($q) => $q->where('institution_id', currentInstitutionId()))->count();
    }

    #[Computed]
    public function activeStaff(): int
    {
        return StaffDetails::when(! isAdmin(), fn ($q) => $q->where('institution_id', currentInstitutionId()))
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
        return StaffDetails::when(! isAdmin(), fn ($q) => $q->where('institution_id', currentInstitutionId()))
            ->whereNotNull('department')
            ->where('department', '!=', '')
            ->distinct()
            ->pluck('department')
            ->sort()
            ->values()
            ->all();
    }
}; ?>

@php
    $staff = $this->staff;
    $totalStaff = $this->totalStaff;
    $activeStaff = $this->activeStaff;
    $totalDepartments = $this->totalDepartments;
    $departments = $this->departments;

    $currentSearch = $this->search;
    $currentDepartment = $this->department;
    $currentStatus = $this->status;

    $totalCount = method_exists($staff, 'total') ? $staff->total() : count($staff);
    $hasActiveFilters = !empty($currentSearch) || !empty($currentDepartment) || !empty($currentStatus);
@endphp

    <div class="flex flex-col gap-6 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">

        {{-- Top Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold tracking-wide uppercase bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-400 border border-blue-200/60">
                        Human Resources
                    </span>
                    <span class="text-zinc-300">&bull;</span>
                    <span class="text-xs text-zinc-500 font-medium">{{ currentInstitution()?->name ?? 'Campus Operations' }}</span>
                </div>
                <flux:heading size="xl" class="font-black tracking-tight text-zinc-900 dark:text-white">Staff Directory</flux:heading>
                <flux:text class="text-zinc-500 text-sm mt-0.5">
                    Manage administrative, operational, and non-teaching personnel, contracts, and compensation.
                </flux:text>
            </div>

            <div class="flex flex-wrap items-center gap-2 sm:gap-3 w-full sm:w-auto">
                <x-export-buttons base-route="staff.export" module="staff"
                    :params="['search' => $currentSearch, 'department' => $currentDepartment, 'status' => $currentStatus]" />
                @can('create staff')
                    <flux:button href="{{ route('staff.create') }}" wire:navigate.hover icon="plus" variant="primary" class="font-semibold shadow-sm px-4">
                        Add Staff Member
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

        {{-- Top Executive Metrics Strip --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
            <x-kpi-card 
                title="Total Staff" 
                icon="users" 
                color="sky" 
                :count="$totalStaff" 
                subtext="Registered staff members" 
            />
            
            <x-kpi-card 
                title="Active Personnel" 
                icon="check-badge" 
                color="emerald" 
                :count="$activeStaff" 
                subtext="Currently on active duty" 
                href="{{ route('staff.index', ['status' => 'active']) }}" 
            />
            
            <x-kpi-card 
                title="Operational Units" 
                icon="building-office-2" 
                color="indigo" 
                :count="$totalDepartments" 
                subtext="Configured departments" 
            />
        </div>

        {{-- Reusable Single-Row Filter Toolbar --}}
        <x-filter-toolbar 
            action="{{ route('staff.index') }}"
            placeholder="Search staff name, employee ID, role, or phone..."
            :search="$currentSearch"
            :hasActiveFilters="$hasActiveFilters"
            resetRoute="{{ route('staff.index') }}"
        >
            {{-- Department Filter --}}
            <div class="w-full sm:w-auto sm:flex-[0_1_200px]">
                <select name="department" onchange="this.form.submit()"
                    class="w-full px-3 py-2 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs font-medium text-zinc-800 dark:text-zinc-200 focus:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
                    <option value="">All Departments</option>
                    @foreach ($departments as $dept)
                        <option value="{{ $dept }}" {{ $currentDepartment == $dept ? 'selected' : '' }}>{{ $dept }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Status Filter --}}
            <div class="w-full sm:w-auto sm:flex-[0_1_150px]">
                <select name="status" onchange="this.form.submit()"
                    class="w-full px-3 py-2 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs font-medium text-zinc-800 dark:text-zinc-200 focus:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
                    <option value="">All Statuses</option>
                    <option value="active" {{ $currentStatus == 'active' ? 'selected' : '' }}>Active Only</option>
                    <option value="on_leave" {{ $currentStatus == 'on_leave' ? 'selected' : '' }}>On Leave</option>
                    <option value="suspended" {{ $currentStatus == 'suspended' ? 'selected' : '' }}>Suspended</option>
                    <option value="resigned" {{ $currentStatus == 'resigned' ? 'selected' : '' }}>Resigned</option>
                </select>
            </div>

            {{-- Filter Scope Removal Tags --}}
            <x-slot:scopeTags>
                @if ($currentDepartment)
                    <a href="{{ route('staff.index', ['search' => $currentSearch, 'status' => $currentStatus]) }}" 
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 text-zinc-800 dark:text-zinc-200 font-semibold transition border border-zinc-200/60"
                        title="Remove Department Filter">
                        <span>Dept: <strong>{{ $currentDepartment }}</strong></span>
                        <span class="text-zinc-400 hover:text-zinc-700 font-bold">✕</span>
                    </a>
                @endif

                @if ($currentStatus)
                    <a href="{{ route('staff.index', ['search' => $currentSearch, 'department' => $currentDepartment]) }}" 
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 text-zinc-800 dark:text-zinc-200 font-semibold transition border border-zinc-200/60"
                        title="Remove Status Filter">
                        <span>Status: <strong>{{ ucfirst(str_replace('_', ' ', $currentStatus)) }}</strong></span>
                        <span class="text-zinc-400 hover:text-zinc-700 font-bold">✕</span>
                    </a>
                @endif

                @if ($currentSearch)
                    <a href="{{ route('staff.index', ['department' => $currentDepartment, 'status' => $currentStatus]) }}" 
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-800 font-semibold transition border border-blue-200/60"
                        title="Remove Search Keyword">
                        <span>Search: <strong>"{{ $currentSearch }}"</strong></span>
                        <span class="text-blue-500 hover:text-blue-800 font-bold">✕</span>
                    </a>
                @endif
            </x-slot:scopeTags>
        </x-filter-toolbar>

        {{-- Responsive Staff Ledger (Mobile Cards + Desktop High-Density Table) --}}
        <div class="bg-white dark:bg-zinc-900 shadow-sm border border-zinc-200/80 dark:border-zinc-800 rounded-2xl overflow-hidden flex flex-col">
            @if($staff->isNotEmpty())
                {{-- Mobile Fluid Stacked Cards View (< sm) --}}
                <div class="block sm:hidden divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($staff as $member)
                        @php
                            $initials = strtoupper(substr($member->name, 0, 2));
                            $phone = $member->phone ?? '';
                            $email = $member->user?->email ?? '';
                            $staffNo = $member->staff_number ?? 'EMP-S-' . str_pad($member->id, 3, '0', STR_PAD_LEFT);
                        @endphp
                        <div class="p-3.5 space-y-2 hover:bg-zinc-50/60 dark:hover:bg-zinc-800/40 transition-colors">
                            {{-- Row 1: Avatar, Name, Staff No & Status --}}
                            <div class="flex items-start justify-between gap-2.5">
                                <div class="flex items-center gap-2.5 min-w-0 flex-1">
                                    <div class="size-10 rounded-xl bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 font-bold text-xs flex items-center justify-center text-zinc-800 dark:text-zinc-200 shrink-0">
                                        {{ $initials }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <a href="{{ route('staff.show', $member->id) }}" wire:navigate.hover class="font-bold text-zinc-900 dark:text-white hover:text-blue-600 transition text-sm">
                                                {{ $member->name }}
                                            </a>
                                            <span class="inline-flex px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 font-mono text-[10px] font-semibold border border-zinc-200 dark:border-zinc-700 whitespace-nowrap shrink-0">
                                                {{ $staffNo }}
                                            </span>
                                        </div>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5 font-medium truncate">
                                            <span>{{ $member->job_title ?? 'Staff Personnel' }}</span>
                                            @if($member->department)
                                                <span class="text-zinc-300 dark:text-zinc-600">&bull;</span>
                                                <span class="text-zinc-600 dark:text-zinc-300">{{ $member->department }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="shrink-0">
                                    @if($member->status === 'active')
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400 border border-emerald-200 whitespace-nowrap shrink-0">
                                            <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                            Active
                                        </span>
                                    @elseif($member->status === 'on_leave')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-50 text-amber-800 dark:bg-amber-950/50 dark:text-amber-400 border border-amber-200 whitespace-nowrap shrink-0">
                                            On Leave
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-700 whitespace-nowrap shrink-0">
                                            {{ ucfirst($member->status ?? 'Inactive') }}
                                        </span>
                                    @endif
                                </div>
                            </div>

                            {{-- Row 2: Contact, Salary & Quick Action Buttons --}}
                            <div class="flex items-center justify-between gap-2 pt-1 border-t border-zinc-100 dark:border-zinc-800/80 text-xs">
                                <div class="flex items-center gap-2.5 min-w-0 flex-wrap">
                                    @if($phone)
                                        <a href="tel:{{ $phone }}" class="text-xs font-medium text-zinc-700 dark:text-zinc-300 hover:text-blue-600 transition flex items-center gap-1 shrink-0">
                                            <flux:icon icon="phone" class="size-3 text-zinc-400 shrink-0" />
                                            <span class="font-mono text-[11px]">{{ $phone }}</span>
                                        </a>
                                    @endif
                                    @if($member->salary)
                                        <span class="font-mono text-[11px] font-bold text-zinc-800 dark:text-zinc-200 shrink-0">
                                            KES {{ number_format($member->salary) }}
                                        </span>
                                    @endif
                                </div>

                                <div class="flex items-center gap-1 shrink-0">
                                    <flux:button href="{{ route('staff.show', $member->id) }}" wire:navigate.hover size="xs" variant="subtle" title="View Profile">
                                        View
                                    </flux:button>
                                    @can('edit staff')
                                        <flux:button href="{{ route('staff.edit', $member->id) }}" wire:navigate.hover size="xs" icon="pencil-square" variant="subtle" title="Edit Staff" />
                                    @endcan
                                    @can('delete staff')
                                        <x-confirm-form
                                            :action="route('staff.destroy', $member->id)"
                                            title="Delete this staff record?"
                                            message="This removes their staff record and system access."
                                            confirm="Delete Staff"
                                            trigger-class="text-rose-600 hover:text-rose-700 text-xs"
                                            tooltip="Delete Staff" />
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
                                <th class="py-3.5 px-5 w-4/12">Staff Personnel &amp; ID</th>
                                <th class="py-3.5 px-5 w-3/12">Role &amp; Department</th>
                                <th class="py-3.5 px-5 w-2.5/12">Contact &amp; Salary</th>
                                <th class="py-3.5 px-5 w-2.5/12 text-right">Status &amp; Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($staff as $member)
                                @php
                                    $initials = strtoupper(substr($member->name, 0, 2));
                                    $phone = $member->phone ?? '';
                                    $email = $member->user?->email ?? '';
                                    $staffNo = $member->staff_number ?? 'EMP-S-' . str_pad($member->id, 3, '0', STR_PAD_LEFT);
                                @endphp
                                <tr class="hover:bg-zinc-50/60 dark:hover:bg-zinc-800/40 transition-colors">
                                    <td class="py-4 px-5">
                                        <div class="flex items-center gap-3">
                                            <div class="size-10 rounded-xl bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 font-bold text-xs flex items-center justify-center text-zinc-800 dark:text-zinc-200 shrink-0">
                                                {{ $initials }}
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <a href="{{ route('staff.show', $member->id) }}" wire:navigate.hover class="font-bold text-zinc-900 dark:text-white hover:text-zinc-600 transition truncate block">
                                                    {{ $member->name }}
                                                </a>
                                                <div class="text-xs text-zinc-400 mt-0.5 flex items-center gap-1.5 font-mono">
                                                    <span class="px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 font-semibold border border-zinc-200 dark:border-zinc-700 text-[11px]">{{ $staffNo }}</span>
                                                    @if($email)
                                                        <span class="text-zinc-300">&bull;</span>
                                                        <span class="text-[11px] text-zinc-500 font-sans truncate">{{ $email }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="py-4 px-5">
                                        <div class="font-bold text-xs text-zinc-900 dark:text-white truncate" title="{{ $member->job_title }}">
                                            {{ $member->job_title ?? 'Staff Personnel' }}
                                        </div>
                                        <div class="text-[11px] text-zinc-500 dark:text-zinc-400 mt-1 font-medium">
                                            <span class="px-2 py-0.5 rounded-md bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 font-medium border border-zinc-200/60 dark:border-zinc-700">
                                                {{ $member->department ?? 'General' }}
                                            </span>
                                        </div>
                                    </td>

                                    <td class="py-4 px-5">
                                        @if($phone)
                                            <a href="tel:{{ $phone }}" class="text-xs font-semibold text-zinc-800 dark:text-zinc-200 hover:text-blue-600 transition flex items-center gap-1 truncate">
                                                <flux:icon icon="phone" class="size-3 text-zinc-400 shrink-0" />
                                                <span class="truncate">{{ $phone }}</span>
                                            </a>
                                        @else
                                            <span class="text-xs text-zinc-400 italic">—</span>
                                        @endif
                                        <div class="font-mono text-xs font-bold text-zinc-900 dark:text-zinc-100 mt-1 truncate">
                                            {{ $member->salary ? 'KES ' . number_format($member->salary, 2) : '—' }}
                                        </div>
                                    </td>

                                    <td class="py-4 px-5 text-right">
                                        <div class="flex flex-col items-end gap-1.5">
                                            @if($member->status === 'active')
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400 border border-emerald-200">
                                                    <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                                    Active
                                                </span>
                                            @elseif($member->status === 'on_leave')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-50 text-amber-800 dark:bg-amber-950/50 dark:text-amber-400 border border-amber-200">
                                                    On Leave
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-700">
                                                    {{ ucfirst($member->status ?? 'Inactive') }}
                                                </span>
                                            @endif

                                            <div class="flex items-center justify-end gap-1">
                                                <flux:button href="{{ route('staff.show', $member->id) }}" wire:navigate.hover size="sm" icon="eye" variant="subtle" title="View Profile" />
                                                @can('edit staff')
                                                    <flux:button href="{{ route('staff.edit', $member->id) }}" wire:navigate.hover size="sm" icon="pencil-square" variant="subtle" title="Edit Staff" />
                                                @endcan
                                                @can('delete staff')
                                                    <x-confirm-form
                                                        :action="route('staff.destroy', $member->id)"
                                                        title="Delete this staff record?"
                                                        message="This removes their staff record and system access."
                                                        confirm="Delete Staff"
                                                        trigger-class="text-rose-600 hover:text-rose-700"
                                                        tooltip="Delete Staff" />
                                                @endcan
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($staff->hasPages())
                    <div class="px-5 py-4 border-t border-zinc-100 dark:border-zinc-800 flex items-center justify-between">
                        {{ $staff->links() }}
                    </div>
                @endif
            @else
                <div class="py-16 text-center">
                    <flux:icon icon="users" class="size-12 mx-auto text-zinc-300 mb-3" />
                    <h4 class="text-base font-bold text-zinc-900 dark:text-white">No Staff Members Found</h4>
                    <p class="text-xs text-zinc-500 mt-1 mb-4">No staff records match your active search or filter criteria.</p>
                    @can('create staff')
                        <flux:button href="{{ route('staff.create') }}" variant="primary" size="sm">
                            Add Staff Member
                        </flux:button>
                    @endcan
                </div>
            @endif
        </div>
    </div>
