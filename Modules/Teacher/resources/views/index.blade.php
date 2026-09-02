@php
    $totalCount = method_exists($teachers, 'total') ? $teachers->total() : count($teachers);
    $currentSearch = request('search');
    $currentDept = request('department');
    $currentStatus = request('status');
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

    // Department style mapping
    $getDeptStyle = function($dept) {
        if (str_contains($dept, 'Computer') || str_contains($dept, 'Technical')) {
            return 'bg-sky-50 text-sky-800 border-sky-200/70';
        } elseif (str_contains($dept, 'Creative') || str_contains($dept, 'Arts') || str_contains($dept, 'Physical')) {
            return 'bg-violet-50 text-violet-800 border-violet-200/70';
        } elseif (str_contains($dept, 'Language') || str_contains($dept, 'Literature') || str_contains($dept, 'Arabic')) {
            return 'bg-indigo-50 text-indigo-800 border-indigo-200/70';
        } elseif (str_contains($dept, 'Humanities') || str_contains($dept, 'Social')) {
            return 'bg-amber-50 text-amber-800 border-amber-200/70';
        } elseif (str_contains($dept, 'Math') || str_contains($dept, 'Statistics')) {
            return 'bg-blue-50 text-blue-800 border-blue-200/70';
        } elseif (str_contains($dept, 'Science') || str_contains($dept, 'Biology') || str_contains($dept, 'Chemistry')) {
            return 'bg-emerald-50 text-emerald-800 border-emerald-200/70';
        }
        return 'bg-zinc-100 text-zinc-800 border-zinc-200';
    };
@endphp

<x-layouts::app :title="__('Teachers & Faculty Directory')">
    <div class="flex flex-col gap-6 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">

        {{-- Top Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold tracking-wide uppercase bg-blue-50 text-blue-700 border border-blue-200/60">
                        Faculty Management
                    </span>
                    <span class="text-zinc-300">&bull;</span>
                    <span class="text-xs text-zinc-500 font-medium">{{ $institution?->name ?? currentInstitution()?->name ?? __('Institution') }}</span>
                </div>
                <flux:heading size="xl" class="font-black tracking-tight text-zinc-900">Teachers Directory</flux:heading>
                <flux:text class="text-zinc-500 text-sm mt-0.5">
                    Manage instructional staff, academic department assignments, credentials, and faculty dossiers.
                </flux:text>
            </div>

            <div class="flex items-center gap-3">
                @can('create teacher')
                    <flux:button href="{{ route('teacher.create') }}" icon="plus" variant="primary" class="font-semibold shadow-sm px-4">
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

        {{-- Compact, Sleek, Guaranteed Horizontal Filter Bar (No Overlaps) --}}
        <div class="bg-white rounded-2xl p-3.5 sm:p-4 border border-zinc-200/80 shadow-sm flex flex-col gap-3">
            <form method="GET" action="{{ route('teacher.index') }}" style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem;">
                {{-- Search Bar (With Guaranteed Left Padding) --}}
                <div style="flex: 1 1 260px; position: relative; display: flex; align-items: center;">
                    <div style="position: absolute; left: 0.875rem; pointer-events: none; color: #a1a1aa; display: flex; align-items: center;">
                        <flux:icon icon="magnifying-glass" class="size-4" />
                    </div>
                    <input type="text" name="search" value="{{ $currentSearch }}" placeholder="Search teacher name, ID, or email..." 
                        style="width: 100%; padding-left: 2.75rem; padding-right: 1rem; padding-top: 0.5rem; padding-bottom: 0.5rem;"
                        class="bg-zinc-50 border border-zinc-200 rounded-xl text-xs placeholder-zinc-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
                </div>

                {{-- Department Dropdown --}}
                <div style="flex: 0 1 220px;">
                    <select name="department" onchange="this.form.submit()"
                        class="w-full px-3 py-2 bg-zinc-50 border border-zinc-200 rounded-xl text-xs font-medium text-zinc-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
                        <option value="">All Academic Departments</option>
                        @foreach ($departments as $dept)
                            <option value="{{ $dept }}" {{ $currentDept === $dept ? 'selected' : '' }}>{{ $dept }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Status Dropdown --}}
                <div style="flex: 0 1 150px;">
                    <select name="status" onchange="this.form.submit()"
                        class="w-full px-3 py-2 bg-zinc-50 border border-zinc-200 rounded-xl text-xs font-medium text-zinc-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
                        <option value="">All Statuses</option>
                        <option value="active" {{ $currentStatus === 'active' ? 'selected' : '' }}>Active Only</option>
                        <option value="on_leave" {{ $currentStatus === 'on_leave' ? 'selected' : '' }}>On Leave</option>
                        <option value="suspended" {{ $currentStatus === 'suspended' ? 'selected' : '' }}>Suspended</option>
                        <option value="resigned" {{ $currentStatus === 'resigned' ? 'selected' : '' }}>Resigned</option>
                    </select>
                </div>

                {{-- Buttons --}}
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <button type="submit" class="px-4 py-2 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-white text-xs font-bold transition shrink-0">
                        Filter
                    </button>
                    @if($hasActiveFilters)
                        <a href="{{ route('teacher.index') }}" class="px-3 py-2 rounded-xl bg-zinc-100 hover:bg-zinc-200 text-zinc-600 text-xs font-semibold transition shrink-0" title="Reset Filters">
                            ✕
                        </a>
                    @endif
                </div>
            </form>

            {{-- Active Filter Scope Tags --}}
            @if ($hasActiveFilters)
                <div class="flex flex-wrap items-center gap-2 pt-2.5 border-t border-zinc-100 text-xs">
                    <span class="text-[11px] font-bold text-zinc-400 uppercase tracking-wider mr-1">Active Filter Scope:</span>

                    @if ($currentDept)
                        <a href="{{ route('teacher.index', ['search' => $currentSearch, 'status' => $currentStatus]) }}" 
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-zinc-100 hover:bg-zinc-200 text-zinc-800 font-semibold transition border border-zinc-200/60"
                            title="Remove Department Filter">
                            <span>Dept: <strong>{{ $currentDept }}</strong></span>
                            <span class="text-zinc-400 hover:text-zinc-700 font-bold">✕</span>
                        </a>
                    @endif

                    @if ($currentStatus)
                        <a href="{{ route('teacher.index', ['search' => $currentSearch, 'department' => $currentDept]) }}" 
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-zinc-100 hover:bg-zinc-200 text-zinc-800 font-semibold transition border border-zinc-200/60"
                            title="Remove Status Filter">
                            <span>Status: <strong>{{ ucfirst(str_replace('_', ' ', $currentStatus)) }}</strong></span>
                            <span class="text-zinc-400 hover:text-zinc-700 font-bold">✕</span>
                        </a>
                    @endif

                    @if ($currentSearch)
                        <a href="{{ route('teacher.index', ['department' => $currentDept, 'status' => $currentStatus]) }}" 
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-800 font-semibold transition border border-blue-200/60"
                            title="Remove Search Keyword">
                            <span>Search: <strong>"{{ $currentSearch }}"</strong></span>
                            <span class="text-blue-500 hover:text-blue-800 font-bold">✕</span>
                        </a>
                    @endif

                    <a href="{{ route('teacher.index') }}" class="text-xs font-semibold text-rose-600 hover:text-rose-800 transition ml-auto">
                        Reset All Filters
                    </a>
                </div>
            @endif
        </div>

        {{-- Clean High-Density 4-Column Table (Spacious, Zero Overlaps, Fully Clickable) --}}
        <div class="bg-white shadow-sm border border-zinc-200/80 rounded-2xl overflow-hidden flex flex-col">
            @if($teachers->isNotEmpty())
                <div class="w-full overflow-x-auto">
                    <table class="w-full text-left text-sm table-fixed min-w-[720px]">
                        <thead class="bg-zinc-50 text-zinc-500 border-b border-zinc-200/80 text-xs font-bold uppercase tracking-wider">
                            <tr>
                                <th class="py-3.5 px-5 w-[42%]">Teacher Personnel & ID</th>
                                <th class="py-3.5 px-5 w-[28%]">Department & Qualification</th>
                                <th class="py-3.5 px-5 w-[18%]">Contact</th>
                                <th class="py-3.5 px-5 w-[12%] text-right">Status & Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100">
                            @foreach ($teachers as $teacherDetails)
                                @php
                                    $teacherUser = $teacherDetails->teacher;
                                    $teacherName = $teacherUser?->name ?? 'Unknown Teacher';
                                    $initials = $getInitials($teacherName);
                                    $deptStyle = $getDeptStyle($teacherDetails->department ?? '');
                                    $status = $teacherDetails->status ?? 'active';
                                @endphp
                                <tr class="hover:bg-zinc-50/60 transition-colors cursor-pointer group" onclick="window.location='{{ route('teacher.show', $teacherDetails->teacher_id) }}'">
                                    {{-- Col 1: Teacher Personnel & ID (Guaranteed Spacing) --}}
                                    <td class="py-4 px-5">
                                        <div style="display: flex; align-items: center; gap: 0.875rem;">
                                            <div style="width: 2.75rem; height: 2.75rem; min-width: 2.75rem; min-height: 2.75rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0;"
                                                class="rounded-xl bg-blue-50 border border-blue-200 text-blue-700 font-bold text-xs group-hover:bg-blue-600 group-hover:text-white transition-colors shadow-2xs">
                                                {{ $initials }}
                                            </div>
                                            <div style="min-width: 0; flex: 1;">
                                                <a href="{{ route('teacher.show', $teacherDetails->teacher_id) }}" class="font-bold text-zinc-900 group-hover:text-blue-600 transition truncate block text-sm" onclick="event.stopPropagation();">
                                                    {{ $teacherName }}
                                                </a>
                                                <div style="display: flex; align-items: center; gap: 0.4rem; margin-top: 0.25rem; flex-wrap: nowrap;">
                                                    <span style="white-space: nowrap;" class="px-2 py-0.5 rounded bg-zinc-100 text-zinc-700 font-mono font-semibold border border-zinc-200 text-[11px]">
                                                        {{ $teacherDetails->employee_number ?? 'EMP-T-' . str_pad($teacherDetails->id, 3, '0', STR_PAD_LEFT) }}
                                                    </span>
                                                    <span class="text-zinc-300">&bull;</span>
                                                    <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" class="font-sans text-[11px] text-zinc-500">
                                                        {{ $teacherDetails->institution?->name ?? 'Main Campus' }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Col 2: Department & Qualification --}}
                                    <td class="py-4 px-5">
                                        <div style="display: flex; flex-direction: column; align-items: flex-start; gap: 0.25rem;">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-bold border {{ $deptStyle }} truncate max-w-full">
                                                {{ $teacherDetails->department ?? 'General Academic' }}
                                            </span>
                                            <div class="text-xs text-zinc-400 truncate font-medium">
                                                {{ $teacherDetails->qualification ?? 'B.Ed Degree' }}
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Col 3: Direct Contact --}}
                                    <td class="py-4 px-5 text-xs">
                                        <div style="display: flex; flex-direction: column; gap: 0.2rem;">
                                            @if($teacherDetails->phone)
                                                <a href="tel:{{ $teacherDetails->phone }}" class="text-zinc-800 hover:text-blue-600 font-semibold transition inline-flex items-center gap-1.5" onclick="event.stopPropagation();">
                                                    <flux:icon icon="phone" class="size-3.5 text-zinc-400" />
                                                    <span style="white-space: nowrap;">{{ $teacherDetails->phone }}</span>
                                                </a>
                                            @endif
                                            @if($teacherUser?->email)
                                                <div class="text-zinc-400 text-[11px] truncate flex items-center gap-1">
                                                    <flux:icon icon="envelope" class="size-3 text-zinc-400 shrink-0" />
                                                    <span class="truncate">{{ $teacherUser->email }}</span>
                                                </div>
                                            @endif
                                        </div>
                                    </td>

                                    {{-- Col 4: Status & Single View Profile Button --}}
                                    <td class="py-4 px-5 text-right">
                                        <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 0.5rem;">
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold
                                                {{ $status === 'active' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-zinc-100 text-zinc-600 border border-zinc-200' }}">
                                                <span class="size-1.5 rounded-full {{ $status === 'active' ? 'bg-emerald-500' : 'bg-zinc-400' }}"></span>
                                                {{ ucfirst(str_replace('_', ' ', $status)) }}
                                            </span>

                                            <a href="{{ route('teacher.show', $teacherDetails->teacher_id) }}" class="inline-flex items-center gap-1 text-xs font-bold text-zinc-600 group-hover:text-blue-600 transition" onclick="event.stopPropagation();">
                                                <span>Profile</span>
                                                <flux:icon icon="chevron-right" class="size-3.5 group-hover:translate-x-0.5 transition-transform" />
                                            </a>
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
                        <span>Showing <strong class="text-zinc-800 font-semibold">{{ $teachers->count() }}</strong> of <strong class="text-zinc-800 font-semibold">{{ number_format($totalCount) }}</strong> teachers</span>
                        <span class="text-zinc-300">&bull;</span>
                        <span>Page <strong class="text-zinc-800 font-semibold">{{ $teachers->currentPage() }}</strong> of <strong class="text-zinc-800 font-semibold">{{ $teachers->lastPage() }}</strong></span>
                    </div>
                    @if(method_exists($teachers, 'links') && $teachers->hasPages())
                        <div>
                            {{ $teachers->links() }}
                        </div>
                    @endif
                </div>
            @else
                {{-- Empty State --}}
                <div class="flex flex-col items-center justify-center py-20 text-center">
                    <div class="size-14 rounded-2xl bg-zinc-100 flex items-center justify-center text-zinc-400 mb-3">
                        <flux:icon icon="academic-cap" class="size-7" />
                    </div>
                    <h4 class="text-base font-bold text-zinc-900">No Teachers Found</h4>
                    <p class="text-zinc-500 text-xs max-w-sm mx-auto mt-1 mb-4">
                        There are no faculty members matching your active filter criteria.
                    </p>
                    @can('create teacher')
                        <flux:button href="{{ route('teacher.create') }}" variant="primary" icon="plus" size="sm">
                            Add Teacher
                        </flux:button>
                    @endcan
                </div>
            @endif
        </div>
    </div>
</x-layouts::app>