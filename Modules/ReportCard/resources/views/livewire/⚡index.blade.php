<?php

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Classes\Models\SchoolClass;
use Modules\Institution\Models\Institution;
use Modules\ReportCard\Models\ReportCard;

new #[Title('Report Cards')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $class_id = '';

    #[Url]
    public string $term_id = '';

    #[Url]
    public string $academic_year_id = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('view reportcard'), 403);
    }

    public function send(int $id, \Modules\ReportCard\Services\ReportCardSender $sender): void
    {
        abort_unless(auth()->user()->can('edit reportcard'), 403);

        $query = ReportCard::query();
        if (! isAdmin()) {
            $query->where('institution_id', currentInstitution()?->id ?? 0);
        }

        $reportCard = $query->findOrFail($id);

        $result = $sender->send($reportCard);

        if (! $result['sent']) {
            \Flux\Flux::toast(text: $result['reason'] ?? 'Could not send report card.', variant: 'danger');

            return;
        }

        \Flux\Flux::toast(text: 'Report card sent successfully.', variant: 'success');
    }

    #[Computed]
    public function reportCards(): LengthAwarePaginator
    {
        return ReportCard::with(['student.studentUserDetails.schoolClass', 'schoolClass', 'institution'])
            ->when(! isAdmin(), fn ($q) => $q->where('institution_id', currentInstitutionId()))
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';

                $q->where(function ($sub) use ($term) {
                    $sub->whereHas('student', fn ($sq) => $sq->where('name', 'like', $term)->orWhere('email', 'like', $term))
                        ->orWhereHas('student.studentUserDetails', fn ($duq) => $duq->where('admission_number', 'like', $term));
                });
            })
            ->when($this->class_id !== '', fn ($q) => $q->where('class_id', $this->class_id))
            ->when($this->term_id !== '', fn ($q) => $q->where('term', $this->term_id))
            ->when($this->academic_year_id !== '', fn ($q) => $q->where('academic_year', $this->academic_year_id))
            ->orderBy('created_at', 'desc')
            ->paginate(15);
    }

    #[Computed]
    public function classes(): array
    {
        return SchoolClass::when(! isAdmin(), fn ($q) => $q->where('institution_id', currentInstitutionId()))
            ->orderBy('name')
            ->get()
            ->all();
    }

    #[Computed]
    public function terms(): array
    {
        return [
            ['id' => 'Term 1', 'name' => 'Term 1'],
            ['id' => 'Term 2', 'name' => 'Term 2'],
            ['id' => 'Term 3', 'name' => 'Term 3'],
        ];
    }

    #[Computed]
    public function academicYears(): array
    {
        $currentYear = (int) date('Y');

        return [
            ['id' => (string) $currentYear, 'name' => (string) $currentYear],
            ['id' => (string) ($currentYear - 1), 'name' => (string) ($currentYear - 1)],
            ['id' => (string) ($currentYear - 2), 'name' => (string) ($currentYear - 2)],
        ];
    }

    #[Computed]
    public function institution(): ?Institution
    {
        return currentInstitution();
    }

    #[Computed]
    public function homeroomClass(): ?SchoolClass
    {
        if (! auth()->user()?->hasRole('Teacher')) {
            return null;
        }

        return SchoolClass::where('institution_id', currentInstitutionId())
            ->where('class_teacher_id', auth()->id())
            ->first();
    }
}; ?>

@php
    $reportCards = $this->reportCards;
    $classes = $this->classes;
    $terms = $this->terms;
    $academicYears = $this->academicYears;
    $institution = $this->institution;

    $currentSearch = $this->search;
    $currentClass = $this->class_id;
    $currentTerm = $this->term_id;
    $currentYear = $this->academic_year_id;

    $homeroomClass = $this->homeroomClass;
    $targetClassId = $currentClass ?: $homeroomClass?->id;

    $totalCount = method_exists($reportCards, 'total') ? $reportCards->total() : count($reportCards);
    $hasActiveFilters = !empty($currentSearch) || !empty($currentClass) || !empty($currentTerm) || !empty($currentYear);

    $cohortAverage = $reportCards->avg('mean_percentage') ?: 0;
    $publishedCount = $reportCards->where('status', 'sent')->count();
    $distinctionCount = $reportCards->where('mean_percentage', '>=', 80)->count();
@endphp

    <div class="flex flex-col gap-6 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">

        {{-- Top Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold tracking-wide uppercase bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-400 border border-blue-200/60">
                        Academic Dossiers
                    </span>
                    <span class="text-zinc-300">&bull;</span>
                    <span class="text-xs text-zinc-500 font-medium">{{ $institution?->name ?? 'Campus Records' }}</span>
                </div>
                <flux:heading size="xl" class="font-black tracking-tight text-zinc-900 dark:text-white">Student Report Cards</flux:heading>
                <flux:text class="text-zinc-500 text-sm mt-0.5">
                    Generate termly performance portfolios, class transcripts, GPA rankings, and grading dossiers.
                </flux:text>
            </div>

            <div class="flex flex-wrap items-center gap-2 sm:gap-3 w-full sm:w-auto">
                @if ($targetClassId && auth()->user()->can('view reportcard'))
                    <flux:button 
                        href="{{ route('reportcard.class.bulk-pdf', ['schoolClass' => $targetClassId, 'term' => $currentTerm, 'academic_year' => $currentYear]) }}" 
                        target="_blank" 
                        icon="printer" 
                        variant="primary" 
                        class="font-semibold shadow-sm px-4">
                        {{ $currentClass ? 'Print Class Bundle (PDF)' : 'Print ' . ($homeroomClass ? $homeroomClass->name : 'Class') . ' Bundle' }}
                    </flux:button>
                @endif
                <x-export-buttons base-route="reportcard.export" module="reportcard" :params="[
                    'search' => $currentSearch,
                    'class_id' => $currentClass,
                    'term_id' => $currentTerm,
                    'academic_year_id' => $currentYear,
                ]" />
                @can('edit reportcard')
                    <x-confirm-form
                        :action="route('reportcard.send-ready')"
                        method="POST"
                        title="Send all ready report cards?"
                        message="Every report card that's been ready for at least a day gets a one-time download link by email and SMS. This may incur messaging charges."
                        confirm="Send Ready Report Cards"
                        confirm-variant="primary"
                        label="Send Ready"
                        icon="paper-airplane"
                        variant="subtle"
                        trigger-class="font-semibold shadow-sm" />
                    <flux:button href="{{ route('reportcard.settings') }}" wire:navigate.hover icon="cog-6-tooth" variant="subtle" class="font-semibold shadow-sm px-4">
                        Grading Settings
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
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 sm:gap-5">
            <x-kpi-card 
                title="Evaluated Learners" 
                icon="academic-cap" 
                color="sky" 
                :count="$totalCount" 
                subtext="Issued term portfolios" 
            />
            
            <x-kpi-card 
                title="Cohort Average Grade" 
                icon="chart-bar" 
                color="indigo" 
                :count="number_format($cohortAverage, 1) . '%'" 
                subtext="Overall mean achievement" 
            />
            
            <div class="col-span-2 sm:col-span-1">
                <x-kpi-card 
                    title="Distinction Honors" 
                    icon="sparkles" 
                    color="emerald" 
                    :count="$distinctionCount" 
                    subtext="Scoring 80% and above" 
                />
            </div>
        </div>

        {{-- Reusable Single-Row Filter Toolbar --}}
        <x-filter-toolbar 
            action="{{ route('reportcard.index') }}"
            placeholder="Search student name, admission no, or class..."
            :search="$currentSearch"
            :hasActiveFilters="$hasActiveFilters"
            resetRoute="{{ route('reportcard.index') }}"
        >
            <div style="flex: 0 1 180px;">
                <select name="class_id" onchange="this.form.submit()"
                    class="w-full px-3 py-2 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs font-medium text-zinc-800 dark:text-zinc-200 focus:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
                    <option value="">All Classes</option>
                    @foreach ($this->classes as $c)
                        <option value="{{ $c->id }}" {{ $currentClass == $c->id ? 'selected' : '' }}>
                            {{ $c->name }} ({{ $c->level }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div style="flex: 0 1 150px;">
                <select name="term_id" onchange="this.form.submit()"
                    class="w-full px-3 py-2 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs font-medium text-zinc-800 dark:text-zinc-200 focus:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
                    <option value="">All Terms</option>
                    @foreach ($terms as $t)
                        <option value="{{ $t['id'] }}" {{ $currentTerm == $t['id'] ? 'selected' : '' }}>
                            {{ $t['name'] }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div style="flex: 0 1 150px;">
                <select name="academic_year_id" onchange="this.form.submit()"
                    class="w-full px-3 py-2 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs font-medium text-zinc-800 dark:text-zinc-200 focus:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
                    <option value="">All Academic Years</option>
                    @foreach ($academicYears as $y)
                        <option value="{{ $y['id'] }}" {{ $currentYear == $y['id'] ? 'selected' : '' }}>
                            {{ $y['name'] }}
                        </option>
                    @endforeach
                </select>
            </div>

            <x-slot:scopeTags>
                @if ($currentClass)
                    <a href="{{ route('reportcard.class.bulk-pdf', ['schoolClass' => $currentClass, 'term' => $currentTerm, 'academic_year' => $currentYear]) }}"
                        target="_blank"
                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-semibold text-xs shadow-xs transition"
                        title="Print All Report Cards for This Class">
                        <flux:icon icon="printer" class="size-3.5" />
                        <span>Print Entire Class Report Cards (PDF)</span>
                    </a>
                    <a href="{{ route('reportcard.index', ['search' => $currentSearch, 'term_id' => $currentTerm, 'academic_year_id' => $currentYear]) }}" 
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 text-zinc-800 dark:text-zinc-200 font-semibold transition border border-zinc-200/60"
                        title="Remove Class Filter">
                        <span>Class Filter Active</span>
                        <span class="text-zinc-400 hover:text-zinc-700 font-bold">✕</span>
                    </a>
                @endif

                @if ($currentTerm)
                    <a href="{{ route('reportcard.index', ['search' => $currentSearch, 'class_id' => $currentClass, 'academic_year_id' => $currentYear]) }}" 
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 text-zinc-800 dark:text-zinc-200 font-semibold transition border border-zinc-200/60"
                        title="Remove Term Filter">
                        <span>Term Filter Active</span>
                        <span class="text-zinc-400 hover:text-zinc-700 font-bold">✕</span>
                    </a>
                @endif

                @if ($currentSearch)
                    <a href="{{ route('reportcard.index', ['class_id' => $currentClass, 'term_id' => $currentTerm, 'academic_year_id' => $currentYear]) }}" 
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-800 font-semibold transition border border-blue-200/60"
                        title="Remove Search Keyword">
                        <span>Search: <strong>"{{ $currentSearch }}"</strong></span>
                        <span class="text-blue-500 hover:text-blue-800 font-bold">✕</span>
                    </a>
                @endif
            </x-slot:scopeTags>
        </x-filter-toolbar>

        {{-- High-Density Report Cards Table --}}
        <div class="bg-white dark:bg-zinc-900 shadow-sm border border-zinc-200/80 dark:border-zinc-800 rounded-2xl overflow-hidden flex flex-col">
            @if($reportCards->isNotEmpty())
                <div class="w-full overflow-x-auto">
                    <table class="w-full text-left text-sm table-fixed min-w-[720px]">
                        <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-500 border-b border-zinc-200/80 dark:border-zinc-700 text-xs font-bold uppercase tracking-wider">
                            <tr>
                                <th class="py-3.5 px-5 w-4/12">Student Profile &amp; ADM</th>
                                <th class="py-3.5 px-5 w-3/12">Class &amp; Academic Period</th>
                                <th class="py-3.5 px-5 w-3/12">Academic Standing &amp; Average</th>
                                <th class="py-3.5 px-5 w-2/12 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($reportCards as $reportCard)
                                @php
                                    $student = $reportCard->student;
                                    $studentName = $student?->name ?? 'Unknown Student';
                                    $initials = strtoupper(substr($studentName, 0, 2));
                                    $admNo = $student?->studentUserDetails?->admission_number ?? 'ADM-' . str_pad($reportCard->student_id, 3, '0', STR_PAD_LEFT);
                                    $className = $student?->studentUserDetails?->schoolClass?->name ?? 'Unassigned Class';
                                    $termName = $reportCard->term ?: 'Term 1';
                                    $yearName = $reportCard->academic_year ?: date('Y');
                                @endphp
                                <tr class="hover:bg-zinc-50/60 dark:hover:bg-zinc-800/40 transition-colors">
                                    <td class="py-4 px-5">
                                        <div class="flex items-center gap-3">
                                            <div class="size-10 rounded-xl bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 font-bold text-xs flex items-center justify-center text-zinc-800 dark:text-zinc-200 shrink-0">
                                                {{ $initials }}
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <a href="{{ route('reportcard.show', $reportCard->id) }}" wire:navigate.hover class="font-bold text-zinc-900 dark:text-white hover:text-blue-600 transition truncate block text-sm">
                                                    {{ $studentName }}
                                                </a>
                                                <div class="text-xs text-zinc-400 mt-0.5 flex items-center gap-1.5 font-mono">
                                                    <span class="px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 font-semibold border border-zinc-200 dark:border-zinc-700 text-[11px]">{{ $admNo }}</span>
                                                    <span class="text-zinc-300">&bull;</span>
                                                    <span class="font-sans text-[11px] text-zinc-500">{{ $className }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="py-4 px-5">
                                        <div class="font-bold text-xs text-zinc-900 dark:text-white truncate">{{ $termName }}</div>
                                        <div class="text-[11px] text-zinc-500 dark:text-zinc-400 mt-1 font-medium flex items-center gap-1.5">
                                            <span class="px-2 py-0.5 rounded-md bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 font-medium border border-zinc-200/60 dark:border-zinc-700">
                                                {{ $yearName }}
                                            </span>
                                        </div>
                                    </td>

                                    <td class="py-4 px-5">
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono font-bold text-zinc-900 dark:text-white text-sm">
                                                {{ $reportCard->mean_percentage ? number_format((float) $reportCard->mean_percentage, 1) . '%' : '—' }}
                                            </span>
                                            @if($reportCard->mean_grade)
                                                <span class="text-zinc-400 text-xs font-medium">({{ $reportCard->mean_grade }})</span>
                                            @endif
                                        </div>
                                    </td>

                                    <td class="py-4 px-5 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <flux:button href="{{ route('reportcard.pdf', $reportCard->id) }}" target="_blank" size="sm" icon="printer" variant="ghost" class="text-xs font-semibold hover:text-blue-600" tooltip="Print PDF" />
                                            @can('edit reportcard')
                                                <flux:button wire:click="send({{ $reportCard->id }})" size="sm" icon="paper-airplane" variant="subtle" class="text-xs font-semibold hover:text-blue-600">
                                                    Send
                                                </flux:button>
                                            @endcan
                                            <flux:button href="{{ route('reportcard.show', $reportCard->id) }}" wire:navigate.hover size="sm" icon-trailing="chevron-right" variant="subtle" class="text-xs font-semibold hover:text-blue-600">
                                                View Report
                                            </flux:button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($reportCards->hasPages())
                    <div class="px-5 py-4 border-t border-zinc-100 dark:border-zinc-800 flex items-center justify-between">
                        {{ $reportCards->links() }}
                    </div>
                @endif
            @else
                <div class="py-16 text-center">
                    <flux:icon icon="document-text" class="size-12 mx-auto text-zinc-300 mb-3" />
                    <h4 class="text-base font-bold text-zinc-900 dark:text-white">No Report Cards Found</h4>
                    <p class="text-xs text-zinc-500 mt-1 mb-4">No academic evaluation cards have been published yet.</p>
                    @can('edit reportcard')
                        <flux:button href="{{ route('reportcard.settings') }}" wire:navigate.hover variant="primary" size="sm">
                            Configure Grading Scales
                        </flux:button>
                    @endcan
                </div>
            @endif
        </div>
    </div>
