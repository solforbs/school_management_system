@php
    $totalCount = method_exists($subjects, 'total') ? $subjects->total() : count($subjects);
    $currentSearch = request('search');
    $currentType = request('is_compulsory');
    $currentStatus = request('status');
    $hasActiveFilters = !empty($currentSearch) || request()->filled('is_compulsory') || !empty($currentStatus);
@endphp

<x-layouts::app :title="__('Subjects & Curriculum Directory')">
    <div class="flex flex-col gap-6 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">

        {{-- Top Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1.5">
                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold tracking-wide uppercase bg-blue-50 text-blue-700 border border-blue-200/60">
                        Academic Curriculum
                    </span>
                    <span class="text-zinc-300">&bull;</span>
                    <span class="text-xs text-zinc-600 font-medium">{{ $institution?->name ?? currentInstitution()?->name ?? __('Institution') }}</span>
                </div>
                <h1 class="text-2xl font-black tracking-tight text-zinc-900">Subjects Directory</h1>
                <p class="text-zinc-500 text-xs mt-0.5">
                    Manage academic disciplines, course codes, syllabus requirements, and examination allocations.
                </p>
            </div>

            <div class="flex items-center gap-3">
                @can('create subject')
                    <flux:button href="{{ route('subject.create') }}" icon="plus" variant="primary" class="font-semibold shadow-sm px-4">
                        Add Subject
                    </flux:button>
                @endcan
            </div>
        </div>

        {{-- Flash Messages --}}
        @if (session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50/70 p-3.5 flex items-center justify-between shadow-xs">
                <div class="flex items-center gap-2.5">
                    <flux:icon icon="check-circle" class="size-4 text-emerald-600 shrink-0" />
                    <span class="text-xs font-semibold text-emerald-900">{{ session('success') }}</span>
                </div>
            </div>
        @endif

        {{-- Animated KPI Cards (60-30-10 Palette) --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <x-kpi-card 
                title="Total Subjects" 
                icon="book-open" 
                color="sky" 
                :count="$totalSubjects" 
                subtext="Curriculum offerings" 
            />
            
            <x-kpi-card 
                title="Compulsory Core" 
                icon="check-badge" 
                color="amber" 
                :count="$totalCompulsory" 
                subtext="Mandatory subjects" 
                href="{{ route('subject.index', ['is_compulsory' => 1]) }}" 
            />
            
            <x-kpi-card 
                title="Elective Disciplines" 
                icon="sparkles" 
                color="indigo" 
                :count="$totalElective" 
                subtext="Optional student choices" 
                href="{{ route('subject.index', ['is_compulsory' => 0]) }}" 
            />
            
            <x-kpi-card 
                title="Exam Papers" 
                icon="document-text" 
                color="emerald" 
                :count="$totalExams" 
                subtext="Evaluations configured" 
            />
        </div>

                {{-- Reusable Single-Row Filter Toolbar --}}
        <x-filter-toolbar 
            action="{{ route('subject.index') }}"
            placeholder="Search by subject title or code (e.g. MATH, ENG)..."
            :search="request('search')"
            :hasActiveFilters="!empty(request('search')) || request('is_compulsory') !== null || !empty(request('status'))"
            resetRoute="{{ route('subject.index') }}"
        >
            {{-- Classification Filter --}}
            <div style="flex: 0 1 200px;">
                <select name="is_compulsory" onchange="this.form.submit()"
                    class="w-full px-3 py-2 bg-zinc-50 border border-zinc-200 rounded-xl text-xs font-medium text-zinc-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
                    <option value="">All Curriculum Types</option>
                    <option value="1" {{ request('is_compulsory') === '1' ? 'selected' : '' }}>Compulsory Core Only</option>
                    <option value="0" {{ request('is_compulsory') === '0' ? 'selected' : '' }}>Elective / Optional Only</option>
                </select>
            </div>

            {{-- Status Filter --}}
            <div style="flex: 0 1 150px;">
                <select name="status" onchange="this.form.submit()"
                    class="w-full px-3 py-2 bg-zinc-50 border border-zinc-200 rounded-xl text-xs font-medium text-zinc-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
                    <option value="">All Statuses</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active Subjects</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive Subjects</option>
                </select>
            </div>

            <x-slot:scopeTags>
                @if (request('is_compulsory') !== null && request('is_compulsory') !== '')
                    <a href="{{ route('subject.index', ['search' => request('search'), 'status' => request('status')]) }}" 
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-zinc-100 hover:bg-zinc-200 text-zinc-800 font-semibold transition border border-zinc-200/60"
                        title="Remove Curriculum Filter">
                        <span>Type: <strong>{{ request('is_compulsory') === '1' ? 'Compulsory' : 'Elective' }}</strong></span>
                        <span class="text-zinc-400 hover:text-zinc-700 font-bold">✕</span>
                    </a>
                @endif

                @if (request('status'))
                    <a href="{{ route('subject.index', ['search' => request('search'), 'is_compulsory' => request('is_compulsory')]) }}" 
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-zinc-100 hover:bg-zinc-200 text-zinc-800 font-semibold transition border border-zinc-200/60"
                        title="Remove Status Filter">
                        <span>Status: <strong>{{ ucfirst(request('status')) }}</strong></span>
                        <span class="text-zinc-400 hover:text-zinc-700 font-bold">✕</span>
                    </a>
                @endif

                @if (request('search'))
                    <a href="{{ route('subject.index', ['is_compulsory' => request('is_compulsory'), 'status' => request('status')]) }}" 
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-800 font-semibold transition border border-blue-200/60"
                        title="Remove Search Keyword">
                        <span>Search: <strong>"{{ request('search') }}"</strong></span>
                        <span class="text-blue-500 hover:text-blue-800 font-bold">✕</span>
                    </a>
                @endif
            </x-slot:scopeTags>
        </x-filter-toolbar>

        {{-- Smart High-Density 4-Column Subject Table (Zero Horizontal Scroll) --}}
        <div class="bg-white shadow-xs border border-zinc-200/80 rounded-2xl overflow-hidden flex flex-col">
            @if($subjects->isNotEmpty())
                <div class="w-full">
                    <table class="w-full text-left text-sm table-fixed">
                        <thead class="bg-zinc-50 text-zinc-500 border-b border-zinc-200/80 text-[11px] font-bold uppercase tracking-wider">
                            <tr>
                                <th class="py-3.5 px-5 w-5/12">Subject Discipline & Code</th>
                                <th class="py-3.5 px-5 w-3/12">Curriculum Type</th>
                                <th class="py-3.5 px-5 w-2/12">Linked Exams</th>
                                <th class="py-3.5 px-5 w-2/12 text-right">Status & Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100">
                            @foreach ($subjects as $subject)
                                @php
                                    $examCount = $subject->examinations_count ?? $subject->examinations->count();
                                @endphp
                                <tr class="hover:bg-zinc-50/60 transition-colors">
                                    {{-- Col 1: Subject Discipline & Code --}}
                                    <td class="py-3.5 px-5">
                                        <div class="flex items-center gap-3">
                                            <div class="size-9 rounded-xl bg-blue-50 border border-blue-100 text-blue-600 flex items-center justify-center shrink-0 shadow-2xs font-medium">
                                                <flux:icon icon="book-open" class="size-4" />
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <a href="{{ route('subject.show', $subject->id) }}" class="font-bold text-zinc-900 hover:text-blue-600 transition truncate block text-xs sm:text-sm">
                                                    {{ $subject->name }}
                                                </a>
                                                <div class="text-[11px] text-zinc-400 mt-0.5 flex items-center gap-1.5 font-mono">
                                                    <span class="px-1.5 py-0.5 rounded bg-zinc-100 text-zinc-700 font-semibold border border-zinc-200/80 text-[10px]">
                                                        {{ $subject->code ?: 'SUB-' . str_pad($subject->id, 3, '0', STR_PAD_LEFT) }}
                                                    </span>
                                                    <span class="text-zinc-300">&bull;</span>
                                                    <span class="font-sans text-[11px] text-zinc-500">{{ $subject->institution?->name ?? 'Main Campus' }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Col 2: Curriculum Classification --}}
                                    <td class="py-3.5 px-5">
                                        @if($subject->is_compulsory)
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-[11px] font-bold bg-amber-50 text-amber-800 border border-amber-200/70">
                                                <span class="size-1.5 rounded-full bg-amber-500"></span>
                                                Compulsory Core
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-[11px] font-medium bg-indigo-50 text-indigo-800 border border-indigo-200/70">
                                                <span class="size-1.5 rounded-full bg-indigo-400"></span>
                                                Optional / Elective
                                            </span>
                                        @endif
                                    </td>

                                    {{-- Col 3: Linked Examinations --}}
                                    <td class="py-3.5 px-5 text-xs">
                                        <div class="flex items-center gap-1.5 text-zinc-700">
                                            <flux:icon icon="document-text" class="size-3.5 text-zinc-400 shrink-0" />
                                            <span class="font-mono font-bold text-zinc-900">{{ $examCount }}</span>
                                            <span class="text-zinc-500 text-[11px]">papers</span>
                                        </div>
                                    </td>

                                    {{-- Col 4: Status & Actions --}}
                                    <td class="py-3.5 px-5 text-right">
                                        <div class="flex flex-col items-end gap-1.5">
                                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold
                                                {{ $subject->is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200/70' : 'bg-zinc-100 text-zinc-500 border border-zinc-200' }}">
                                                <span class="size-1.5 rounded-full {{ $subject->is_active ? 'bg-emerald-500' : 'bg-zinc-400' }}"></span>
                                                {{ $subject->is_active ? 'Active' : 'Inactive' }}
                                            </span>

                                            <div class="flex items-center justify-end gap-1">
                                                <flux:button href="{{ route('subject.show', $subject->id) }}" size="sm" icon="eye" variant="subtle" tooltip="View Syllabus" />
                                                @can('edit subject')
                                                    <flux:button href="{{ route('subject.edit', $subject->id) }}" size="sm" icon="pencil-square" variant="subtle" tooltip="Edit Subject" />
                                                @endcan
                                                @can('delete subject')
                                                    <form action="{{ route('subject.destroy', $subject->id) }}" method="POST" class="inline" onsubmit="return confirm('Permanently remove {{ addslashes($subject->name) }} from curriculum?');">
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
                <div class="border-t border-zinc-200/80 px-5 py-3.5 bg-zinc-50/50 flex flex-col sm:flex-row justify-between items-center text-xs text-zinc-500 gap-3">
                    <div class="flex items-center gap-2">
                        <span>Showing <strong class="text-zinc-800 font-semibold">{{ $subjects->count() }}</strong> of <strong class="text-zinc-800 font-semibold">{{ number_format($totalCount) }}</strong> subjects</span>
                        <span class="text-zinc-300">&bull;</span>
                        <span>Page <strong class="text-zinc-800 font-semibold">{{ $subjects->currentPage() }}</strong> of <strong class="text-zinc-800 font-semibold">{{ $subjects->lastPage() }}</strong></span>
                    </div>
                    @if(method_exists($subjects, 'links') && $subjects->hasPages())
                        <div>
                            {{ $subjects->links() }}
                        </div>
                    @endif
                </div>
            @else
                {{-- Empty State --}}
                <div class="flex flex-col items-center justify-center py-16 text-center">
                    <div class="size-12 rounded-xl bg-zinc-100 flex items-center justify-center text-zinc-400 mb-2.5">
                        <flux:icon icon="book-open" class="size-6" />
                    </div>
                    <h4 class="text-sm font-bold text-zinc-900">No Subjects Found</h4>
                    <p class="text-zinc-500 text-xs max-w-sm mx-auto mt-0.5 mb-3">
                        There are no academic subjects matching your active filter criteria.
                    </p>
                    @can('create subject')
                        <flux:button href="{{ route('subject.create') }}" variant="primary" icon="plus" size="sm">
                            Add Subject
                        </flux:button>
                    @endcan
                </div>
            @endif
        </div>
    </div>
</x-layouts::app>