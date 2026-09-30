@php
    $totalCount = method_exists($staff, 'total') ? $staff->total() : count($staff);
    $currentDept = request('department');
    $currentStatus = request('status');
    $currentSearch = request('search');
    $hasActiveFilters = !empty($currentDept) || !empty($currentStatus) || !empty($currentSearch);
@endphp

<x-layouts::app :title="__('Staff Directory')">
    <div class="flex flex-col gap-8 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">
        
        {{-- Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold tracking-wide uppercase bg-blue-50 text-blue-700 border border-blue-200/60">
                        Human Resources
                    </span>
                    <span class="text-xs text-zinc-400">&bull;</span>
                    <span class="text-xs text-zinc-500 font-medium">{{ $institution?->name ?? currentInstitution()?->name ?? __('Institution') }}</span>
                </div>
                <flux:heading size="xl" class="font-black tracking-tight text-zinc-900">Staff Directory</flux:heading>
                <flux:text class="text-zinc-500 text-sm mt-0.5">
                    Manage institutional personnel, professional designations, departments, and payroll allocations.
                </flux:text>
            </div>
            
            <div class="flex items-center gap-3">
                <flux:button href="{{ route('staff.payments.index') }}" icon="credit-card" variant="subtle" class="font-semibold shadow-sm">
                    Staff Payments
                </flux:button>
                @can('create staff')
                    <flux:button href="{{ route('staff.create') }}" icon="plus" variant="primary" class="font-semibold shadow-sm px-4">
                        Add Staff
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

        {{-- Top Staff & Payroll KPI Strip --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
            <x-kpi-card 
                title="Total Staff Employed" 
                icon="users" 
                color="sky" 
                :count="$totalStaffCount ?? $totalCount" 
                subtext="Verified employment contracts" 
            />
            
            <x-kpi-card 
                title="Operational Units" 
                icon="building-office-2" 
                color="indigo" 
                :count="$deptCount ?? count($departments)" 
                subtext="Active administrative branches" 
            />
            
            <x-kpi-card 
                title="Monthly Payroll Commitment" 
                icon="banknotes" 
                color="emerald" 
                count="KES {{ number_format($totalPayroll ?? 0, 2) }}" 
                href="{{ route('staff.payments.index') }}"
                subtext="Institutional payroll allocation" 
            />
        </div>

                {{-- Reusable Single-Row Filter Toolbar --}}
        <x-filter-toolbar 
            action="{{ route('staff.index') }}"
            placeholder="Search staff name, staff no, or email..."
            :search="$currentSearch"
            :hasActiveFilters="$hasActiveFilters"
            resetRoute="{{ route('staff.index') }}"
        >
            {{-- Department Selector --}}
            <div style="flex: 0 1 220px;">
                <select name="department" onchange="this.form.submit()"
                    class="w-full px-3 py-2 bg-zinc-50 border border-zinc-200 rounded-xl text-xs font-medium text-zinc-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
                    <option value="">All Departments ({{ count($departments) }})</option>
                    @foreach ($departments as $dept)
                        <option value="{{ $dept }}" {{ $currentDept === $dept ? 'selected' : '' }}>
                            {{ $dept }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Status Selector --}}
            <div style="flex: 0 1 150px;">
                <select name="status" onchange="this.form.submit()"
                    class="w-full px-3 py-2 bg-zinc-50 border border-zinc-200 rounded-xl text-xs font-medium text-zinc-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
                    <option value="">All Statuses</option>
                    <option value="active" {{ $currentStatus === 'active' ? 'selected' : '' }}>Active Only</option>
                    <option value="inactive" {{ $currentStatus === 'inactive' ? 'selected' : '' }}>Inactive Only</option>
                    <option value="on_leave" {{ $currentStatus === 'on_leave' ? 'selected' : '' }}>On Leave</option>
                    <option value="suspended" {{ $currentStatus === 'suspended' ? 'selected' : '' }}>Suspended</option>
                </select>
            </div>

            <x-slot:scopeTags>
                @if ($currentDept)
                    <a href="{{ route('staff.index', ['search' => $currentSearch, 'status' => $currentStatus]) }}" 
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-zinc-100 hover:bg-zinc-200 text-zinc-800 font-semibold transition border border-zinc-200/60"
                        title="Remove Department Filter">
                        <span>Dept: <strong>{{ $currentDept }}</strong></span>
                        <span class="text-zinc-400 hover:text-zinc-700 font-bold">✕</span>
                    </a>
                @endif

                @if ($currentStatus)
                    <a href="{{ route('staff.index', ['search' => $currentSearch, 'department' => $currentDept]) }}" 
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-zinc-100 hover:bg-zinc-200 text-zinc-800 font-semibold transition border border-zinc-200/60"
                        title="Remove Status Filter">
                        <span>Status: <strong>{{ ucfirst(str_replace('_', ' ', $currentStatus)) }}</strong></span>
                        <span class="text-zinc-400 hover:text-zinc-700 font-bold">✕</span>
                    </a>
                @endif

                @if ($currentSearch)
                    <a href="{{ route('staff.index', ['department' => $currentDept, 'status' => $currentStatus]) }}" 
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-800 font-semibold transition border border-blue-200/60"
                        title="Remove Search Keyword">
                        <span>Search: <strong>"{{ $currentSearch }}"</strong></span>
                        <span class="text-blue-500 hover:text-blue-800 font-bold">✕</span>
                    </a>
                @endif
            </x-slot:scopeTags>
        </x-filter-toolbar>

        {{-- Smart Grouped 4-Column Staff Ledger (Zero Horizontal Scroll) --}}
        <div class="bg-white shadow-sm border border-zinc-200/80 rounded-2xl overflow-hidden flex flex-col">
            @if($staff->isNotEmpty())
                <div class="w-full overflow-x-auto">
                    <table class="w-full text-left text-sm table-fixed min-w-[720px]">
                        <thead class="bg-zinc-50 text-zinc-500 border-b border-zinc-200/80 text-xs font-bold uppercase tracking-wider">
                            <tr>
                                <th class="py-3.5 px-5 w-4/12">Staff Personnel & ID</th>
                                <th class="py-3.5 px-5 w-3/12">Role & Department</th>
                                <th class="py-3.5 px-5 w-2.5/12">Contact & Salary</th>
                                <th class="py-3.5 px-5 w-2.5/12 text-right">Status & Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100">
                            @foreach ($staff as $member)
                                @php
                                    $initials = strtoupper(substr($member->name, 0, 2));
                                    $phone = $member->phone ?? '';
                                    $email = $member->user?->email ?? '';
                                    $staffNo = $member->staff_number ?? 'EMP-S-' . str_pad($member->id, 3, '0', STR_PAD_LEFT);
                                @endphp
                                <tr class="hover:bg-zinc-50/60 transition-colors">
                                    {{-- Col 1: Staff Personnel & ID --}}
                                    <td class="py-4 px-5">
                                        <div class="flex items-center gap-3">
                                            <div class="size-10 rounded-xl bg-zinc-100 border border-zinc-200 font-bold text-xs flex items-center justify-center text-zinc-800 shrink-0">
                                                {{ $initials }}
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <a href="{{ route('staff.show', $member->id) }}" class="font-bold text-zinc-900 hover:text-zinc-600 transition truncate block">
                                                    {{ $member->name }}
                                                </a>
                                                <div class="text-xs text-zinc-400 mt-0.5 flex flex-wrap items-center gap-1.5 font-mono">
                                                    <span class="px-1.5 py-0.5 rounded bg-zinc-100 text-zinc-700 font-semibold border border-zinc-200 text-[11px]">{{ $staffNo }}</span>
                                                    @if($email)
                                                        <span class="text-zinc-300">&bull;</span>
                                                        <a href="mailto:{{ $email }}" class="text-zinc-500 hover:text-zinc-800 font-sans text-xs truncate max-w-[160px]">{{ $email }}</a>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Col 2: Role & Department --}}
                                    <td class="py-4 px-5">
                                        <div class="font-bold text-xs text-zinc-900 truncate" title="{{ $member->job_title }}">
                                            {{ $member->job_title ?? 'Staff Personnel' }}
                                        </div>
                                        <div class="text-[11px] text-zinc-500 mt-1 font-medium">
                                            <span class="px-2 py-0.5 rounded-md bg-zinc-100 text-zinc-700 font-medium border border-zinc-200/60">
                                                {{ $member->department ?? 'General' }}
                                            </span>
                                        </div>
                                    </td>

                                    {{-- Col 3: Contact & Monthly Salary --}}
                                    <td class="py-4 px-5">
                                        @if($phone)
                                            <a href="tel:{{ $phone }}" class="text-xs font-semibold text-zinc-800 hover:text-blue-600 transition flex items-center gap-1">
                                                <flux:icon icon="phone" class="size-3 text-zinc-400 shrink-0" />
                                                <span class="truncate">{{ $phone }}</span>
                                            </a>
                                        @else
                                            <span class="text-xs text-zinc-400 italic">—</span>
                                        @endif
                                        <div class="font-mono text-xs font-bold text-zinc-900 mt-1">
                                            {{ $member->salary ? 'KES ' . number_format($member->salary, 2) : '—' }}
                                        </div>
                                    </td>

                                    {{-- Col 4: Status & Actions --}}
                                    <td class="py-4 px-5 text-right">
                                        <div class="flex flex-col items-end gap-2">
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold
                                                {{ $member->is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-zinc-100 text-zinc-600 border border-zinc-200' }}">
                                                <span class="size-1.5 rounded-full {{ $member->is_active ? 'bg-emerald-500' : 'bg-zinc-400' }}"></span>
                                                {{ ucfirst(str_replace('_', ' ', $member->status)) }}
                                            </span>

                                            <div class="flex items-center justify-end gap-1">
                                                <flux:button href="{{ route('staff.show', $member->id) }}" size="sm" icon="eye" variant="subtle" tooltip="View Profile" />
                                                @can('edit staff')
                                                    <flux:button href="{{ route('staff.edit', $member->id) }}" size="sm" icon="pencil-square" variant="subtle" tooltip="Edit Staff" />
                                                @endcan
                                                @can('delete staff')
                                                    <form action="{{ route('staff.destroy', $member->id) }}" method="POST" class="inline" onsubmit="return confirm('Remove this staff member?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <flux:button type="submit" size="sm" icon="trash" variant="subtle" class="text-rose-500 hover:text-rose-600 hover:bg-rose-50" tooltip="Delete" />
                                                    </form>
                                                @endcan
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Table Footer Summary & Pagination --}}
                <div class="border-t border-zinc-200/80 px-6 py-4 bg-zinc-50/50 flex flex-col sm:flex-row justify-between items-center text-xs text-zinc-500 gap-4">
                    <div class="flex items-center gap-3">
                        <span>Showing <strong class="text-zinc-800 font-semibold">{{ $staff->count() }}</strong> of <strong class="text-zinc-800 font-semibold">{{ number_format($totalCount) }}</strong> staff members</span>
                        <span class="text-zinc-300">&bull;</span>
                        <span>Page <strong class="text-zinc-800 font-semibold">{{ $staff->currentPage() }}</strong> of <strong class="text-zinc-800 font-semibold">{{ $staff->lastPage() }}</strong></span>
                    </div>
                    @if(method_exists($staff, 'links') && $staff->hasPages())
                        <div>
                            {{ $staff->links() }}
                        </div>
                    @endif
                </div>
            @else
                {{-- Empty State --}}
                <div class="flex flex-col items-center justify-center py-20 text-center">
                    <div class="size-14 rounded-2xl bg-zinc-100 flex items-center justify-center text-zinc-400 mb-3">
                        <flux:icon icon="users" class="size-7" />
                    </div>
                    <h4 class="text-base font-bold text-zinc-900">No Staff Members Found</h4>
                    <p class="text-zinc-500 text-xs max-w-sm mx-auto mt-1 mb-4">
                        There are no personnel matching your active filter criteria. Try resetting your search or department filter.
                    </p>
                    @can('create staff')
                        <flux:button href="{{ route('staff.create') }}" variant="primary" icon="plus" size="sm">
                            Add Staff Member
                        </flux:button>
                    @endcan
                </div>
            @endif
        </div>
    </div>
</x-layouts::app>