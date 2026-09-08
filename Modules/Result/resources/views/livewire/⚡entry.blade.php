<?php

use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Examinations\Models\Examination;
use Modules\Examinations\Models\ExamSitting;
use Modules\Institution\Models\Institution;
use Modules\Result\Actions\SaveResult;
use Modules\Result\Models\Result;
use Modules\Result\Services\ResultAccessService;
use Modules\Student\Models\StudentDetails;

/**
 * The marks sheet: one exam paper, one subject, a whole class on one
 * screen.
 *
 * The teacher normally arrives from the sitting picker with `sitting_id`
 * and `class_id` set - they pick a subject (auto-selected when they teach
 * only one in that class) and the concrete paper is resolved from that.
 * A Director-pinned paper still opens straight here with `examination_id`.
 * Saving happens in place - the sheet stays open with the grades filled in.
 */
new #[Title('Enter Marks')] class extends Component {
    #[Url(as: 'examination_id')]
    public string $examinationId = '';

    #[Url(as: 'sitting_id')]
    public string $sittingId = '';

    #[Url(as: 'subject_id')]
    public string $subjectId = '';

    #[Url(as: 'class_id')]
    public string $classId = '';

    /** A pinned paper, or a sitting reached with a class already chosen. */
    public bool $subjectLocked = false;

    public bool $classLocked = false;

    /** @var array<int, string> marks keyed by student id */
    public array $marks = [];

    /** @var array<int, string> remarks keyed by student id */
    public array $remarks = [];

    public function mount(?string $examinationId = null): void
    {
        abort_unless(auth()->user()->can('create result'), 403);

        if ($examinationId !== null && $examinationId !== '') {
            $this->examinationId = $examinationId;
        }

        // Path 1: opened straight on a paper (a Director-pinned one, or the
        // test suite). The paper decides its own subject/class.
        if ($this->examinationId !== '') {
            abort_unless($this->examination, 403, 'That exam paper is not available to you.');

            if ($this->examination->subject_id) {
                $this->subjectId = (string) $this->examination->subject_id;
                $this->subjectLocked = true;
            }

            if ($this->examination->class_id) {
                $this->classId = (string) $this->examination->class_id;
                $this->classLocked = true;
            }

            if ($this->classId !== '') {
                $this->loadSheet();
            }

            return;
        }

        // Path 2: came through the sitting -> class pickers. Lock the class,
        // and if the teacher has just one subject in it, lock that too and
        // resolve the paper.
        if ($this->sitting && $this->classId !== '') {
            abort_unless(
                ResultAccessService::classesForSitting(auth()->user(), $this->sitting)
                    ->contains('id', (int) $this->classId),
                403,
                'That class is not yours to mark in this exam.',
            );

            $this->classLocked = true;

            $subjects = $this->subjects;

            if ($subjects->count() === 1) {
                $this->subjectId = (string) $subjects->first()->id;
                $this->subjectLocked = true;
                $this->resolvePaper();
                $this->loadSheet();
            }

            return;
        }

        // Nothing to go on - back to the start.
        $this->redirectRoute('result.entry.exams', navigate: true);
    }

    public function updatedSubjectId(): void
    {
        if (! $this->classLocked) {
            $this->reset(['classId']);
        }

        $this->reset(['marks', 'remarks']);
        $this->resetValidation();

        // Sitting mode: which paper is being marked depends on the subject
        // just chosen.
        if ($this->sitting && $this->classId !== '') {
            $this->resolvePaper();
        }

        if ($this->classId !== '') {
            $this->loadSheet();
        }
    }

    /**
     * In sitting mode, pin `examinationId` to the one paper the sitting
     * holds for the chosen class and subject.
     */
    private function resolvePaper(): void
    {
        unset($this->examination);

        $this->examinationId = $this->sitting && $this->classId !== '' && $this->subjectId !== ''
            ? (string) (ResultAccessService::resolvePaper($this->sitting, (int) $this->classId, (int) $this->subjectId)?->id ?? '')
            : '';
    }

    public function updatedClassId(): void
    {
        $this->reset(['marks', 'remarks']);
        $this->resetValidation();

        if ($this->classId !== '') {
            $this->loadSheet();
        }
    }

    public function save(): void
    {
        abort_unless(auth()->user()->can('create result'), 403);

        $examination = $this->examination;
        abort_unless($examination, 403);

        $this->validate([
            'subjectId' => 'required|integer',
            'classId' => 'required|integer',
        ], attributes: ['subjectId' => 'subject', 'classId' => 'class']);

        $classId = (int) $this->classId;
        $subjectId = (int) $this->subjectId;

        // The dropdowns only ever offer this viewer's own classes/subjects,
        // but re-check server-side against a crafted request.
        if (auth()->user()->hasRole('Teacher')) {
            abort_unless(ResultAccessService::canGrade(auth()->user(), $classId, $subjectId), 403);
        }

        $institution = Institution::find($examination->institution_id);
        abort_unless($institution, 422, 'This examination has no institution.');

        $entered = collect($this->marks)
            ->reject(fn ($value) => $value === null || $value === '')
            ->mapWithKeys(fn ($value, $studentId) => [(int) $studentId => (float) $value]);

        if ($entered->isEmpty()) {
            Flux::toast(text: 'No marks were entered, so nothing was saved.', variant: 'warning');

            return;
        }

        $ceiling = (float) $examination->total_marks;
        $overMax = $entered->filter(fn ($value) => $ceiling > 0 && $value > $ceiling);

        if ($overMax->isNotEmpty()) {
            foreach ($overMax->keys() as $studentId) {
                $this->addError('marks.' . $studentId, "Over the paper's total of {$ceiling}.");
            }

            Flux::toast(text: $overMax->count() . ' mark(s) are over the total of ' . $ceiling . ', so nothing was saved.', variant: 'danger');

            return;
        }

        // Every id must belong to the class being marked - a crafted
        // request could otherwise carry a student from another class.
        $roll = $this->students->pluck('student_id')->map(fn ($id) => (int) $id);
        abort_unless($entered->keys()->every(fn ($id) => $roll->contains($id)), 403);

        // Amending a mark that's already recorded is an edit, not a
        // creation, and is gated as one.
        $amending = Result::where('examination_id', $examination->id)
            ->where('subject_id', $subjectId)
            ->whereIn('student_id', $entered->keys())
            ->exists();

        if ($amending) {
            abort_unless(auth()->user()->can('edit result') || auth()->user()->can('update result'), 403, 'Some of these students already have a mark for this subject, and you may not amend recorded results.');
        }

        $saved = SaveResult::saveSheet(
            $examination,
            $institution,
            $entered,
            collect($this->remarks)->map(fn ($value) => (string) $value)->all(),
            recordedBy: auth()->id(),
            classId: $classId,
            subjectId: $subjectId,
        );

        // Re-read so every row shows the grade the scale just worked out.
        unset($this->recorded);
        $this->loadSheet();

        Flux::toast(text: $saved . ' result(s) saved for ' . ($this->subjectName ?? $examination->title) . '.', variant: 'success');
    }

    #[Computed]
    public function examination(): ?Examination
    {
        if ($this->examinationId === '') {
            return null;
        }

        return ResultAccessService::selectableExaminations(auth()->user())
            ->firstWhere('id', (int) $this->examinationId);
    }

    /**
     * The sitting being marked, when the teacher came through the pickers.
     */
    #[Computed]
    public function sitting(): ?ExamSitting
    {
        if ($this->sittingId === '') {
            return null;
        }

        return ResultAccessService::selectableSittings(auth()->user())
            ->firstWhere('id', (int) $this->sittingId);
    }

    /**
     * The subjects this viewer may record for - narrowed to the ones this
     * sitting examines in the chosen class when in sitting mode.
     *
     * @return Collection<int, \Modules\Subject\Models\Subject>
     */
    #[Computed]
    public function subjects(): Collection
    {
        if ($this->sitting && $this->classId !== '') {
            return ResultAccessService::subjectsForSittingClass(auth()->user(), $this->sitting, (int) $this->classId);
        }

        return ResultAccessService::selectableSubjects(auth()->user());
    }

    /**
     * The classes this viewer may record for.
     *
     * @return Collection<int, \Modules\Classes\Models\SchoolClass>
     */
    #[Computed]
    public function classes(): Collection
    {
        return ResultAccessService::selectableClasses(auth()->user());
    }

    #[Computed]
    public function subjectName(): ?string
    {
        if ($this->subjectId === '') {
            return null;
        }

        return $this->subjects->firstWhere('id', (int) $this->subjectId)?->name
            ?? $this->examination?->subject?->name;
    }

    /**
     * The class being marked, roll-call order.
     *
     * @return Collection<int, StudentDetails>
     */
    #[Computed]
    public function students(): Collection
    {
        if ($this->classId === '') {
            return collect();
        }

        return StudentDetails::with('student')
            ->where('class_id', (int) $this->classId)
            ->get()
            ->sortBy(fn (StudentDetails $details) => [$details->admission_number, $details->student?->name])
            ->values();
    }

    /**
     * Marks already recorded for this sitting + subject, keyed by student id.
     *
     * @return Collection<int, Result>
     */
    #[Computed]
    public function recorded(): Collection
    {
        $examination = $this->examination;

        if (! $examination || $this->subjectId === '') {
            return collect();
        }

        return Result::where('examination_id', $examination->id)
            ->where('subject_id', (int) $this->subjectId)
            ->whereIn('student_id', $this->students->pluck('student_id'))
            ->get()
            ->keyBy('student_id');
    }

    /**
     * The mark at or above which this paper counts as a pass. Falls back to
     * a straight 50% when the examination carries no pass mark of its own.
     */
    #[Computed]
    public function passingMarks(): float
    {
        $examination = $this->examination;

        if (! $examination) {
            return 0.0;
        }

        $passing = (float) ($examination->passing_marks ?? 0);

        return $passing > 0 ? $passing : (float) $examination->total_marks * 0.5;
    }

    /**
     * Live class analytics from whatever is currently in the sheet - the
     * same figures that stand once Save is hit.
     *
     * @return array{appeared: int, passed: int, failed: int, mean: float}
     */
    #[Computed]
    public function analytics(): array
    {
        $rollIds = $this->students->pluck('student_id')->map(fn ($id) => (int) $id)->all();

        $scores = collect($this->marks)
            ->reject(fn ($value) => $value === null || $value === '')
            ->filter(fn ($value, $studentId) => in_array((int) $studentId, $rollIds, true))
            ->map(fn ($value) => (float) $value);

        $passing = $this->passingMarks;
        $passed = $scores->filter(fn ($score) => $score >= $passing)->count();

        return [
            'appeared' => $scores->count(),
            'passed' => $passed,
            'failed' => $scores->count() - $passed,
            'mean' => $scores->isNotEmpty() ? round((float) $scores->avg(), 1) : 0.0,
        ];
    }

    /**
     * This student's mark as a percentage of the paper's total, or null
     * when nothing has been entered for them yet.
     */
    public function rowPercentage(string|int|null $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $total = (float) ($this->examination?->total_marks ?? 0);

        return $total > 0 ? round(((float) $value / $total) * 100, 1) : null;
    }

    /**
     * "Pass" / "Fail" for this student's mark, or null when nothing has
     * been entered for them yet.
     */
    public function rowStatus(string|int|null $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value >= $this->passingMarks ? 'Pass' : 'Fail';
    }

    /**
     * Prefill the sheet with whatever has already been recorded, so the
     * screen always shows the current state of the paper.
     */
    private function loadSheet(): void
    {
        unset($this->students, $this->recorded);
        $this->marks = [];
        $this->remarks = [];

        foreach ($this->recorded as $studentId => $result) {
            $this->marks[$studentId] = (string) $result->marks_obtained;
            $this->remarks[$studentId] = (string) $result->remarks;
        }
    }
}; ?>

<div class="p-4">
    @php($sitting = $this->sitting)
    @php($heading = $this->examination?->title ?? $sitting?->displayName() ?? 'Enter Marks')
    @php($lockedClassName = $classLocked ? $this->classes->firstWhere('id', (int) $classId)?->name : null)
    @if ($this->examination || $sitting)
        <flux:card class="mb-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <flux:heading size="lg" class="mb-1">{{ $heading }}</flux:heading>
                    <flux:text class="text-zinc-500">
                        @if ($this->examination)
                            out of {{ $this->examination->total_marks }} ·
                            pass mark {{ number_format($this->passingMarks, 1) }}
                            @if ($this->examination->term)
                                · {{ $this->examination->term }} {{ $this->examination->academic_year }}
                            @endif
                        @elseif ($sitting)
                            {{ $sitting->periodLabel() }}@if ($lockedClassName) · {{ $lockedClassName }}@endif
                        @endif
                    </flux:text>
                </div>
                <flux:button
                    href="{{ $sitting ? route('result.entry.classes', ['sitting_id' => $sitting->id]) : route('result.entry.exams') }}"
                    wire:navigate size="sm" variant="subtle" icon="arrow-left">
                    {{ $sitting ? 'Change class' : 'Change exam' }}
                </flux:button>
            </div>

            <flux:text class="mt-4 mb-3 text-sm text-zinc-500">
                @if ($sitting)
                    Pick the subject you're marking for {{ $lockedClassName ?? 'this class' }}. The class roll loads below and you enter every mark in one go.
                @else
                    Choose the subject, then the class. The class roll loads below and you enter every mark in one go.
                @endif
            </flux:text>

            <div class="grid max-w-3xl grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:select label="Subject" wire:model.live="subjectId" :disabled="$subjectLocked">
                    <flux:select.option value="">Select a subject</flux:select.option>
                    @foreach ($this->subjects as $subject)
                        <flux:select.option value="{{ $subject->id }}">{{ $subject->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select label="Class" wire:model.live="classId"
                    :disabled="$classLocked || $subjectId === ''">
                    <flux:select.option value="">
                        {{ $subjectId === '' ? 'Select a subject first' : 'Select a class' }}
                    </flux:select.option>
                    @foreach ($this->classes as $schoolClass)
                        <flux:select.option value="{{ $schoolClass->id }}">{{ $schoolClass->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            @if ($sitting && $classLocked)
                <flux:text class="mt-3 text-xs text-amber-600 dark:text-amber-400">
                    Class chosen on the previous screen.{{ $subjectLocked ? ' Only one subject here is yours, so it is fixed.' : '' }}
                </flux:text>
            @elseif ($subjectLocked || $classLocked)
                <flux:text class="mt-3 text-xs text-amber-600 dark:text-amber-400">
                    This paper is pinned{{ $subjectLocked ? ' to a subject' : '' }}{{ $subjectLocked && $classLocked ? ' and' : '' }}{{ $classLocked ? ' to a class' : '' }},
                    so that choice is fixed.
                </flux:text>
            @endif
        </flux:card>

        @if ($this->subjectId !== '' && $this->classId !== '')
            <flux:card>
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <flux:heading size="lg">
                            {{ $this->subjectName }} —
                            {{ $this->classes->firstWhere('id', (int) $this->classId)?->name ?? 'Class' }}
                        </flux:heading>
                        <flux:text class="text-zinc-500">{{ $this->examination->title }}</flux:text>
                    </div>
                    <flux:badge color="zinc">{{ $this->students->count() }} student(s)</flux:badge>
                </div>

                @if ($this->students->isEmpty())
                    <flux:text class="text-zinc-500">
                        No students are enrolled in this class yet, so there's nothing to mark.
                    </flux:text>
                @else
                    @php($analytics = $this->analytics)
                    <form wire:submit="save">
                        <flux:table class="p-2">
                            <flux:table.columns>
                                <flux:table.column>Roll No.</flux:table.column>
                                <flux:table.column>Student Name</flux:table.column>
                                <flux:table.column>Marks Obtained</flux:table.column>
                                <flux:table.column>Status</flux:table.column>
                                <flux:table.column>Percentage</flux:table.column>
                                <flux:table.column>Grade</flux:table.column>
                                <flux:table.column>Notes / Remarks</flux:table.column>
                            </flux:table.columns>
                            <flux:table.rows>
                                @foreach ($this->students as $index => $details)
                                    @php($result = $this->recorded->get($details->student_id))
                                    @php($mark = $this->marks[$details->student_id] ?? null)
                                    @php($status = $this->rowStatus($mark))
                                    @php($percentage = $this->rowPercentage($mark))
                                    <flux:table.row :key="$details->student_id">
                                        <flux:table.cell>{{ $details->admission_number ?? $index + 1 }}</flux:table.cell>
                                        <flux:table.cell>{{ $details->student?->name ?? '—' }}</flux:table.cell>
                                        <flux:table.cell>
                                            <flux:input type="number" step="0.01" min="0"
                                                max="{{ $this->examination->total_marks }}" size="sm"
                                                wire:model.blur="marks.{{ $details->student_id }}" />
                                            <flux:error name="marks.{{ $details->student_id }}"
                                                class="text-xs text-red-500" />
                                        </flux:table.cell>
                                        <flux:table.cell>
                                            @if ($status === 'Pass')
                                                <flux:badge color="emerald" size="sm">Pass</flux:badge>
                                            @elseif ($status === 'Fail')
                                                <flux:badge color="rose" size="sm">Fail</flux:badge>
                                            @else
                                                <flux:text class="text-zinc-500">—</flux:text>
                                            @endif
                                        </flux:table.cell>
                                        <flux:table.cell>
                                            {{ $percentage !== null ? number_format($percentage, 1) . '%' : '—' }}
                                        </flux:table.cell>
                                        <flux:table.cell>
                                            @if ($result?->grade)
                                                <flux:badge color="emerald">{{ $result->grade }}</flux:badge>
                                            @else
                                                <flux:text class="text-zinc-500">—</flux:text>
                                            @endif
                                        </flux:table.cell>
                                        <flux:table.cell>
                                            <flux:input size="sm" wire:model="remarks.{{ $details->student_id }}" />
                                        </flux:table.cell>
                                    </flux:table.row>
                                @endforeach
                            </flux:table.rows>
                        </flux:table>

                        {{-- Auto-calculated class analytics. Live as marks are
                        entered, and authoritative once Save is hit. --}}
                        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-800/40">
                                <div class="text-xs font-medium text-zinc-500">🧮 Mean Score</div>
                                <div class="mt-1 text-lg font-bold text-zinc-900 dark:text-white">
                                    {{ number_format($analytics['mean'], 1) }}
                                </div>
                                <div class="text-[11px] text-zinc-400">Average of marks entered</div>
                            </div>
                            <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-800/40">
                                <div class="text-xs font-medium text-zinc-500">📈 Total Appeared</div>
                                <div class="mt-1 text-lg font-bold text-zinc-900 dark:text-white">
                                    {{ $analytics['appeared'] }}
                                </div>
                                <div class="text-[11px] text-zinc-400">of {{ $this->students->count() }} enrolled</div>
                            </div>
                            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 dark:border-emerald-800/40 dark:bg-emerald-900/10">
                                <div class="text-xs font-medium text-emerald-700 dark:text-emerald-400">✅ Total Passed</div>
                                <div class="mt-1 text-lg font-bold text-emerald-700 dark:text-emerald-300">
                                    {{ $analytics['passed'] }}
                                </div>
                                <div class="text-[11px] text-emerald-600/70">pass mark {{ number_format($this->passingMarks, 1) }}</div>
                            </div>
                            <div class="rounded-xl border border-rose-200 bg-rose-50 p-3 dark:border-rose-800/40 dark:bg-rose-900/10">
                                <div class="text-xs font-medium text-rose-700 dark:text-rose-400">❌ Total Failed</div>
                                <div class="mt-1 text-lg font-bold text-rose-700 dark:text-rose-300">
                                    {{ $analytics['failed'] }}
                                </div>
                                <div class="text-[11px] text-rose-600/70">below the pass mark</div>
                            </div>
                        </div>

                        <div class="mt-4 flex items-center justify-between gap-3">
                            <flux:text class="text-xs text-zinc-500">
                                Leave a row blank to skip it - blanks never overwrite a mark that's already recorded.
                                Grades are worked out from your school's grading scale for this class's curriculum.
                            </flux:text>
                            <flux:button type="submit" variant="primary" icon="check">
                                <span wire:loading.remove wire:target="save">Save Marks</span>
                                <span wire:loading wire:target="save">Saving…</span>
                            </flux:button>
                        </div>
                    </form>
                @endif
            </flux:card>
        @endif
    @endif
</div>
