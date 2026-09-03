@php
    $totalCount = method_exists($students, 'total') ? $students->total() : count($students);
    $currentSearch = request('search');
    $currentClassId = request('class_id');
    $currentStatus = request('status');
    $currentGender = request('gender');
    $hasActiveFilters = !empty($currentSearch) || !empty($currentClassId) || !empty($currentStatus) || !empty($currentGender);
    $selectedClass = $classes->firstWhere('id', $currentClassId);
@endphp

<x-layouts::app :title="__('Student Directory')">
    <div class="flex flex-col gap-8 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">

        {{-- Top Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold tracking-wide uppercase bg-blue-50 text-blue-700 border border-blue-200/60">
                        Academic Enrollment
                    </span>
                    <span class="text-xs text-zinc-400">&bull;</span>
                    <span class="text-xs text-zinc-500 font-medium">{{ $institution?->name ?? currentInstitution()?->name ?? __('Institution') }}</span>
                </div>
                <flux:heading size="xl" class="font-black tracking-tight text-zinc-900">Student Directory</flux:heading>
                <flux:text class="text-zinc-500 text-sm mt-0.5">
                    Manage student admissions, classroom assignments, guardian contacts, and enrollment profiles.
                </flux:text>
            </div>

            <div class="flex items-center gap-3">
                @can('create student')
                    {{-- <flux:button href="{{ route('student.create') }}" icon="plus" variant="primary" class="font-semibold shadow-sm px-4">
                        Enroll Student
                    </flux:button> --}}
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

        {{-- Consistent Animated KPI Cards (Matching Dashboard Zoom & Hover Effects) --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <x-kpi-card 
                title="Total Students" 
                icon="academic-cap" 
                color="sky" 
                :count="$totalStudents" 
                subtext="Official learner headcount" 
            />
            
            <x-kpi-card 
                title="Active Roster" 
                icon="check-badge" 
                color="emerald" 
                :count="$totalActive" 
                subtext="Currently attending learners" 
                href="{{ route('student.index', ['status' => 'active']) }}" 
            />
            
            <x-kpi-card 
                title="Class Sections" 
                icon="building-library" 
                color="indigo" 
                :count="$totalClasses" 
                subtext="Configured grade streams" 
                href="{{ route('classes.index') }}"
            />
        </div>

                {{-- Reusable Single-Row Filter Toolbar --}}
        <x-filter-toolbar 
            action="{{ route('student.index') }}"
            placeholder="Search student name, admission no, or phone..."
            :search="$currentSearch"
            :hasActiveFilters="$hasActiveFilters"
            resetRoute="{{ route('student.index') }}"
        >
            {{-- Class Filter --}}
            <div style="flex: 0 1 200px;">
                <select name="class_id" onchange="this.form.submit()"
                    class="w-full px-3 py-2 bg-zinc-50 border border-zinc-200 rounded-xl text-xs font-medium text-zinc-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
                    <option value="">All Classes</option>
                    @foreach ($classes as $c)
                        <option value="{{ $c->id }}" {{ $currentClassId == $c->id ? 'selected' : '' }}>
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
                    <option value="active" {{ $currentStatus === 'active' ? 'selected' : '' }}>Active Only</option>
                    <option value="inactive" {{ $currentStatus === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    <option value="graduated" {{ $currentStatus === 'graduated' ? 'selected' : '' }}>Graduated</option>
                    <option value="transferred" {{ $currentStatus === 'transferred' ? 'selected' : '' }}>Transferred</option>
                    <option value="suspended" {{ $currentStatus === 'suspended' ? 'selected' : '' }}>Suspended</option>
                </select>
            </div>

            {{-- Gender Filter --}}
            <div style="flex: 0 1 130px;">
                <select name="gender" onchange="this.form.submit()"
                    class="w-full px-3 py-2 bg-zinc-50 border border-zinc-200 rounded-xl text-xs font-medium text-zinc-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
                    <option value="">All Genders</option>
                    <option value="male" {{ $currentGender === 'male' ? 'selected' : '' }}>Male</option>
                    <option value="female" {{ $currentGender === 'female' ? 'selected' : '' }}>Female</option>
                </select>
            </div>

            <x-slot:scopeTags>
                @if ($currentClassId && ($selectedClass = $classes->firstWhere('id', $currentClassId)))
                    <a href="{{ route('student.index', ['search' => $currentSearch, 'status' => $currentStatus, 'gender' => $currentGender]) }}" 
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-zinc-100 hover:bg-zinc-200 text-zinc-800 font-semibold transition border border-zinc-200/60"
                        title="Remove Class Filter">
                        <span>Class: <strong>{{ $selectedClass->name }}</strong></span>
                        <span class="text-zinc-400 hover:text-zinc-700 font-bold">✕</span>
                    </a>
                @endif

                @if ($currentStatus)
                    <a href="{{ route('student.index', ['search' => $currentSearch, 'class_id' => $currentClassId, 'gender' => $currentGender]) }}" 
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-zinc-100 hover:bg-zinc-200 text-zinc-800 font-semibold transition border border-zinc-200/60"
                        title="Remove Status Filter">
                        <span>Status: <strong>{{ ucfirst($currentStatus) }}</strong></span>
                        <span class="text-zinc-400 hover:text-zinc-700 font-bold">✕</span>
                    </a>
                @endif

                @if ($currentGender)
                    <a href="{{ route('student.index', ['search' => $currentSearch, 'class_id' => $currentClassId, 'status' => $currentStatus]) }}" 
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-zinc-100 hover:bg-zinc-200 text-zinc-800 font-semibold transition border border-zinc-200/60"
                        title="Remove Gender Filter">
                        <span>Gender: <strong>{{ ucfirst($currentGender) }}</strong></span>
                        <span class="text-zinc-400 hover:text-zinc-700 font-bold">✕</span>
                    </a>
                @endif

                @if ($currentSearch)
                    <a href="{{ route('student.index', ['class_id' => $currentClassId, 'status' => $currentStatus, 'gender' => $currentGender]) }}" 
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-800 font-semibold transition border border-blue-200/60"
                        title="Remove Search Keyword">
                        <span>Search: <strong>"{{ $currentSearch }}"</strong></span>
                        <span class="text-blue-500 hover:text-blue-800 font-bold">✕</span>
                    </a>
                @endif
            </x-slot:scopeTags>
        </x-filter-toolbar>

        {{-- Smart High-Density 4-Column Student Roster Table (Zero Horizontal Scroll) --}}
        <div class="bg-white shadow-sm border border-zinc-200/80 rounded-2xl overflow-hidden flex flex-col">
            @if($students->isNotEmpty())
                <div class="w-full">
                    <table class="w-full text-left text-sm table-fixed">
                        <thead class="bg-zinc-50 text-zinc-500 border-b border-zinc-200/80 text-xs font-bold uppercase tracking-wider">
                            <tr>
                                <th class="py-3.5 px-5 w-4/12">Student Profile & Admission</th>
                                <th class="py-3.5 px-5 w-3/12">Class Stream & Gender</th>
                                <th class="py-3.5 px-5 w-3/12">Guardian & Contact</th>
                                <th class="py-3.5 px-5 w-2/12 text-right">Status & Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100">
                            @foreach ($students as $student)
                                @php
                                    $user = $student->student;
                                    $initials = $user ? strtoupper(substr($user->name, 0, 2)) : 'ST';
                                    $className = $student->schoolClass?->name ?? 'Unassigned Class';
                                    $classLevel = $student->schoolClass?->level;
                                    $admNo = $student->admission_number ?? 'ADM-' . str_pad($student->id, 4, '0', STR_PAD_LEFT);
                                    $guardian = $student->guardian_name ?? $student->parent_name ?? 'Not Listed';
                                    $guardianPhone = $student->guardian_phone ?? $student->parent_phone ?? $student->phone;
                                    $status = $student->enrollment_status ?? ($student->is_active ? 'active' : 'inactive');
                                @endphp
                                <tr class="hover:bg-zinc-50/60 transition-colors">
                                    {{-- Col 1: Student Profile & Admission --}}
                                    <td class="py-4 px-5">
                                        <div class="flex items-center gap-3">
                                            @if($student->profile_photo && Storage::disk('public')->exists($student->profile_photo))
                                                <img src="{{ asset('storage/' . $student->profile_photo) }}" alt="{{ $user?->name }}" 
                                                    class="size-10 rounded-xl object-cover border border-zinc-200 shrink-0">
                                            @else
                                                <div class="size-10 rounded-xl bg-zinc-100 border border-zinc-200 font-bold text-xs flex items-center justify-center text-zinc-800 shrink-0">
                                                    {{ $initials }}
                                                </div>
                                            @endif
                                            <div class="min-w-0 flex-1">
                                                <a href="{{ $user ? route('student.show', $user->id) : '#' }}" class="font-bold text-zinc-900 hover:text-blue-600 transition truncate block">
                                                    {{ $user?->name ?? 'Student' }}
                                                </a>
                                                <div class="text-xs text-zinc-400 mt-0.5 flex flex-wrap items-center gap-1.5 font-mono">
                                                    <span class="px-1.5 py-0.5 rounded bg-zinc-100 text-zinc-700 font-semibold border border-zinc-200 text-[11px]">{{ $admNo }}</span>
                                                    @if($student->student_number)
                                                        <span class="text-zinc-300">&bull;</span>
                                                        <span class="text-[11px] text-zinc-500">{{ $student->student_number }}</span>
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
                                            @if($classLevel)
                                                <span class="px-2 py-0.5 rounded bg-zinc-100 text-zinc-700 font-medium border border-zinc-200/60">{{ $classLevel }}</span>
                                                <span class="text-zinc-300">&bull;</span>
                                            @endif
                                            <span class="capitalize text-zinc-600 font-medium">{{ $student->gender ?? 'Unspecified' }}</span>
                                        </div>
                                    </td>

                                    {{-- Col 3: Guardian & Contact --}}
                                    <td class="py-4 px-5 text-xs">
                                        <div class="font-medium text-zinc-900 flex items-center gap-1.5">
                                            <flux:icon icon="user" class="size-3.5 text-zinc-400 shrink-0" />
                                            <span class="truncate">{{ $guardian }}</span>
                                        </div>
                                        <div class="text-[11px] text-zinc-500 mt-1 flex items-center gap-1 font-mono">
                                            @if($guardianPhone)
                                                <flux:icon icon="phone" class="size-3 text-zinc-400 shrink-0" />
                                                <a href="tel:{{ $guardianPhone }}" class="hover:text-blue-600 transition">{{ $guardianPhone }}</a>
                                            @else
                                                <span class="text-zinc-400">No contact provided</span>
                                            @endif
                                        </div>
                                    </td>

                                    {{-- Col 4: Status & Actions --}}
                                    <td class="py-4 px-5 text-right">
                                        <div class="flex flex-col items-end gap-2">
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold
                                                {{ $status === 'active' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' :
                                                   ($status === 'suspended' ? 'bg-amber-50 text-amber-700 border border-amber-200' :
                                                   ($status === 'graduated' ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' : 'bg-rose-50 text-rose-700 border border-rose-200')) }}">
                                                <span class="size-1.5 rounded-full {{ $status === 'active' ? 'bg-emerald-500' : ($status === 'suspended' ? 'bg-amber-500' : ($status === 'graduated' ? 'bg-indigo-500' : 'bg-rose-500')) }}"></span>
                                                {{ ucfirst($status) }}
                                            </span>

                                            @if($user)
                                                <div class="flex items-center justify-end gap-1">
                                                    <flux:button href="{{ route('student.show', $user->id) }}" size="sm" icon="eye" variant="subtle" tooltip="View Dossier" />
                                                    @can('edit student')
                                                        <flux:button href="{{ route('student.edit', $user->id) }}" size="sm" icon="pencil-square" variant="subtle" tooltip="Edit Profile" />
                                                    @endcan
                                                    @can('delete student')
                                                        <form action="{{ route('student.destroy', $user->id) }}" method="POST" class="inline" onsubmit="return confirm('Remove student {{ addslashes($user->name) }} from the directory?');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <flux:button type="submit" size="sm" icon="trash" variant="subtle" class="text-rose-500 hover:text-rose-600 hover:bg-rose-50" tooltip="Delete" />
                                                        </form>
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
                <div class="border-t border-zinc-200/80 px-6 py-4 bg-zinc-50/50 flex flex-col sm:flex-row justify-between items-center text-xs text-zinc-500 gap-4">
                    <div class="flex items-center gap-3">
                        <span>Showing <strong class="text-zinc-800 font-semibold">{{ $students->count() }}</strong> of <strong class="text-zinc-800 font-semibold">{{ number_format($totalCount) }}</strong> student records</span>
                        <span class="text-zinc-300">&bull;</span>
                        <span>Page <strong class="text-zinc-800 font-semibold">{{ $students->currentPage() }}</strong> of <strong class="text-zinc-800 font-semibold">{{ $students->lastPage() }}</strong></span>
                    </div>
                    @if(method_exists($students, 'links') && $students->hasPages())
                        <div>
                            {{ $students->links() }}
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
                        {{-- <flux:button href="{{ route('student.create') }}" variant="primary" icon="plus" size="sm">
                            Enroll Student
                        </flux:button> --}}
                    @endcan
                </div>
            @endif
        </div>
    </div>
</x-layouts::app>