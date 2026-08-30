@php
    $totalCount = method_exists($results, 'total') ? $results->total() : count($results);
    $currentClassId = request('class_id');
    $currentExamId = request('examination_id');
    $currentSearch = request('search');
    
    $selectedClassModel = $currentClassId ? $classes->firstWhere('id', $currentClassId) : null;
    $selectedExamModel = $currentExamId ? $examinations->firstWhere('id', $currentExamId) : null;
    $hasActiveFilters = !empty($currentClassId) || !empty($currentExamId) || !empty($currentSearch);

    // Clean initials calculation stripping honorifics
    $getInitials = function($name) {
        $clean = trim(preg_replace('/^(mr\.|mrs\.|ms\.|dr\.|prof\.)\s+/i', '', $name ?? ''));
        $parts = array_filter(explode(' ', $clean));
        if (count($parts) >= 2) {
            return mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1));
        }
        return mb_strtoupper(mb_substr($clean ?: ($name ?: 'ST'), 0, 2));
    };
@endphp

<x-layouts::app :title="__('Examination Results')">
    <div class="flex flex-col gap-6 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">
        
        {{-- Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold tracking-wide uppercase bg-blue-50 text-blue-700 border border-blue-200/60">
                        Academic Performance
                    </span>
                    <span class="text-xs text-zinc-400">&bull;</span>
                    <span class="text-xs text-zinc-500 font-medium">{{ $institution?->name ?? currentInstitution()?->name ?? 'Green Valley Academy' }}</span>
                </div>
                <flux:heading size="xl" class="font-black tracking-tight text-zinc-900">Examination Results</flux:heading>
                <flux:text class="text-zinc-500 text-sm mt-0.5">
                    Record, inspect, and analyze student assessment scores across subjects and class streams.
                </flux:text>
            </div>
            
            <div class="flex items-center gap-3">
                @can('create result')
                    <flux:button href="{{ route('result.create') }}" icon="plus" variant="primary" class="font-semibold shadow-sm px-4">
                        Add Result
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

        {{-- Top Performance Overview KPI Strip (Using canonical <x-kpi-card>) --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
            <x-kpi-card 
                title="Total Scores Logged" 
                icon="document-chart-bar" 
                color="sky" 
                :count="$totalResultsCount ?? $totalCount" 
                subtext="All campus scores indexed" 
            />
            
            <x-kpi-card 
                title="Assessment Average" 
                icon="chart-bar" 
                color="indigo" 
                :count="$totalResultsCount > 0 ? $avgScore : '—'" 
                countSuffix="{{ $totalResultsCount > 0 ? '/ 100' : '' }}"
                subtext="Mean score of filtered results" 
            />
            
            <x-kpi-card 
                title="Pass & Mastery Rate" 
                icon="check-badge" 
                color="emerald" 
                :count="$totalResultsCount > 0 ? $passRate : '—'" 
                countSuffix="{{ $totalResultsCount > 0 ? '%' : '' }}"
                subtext="Scores ≥50% threshold" 
            />
        </div>

        {{-- Reusable Single-Row Filter Toolbar --}}
        <x-filter-toolbar 
            action="{{ route('result.index') }}"
            placeholder="Search student name or admission number..."
            :search="$currentSearch"
            :hasActiveFilters="$hasActiveFilters"
            resetRoute="{{ route('result.index') }}"
        >
            {{-- Class Selector --}}
            <div style="flex: 0 1 200px;">
                <select name="class_id" onchange="document.getElementById('examination_id').value=''; this.form.submit()"
                    class="w-full px-3 py-2 bg-zinc-50 border border-zinc-200 rounded-xl text-xs font-medium text-zinc-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
                    <option value="">All Classes ({{ count($classes) }})</option>
                    @foreach ($classes as $schoolClass)
                        <option value="{{ $schoolClass->id }}" {{ $currentClassId == $schoolClass->id ? 'selected' : '' }}>
                            {{ $schoolClass->name }} ({{ $schoolClass->level }})
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Examination Selector --}}
            <div style="flex: 0 1 240px;">
                <select name="examination_id" id="examination_id" onchange="this.form.submit()"
                    class="w-full px-3 py-2 bg-zinc-50 border border-zinc-200 rounded-xl text-xs font-medium text-zinc-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
                    <option value="">All Evaluations ({{ count($examinations) }})</option>
                    @foreach ($examinations as $exam)
                        <option value="{{ $exam->id }}" {{ $currentExamId == $exam->id ? 'selected' : '' }}>
                            {{ $exam->title }} ({{ $exam->term }})
                        </option>
                    @endforeach
                </select>
            </div>
        </x-filter-toolbar>

        {{-- Results Data Table --}}
        <div class="bg-white shadow-sm border border-zinc-200/80 rounded-2xl overflow-hidden flex flex-col">
            @if($results->isNotEmpty())
                <div class="w-full overflow-x-auto">
                    <table class="w-full text-left text-sm table-fixed min-w-[760px]">
                        <thead class="bg-zinc-50 text-zinc-500 border-b border-zinc-200/80 text-xs font-bold uppercase tracking-wider">
                            <tr>
                                <th class="py-3.5 px-5 w-[30%]">Student / Learner</th>
                                <th class="py-3.5 px-5 w-[18%]">Class Stream</th>
                                <th class="py-3.5 px-5 w-[25%]">Examination Assessment</th>
                                <th class="py-3.5 px-5 w-[15%]">Marks & Grade</th>
                                <th class="py-3.5 px-5 w-[12%] text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100">
                            @foreach ($results as $result)
                                @php
                                    $student = $result->student;
                                    $admNo = $student?->studentUserDetails?->admission_number ?? 'ADM-' . $result->student_id;
                                    $initials = $getInitials($student?->name ?? 'ST');
                                    $marks = (float) $result->marks_obtained;
                                    $totalMarks = (float) ($result->examination?->total_marks ?? 100);
                                    $grade = $result->grade ?? '—';
                                @endphp
                                <tr class="hover:bg-zinc-50/60 transition-colors cursor-pointer group" onclick="window.location='{{ route('result.show', $result->id) }}'">
                                    {{-- Student Column --}}
                                    <td class="py-4 px-5">
                                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                                            <div style="width: 2.5rem; height: 2.5rem; min-width: 2.5rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0;"
                                                class="rounded-xl bg-blue-50 border border-blue-100 text-blue-700 font-bold text-xs group-hover:bg-blue-600 group-hover:text-white transition-colors">
                                                {{ $initials }}
                                            </div>
                                            <div style="min-width: 0; flex: 1;">
                                                <a href="{{ route('result.show', $result->id) }}" class="font-bold text-zinc-900 group-hover:text-blue-600 transition truncate block text-sm" onclick="event.stopPropagation();">
                                                    {{ $student?->name ?? 'Student Name' }}
                                                </a>
                                                <div class="text-xs text-zinc-400 font-mono mt-0.5">
                                                    {{ $admNo }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Class Column --}}
                                    <td class="py-4 px-5">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-zinc-100 text-zinc-700 font-medium text-xs border border-zinc-200/60">
                                            {{ $result->schoolClass?->name ?? 'Class Assigned' }}
                                        </span>
                                    </td>

                                    {{-- Examination Title Column --}}
                                    <td class="py-4 px-5">
                                        <div class="font-bold text-zinc-900 text-xs truncate">
                                            {{ $result->examination?->title ?? 'Assessment' }}
                                        </div>
                                        <div class="text-[11px] text-zinc-400 mt-0.5 font-mono">
                                            {{ $result->examination?->exam_type ?? 'Exam' }} &bull; {{ $result->examination?->term ?? 'Term 1' }}
                                        </div>
                                    </td>

                                    {{-- Marks & Grade Column --}}
                                    <td class="py-4 px-5">
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono font-bold text-zinc-900 text-sm">{{ $result->marks_obtained }}</span>
                                            <span class="text-zinc-400 text-xs font-medium">/ {{ $result->examination?->total_marks ?? 100 }}</span>
                                            <span class="px-2 py-0.5 rounded-full text-xs font-bold font-mono ml-auto
                                                {{ in_array($grade, ['A', 'A-']) ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' :
                                                   (in_array($grade, ['B+', 'B', 'B-']) ? 'bg-blue-50 text-blue-700 border border-blue-200' :
                                                   (in_array($grade, ['C+', 'C', 'C-']) ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-zinc-100 text-zinc-700 border border-zinc-200')) }}">
                                                {{ $grade }}
                                            </span>
                                        </div>
                                    </td>

                                    {{-- Actions Column --}}
                                    <td class="py-4 px-5 text-right">
                                        <div class="flex items-center justify-end gap-1.5" onclick="event.stopPropagation();">
                                            <flux:button href="{{ route('result.show', $result->id) }}" size="sm" icon="eye" variant="subtle" tooltip="View Details" />
                                            @can('edit result')
                                                <flux:button href="{{ route('result.edit', $result->id) }}" size="sm" icon="pencil-square" variant="subtle" tooltip="Edit Score" />
                                            @endcan
                                            @can('delete result')
                                                <form action="{{ route('result.destroy', $result->id) }}" method="POST" class="inline" onsubmit="return confirm('Remove this exam score?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <flux:button type="submit" size="sm" icon="trash" variant="subtle" class="text-rose-500 hover:text-rose-600 hover:bg-rose-50" tooltip="Delete" />
                                                </form>
                                            @endcan
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
                        <span>Showing <strong class="text-zinc-800 font-semibold">{{ $results->count() }}</strong> of <strong class="text-zinc-800 font-semibold">{{ number_format($totalCount) }}</strong> results</span>
                        <span class="text-zinc-300">&bull;</span>
                        <span>Page <strong class="text-zinc-800 font-semibold">{{ $results->currentPage() }}</strong> of <strong class="text-zinc-800 font-semibold">{{ $results->lastPage() }}</strong></span>
                    </div>
                    @if(method_exists($results, 'links') && $results->hasPages())
                        <div>
                            {{ $results->links() }}
                        </div>
                    @endif
                </div>
            @else
                {{-- Empty State --}}
                <div class="flex flex-col items-center justify-center py-20 text-center">
                    <div class="size-14 rounded-2xl bg-zinc-100 flex items-center justify-center text-zinc-400 mb-3">
                        <flux:icon icon="academic-cap" class="size-7" />
                    </div>
                    <h4 class="text-base font-bold text-zinc-900">No Examination Results Found</h4>
                    <p class="text-zinc-500 text-xs max-w-sm mx-auto mt-1 mb-4">
                        There are no recorded examination marks matching your active filter criteria.
                    </p>
                    <a href="{{ route('result.index') }}" class="px-4 py-2 rounded-xl bg-zinc-900 text-white text-xs font-bold hover:bg-zinc-800 transition">
                        Reset All Filters
                    </a>
                </div>
            @endif
        </div>
    </div>
</x-layouts::app>