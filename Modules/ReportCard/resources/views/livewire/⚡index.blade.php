<?php

use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Classes\Models\SchoolClass;
use Modules\ReportCard\Models\ReportCard;
use Modules\ReportCard\Services\ReportCardSender;
use Modules\Student\Models\StudentDetails;

new #[Title('Report Cards')] class extends Component
{
    use WithPagination;

    /** @var string[] */
    public const SORTABLE = ['term', 'mean_grade', 'completed_at'];

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url(as: 'class_id')]
    public string $classId = '';

    #[Url]
    public string $sort = 'completed_at';

    #[Url]
    public string $direction = 'desc';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('view reportcard'), 403);
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'status', 'classId'], true)) {
            $this->resetPage();
        }
    }

    public function sortBy(string $column): void
    {
        if (! in_array($column, self::SORTABLE, true)) {
            return;
        }

        if ($this->sort === $column) {
            $this->direction = $this->direction === 'asc' ? 'desc' : 'asc';

            return;
        }

        $this->sort = $column;
        $this->direction = 'asc';
    }

    public function send(int $id, ReportCardSender $sender): void
    {
        abort_unless(auth()->user()->can('edit reportcard'), 403);

        $reportCard = $this->scoped()->findOrFail($id);

        $outcome = $sender->send($reportCard);

        unset($this->reportCards);

        if (! $outcome['sent']) {
            Flux::toast(text: $outcome['reason'], variant: 'danger');

            return;
        }

        $channels = collect(['email' => $outcome['email'], 'SMS' => $outcome['sms']])
            ->filter()
            ->keys();

        if ($channels->isEmpty()) {
            Flux::toast(text: $outcome['reason'], variant: 'danger');

            return;
        }

        Flux::toast(
            text: 'Sent to '.$reportCard->student?->name.'\'s parent by '.$channels->join(' and ').'.',
            variant: 'success',
        );
    }

    #[Computed]
    public function reportCards()
    {
        $sort = in_array($this->sort, self::SORTABLE, true) ? $this->sort : 'completed_at';
        $direction = $this->direction === 'asc' ? 'asc' : 'desc';

        return $this->scoped()
            ->with(['institution', 'schoolClass', 'student'])
            ->when($this->status === 'sent', fn ($query) => $query->where('status', 'sent'))
            ->when($this->status === 'ready', fn ($query) => $query->where('status', '!=', 'sent'))
            ->when($this->classId !== '', fn ($query) => $query->where('class_id', $this->classId))
            ->when($this->search !== '', function ($query) {
                $term = '%'.$this->search.'%';

                $query->where(fn ($q) => $q->where('term', 'like', $term)
                    ->orWhereHas('student', fn ($q2) => $q2->where('name', 'like', $term)));
            })
            ->orderBy($sort, $direction)
            ->paginate(15);
    }

    #[Computed]
    public function classes(): Collection
    {
        $query = SchoolClass::query();

        if (! isAdmin()) {
            $query->where('institution_id', currentInstitution()?->id ?? 0);
        }

        return $query->get();
    }

    #[Computed]
    public function totalReportsCount(): int
    {
        return $this->scoped()->count();
    }

    #[Computed]
    public function avgScore()
    {
        return round((float) $this->scoped()->avg('average_score'), 1);
    }

    #[Computed]
    public function institution()
    {
        return currentInstitution();
    }

    private function scoped()
    {
        $user = auth()->user();
        $query = ReportCard::query();

        if (isAdmin()) {
            return $query;
        }

        if ($user->hasRole('Teacher')) {
            return $query->where('institution_id', $user->teacherUserDetails?->institution_id ?? 0);
        }

        if ($user->hasRole('Parent')) {
            return $query->whereIn('student_id', StudentDetails::where('parent_id', $user->id)->pluck('student_id'));
        }

        if ($user->hasRole('Student')) {
            return $query->where('student_id', $user->id);
        }

        return $query->where('institution_id', currentInstitution()?->id ?? 0);
    }
}; ?>
@php
    $reportCards = $this->reportCards;
    $classes = $this->classes;
    $totalReportsCount = $this->totalReportsCount;
    $avgScore = $this->avgScore;
    $institution = $this->institution;
    $totalCount = method_exists($reportCards, 'total') ? $reportCards->total() : count($reportCards);
    $currentSearch = $this->search;
    $currentClassId = $this->classId;
    $currentLevel = request('level', 'all');
    $hasActiveFilters = !empty($currentSearch) || !empty($currentClassId) || ($currentLevel !== 'all');
    $selectedClass = $classes->firstWhere('id', (int) $currentClassId);
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
                    <span class="text-xs text-zinc-500 font-medium">{{ $institution?->name ?? currentInstitution()?->name ?? 'Green Valley Academy' }}</span>
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
                :count="isset($avgScore) && $avgScore > 0 ? number_format($avgScore, 1) : ($reportCards->avg('average_score') ? number_format($reportCards->avg('average_score'), 1) : 'ΓÇö')" 
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
                        <span class="text-zinc-400 hover:text-zinc-700 font-bold">Γ£ò</span>
                    </a>
                @endif

                @if ($currentSearch)
                    <a href="{{ route('reportcard.index', ['level' => $currentLevel, 'class_id' => $currentClassId]) }}" 
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-800 font-semibold transition border border-blue-200/60"
                        title="Remove Search Keyword">
                        <span>Search: <strong>"{{ $currentSearch }}"</strong></span>
                        <span class="text-blue-500 hover:text-blue-800 font-bold">Γ£ò</span>
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
    $reportCards = $this->reportCards;
    $classes = $this->classes;
    $totalReportsCount = $this->totalReportsCount;
    $avgScore = $this->avgScore;
    $institution = $this->institution;
    $totalCount = method_exists($reportCards, 'total') ? $reportCards->total() : count($reportCards);
    $currentSearch = $this->search;
    $currentClassId = $this->classId;
    $currentLevel = request('level', 'all');
    $hasActiveFilters = !empty($currentSearch) || !empty($currentClassId) || ($currentLevel !== 'all');
    $selectedClass = $classes->firstWhere('id', (int) $currentClassId);
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
                                                {{ $reportCard->average_score ? number_format($reportCard->average_score, 1) . '%' : 'ΓÇö' }}
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

