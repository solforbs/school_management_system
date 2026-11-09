<?php

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Subject\Models\Subject;

new #[Title('Academic Subjects')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $compulsory = '';

    #[Url]
    public string $tab = 'all';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('view subject') || auth()->user()->can('view subjects'), 403);
    }

    #[Computed]
    public function assignedSubjectMap(): array
    {
        if (! isTeacher()) {
            return [];
        }

        $userId = auth()->id();

        // Map subject_id => array of class names taught by this teacher
        $allocations = \Modules\Subject\Models\SubjectTeacher::where('teacher_id', $userId)
            ->with('schoolClass')
            ->get();

        $map = [];
        foreach ($allocations as $alloc) {
            $map[$alloc->subject_id][] = $alloc->schoolClass?->name ?? 'Class';
        }

        // Also merge legacy teacher_subjects if any
        $legacySubjIds = \Illuminate\Support\Facades\DB::table('teacher_subjects')
            ->where('teacher_id', $userId)
            ->pluck('subject_id')
            ->toArray();

        foreach ($legacySubjIds as $sId) {
            if (! isset($map[$sId])) {
                $map[$sId] = ['Assigned'];
            }
        }

        return $map;
    }

    #[Computed]
    public function assignedSubjectIds(): array
    {
        return array_keys($this->assignedSubjectMap);
    }

    #[Computed]
    public function allSchoolSubjectsCount(): int
    {
        return Subject::when(! isAdmin(), fn ($q) => $q->where('institution_id', currentInstitutionId()))->count();
    }

    #[Computed]
    public function subjects(): LengthAwarePaginator
    {
        return Subject::with(['institution'])
            ->withCount('examinations')
            // Institution filter (for non‑admins)
            ->when(!isAdmin(), fn($q) => $q->where('institution_id', currentInstitutionId()))
            // Teacher scope filter: if tab is 'my', show only assigned subjects
            ->when(isTeacher() && $this->tab === 'my', function ($q) {
                $q->whereIn('id', $this->assignedSubjectIds);
            })
            // Search filter
            ->when($this->search !== '', function ($q) {
                $term = '%' . $this->search . '%';
                $q->where(fn($sq) => $sq->where('name', 'like', $term)->orWhere('code', 'like', $term));
            })
            // Compulsory filter
            ->when($this->compulsory !== '', fn($q) => $q->where('is_compulsory', $this->compulsory === 'yes'))
            ->orderBy('name')
            ->paginate(15);
    }

    #[Computed]
    public function totalSubjects(): int
    {
        return Subject::when(!isAdmin(), fn($q) => $q->where('institution_id', currentInstitutionId()))
            ->when(isTeacher() && $this->tab === 'my', fn ($q) => $q->whereIn('id', $this->assignedSubjectIds))
            ->count();
    }

    #[Computed]
    public function compulsorySubjects(): int
    {
        return Subject::when(!isAdmin(), fn($q) => $q->where('institution_id', currentInstitutionId()))
            ->when(isTeacher() && $this->tab === 'my', fn ($q) => $q->whereIn('id', $this->assignedSubjectIds))
            ->where('is_compulsory', true)
            ->count();
    }

    #[Computed]
    public function optionalSubjects(): int
    {
        return Subject::when(!isAdmin(), fn($q) => $q->where('institution_id', currentInstitutionId()))
            ->when(isTeacher() && $this->tab === 'my', fn ($q) => $q->whereIn('id', $this->assignedSubjectIds))
            ->where('is_compulsory', false)
            ->count();
    }
}; ?>
@php
    $subjects = $this->subjects;
    $totalSubjects = $this->totalSubjects;
    $compulsorySubjects = $this->compulsorySubjects;
    $optionalSubjects = $this->optionalSubjects;

    $currentSearch = $this->search;
    $currentCompulsory = $this->compulsory;

    $totalCount = method_exists($subjects, 'total') ? $subjects->total() : count($subjects);
    $hasActiveFilters = !empty($currentSearch) || !empty($currentCompulsory);
@endphp

<div class="flex flex-col gap-6 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">
    {{-- Top Header Section --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span
                    class="px-2.5 py-0.5 rounded-full text-xs font-bold tracking-wide uppercase bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-400 border border-blue-200/60">
                    Curriculum Management
                </span>
                <span class="text-zinc-300">&bull;</span>
                <span
                    class="text-xs text-zinc-500 font-medium"><span translate="no">{{ currentInstitution()?->name ?? 'Campus Operations' }}</span></span>
            </div>
            <flux:heading size="xl" class="font-black tracking-tight text-zinc-900 dark:text-white">Academic Subjects
            </flux:heading>
            <flux:text class="text-zinc-500 text-sm mt-0.5">
                Manage learning disciplines, syllabi, course codes, and curriculum subject allocations.
            </flux:text>
        </div>

        <div class="flex flex-wrap items-center gap-2 sm:gap-3 w-full sm:w-auto">
            <x-export-buttons base-route="subject.export" module="subject" :params="[
                'search' => $currentSearch,
                'compulsory' => $currentCompulsory,
            ]" />
            @can('create subject')
                <flux:button href="{{ route('subject.create') }}" wire:navigate.hover icon="plus" variant="primary"
                    class="font-semibold shadow-sm px-4">
                    Add Subject
                </flux:button>
            @endcan
        </div>
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

    {{-- Teacher Scope Navigation Tabs --}}
    @if (isTeacher())
        <div class="flex items-center gap-2 border-b border-zinc-200 dark:border-zinc-800 pb-2">
            <button type="button" wire:click="$set('tab', 'all')"
                class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-2 {{ $this->tab === 'all' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900 shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800' }}">
                <flux:icon icon="academic-cap" class="size-3.5" />
                All School Disciplines
                <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $this->tab === 'all' ? 'bg-zinc-700 text-white dark:bg-zinc-200 dark:text-zinc-900' : 'bg-zinc-200 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300' }}">
                    {{ $this->allSchoolSubjectsCount }}
                </span>
            </button>
            <button type="button" wire:click="$set('tab', 'my')"
                class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-2 {{ $this->tab === 'my' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900 shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800' }}">
                <flux:icon icon="star" class="size-3.5 text-amber-500" />
                My Teaching Allocations
                <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $this->tab === 'my' ? 'bg-zinc-700 text-white dark:bg-zinc-200 dark:text-zinc-900' : 'bg-zinc-200 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300' }}">
                    {{ count($this->assignedSubjectIds) }}
                </span>
            </button>
        </div>
    @endif

    {{-- Top Executive Metrics Strip --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 sm:gap-5">
        <x-kpi-card title="Total Subjects" icon="book-open" color="sky" :count="$totalSubjects"
            subtext="{{ isTeacher() && $this->tab === 'my' ? 'Your allocated disciplines' : 'Registered academic disciplines' }}" />

        <x-kpi-card title="Compulsory Subjects" icon="academic-cap" color="indigo" :count="$compulsorySubjects"
            subtext="Mandatory curriculum courses" href="{{ route('subject.index', ['compulsory' => 'yes']) }}" />

        <div class="col-span-2 sm:col-span-1">
            <x-kpi-card title="Optional Subjects" icon="sparkles" color="emerald" :count="$optionalSubjects"
                subtext="Elective learning pathways" href="{{ route('subject.index', ['compulsory' => 'no']) }}" />
        </div>
    </div>

    {{-- Reusable Single-Row Filter Toolbar --}}
    <x-filter-toolbar action="{{ route('subject.index') }}"
        placeholder="Search subject title, curriculum code, or keywords..." :search="$currentSearch" :hasActiveFilters="$hasActiveFilters"
        resetRoute="{{ route('subject.index') }}">
        {{-- Compulsory Filter --}}
        <div style="flex: 0 1 180px;">
            <select name="compulsory" onchange="this.form.submit()"
                class="w-full px-3 py-2 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs font-medium text-zinc-800 dark:text-zinc-200 focus:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
                <option value="">All Subject Types</option>
                <option value="yes" {{ $currentCompulsory == 'yes' ? 'selected' : '' }}>Compulsory</option>
                <option value="no" {{ $currentCompulsory == 'no' ? 'selected' : '' }}>Optional / Elective</option>
            </select>
        </div>

        {{-- Filter Scope Removal Tags --}}
        <x-slot:scopeTags>
            @if ($currentCompulsory)
                <a href="{{ route('subject.index', ['search' => $currentSearch]) }}"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 text-zinc-800 dark:text-zinc-200 font-semibold transition border border-zinc-200/60"
                    title="Remove Subject Type Filter">
                    <span>Type: <strong>{{ $currentCompulsory === 'yes' ? 'Compulsory' : 'Optional' }}</strong></span>
                    <span class="text-zinc-400 hover:text-zinc-700 font-bold">✕</span>
                </a>
            @endif

            @if ($currentSearch)
                <a href="{{ route('subject.index', ['compulsory' => $currentCompulsory]) }}"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-800 font-semibold transition border border-blue-200/60"
                    title="Remove Search Keyword">
                    <span>Search: <strong>"{{ $currentSearch }}"</strong></span>
                    <span class="text-blue-500 hover:text-blue-800 font-bold">✕</span>
                </a>
            @endif
        </x-slot:scopeTags>
    </x-filter-toolbar>

    {{-- Smart High-Density 4-Column Subject Table --}}
    <div
        class="bg-white dark:bg-zinc-900 shadow-xs border border-zinc-200/80 dark:border-zinc-800 rounded-2xl overflow-hidden flex flex-col">
        @if ($subjects->isNotEmpty())
            <div class="w-full overflow-x-auto">
                <table class="w-full text-left text-sm table-fixed min-w-[720px]">
                    <thead
                        class="bg-zinc-50 dark:bg-zinc-800 text-zinc-500 border-b border-zinc-200/80 dark:border-zinc-700 text-[11px] font-bold uppercase tracking-wider">
                        <tr>
                            <th class="py-3.5 px-5 w-5/12">Subject Discipline &amp; Code</th>
                            <th class="py-3.5 px-5 w-3/12">Curriculum Type</th>
                            <th class="py-3.5 px-5 w-2/12">Linked Exams</th>
                            <th class="py-3.5 px-5 w-2/12 text-right">Status &amp; Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($this->subjects as $subject)
                            @php
                                $examCount = $subject->examinations_count ?? $subject->examinations->count();
                                $assignedClasses = $this->assignedSubjectMap[$subject->id] ?? null;
                                $isTaughtByMe = !empty($assignedClasses);
                            @endphp
                            <tr class="hover:bg-zinc-50/60 dark:hover:bg-zinc-800/40 transition-colors">
                                <td class="py-3.5 px-5">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="size-9 rounded-xl {{ $isTaughtByMe ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-300 border border-emerald-200/80' : 'bg-blue-50 text-blue-600 dark:bg-blue-950/50 dark:text-blue-300 border border-blue-100 dark:border-blue-800' }} flex items-center justify-center shrink-0 font-medium">
                                            <flux:icon icon="{{ $isTaughtByMe ? 'star' : 'book-open' }}" class="size-4" />
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2">
                                                <a href="{{ route('subject.show', $subject->id) }}" wire:navigate.hover
                                                    class="font-bold text-zinc-900 dark:text-white hover:text-blue-600 transition truncate block text-xs sm:text-sm">
                                                    {{ $subject->name }}
                                                </a>
                                                @if ($isTaughtByMe)
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-300/50 shrink-0">
                                                        <flux:icon icon="star" class="size-2.5" />
                                                        You Teach: {{ implode(', ', array_unique($assignedClasses)) }}
                                                    </span>
                                                @endif
                                            </div>
                                            <div
                                                class="text-[11px] text-zinc-400 mt-0.5 flex flex-wrap items-center gap-1.5 font-mono">
                                                <span
                                                    class="px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 font-semibold border border-zinc-200/80 dark:border-zinc-700 text-[10px]">{{ $subject->code ?? '—' }}</span>
                                                @if ($subject->learning_area)
                                                    <span class="text-zinc-300">&bull;</span>
                                                    <span class="font-sans text-[11px] text-zinc-600 dark:text-zinc-400 font-medium">{{ $subject->learning_area }}</span>
                                                @endif
                                                @if ($subject->strand)
                                                    <span class="text-zinc-300">&bull;</span>
                                                    <span class="font-sans text-[10px] text-zinc-400 italic">{{ $subject->strand }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td class="py-3.5 px-5">
                                    <span
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $subject->is_compulsory ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300 border border-indigo-200/80' : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300 border border-emerald-200/80' }}">
                                        {{ $subject->is_compulsory ? 'Compulsory' : 'Optional' }}
                                    </span>
                                </td>

                                <td class="py-3.5 px-5">
                                    <div class="font-mono text-xs font-semibold text-zinc-800 dark:text-zinc-200">
                                        {{ $examCount }} Assessment{{ $examCount === 1 ? '' : 's' }}
                                    </div>
                                </td>

                                <td class="py-3.5 px-5 text-right">
                                    <div class="flex items-center justify-end gap-1.5 whitespace-nowrap">
                                        @if ($isTaughtByMe)
                                            <flux:button href="{{ route('assessment.formative') }}"
                                                wire:navigate.hover size="xs" variant="primary" icon="sparkles"
                                                tooltip="Record Assessment for {{ $subject->name }}">
                                                Assess
                                            </flux:button>
                                        @endif
                                        <flux:button href="{{ route('subject.show', $subject->id) }}"
                                            wire:navigate.hover size="xs" icon="eye" variant="subtle"
                                            tooltip="View Subject Details" />
                                        @can('edit subject')
                                            <flux:button href="{{ route('subject.edit', $subject->id) }}"
                                                wire:navigate.hover size="sm" icon="pencil-square" variant="subtle"
                                                title="Edit Subject" />
                                        @endcan
                                        @can('delete subject')
                                            <x-confirm-form
                                                :action="route('subject.destroy', $subject->id)"
                                                title="Delete {{ $subject->name }}?"
                                                message="This permanently removes the subject. Recorded results are kept."
                                                confirm="Delete Subject"
                                                trigger-class="text-rose-600 hover:text-rose-700"
                                                tooltip="Delete Subject" />
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($subjects->hasPages())
                <div class="px-5 py-4 border-t border-zinc-100 dark:border-zinc-800 flex items-center justify-between">
                    {{ $subjects->links() }}
                </div>
            @endif
        @else
            <div class="py-16 text-center">
                <flux:icon icon="book-open" class="size-12 mx-auto text-zinc-300 mb-3" />
                <h4 class="text-base font-bold text-zinc-900 dark:text-white">No Subjects Found</h4>
                <p class="text-xs text-zinc-500 mt-1 mb-4">No curriculum courses match your search or filter
                    parameters.</p>
                @can('create subject')
                    <flux:button href="{{ route('subject.create') }}" wire:navigate.hover variant="primary"
                        size="sm">
                        Add First Subject
                    </flux:button>
                @endcan
            </div>
        @endif
    </div>
</div>
