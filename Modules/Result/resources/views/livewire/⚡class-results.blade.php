<?php

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Classes\Models\SchoolClass;
use Modules\Examinations\Models\Examination;
use Modules\ReportCard\Services\ReportCardGenerationService;
use Modules\Result\Models\Result;
use Modules\Result\Services\ResultAccessService;
use Modules\Subject\Models\Subject;

/**
 * One class's marks ledger. Reached from the results landing page, which is
 * now a class picker - a teacher only ever lands here for a class they
 * teach, a Director for any class at their school.
 */
new #[Title('Class Results')] class extends Component {
    use WithPagination;

    public int $classId;

    #[Url]
    public string $search = '';

    #[Url(as: 'exam_id')]
    public string $examinationId = '';

    #[Url(as: 'subject_id')]
    public string $subjectId = '';

    public function mount(int $classId): void
    {
        abort_unless(auth()->user()->can('view result'), 403);

        $this->classId = $classId;

        // A teacher only sees classes they teach; anyone else, their
        // school's. Anything else is refused rather than shown empty.
        abort_unless($this->schoolClass, 404);

        if (auth()->user()->hasRole('Teacher')) {
            abort_unless(ResultAccessService::classIds(auth()->user())->contains($classId), 403);
        } elseif (! isAdmin()) {
            abort_unless($this->schoolClass->institution_id === currentInstitution()?->id, 403);
        }
    }

    public function delete(int $id): void
    {
        abort_unless(auth()->user()->can('delete result'), 403);

        $query = Result::where('class_id', $this->classId);
        ResultAccessService::scopeVisibleResults($query, auth()->user());

        $query->findOrFail($id)->delete();
    }

    #[Computed]
    public function schoolClass(): ?SchoolClass
    {
        return SchoolClass::find($this->classId);
    }

    #[Computed]
    public function currentTerm(): ?string
    {
        return Result::where('class_id', $this->classId)
            ->whereHas('examination', fn ($q) => $q->whereNotNull('term'))
            ->with('examination')
            ->latest('id')
            ->first()?->examination?->term;
    }

    public function generateReportCards(ReportCardGenerationService $service): void
    {
        abort_unless(auth()->user()->can('edit reportcard') || auth()->user()->can('create reportcard'), 403);

        $schoolClass = $this->schoolClass;
        $term = $this->currentTerm;

        if (! $schoolClass || ! $term) {
            return;
        }

        $latestExam = Examination::where('term', $term)
            ->where(fn ($q) => $q->where('class_id', $schoolClass->id)->orWhereNull('class_id'))
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
        $query = Result::with(['student.studentUserDetails', 'subject', 'examination.subject', 'schoolClass'])
            ->where('class_id', $this->classId);

        ResultAccessService::scopeVisibleResults($query, auth()->user());

        return $query
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';

                $q->where(function ($sub) use ($term) {
                    $sub->whereHas('student', fn ($sq) => $sq->where('name', 'like', $term)->orWhere('email', 'like', $term))
                        ->orWhereHas('examination', fn ($eq) => $eq->where('title', 'like', $term))
                        ->orWhereHas('subject', fn ($sq) => $sq->where('name', 'like', $term)->orWhere('code', 'like', $term));
                });
            })
            ->when($this->examinationId !== '', fn ($q) => $q->where('examination_id', $this->examinationId))
            ->when($this->subjectId !== '', fn ($q) => $q->where('subject_id', $this->subjectId))
            ->orderByDesc('created_at')
            ->paginate(15);
    }

    /**
     * @return Collection<int, Examination>
     */
    #[Computed]
    public function examinations(): Collection
    {
        return Examination::whereIn('id', Result::where('class_id', $this->classId)->distinct()->pluck('examination_id'))
            ->orderByDesc('exam_date')
            ->get();
    }

    /**
     * @return Collection<int, Subject>
     */
    #[Computed]
    public function subjects(): Collection
    {
        return Subject::whereIn('id', Result::where('class_id', $this->classId)->whereNotNull('subject_id')->distinct()->pluck('subject_id'))
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array{records: int, mean: float, pass_rate: float}
     */
    #[Computed]
    public function stats(): array
    {
        $query = Result::where('class_id', $this->classId);
        ResultAccessService::scopeVisibleResults($query, auth()->user());

        $row = $query->selectRaw('count(*) as records, avg(marks_obtained) as mean_mark, sum(case when marks_obtained >= 50 then 1 else 0 end) as passes')->first();
        $records = (int) ($row->records ?? 0);

        return [
            'records' => $records,
            'mean' => round((float) ($row->mean_mark ?? 0), 1),
            'pass_rate' => $records > 0 ? round(((int) ($row->passes ?? 0) / $records) * 100, 1) : 0.0,
        ];
    }
}; ?>

<div class="flex flex-col gap-6 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <a href="{{ route('result.index') }}" wire:navigate
                class="inline-flex items-center gap-1.5 text-xs font-bold text-zinc-500 hover:text-zinc-900 transition mb-1.5">
                <flux:icon icon="arrow-left" class="size-3.5" />
                <span>All classes</span>
            </a>
            <flux:heading size="xl" class="font-black tracking-tight text-zinc-900 dark:text-white">
                {{ $this->schoolClass?->name ?? 'Class' }} — Results
            </flux:heading>
            <flux:text class="text-zinc-500 text-sm mt-0.5">
                Recorded marks, grades and remarks for this class.
                @if ($this->currentTerm)
                    <span class="font-semibold text-zinc-600 dark:text-zinc-300">{{ $this->currentTerm }}</span>
                @endif
            </flux:text>
        </div>

        <div class="flex items-center gap-3">
            @can('edit reportcard')
                <flux:button wire:click="generateReportCards" icon="sparkles" variant="subtle"
                    class="font-semibold shadow-sm">
                    Generate Report Cards
                </flux:button>
            @endcan
            @can('create result')
                <flux:button href="{{ route('result.entry.exams') }}" wire:navigate.hover icon="clipboard-document-check"
                    variant="primary" class="font-semibold shadow-sm px-4">
                    Record Exam Score
                </flux:button>
            @endcan
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        <x-kpi-card title="Recorded Marks" icon="academic-cap" color="sky" :count="$this->stats['records']"
            subtext="in this class" />
        <x-kpi-card title="Class Average" icon="chart-bar" color="indigo"
            :count="number_format($this->stats['mean'], 1)" subtext="mean mark recorded" />
        <x-kpi-card title="Pass Rate" icon="check-badge" color="emerald" :count="$this->stats['pass_rate'] . '%'"
            subtext="scoring 50% and above" />
    </div>

    {{-- Filters --}}
    <div class="flex flex-wrap items-center gap-3">
        <flux:input type="search" icon="magnifying-glass" placeholder="Search student or assessment..."
            wire:model.live.debounce.300ms="search" class="max-w-xs" />
        <flux:select wire:model.live="subjectId" class="max-w-[12rem]">
            <flux:select.option value="">All subjects</flux:select.option>
            @foreach ($this->subjects as $subject)
                <flux:select.option value="{{ $subject->id }}">{{ $subject->name }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="examinationId" class="max-w-[14rem]">
            <flux:select.option value="">All sittings</flux:select.option>
            @foreach ($this->examinations as $exam)
                <flux:select.option value="{{ $exam->id }}">{{ $exam->title }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <div class="bg-white dark:bg-zinc-900 shadow-sm border border-zinc-200/80 dark:border-zinc-800 rounded-2xl overflow-hidden">
        @if ($this->results->isNotEmpty())
            <table class="w-full text-left text-sm">
                <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-500 border-b border-zinc-200/80 dark:border-zinc-700 text-xs font-bold uppercase tracking-wider">
                    <tr>
                        <th class="py-3.5 px-5">Student</th>
                        <th class="py-3.5 px-5">Assessment &amp; Subject</th>
                        <th class="py-3.5 px-5">Marks &amp; Grade</th>
                        <th class="py-3.5 px-5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($this->results as $result)
                        @php($studentName = $result->student?->name ?? 'Unknown Student')
                        @php($admNo = $result->student?->studentUserDetails?->admission_number ?? 'ADM-' . str_pad((string) $result->student_id, 3, '0', STR_PAD_LEFT))
                        @php($grade = $result->grade ?? 'N/A')
                        @php($subjectName = $result->subject?->name ?? $result->examination?->subject?->name ?? $result->examination?->subject_name ?? 'Subject')
                        <tr class="hover:bg-zinc-50/60 dark:hover:bg-zinc-800/40 transition-colors">
                            <td class="py-4 px-5">
                                <a href="{{ route('student.show', $result->student_id) }}" wire:navigate.hover
                                    class="font-bold text-zinc-900 dark:text-white hover:text-blue-600 transition">
                                    {{ $studentName }}
                                </a>
                                <div class="text-xs text-zinc-400 mt-0.5 font-mono">{{ $admNo }}</div>
                            </td>
                            <td class="py-4 px-5">
                                <div class="font-bold text-xs text-zinc-900 dark:text-white truncate">
                                    {{ $result->examination?->title ?? 'Exam' }}
                                </div>
                                <div class="text-[11px] text-zinc-500 mt-1">
                                    <span class="px-2 py-0.5 rounded-md bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 font-medium border border-zinc-200/60 dark:border-zinc-700">
                                        {{ $subjectName }}
                                    </span>
                                </div>
                            </td>
                            <td class="py-4 px-5">
                                <span class="font-mono font-bold text-zinc-900 dark:text-white text-sm">{{ $result->marks_obtained }}</span>
                                <span class="text-zinc-400 text-xs">/ {{ $result->examination?->total_marks ?? 100 }}</span>
                                <span class="ml-2 px-2 py-0.5 rounded-full text-xs font-bold font-mono bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700">
                                    {{ $grade }}
                                </span>
                            </td>
                            <td class="py-4 px-5 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <flux:button href="{{ route('result.show', $result->id) }}" wire:navigate.hover
                                        size="sm" icon="eye" variant="subtle" tooltip="View" />
                                    @can('edit result')
                                        <flux:button href="{{ route('result.edit', $result->id) }}" wire:navigate.hover
                                            size="sm" icon="pencil-square" variant="subtle" tooltip="Edit" />
                                    @endcan
                                    @can('delete result')
                                        <flux:button wire:click="delete({{ $result->id }})"
                                            wire:confirm="Remove this exam score?" size="sm" icon="trash" variant="subtle"
                                            class="text-rose-500 hover:bg-rose-50" tooltip="Delete" />
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if ($this->results->hasPages())
                <div class="px-5 py-4 border-t border-zinc-100 dark:border-zinc-800">
                    {{ $this->results->links() }}
                </div>
            @endif
        @else
            <div class="py-16 text-center">
                <flux:icon icon="academic-cap" class="size-12 mx-auto text-zinc-300 mb-3" />
                <h4 class="text-base font-bold text-zinc-900 dark:text-white">No results for this class yet</h4>
                <p class="text-xs text-zinc-500 mt-1 mb-4">Record marks for a sitting to see them here.</p>
                @can('create result')
                    <flux:button href="{{ route('result.entry.exams') }}" wire:navigate variant="primary" size="sm">
                        Record Exam Score
                    </flux:button>
                @endcan
            </div>
        @endif
    </div>
</div>
