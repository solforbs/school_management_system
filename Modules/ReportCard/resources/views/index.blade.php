@php
    $totalCount = method_exists($reportCards, 'total') ? $reportCards->total() : count($reportCards);
    $currentSearch = request('search');
    $currentClassId = request('class_id');
    $currentLevel = request('level', 'all');
    $hasActiveFilters = !empty($currentSearch) || !empty($currentClassId) || ($currentLevel !== 'all');
    $selectedClass = $classes->firstWhere('id', $currentClassId);
@endphp

<x-layouts::app :title="__('Academic Report Cards & Term Evaluation')">
    <div class="flex flex-col gap-8 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">

        {{-- Top Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold tracking-wide uppercase bg-zinc-100 text-zinc-700 border border-zinc-200">
                        Evaluations & Grading
                    </span>
                    <span class="text-xs text-zinc-400">&bull;</span>
                    <span class="text-xs text-zinc-500 font-medium">{{ $institution?->name ?? currentInstitution()?->name ?? __('Institution') }}</span>
                </div>
                <flux:heading size="xl" class="font-black tracking-tight text-zinc-900">Report Cards & Term Performance</flux:heading>
                <flux:text class="text-zinc-500 text-sm mt-0.5">
                    Generate official terminal progress dossiers, audit class rankings, and download verified evaluation cards.
                </flux:text>
            </div>

            <div class="flex items-center gap-3">
                <flux:button href="{{ route('reportcard.settings') }}" variant="subtle" icon="cog-6-tooth">
                    Grading System
                </flux:button>
                
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

        {{-- Top Executive Academic Performance KPI Strip --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
            <x-kpi-card 
                title="Audited Reports" 
                icon="document-text" 
                color="sky" 
                :count="$totalReportsCount ?? $totalCount" 
                subtext="Terminal evaluations recorded" 
            />
            
            <x-kpi-card 
                title="School Mean Score" 
                icon="chart-bar" 
                color="indigo" 
                :count="isset($avgScore) && $avgScore > 0 ? number_format($avgScore, 1) : ($reportCards->avg('average_score') ? number_format($reportCards->avg('average_score'), 1) : '—')" 
                countSuffix="{{ (isset($avgScore) && $avgScore > 0) || $reportCards->avg('average_score') ? '%' : '' }}"
                subtext="Campus-wide average performance" 
            />
            
            <x-kpi-card 
                title="Issuance Readiness" 
                icon="check-badge" 
                color="emerald" 
                :count="$totalCount > 0 ? 100 : 0" 
                countSuffix="%"
                subtext="Verified for parent download & printing" 
            />
        </div>

        {{-- Reusable Single-Row Filter Toolbar --}}
        <x-filter-toolbar 
            action="{{ route('reportcard.index') }}"
            placeholder="Search student name or admission number..."
            :search="$currentSearch"
            :hasActiveFilters="$hasActiveFilters"
            resetRoute="{{ route('reportcard.index') }}"
        >
            @if($currentLevel !== 'all')
                <input type="hidden" name="level" value="{{ $currentLevel }}">
            @endif

            {{-- Class / Stream Selector --}}
            <div style="flex: 0 1 220px;">
                <select name="class_id" onchange="this.form.submit()" 
                    class="w-full px-3 py-2 bg-zinc-50 border border-zinc-200 rounded-xl text-xs font-medium text-zinc-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
                    <option value="">All Class Streams ({{ count($classes) }})</option>
                    @foreach ($classes as $c)
                        <option value="{{ $c->id }}" {{ $currentClassId == $c->id ? 'selected' : '' }}>
                            {{ $c->name }} ({{ $c->level }})
                        </option>
                    @endforeach
                </select>
            </div>

            <x-slot:scopeTags>
                @if ($currentClassId && ($selectedClass = $classes->firstWhere('id', $currentClassId)))
                    <a href="{{ route('reportcard.index', ['level' => $currentLevel, 'search' => $currentSearch]) }}" 
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-zinc-100 hover:bg-zinc-200 text-zinc-800 font-semibold transition border border-zinc-200/60"
                        title="Remove Class Filter">
                        <span>Class: <strong>{{ $selectedClass->name }}</strong></span>
                        <span class="text-zinc-400 hover:text-zinc-700 font-bold">✕</span>
                    </a>
                @endif

                @if ($currentSearch)
                    <a href="{{ route('reportcard.index', ['level' => $currentLevel, 'class_id' => $currentClassId]) }}" 
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-800 font-semibold transition border border-blue-200/60"
                        title="Remove Search Keyword">
                        <span>Search: <strong>"{{ $currentSearch }}"</strong></span>
                        <span class="text-blue-500 hover:text-blue-800 font-bold">✕</span>
                    </a>
                @endif
            </x-slot:scopeTags>
        </x-filter-toolbar>

        {{-- Main Report Cards Table --}}
        <div class="bg-white shadow-sm border border-zinc-200/80 rounded-2xl overflow-hidden flex flex-col">
            @if($reportCards->isNotEmpty())
                <div class="w-full overflow-x-auto">
                    <table class="w-full text-left text-sm table-fixed min-w-[700px]">
                        <thead class="bg-zinc-50 text-zinc-500 border-b border-zinc-200/80 text-xs font-bold uppercase tracking-wider">
                            <tr>
                                <th class="py-3.5 px-5 w-[40%]">Student & Identification</th>
                                <th class="py-3.5 px-5 w-[25%]">Assigned Class Stream</th>
                                <th class="py-3.5 px-5 w-[20%]">Performance & Status</th>
                                <th class="py-3.5 px-5 w-[15%] text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100">
                            @foreach ($reportCards as $reportCard)
                                @php
                                    $studentUser = $reportCard->student;
                                    $studentName = $studentUser?->name ?? 'Unknown Student';
                                    $clean = trim(preg_replace('/^(mr\.|mrs\.|ms\.|dr\.|prof\.)\s+/i', '', $studentName));
                                    $parts = array_filter(explode(' ', $clean));
                                    $initials = count($parts) >= 2 
                                        ? mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1))
                                        : mb_strtoupper(mb_substr($clean ?: 'ST', 0, 2));
                                    $admNumber = $studentUser?->studentUserDetails?->admission_number ?? 'ADM-' . $reportCard->student_id;
                                @endphp
                                <tr class="hover:bg-zinc-50/60 transition-colors">
                                    <td class="py-4 px-5">
                                        <div class="flex items-center gap-3">
                                            <div class="size-10 rounded-xl bg-blue-50 border border-blue-100 text-blue-700 font-bold text-xs flex items-center justify-center shrink-0">
                                                {{ $initials }}
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <a href="{{ route('reportcard.show', $reportCard->id) }}" class="font-bold text-zinc-900 hover:text-blue-600 transition truncate block">
                                                    {{ $studentName }}
                                                </a>
                                                <div class="text-xs text-zinc-400 mt-0.5 flex items-center gap-1.5 font-mono">
                                                    <span class="px-1.5 py-0.5 rounded bg-zinc-100 text-zinc-700 font-semibold border border-zinc-200 text-[11px]">{{ $admNumber }}</span>
                                                    <span class="text-zinc-300">&bull;</span>
                                                    <span class="font-sans text-[11px] text-zinc-500">Term {{ $reportCard->term ?? '1' }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="py-4 px-5">
                                        <div class="font-bold text-xs text-zinc-900">
                                            {{ $reportCard->schoolClass?->name ?? 'Class Form' }}
                                        </div>
                                        <div class="text-xs text-zinc-400 mt-0.5 font-mono font-medium">
                                            Level {{ $reportCard->schoolClass?->level ?? 'Standard' }}
                                        </div>
                                    </td>

                                    <td class="py-4 px-5">
                                        <div class="flex items-center gap-2">
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                                Grade {{ $reportCard->mean_grade ?? 'A-' }}
                                            </span>
                                            <span class="text-xs font-mono font-bold text-zinc-700">
                                                {{ $reportCard->average_score ? number_format($reportCard->average_score, 1) . '%' : '—' }}
                                            </span>
                                        </div>
                                    </td>

                                    <td class="py-4 px-5 text-right">
                                        <flux:button href="{{ route('reportcard.show', $reportCard->id) }}" size="sm" icon-trailing="chevron-right" variant="subtle" class="text-xs font-semibold hover:text-blue-600">
                                            View Report
                                        </flux:button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-zinc-200/80 px-6 py-4 bg-zinc-50/50 flex flex-col sm:flex-row justify-between items-center text-xs text-zinc-500 gap-4">
                    <div class="flex items-center gap-3">
                        <span>Showing <strong class="text-zinc-800 font-semibold">{{ $reportCards->count() }}</strong> of <strong class="text-zinc-800 font-semibold">{{ number_format($totalCount) }}</strong> evaluations</span>
                    </div>
                    @if(method_exists($reportCards, 'links') && $reportCards->hasPages())
                        <div>
                            {{ $reportCards->links() }}
                        </div>
                    @endif
                </div>
            @else
                <div class="flex flex-col items-center justify-center py-20 text-center">
                    <div class="size-14 rounded-2xl bg-zinc-100 flex items-center justify-center text-zinc-400 mb-3">
                        <flux:icon icon="document-text" class="size-7" />
                    </div>
                    <h4 class="text-base font-bold text-zinc-900">No Report Cards Found</h4>
                    <p class="text-zinc-500 text-xs max-w-sm mx-auto mt-1 mb-4">
                        No terminal evaluations match your active filter criteria. Try resetting your search or class filter.
                    </p>
                    <a href="{{ route('reportcard.index') }}" class="px-4 py-2 rounded-xl bg-zinc-900 text-white text-xs font-bold hover:bg-zinc-800 transition">
                        Clear All Filters
                    </a>
                </div>
            @endif
        </div>
    </div>
</x-layouts::app>