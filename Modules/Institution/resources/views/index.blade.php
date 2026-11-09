@php
    $totalCampuses = count($institution);
    $activeCampus = $institution->firstWhere('id', auth()->user()->active_institution_id);

    // Pure frontend calculation across loaded models without modifying backend controller
    $totalStudentsCombined = 0;
    $totalTeachersCombined = 0;
    foreach ($institution as $inst) {
        $totalStudentsCombined += $inst->student_details_count ?? $inst->studentDetails()->count();
        $totalTeachersCombined += $inst->teacher_details_count ?? $inst->teacherDetails()->count();
    }
@endphp

<x-layouts::app :title="__('Institutions')">
    <div class="flex flex-col gap-8 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8" x-data="{
        search: '',
        statusFilter: 'all',
        viewMode: 'grid',
        matches(inst) {
            const matchesSearch = !this.search ||
                inst.name.toLowerCase().includes(this.search.toLowerCase()) ||
                inst.code.toLowerCase().includes(this.search.toLowerCase()) ||
                inst.email.toLowerCase().includes(this.search.toLowerCase()) ||
                inst.city.toLowerCase().includes(this.search.toLowerCase());
            const matchesStatus = this.statusFilter === 'all' ||
                (this.statusFilter === 'active' && inst.is_current) ||
                (this.statusFilter === 'approved' && inst.is_approved) ||
                (this.statusFilter === 'pending' && !inst.is_approved);
            return matchesSearch && matchesStatus;
        }
    }">

        {{-- Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <flux:heading size="xl" class="font-black tracking-tight text-zinc-900">Institutions & Campuses
                </flux:heading>
                <flux:text class="text-zinc-500 text-sm mt-0.5">
                    Switch between campuses, manage institutional profiles, and monitor cross-school operations.
                </flux:text>
            </div>

            @hasrole('Director')
                <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                    <flux:button href="{{ route('institution.create') }}" icon="plus" variant="primary"
                        class="font-semibold shadow-sm px-3.5 sm:px-4 text-xs sm:text-sm min-h-[38px]">
                        Add Institution
                    </flux:button>
                </div>
            @endhasrole
        </div>

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

        {{-- Top Multi-Campus KPI Strip --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5">
            {{-- Card 1: Registered Campuses --}}
            <div
                class="bg-white rounded-2xl p-4 sm:p-5 border border-zinc-200/80 shadow-sm hover:border-zinc-300 transition duration-150">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-semibold uppercase tracking-wider text-zinc-400">Registered
                        Campuses</span>
                    <div class="size-9 rounded-xl bg-zinc-100 flex items-center justify-center text-zinc-700">
                        <flux:icon icon="building-office-2" class="size-4" />
                    </div>
                </div>
                <div class="text-2xl font-black text-zinc-900 tracking-tight">
                    <span x-data="{ count: 0, target: {{ $totalCampuses }}, init() { let s = null,
                                d = 800; let step = (t) => { if (!s) s = t; let p = Math.min((t - s) / d, 1);
                                this.count = Math.floor((1 - Math.pow(1 - p, 3)) * this.target); if (p < 1) requestAnimationFrame(step);
                                else this.count = this.target; };
                            requestAnimationFrame(step); } }" x-text="count">{{ $totalCampuses }}</span> Campuses
                </div>
                <div class="text-xs text-zinc-500 mt-2 font-medium">
                    Full administrative ownership
                </div>
            </div>

            {{-- Card 2: Active Workspace --}}
            <a href="{{ route('dashboard') }}"
                class="bg-white rounded-2xl p-4 sm:p-5 border border-zinc-200/80 shadow-sm hover:border-emerald-300 hover:shadow-md transition duration-150 block group">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-semibold uppercase tracking-wider text-emerald-700">Active
                        Workspace</span>
                    <div
                        class="size-9 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-600 group-hover:scale-105 transition-transform">
                        <flux:icon icon="check-badge" class="size-4" />
                    </div>
                </div>
                <div
                    class="text-xl font-black text-zinc-900 tracking-tight truncate group-hover:text-emerald-700 transition">
                    {{ $activeCampus?->name ?? 'None Selected' }}
                </div>
                <div class="text-xs text-emerald-700 mt-2 flex items-center gap-1.5 font-semibold">
                    <span class="size-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>Currently Live &bull; Go to Dashboard &rarr;</span>
                </div>
            </a>

            {{-- Card 3: Total Combined Learners --}}
            @if (currentInstitution()?->isApproved())
                <a href="{{ route('student.index') }}"
                    class="bg-white rounded-2xl p-4 sm:p-5 border border-zinc-200/80 shadow-sm hover:border-zinc-300 hover:shadow-md transition duration-150 block group sm:col-span-2 lg:col-span-1">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-semibold uppercase tracking-wider text-zinc-400">Total
                            Enrollment</span>
                        <div
                            class="size-9 rounded-xl bg-zinc-100 flex items-center justify-center text-zinc-700 group-hover:scale-105 transition-transform">
                            <flux:icon icon="academic-cap" class="size-4" />
                        </div>
                    </div>
                    <div class="text-2xl font-black text-zinc-900 tracking-tight group-hover:text-blue-600 transition">
                        <span x-data="animatedCounter({{ $totalStudentsCombined }})"
                            x-text="displayCount">{{ number_format($totalStudentsCombined) }}</span> Students
                    </div>
                    <div class="text-xs text-zinc-500 mt-2 font-medium flex items-center justify-between">
                        <span><strong class="text-zinc-800 font-semibold" x-data="animatedCounter({{ $totalTeachersCombined }})"
                                x-text="displayCount">{{ $totalTeachersCombined }}</strong> Faculty Members</span>
                        <span class="text-blue-600 font-semibold group-hover:underline">View Directory &rarr;</span>
                    </div>
                </a>
            @endif
        </div>

        {{-- Filter, Search & View Switcher Bar --}}
        <div
            class="bg-white rounded-2xl p-3.5 sm:p-4 border border-zinc-200/80 shadow-sm flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3 sm:gap-4">
            {{-- Search Bar --}}
            <div class="relative w-full md:w-80">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-zinc-400">
                    <flux:icon icon="magnifying-glass" class="size-4" />
                </div>
                <input type="text" x-model="search" placeholder="Search campus name, code, email, city..."
                    style="width: 100%; padding-left: 2.75rem !important; padding-right: 1rem; padding-top: 0.5rem; padding-bottom: 0.5rem;"
                    class="bg-zinc-50 border border-zinc-200 rounded-xl text-xs placeholder-zinc-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
            </div>

            {{-- Status Filter Chips & View Mode --}}
            <div class="flex flex-wrap items-center justify-between sm:justify-end w-full md:w-auto gap-2.5 sm:gap-3">
                <div class="flex flex-wrap items-center gap-1.5 sm:gap-2">
                    <button type="button" @click="statusFilter = 'all'"
                        :class="statusFilter === 'all' ? 'bg-zinc-900 text-white shadow-sm font-semibold' :
                            'bg-zinc-100 text-zinc-600 hover:bg-zinc-200'"
                        class="px-3 sm:px-3.5 py-1.5 rounded-xl text-xs font-medium transition duration-150 min-h-[34px]">
                        All ({{ $totalCampuses }})
                    </button>
                    <button type="button" @click="statusFilter = 'approved'"
                        :class="statusFilter === 'approved' ? 'bg-emerald-600 text-white shadow-sm font-semibold' :
                            'bg-emerald-50 text-emerald-700 hover:bg-emerald-100'"
                        class="px-3 sm:px-3.5 py-1.5 rounded-xl text-xs font-medium transition duration-150 min-h-[34px]">
                        Approved
                    </button>
                </div>

                {{-- Grid / List View Toggle --}}
                <div class="flex items-center p-1 bg-zinc-100 rounded-xl border border-zinc-200 shrink-0">
                    <button type="button" @click="viewMode = 'grid'"
                        :class="viewMode === 'grid' ? 'bg-white text-zinc-900 shadow-sm font-bold' :
                            'text-zinc-500 hover:text-zinc-900'"
                        class="p-1.5 rounded-lg transition min-w-[32px] min-h-[32px] flex items-center justify-center" title="Grid View">
                        <flux:icon icon="squares-2x2" class="size-4" />
                    </button>
                    <button type="button" @click="viewMode = 'list'"
                        :class="viewMode === 'list' ? 'bg-white text-zinc-900 shadow-sm font-bold' :
                            'text-zinc-500 hover:text-zinc-900'"
                        class="p-1.5 rounded-lg transition min-w-[32px] min-h-[32px] flex items-center justify-center" title="List View">
                        <flux:icon icon="list-bullet" class="size-4" />
                    </button>
                </div>
            </div>
        </div>

        {{-- 1. Executive Campus Grid View (Default) --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6" x-show="viewMode === 'grid'">
            @forelse ($institution as $institute)
                @php
                    $isCurrent = auth()->user()->active_institution_id === $institute->id;
                    $initial = strtoupper(substr($institute->name, 0, 1));
                    $studentCount = $institute->student_details_count ?? ($institute->studentDetails()->count() ?? 0);
                    $teacherCount = $institute->teacher_details_count ?? ($institute->teacherDetails()->count() ?? 0);
                    $classCount = $institute->classes_count ?? ($institute->classes()->count() ?? 0);
                @endphp
                <div class="bg-white rounded-3xl p-6 border transition-all duration-150 flex flex-col justify-between
                    {{ $isCurrent ? 'border-emerald-300 ring-2 ring-emerald-500/20 shadow-sm' : 'border-zinc-200/80 hover:border-zinc-300 shadow-sm' }}"
                    x-show="matches({
                        name: '{{ addslashes($institute->name) }}',
                        code: '{{ addslashes($institute->code ?? '') }}',
                        email: '{{ addslashes($institute->email ?? '') }}',
                        city: '{{ addslashes($institute->city ?? '') }}',
                        is_current: {{ $isCurrent ? 'true' : 'false' }},
                        is_approved: {{ $institute->is_approved ? 'true' : 'false' }}
                    })">

                    <div>
                        {{-- Card Header: Logo, Title, Code & Badge --}}
                        <div class="flex items-start justify-between gap-4 mb-4">
                            <div class="flex items-center gap-3.5">
                                @if ($institute->logo)
                                    <img src="{{ Storage::url($institute->logo) }}"
                                        class="size-12 rounded-2xl border border-zinc-200 bg-white object-contain p-1 shadow-sm shrink-0">
                                @else
                                    <div
                                        class="size-12 rounded-2xl bg-zinc-100 text-zinc-800 font-black text-lg flex items-center justify-center border border-zinc-200 shadow-sm shrink-0">
                                        {{ $initial }}
                                    </div>
                                @endif
                                <div>
                                    <h3 class="text-base font-bold text-zinc-900">
                                        <span translate="no">{{ $institute->name }}</span>
                                    </h3>
                                    <div class="flex items-center gap-2 mt-0.5">
                                        <span
                                            class="px-2 py-0.5 rounded-md bg-zinc-100 text-zinc-600 font-mono font-semibold text-[11px] uppercase border border-zinc-200">
                                            {{ $institute->code ?? 'CAMPUS' }}
                                        </span>
                                        <span class="text-xs text-zinc-400">&bull;</span>
                                        <span
                                            class="text-xs text-zinc-500 font-medium">{{ $institute->city ?? ($institute->county ?? 'Kenya') }}</span>
                                    </div>
                                </div>
                            </div>

                            {{-- Active Workspace Badge --}}
                            <div>
                                @if ($isCurrent)
                                    <span
                                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                        Active
                                    </span>
                                @else
                                    <span
                                        class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-zinc-100 text-zinc-600">
                                        Standby
                                    </span>
                                @endif
                            </div>
                        </div>

                        {{-- Campus Stats Pill Strip (Interactive Deep Links if Active) --}}
                        <div class="grid grid-cols-3 gap-2.5 my-4">
                            @if ($isCurrent)
                                <a href="{{ route('student.index') }}"
                                    class="p-2.5 rounded-xl bg-zinc-50 border border-zinc-100 text-center hover:bg-zinc-100 hover:border-zinc-200 transition group/tile"
                                    title="View Student Directory">
                                    <span
                                        class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block group-hover/tile:text-blue-600 transition">Students
                                        &rarr;</span>
                                    <span class="text-sm font-black text-zinc-900 mt-0.5 block"
                                        x-data="animatedCounter({{ $studentCount }})"
                                        x-text="displayCount">{{ number_format($studentCount) }}</span>
                                </a>
                                <a href="{{ route('teacher.index') }}"
                                    class="p-2.5 rounded-xl bg-zinc-50 border border-zinc-100 text-center hover:bg-zinc-100 hover:border-zinc-200 transition group/tile"
                                    title="View Faculty List">
                                    <span
                                        class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block group-hover/tile:text-purple-600 transition">Teachers
                                        &rarr;</span>
                                    <span class="text-sm font-black text-zinc-900 mt-0.5 block"
                                        x-data="animatedCounter({{ $teacherCount }})"
                                        x-text="displayCount">{{ number_format($teacherCount) }}</span>
                                </a>
                                <a href="{{ route('classes.index') }}"
                                    class="p-2.5 rounded-xl bg-zinc-50 border border-zinc-100 text-center hover:bg-zinc-100 hover:border-zinc-200 transition group/tile"
                                    title="View Class Streams">
                                    <span
                                        class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block group-hover/tile:text-emerald-600 transition">Streams
                                        &rarr;</span>
                                    <span class="text-sm font-black text-zinc-900 mt-0.5 block"
                                        x-data="animatedCounter({{ $classCount }})"
                                        x-text="displayCount">{{ number_format($classCount) }}</span>
                                </a>
                            @else
                                <div class="p-2.5 rounded-xl bg-zinc-50 border border-zinc-100 text-center">
                                    <span
                                        class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block">Students</span>
                                    <span class="text-sm font-black text-zinc-900 mt-0.5 block"
                                        x-data="animatedCounter({{ $studentCount }})"
                                        x-text="displayCount">{{ number_format($studentCount) }}</span>
                                </div>
                                <div class="p-2.5 rounded-xl bg-zinc-50 border border-zinc-100 text-center">
                                    <span
                                        class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block">Teachers</span>
                                    <span class="text-sm font-black text-zinc-900 mt-0.5 block"
                                        x-data="animatedCounter({{ $teacherCount }})"
                                        x-text="displayCount">{{ number_format($teacherCount) }}</span>
                                </div>
                                <div class="p-2.5 rounded-xl bg-zinc-50 border border-zinc-100 text-center">
                                    <span
                                        class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block">Streams</span>
                                    <span class="text-sm font-black text-zinc-900 mt-0.5 block"
                                        x-data="animatedCounter({{ $classCount }})"
                                        x-text="displayCount">{{ number_format($classCount) }}</span>
                                </div>
                            @endif
                        </div>

                        {{-- Contact Details --}}
                        <div class="space-y-1 text-xs text-zinc-500 py-2 border-t border-zinc-100">
                            @if ($institute->email)
                                <div class="flex items-center gap-2">
                                    <flux:icon icon="envelope" class="size-3.5 text-zinc-400 shrink-0" />
                                    <a href="mailto:{{ $institute->email }}"
                                        class="text-zinc-600 hover:text-zinc-900 truncate transition font-medium"><span translate="no">{{ $institute->email }}</span></a>
                                </div>
                            @endif
                            @if ($institute->phone)
                                <div class="flex items-center gap-2">
                                    <flux:icon icon="phone" class="size-3.5 text-zinc-400 shrink-0" />
                                    <span class="text-zinc-600 font-medium"><span translate="no">{{ $institute->phone }}</span></span>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Action Footer --}}
                    <div class="pt-3 mt-1 border-t border-zinc-100 flex flex-wrap items-center justify-between gap-2.5">
                        <div class="flex items-center">
                            @if ($isCurrent)
                                <a href="{{ route('dashboard') }}"
                                    class="inline-flex items-center gap-1.5 px-3 sm:px-3.5 py-1.5 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-white text-xs font-semibold shadow-sm transition min-h-[36px]">
                                    <flux:icon icon="arrow-top-right-on-square" class="size-3.5" />
                                    <span>Open Dashboard</span>
                                </a>
                            @elseif (institutionOwner($institute->owner->id))
                                <form action="{{ route('institution.choose', $institute->id) }}" method="POST"
                                    class="inline-block">
                                    @csrf
                                    <button type="submit"
                                        class="inline-flex items-center gap-1.5 px-3 sm:px-3.5 py-1.5 rounded-xl bg-zinc-100 hover:bg-zinc-200 text-zinc-800 text-xs font-bold transition min-h-[36px]">
                                        <flux:icon icon="arrow-path" class="size-3.5" />
                                        <span>Switch Campus</span>
                                    </button>
                                </form>
                            @endif
                        </div>

                        <div class="flex items-center gap-1.5 ml-auto">
                            <flux:button href="{{ route('institution.show', $institute->id) }}" size="sm"
                                icon="eye" variant="subtle" tooltip="View Profile" class="min-h-[36px] min-w-[36px]" />
                            @if (institutionOwner($institute->owner->id))
                                <flux:button href="{{ route('institution.edit', $institute->id) }}" size="sm"
                                    icon="pencil-square" variant="subtle" tooltip="Settings" class="min-h-[36px] min-w-[36px]" />
                            @endif
                        </div>
                    </div>

                </div>
            @empty
                <div class="col-span-2 py-16 text-center bg-white rounded-3xl border border-zinc-200">
                    <flux:icon icon="building-office-2" class="size-12 mx-auto text-zinc-300 mb-3" />
                    <h4 class="text-base font-bold text-zinc-900">No Institutions Found</h4>
                    <p class="text-xs text-zinc-500 mt-1 mb-4">You have not registered any school campuses yet.</p>
                    @hasrole('Director')
                        <flux:button href="{{ route('institution.create') }}" variant="primary" size="sm">
                            Add Institution
                        </flux:button>
                    @endhasrole
                </div>
            @endforelse
        </div>

        {{-- 2. Clean List Table View (Alternative) --}}
        <div class="bg-white rounded-2xl shadow-sm border border-zinc-200/80 overflow-hidden flex flex-col"
            x-show="viewMode === 'list'">
            <div class="responsive-table-container">
                <table class="w-full text-left text-sm min-w-[720px]">
                    <thead
                        class="bg-zinc-50 text-zinc-500 border-b border-zinc-200/80 text-xs font-bold uppercase tracking-wider">
                        <tr>
                            <th class="py-3.5 px-4 sm:px-5 whitespace-nowrap">Institution</th>
                            <th class="py-3.5 px-4 sm:px-5 whitespace-nowrap">Campus Contact</th>
                            <th class="py-3.5 px-4 sm:px-5 whitespace-nowrap">Enrollment</th>
                            <th class="py-3.5 px-4 sm:px-5 whitespace-nowrap">Status</th>
                            <th class="py-3.5 px-4 sm:px-5 whitespace-nowrap">Workspace</th>
                            <th class="py-3.5 px-4 sm:px-5 text-right whitespace-nowrap">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @foreach ($institution as $institute)
                            @php
                                $isCurrent = auth()->user()->active_institution_id === $institute->id;
                                $studentCount =
                                    $institute->student_details_count ?? ($institute->studentDetails()->count() ?? 0);
                            @endphp
                            <tr class="hover:bg-zinc-50/60 transition-colors">
                                <td class="py-4 px-4 sm:px-5 min-w-[180px]">
                                    <div class="flex items-center gap-3">
                                        @if ($institute->logo)
                                            <img src="{{ Storage::url($institute->logo) }}"
                                                class="size-9 rounded-xl border border-zinc-200 bg-white object-contain p-0.5 shrink-0">
                                        @else
                                            <div
                                                class="size-9 rounded-xl bg-zinc-100 font-bold text-xs flex items-center justify-center text-zinc-700 shrink-0">
                                                <span translate="no">{{ substr($institute->name, 0, 1) }}</span>
                                            </div>
                                        @endif
                                        <div class="min-w-0">
                                            <div class="font-bold text-zinc-900 truncate"><span translate="no">{{ $institute->name }}</span></div>
                                            <div class="text-xs text-zinc-400 font-mono">{{ $institute->code ?? '—' }}
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td class="py-4 px-4 sm:px-5 text-xs text-zinc-600 min-w-[160px]">
                                    <div class="truncate"><span translate="no">{{ $institute->email ?? '—' }}</span></div>
                                    <div class="text-zinc-400 mt-0.5 truncate"><span translate="no">{{ $institute->phone ?? '' }}</span></div>
                                </td>

                                <td class="py-4 px-4 sm:px-5 text-xs font-semibold text-zinc-900 whitespace-nowrap">
                                    <span x-data="animatedCounter({{ $studentCount }})"
                                        x-text="displayCount">{{ number_format($studentCount) }}</span>
                                    Learners
                                </td>

                                <td class="py-4 px-4 sm:px-5 whitespace-nowrap">
                                    <span
                                        class="px-2.5 py-0.5 rounded-full text-xs font-bold {{ $institute->is_approved ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200' }}">
                                        {{ $institute->is_approved ? 'Approved' : 'Pending' }}
                                    </span>
                                </td>

                                <td class="py-4 px-4 sm:px-5 whitespace-nowrap">
                                    @if ($isCurrent)
                                        <span
                                            class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                            Active
                                        </span>
                                    @elseif (institutionOwner($institute->owner->id))
                                        <form action="{{ route('institution.choose', $institute->id) }}"
                                            method="POST">
                                            @csrf
                                            <button type="submit"
                                                class="inline-flex items-center px-3 py-1.5 rounded-xl bg-zinc-100 hover:bg-zinc-200 text-zinc-700 text-xs font-bold transition min-h-[32px]">
                                                Switch
                                            </button>
                                        </form>
                                    @endif
                                </td>

                                <td class="py-4 px-4 sm:px-5 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <flux:button href="{{ route('institution.show', $institute->id) }}"
                                            size="sm" icon="eye" variant="subtle" class="min-h-[34px] min-w-[34px]" />
                                        @if (institutionOwner($institute->owner->id))
                                            <flux:button href="{{ route('institution.edit', $institute->id) }}"
                                                size="sm" icon="pencil-square" variant="subtle" class="min-h-[34px] min-w-[34px]" />
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</x-layouts::app>