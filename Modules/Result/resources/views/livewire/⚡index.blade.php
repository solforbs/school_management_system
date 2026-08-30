<?php

use Modules\Institution\Models\Institution;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Classes\Models\SchoolClass;
use Modules\Examinations\Models\Examination;
use Modules\Result\Models\Result;
use Modules\Subject\Models\Subject;

new #[Title('Academic Performance')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url(as: 'exam_id')]
    public string $examinationId = '';

    #[Url(as: 'subject_id')]
    public string $subjectId = '';

    #[Url(as: 'class_id')]
    public string $classId = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('view result'), 403);
    }

    public function delete(int $id): void
    {
        abort_unless(auth()->user()->can('delete result'), 403);
        $result = Result::when(! isAdmin(), fn ($q) => $q->where('institution_id', currentInstitutionId()))
            ->findOrFail($id);
        $result->delete();
    }

    #[Computed]
    public function currentTerm(): ?string
    {
        if ($this->classId === '') {
            return null;
        }

        $latestResult = Result::where('class_id', $this->classId)
            ->when(! isAdmin(), fn ($q) => $q->where('institution_id', currentInstitutionId()))
            ->whereHas('examination', fn ($q) => $q->whereNotNull('term'))
            ->with('examination')
            ->latest('id')
            ->first();

        return $latestResult?->examination?->term;
    }

    public function generateReportCards(\Modules\ReportCard\Services\ReportCardGenerationService $service): void
    {
        abort_unless(auth()->user()->can('edit reportcard') || auth()->user()->can('create reportcard'), 403);

        if ($this->classId === '') {
            return;
        }

        $schoolClass = SchoolClass::when(! isAdmin(), fn ($q) => $q->where('institution_id', currentInstitutionId()))
            ->find($this->classId);

        if (! $schoolClass) {
            return;
        }

        $term = $this->currentTerm;
        if (! $term) {
            return;
        }

        $latestExam = Examination::where('class_id', $schoolClass->id)
            ->where('term', $term)
            ->latest('exam_date')
            ->first();

        $year = $latestExam?->academic_year ?? (int) date('Y');

        $result = $service->forClass($schoolClass, $term, $year);

        \Flux\Flux::toast(
            text: "Generated {$result['generated']} report cards for {$schoolClass->name}.",
            variant: 'success',
        );
    }

    #[Computed]
    public function results(): LengthAwarePaginator
    {
        return Result::with(['student.studentUserDetails', 'examination.subject', 'schoolClass', 'institution'])
            ->when(! isAdmin(), fn ($q) => $q->where('institution_id', currentInstitutionId()))
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';

                $q->where(function ($sub) use ($term) {
                    $sub->whereHas('student', fn ($sq) => $sq->where('name', 'like', $term)->orWhere('email', 'like', $term))
                        ->orWhereHas('examination', fn ($eq) => $eq->where('title', 'like', $term))
                        ->orWhereHas('examination.subject', fn ($subjQ) => $subjQ->where('name', 'like', $term)->orWhere('code', 'like', $term));
                });
            })
            ->when($this->examinationId !== '', fn ($q) => $q->where('examination_id', $this->examinationId))
            ->when($this->subjectId !== '', fn ($q) => $q->whereHas('examination', fn ($eq) => $eq->where('subject_id', $this->subjectId)))
            ->when($this->classId !== '', fn ($q) => $q->where('class_id', $this->classId))
            ->orderBy('created_at', 'desc')
            ->paginate(15);
    }

    #[Computed]
    public function examinations(): array
    {
        return Examination::when(! isAdmin(), fn ($q) => $q->where('institution_id', currentInstitutionId()))
            ->orderBy('exam_date', 'desc')
            ->get()
            ->all();
    }

    #[Computed]
    public function subjects(): array
    {
        return Subject::when(! isAdmin(), fn ($q) => $q->where('institution_id', currentInstitutionId()))
            ->orderBy('name')
            ->get()
            ->all();
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
    public function institution(): ?Institution
    {
        return currentInstitution();
    }
}; ?>

@php
    $results = $this->results;
    $examinations = $this->examinations;
    $subjects = $this->subjects;
    $classes = $this->classes;
    $institution = $this->institution;

    $currentSearch = $this->search;
    $currentExam = $this->examinationId;
    $currentSubject = $this->subjectId;
    $currentClass = $this->classId;

    $totalCount = method_exists($results, 'total') ? $results->total() : count($results);
    $hasActiveFilters = !empty($currentSearch) || !empty($currentExam) || !empty($currentSubject) || !empty($currentClass);

    $avgScore = $results->avg('marks_obtained') ?: 0;
    $passCount = $results->where('marks_obtained', '>=', 50)->count();
    $passRate = count($results) > 0 ? round(($passCount / count($results)) * 100, 1) : 0;
@endphp

    <div class="flex flex-col gap-6 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">

        {{-- Top Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold tracking-wide uppercase bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-400 border border-blue-200/60">
                        Academic Performance
                    </span>
                    <span class="text-zinc-300">&bull;</span>
                    <span class="text-xs text-zinc-500 font-medium">{{ $institution?->name ?? 'Campus Records' }}</span>
                </div>
                <flux:heading size="xl" class="font-black tracking-tight text-zinc-900 dark:text-white">Exam Results &amp; Marks Ledger</flux:heading>
                <flux:text class="text-zinc-500 text-sm mt-0.5">
                    Track recorded student marks, assessment grades, class rankings, and academic score breakdowns.
                </flux:text>
            </div>

            <div class="flex items-center gap-3">
                @if ($this->currentTerm)
                    <span class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">{{ $this->currentTerm }}</span>
                @endif
                @can('edit reportcard')
                    <flux:button wire:click="generateReportCards" icon="sparkles" variant="subtle" class="font-semibold shadow-sm">
                        Generate Report Cards
                    </flux:button>
                @endcan
                @can('create result')
                    <flux:button href="{{ route('result.create') }}" wire:navigate.hover icon="plus" variant="primary" class="font-semibold shadow-sm px-4">
                        Record Exam Score
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
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
            <x-kpi-card 
                title="Total Recorded Marks" 
                icon="academic-cap" 
                color="sky" 
                :count="$totalCount" 
                subtext="Individual subject scores" 
            />
            
            <x-kpi-card 
                title="Cohort Average Score" 
                icon="chart-bar" 
                color="indigo" 
                :count="number_format($avgScore, 1) . '%'" 
                subtext="Overall mean achievement" 
            />
            
            <x-kpi-card 
                title="Pass Proficiency" 
                icon="check-badge" 
                color="emerald" 
                :count="$passRate . '%'" 
                subtext="Scoring 50% and above" 
            />
        </div>

        {{-- Reusable Single-Row Filter Toolbar --}}
        <x-filter-toolbar 
            action="{{ route('result.index') }}"
            placeholder="Search student name, ADM, or assessment..."
            :search="$currentSearch"
            :hasActiveFilters="$hasActiveFilters"
            resetRoute="{{ route('result.index') }}"
        >
            <div style="flex: 0 1 180px;">
                <select name="exam_id" onchange="this.form.submit()"
                    class="w-full px-3 py-2 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs font-medium text-zinc-800 dark:text-zinc-200 focus:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
                    <option value="">All Examinations</option>
                    @foreach ($examinations as $e)
                        <option value="{{ $e->id }}" {{ $currentExam == $e->id ? 'selected' : '' }}>
                            {{ $e->title }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div style="flex: 0 1 160px;">
                <select name="subject_id" onchange="this.form.submit()"
                    class="w-full px-3 py-2 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs font-medium text-zinc-800 dark:text-zinc-200 focus:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
                    <option value="">All Subjects</option>
                    @foreach ($this->subjects as $s)
                        <option value="{{ $s->id }}" {{ $currentSubject == $s->id ? 'selected' : '' }}>
                            {{ $s->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div style="flex: 0 1 150px;">
                <select name="class_id" onchange="this.form.submit()"
                    class="w-full px-3 py-2 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs font-medium text-zinc-800 dark:text-zinc-200 focus:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
                    <option value="">All Classes</option>
                    @foreach ($this->classes as $c)
                        <option value="{{ $c->id }}" {{ $currentClass == $c->id ? 'selected' : '' }}>
                            {{ $c->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <x-slot:scopeTags>
                @if ($currentExam)
                    <a href="{{ route('result.index', ['search' => $currentSearch, 'subject_id' => $currentSubject, 'class_id' => $currentClass]) }}" 
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 text-zinc-800 dark:text-zinc-200 font-semibold transition border border-zinc-200/60"
                        title="Remove Exam Filter">
                        <span>Exam Filter Active</span>
                        <span class="text-zinc-400 hover:text-zinc-700 font-bold">✕</span>
                    </a>
                @endif

                @if ($currentSubject)
                    <a href="{{ route('result.index', ['search' => $currentSearch, 'exam_id' => $currentExam, 'class_id' => $currentClass]) }}" 
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 text-zinc-800 dark:text-zinc-200 font-semibold transition border border-zinc-200/60"
                        title="Remove Subject Filter">
                        <span>Subject Filter Active</span>
                        <span class="text-zinc-400 hover:text-zinc-700 font-bold">✕</span>
                    </a>
                @endif

                @if ($currentClass)
                    <a href="{{ route('result.index', ['search' => $currentSearch, 'exam_id' => $currentExam, 'subject_id' => $currentSubject]) }}" 
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 text-zinc-800 dark:text-zinc-200 font-semibold transition border border-zinc-200/60"
                        title="Remove Class Filter">
                        <span>Class Filter Active</span>
                        <span class="text-zinc-400 hover:text-zinc-700 font-bold">✕</span>
                    </a>
                @endif

                @if ($currentSearch)
                    <a href="{{ route('result.index', ['exam_id' => $currentExam, 'subject_id' => $currentSubject, 'class_id' => $currentClass]) }}" 
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-800 font-semibold transition border border-blue-200/60"
                        title="Remove Search Keyword">
                        <span>Search: <strong>"{{ $currentSearch }}"</strong></span>
                        <span class="text-blue-500 hover:text-blue-800 font-bold">✕</span>
                    </a>
                @endif
            </x-slot:scopeTags>
        </x-filter-toolbar>

        {{-- Clean High-Density 4-Column Table --}}
        <div class="bg-white dark:bg-zinc-900 shadow-sm border border-zinc-200/80 dark:border-zinc-800 rounded-2xl overflow-hidden flex flex-col">
            @if($results->isNotEmpty())
                <div class="w-full">
                    <table class="w-full text-left text-sm table-fixed">
                        <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-500 border-b border-zinc-200/80 dark:border-zinc-700 text-xs font-bold uppercase tracking-wider">
                            <tr>
                                <th class="py-3.5 px-5 w-4/12">Student Profile &amp; ADM</th>
                                <th class="py-3.5 px-5 w-3/12">Assessment &amp; Subject</th>
                                <th class="py-3.5 px-5 w-3/12">Marks &amp; Grade</th>
                                <th class="py-3.5 px-5 w-2/12 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($results as $result)
                                @php
                                    $student = $result->student;
                                    $studentName = $student?->name ?? 'Unknown Student';
                                    $initials = strtoupper(substr($studentName, 0, 2));
                                    $admNo = $student?->studentUserDetails?->admission_number ?? 'ADM-' . str_pad($result->student_id, 3, '0', STR_PAD_LEFT);
                                    $grade = $result->grade ?? 'N/A';
                                @endphp
                                <tr class="hover:bg-zinc-50/60 dark:hover:bg-zinc-800/40 transition-colors">
                                    <td class="py-4 px-5">
                                        <div class="flex items-center gap-3">
                                            <div class="size-10 rounded-xl bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 font-bold text-xs flex items-center justify-center text-zinc-800 dark:text-zinc-200 shrink-0">
                                                {{ $initials }}
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <a href="{{ route('student.show', $result->student_id) }}" wire:navigate.hover class="font-bold text-zinc-900 dark:text-white hover:text-blue-600 transition truncate block text-sm">
                                                    {{ $studentName }}
                                                </a>
                                                <div class="text-xs text-zinc-400 mt-0.5 flex items-center gap-1.5 font-mono">
                                                    <span class="px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 font-semibold border border-zinc-200 dark:border-zinc-700 text-[11px]">{{ $admNo }}</span>
                                                    <span class="text-zinc-300">&bull;</span>
                                                    <span class="font-sans text-[11px] text-zinc-500">{{ $result->schoolClass?->name ?? 'Class Room' }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="py-4 px-5">
                                        <div class="font-bold text-xs text-zinc-900 dark:text-white truncate">{{ $result->examination?->title ?? 'Exam Term' }}</div>
                                        <div class="text-[11px] text-zinc-500 dark:text-zinc-400 mt-1 font-medium flex items-center gap-1.5">
                                            <span class="px-2 py-0.5 rounded-md bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 font-medium border border-zinc-200/60 dark:border-zinc-700">
                                                {{ $result->examination?->subject?->name ?? 'Discipline' }}
                                            </span>
                                        </div>
                                    </td>

                                    <td class="py-4 px-5">
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono font-bold text-zinc-900 dark:text-white text-sm">{{ $result->marks_obtained }}</span>
                                            <span class="text-zinc-400 text-xs font-medium">/ {{ $result->examination?->total_marks ?? 100 }}</span>
                                            <span class="px-2 py-0.5 rounded-full text-xs font-bold font-mono ml-auto
                                                {{ in_array($grade, ['A', 'A-']) ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300 border border-emerald-200' :
                                                   (in_array($grade, ['B+', 'B', 'B-']) ? 'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300 border border-blue-200' :
                                                   (in_array($grade, ['C+', 'C', 'C-']) ? 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300 border border-amber-200' : 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 border border-zinc-200')) }}">
                                                {{ $grade }}
                                            </span>
                                        </div>
                                    </td>

                                    <td class="py-4 px-5 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <flux:button href="{{ route('result.show', $result->id) }}" wire:navigate.hover size="sm" icon="eye" variant="subtle" tooltip="View Details" />
                                            @can('edit result')
                                                <flux:button href="{{ route('result.edit', $result->id) }}" wire:navigate.hover size="sm" icon="pencil-square" variant="subtle" tooltip="Edit Score" />
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

                @if($results->hasPages())
                    <div class="px-5 py-4 border-t border-zinc-100 dark:border-zinc-800 flex items-center justify-between">
                        {{ $results->links() }}
                    </div>
                @endif
            @else
                <div class="py-16 text-center">
                    <flux:icon icon="academic-cap" class="size-12 mx-auto text-zinc-300 mb-3" />
                    <h4 class="text-base font-bold text-zinc-900 dark:text-white">No Exam Results Found</h4>
                    <p class="text-xs text-zinc-500 mt-1 mb-4">No marks match your active search or subject filters.</p>
                    @can('create result')
                        <flux:button href="{{ route('result.create') }}" variant="primary" size="sm">
                            Record First Score
                        </flux:button>
                    @endcan
                </div>
            @endif
        </div>
    </div>
