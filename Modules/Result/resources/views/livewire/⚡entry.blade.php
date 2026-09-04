<?php

use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Examinations\Models\Examination;
use Modules\Institution\Models\Institution;
use Modules\Result\Actions\SaveResult;
use Modules\Result\Models\Result;
use Modules\Result\Services\ResultAccessService;
use Modules\Student\Models\StudentDetails;

/**
 * The marks sheet: one examination, every student in the class, one screen.
 *
 * This is how a subject teacher actually works - the Form 2 maths teacher
 * marks the whole class's papers in one sitting and enters them in one go,
 * rather than opening the single-result form forty times. A class teacher
 * uses the same screen for any subject in their own class. Saving happens
 * in place: the sheet stays open with the new grades filled in.
 *
 * The teacher gets there by picking one of their classes, then one of the
 * subjects they teach, then the paper - so the examination list is never a
 * wall of every exam in the school.
 */
new #[Title('Enter Marks')] class extends Component {
    #[Url(as: 'class_id')]
    public string $classId = '';

    #[Url(as: 'subject_id')]
    public string $subjectId = '';

    #[Url(as: 'examination_id')]
    public string $examinationId = '';

    /** @var array<int, string> marks keyed by student id */
    public array $marks = [];

    /** @var array<int, string> remarks keyed by student id */
    public array $remarks = [];

    public function mount(): void
    {
        abort_unless(auth()->user()->can('create result'), 403);

        if ($this->examinationId !== '') {
            $this->assertSelectable();
            $this->loadSheet();
        }
    }

    public function updatedClassId(): void
    {
        $this->reset(['subjectId', 'examinationId', 'marks', 'remarks']);
        $this->resetValidation();
    }

    public function updatedSubjectId(): void
    {
        $this->reset(['examinationId', 'marks', 'remarks']);
        $this->resetValidation();
    }

    public function updatedExaminationId(): void
    {
        $this->reset(['marks', 'remarks']);
        $this->resetValidation();

        if ($this->examinationId !== '') {
            $this->assertSelectable();
            $this->loadSheet();
        }
    }

    /**
     * The picker only ever offers examinations this viewer may grade, so an
     * id arriving from anywhere else - a hand-typed URL, a crafted request -
     * is refused rather than quietly ignored.
     */
    private function assertSelectable(): void
    {
        unset($this->examination);

        abort_unless($this->examination, 403);
    }

    public function save(): void
    {
        abort_unless(auth()->user()->can('create result'), 403);

        $examination = $this->examination;

        // Not merely "not found": the picker only ever offers what this
        // viewer may grade, so anything else is out of their reach.
        abort_unless($examination, 403);

        $institution = Institution::find($examination->institution_id);
        abort_unless($institution, 422, 'This examination has no institution.');

        $entered = collect($this->marks)->reject(fn($value) => $value === null || $value === '')->mapWithKeys(fn($value, $studentId) => [(int) $studentId => (float) $value]);

        if ($entered->isEmpty()) {
            Flux::toast(text: 'No marks were entered, so nothing was saved.', variant: 'warning');

            return;
        }

        $ceiling = (float) $examination->total_marks;
        $overMax = $entered->filter(fn($value) => $ceiling > 0 && $value > $ceiling);

        if ($overMax->isNotEmpty()) {
            foreach ($overMax->keys() as $studentId) {
                $this->addError('marks.' . $studentId, "Over the paper's total of {$ceiling}.");
            }

            Flux::toast(text: $overMax->count() . ' mark(s) are over the total of ' . $ceiling . ', so nothing was saved.', variant: 'danger');

            return;
        }

        // Every id must belong to the class being marked - a hand-crafted
        // request could otherwise carry a student from another class.
        $roll = $this->students->pluck('student_id')->map(fn($id) => (int) $id);
        abort_unless($entered->keys()->every(fn($id) => $roll->contains($id)), 403);

        // Amending a mark that's already recorded is an edit, not a
        // creation, and is gated as one.
        $amending = Result::where('examination_id', $examination->id)->whereIn('student_id', $entered->keys())->exists();

        if ($amending) {
            abort_unless(auth()->user()->can('edit result') || auth()->user()->can('update result'), 403, 'Some of these students already have a mark for this examination, and you may not amend recorded results.');
        }

        $saved = SaveResult::saveSheet($examination, $institution, $entered, collect($this->remarks)->map(fn($value) => (string) $value)->all(), recordedBy: auth()->id());

        // Re-read so every row shows the grade the scale just worked out.
        unset($this->recorded);
        $this->loadSheet();

        Flux::toast(text: $saved . ' result(s) saved for ' . ($examination->subject?->name ?? $examination->title) . '.', variant: 'success');
    }

    /**
     * The classes this teacher may enter marks in.
     *
     * @return Collection<int, \Modules\Classes\Models\SchoolClass>
     */
    #[Computed]
    public function classes(): Collection
    {
        return ResultAccessService::selectableClasses(auth()->user());
    }

    /**
     * The subjects this teacher owns marks for, narrowed to the picked
     * class once there is one.
     *
     * @return Collection<int, \Modules\Subject\Models\Subject>
     */
    #[Computed]
    public function subjects(): Collection
    {
        return ResultAccessService::selectableSubjects(
            auth()->user(),
            $this->classId !== '' ? (int) $this->classId : null,
        );
    }

    /**
     * @return Collection<int, Examination>
     */
    #[Computed]
    public function examinations(): Collection
    {
        if ($this->classId === '' || $this->subjectId === '') {
            return collect();
        }

        return ResultAccessService::selectableExaminations(
            auth()->user(),
            (int) $this->classId,
            (int) $this->subjectId,
        )
            ->filter(fn(Examination $examination) => $examination->class_id !== null)
            ->values();
    }

    #[Computed]
    public function examination(): ?Examination
    {
        if ($this->examinationId === '') {
            return null;
        }

        return $this->examinations->firstWhere('id', (int) $this->examinationId);
    }

    /**
     * The class being examined, roll-call order.
     *
     * @return Collection<int, StudentDetails>
     */
    #[Computed]
    public function students(): Collection
    {
        $examination = $this->examination;

        if (!$examination?->class_id) {
            return collect();
        }

        return StudentDetails::with('student')->where('class_id', $examination->class_id)->get()->sortBy(fn(StudentDetails $details) => [$details->admission_number, $details->student?->name])->values();
    }

    /**
     * Marks already recorded for this paper, keyed by student id.
     *
     * @return Collection<int, Result>
     */
    #[Computed]
    public function recorded(): Collection
    {
        $examination = $this->examination;

        if (!$examination) {
            return collect();
        }

        return Result::where('examination_id', $examination->id)
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

        if (!$examination) {
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
        $rollIds = $this->students->pluck('student_id')->map(fn($id) => (int) $id)->all();

        $scores = collect($this->marks)
            ->reject(fn($value) => $value === null || $value === '')
            ->filter(fn($value, $studentId) => in_array((int) $studentId, $rollIds, true))
            ->map(fn($value) => (float) $value);

        $passing = $this->passingMarks;
        $passed = $scores->filter(fn($score) => $score >= $passing)->count();

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
        unset($this->examination, $this->students, $this->recorded);

        foreach ($this->recorded as $studentId => $result) {
            $this->marks[$studentId] = (string) $result->marks_obtained;
            $this->remarks[$studentId] = (string) $result->remarks;
        }
    }
}; ?>

<div class="p-4">
    <flux:card class="mb-4">
        <flux:heading size="lg" class="mb-2">Enter Marks</flux:heading>
        <flux:text class="mb-4 text-zinc-500">
            Pick one of your classes, then one of your subjects, then the paper - and mark the whole class in
            one go. You'll only see the classes and subjects you've been assigned; a class teacher also sees
            every subject in their own class.
        </flux:text>

        <div class="grid max-w-4xl grid-cols-1 gap-4 md:grid-cols-3">
            <flux:select label="Class" wire:model.live="classId">
                <flux:select.option value="">Select a class</flux:select.option>
                @foreach ($this->classes as $schoolClass)
                    <flux:select.option value="{{ $schoolClass->id }}">{{ $schoolClass->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select label="Subject" wire:model.live="subjectId" :disabled="$classId === ''">
                <flux:select.option value="">
                    {{ $classId === '' ? 'Select a class first' : 'Select a subject' }}
                </flux:select.option>
                @foreach ($this->subjects as $subject)
                    <flux:select.option value="{{ $subject->id }}">{{ $subject->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select label="Examination" wire:model.live="examinationId" :disabled="$subjectId === ''">
                <flux:select.option value="">
                    {{ $subjectId === '' ? 'Select a subject first' : 'Select an examination' }}
                </flux:select.option>
                @foreach ($this->examinations as $option)
                    <flux:select.option value="{{ $option->id }}">
                        {{ $option->title }}
                        @if ($option->exam_date)
                            ({{ $option->exam_date->format('d M Y') }})
                        @endif
                    </flux:select.option>
                @endforeach
            </flux:select>
        </div>

        @if ($classId !== '' && $subjectId !== '' && $this->examinations->isEmpty())
            <flux:text class="mt-3 text-sm text-amber-600 dark:text-amber-400">
                No examinations have been set for this subject in this class yet.
            </flux:text>
        @endif
    </flux:card>

    @if ($this->examination)
        <flux:card>
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <flux:heading size="lg">
                        {{ $this->examination->subject?->name ?? $this->examination->subject_name }} —
                        {{ $this->examination->schoolClass?->name ?? $this->examination->class_name }}
                    </flux:heading>
                    <flux:text class="text-zinc-500">
                        {{ $this->examination->title }} · out of {{ $this->examination->total_marks }}
                        @if ($this->examination->term)
                            · {{ $this->examination->term }} {{ $this->examination->academic_year }}
                        @endif
                    </flux:text>
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
</div>
