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
 */
new #[Title('Enter Marks')] class extends Component
{
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
                $this->addError('marks.'.$studentId, "Over the paper's total of {$ceiling}.");
            }

            Flux::toast(
                text: $overMax->count().' mark(s) are over the total of '.$ceiling.', so nothing was saved.',
                variant: 'danger',
            );

            return;
        }

        // Every id must belong to the class being marked - a hand-crafted
        // request could otherwise carry a student from another class.
        $roll = $this->students->pluck('student_id')->map(fn ($id) => (int) $id);
        abort_unless($entered->keys()->every(fn ($id) => $roll->contains($id)), 403);

        // Amending a mark that's already recorded is an edit, not a
        // creation, and is gated as one.
        $amending = Result::where('examination_id', $examination->id)
            ->whereIn('student_id', $entered->keys())
            ->exists();

        if ($amending) {
            abort_unless(
                auth()->user()->can('edit result') || auth()->user()->can('update result'),
                403,
                'Some of these students already have a mark for this examination, and you may not amend recorded results.'
            );
        }

        $saved = SaveResult::saveSheet(
            $examination,
            $institution,
            $entered,
            collect($this->remarks)->map(fn ($value) => (string) $value)->all(),
            recordedBy: auth()->id(),
        );

        // Re-read so every row shows the grade the scale just worked out.
        unset($this->recorded);
        $this->loadSheet();

        Flux::toast(
            text: $saved.' result(s) saved for '.($examination->subject?->name ?? $examination->title).'.',
            variant: 'success',
        );
    }

    #[Computed]
    public function examinations(): Collection
    {
        return ResultAccessService::selectableExaminations(auth()->user())
            ->filter(fn (Examination $examination) => $examination->class_id !== null)
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

        if (! $examination?->class_id) {
            return collect();
        }

        return StudentDetails::with('student')
            ->where('class_id', $examination->class_id)
            ->get()
            ->sortBy(fn (StudentDetails $details) => [$details->admission_number, $details->student?->name])
            ->values();
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

        if (! $examination) {
            return collect();
        }

        return Result::where('examination_id', $examination->id)
            ->whereIn('student_id', $this->students->pluck('student_id'))
            ->get()
            ->keyBy('student_id');
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
            Pick an examination to mark the whole class in one go. You'll only see the examinations for subjects
            you teach - and, if you're a class teacher, every subject in your own class.
        </flux:text>

        <flux:select label="Examination" wire:model.live="examinationId" class="max-w-2xl">
            <flux:select.option value="">Select an examination</flux:select.option>
            @foreach ($this->examinations as $option)
                <flux:select.option value="{{ $option->id }}">
                    {{ $option->schoolClass?->name ?? $option->class_name }} —
                    {{ $option->subject?->name ?? $option->subject_name }} —
                    {{ $option->title }}
                    @if ($option->exam_date)
                        ({{ $option->exam_date->format('d M Y') }})
                    @endif
                </flux:select.option>
            @endforeach
        </flux:select>
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
                <form wire:submit="save">
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>#</flux:table.column>
                            <flux:table.column>Admission No.</flux:table.column>
                            <flux:table.column>Student</flux:table.column>
                            <flux:table.column>Marks (out of {{ $this->examination->total_marks }})
                            </flux:table.column>
                            <flux:table.column>Grade</flux:table.column>
                            <flux:table.column>Remarks</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($this->students as $index => $details)
                                @php($result = $this->recorded->get($details->student_id))
                                <flux:table.row :key="$details->student_id">
                                    <flux:table.cell>{{ $index + 1 }}</flux:table.cell>
                                    <flux:table.cell>{{ $details->admission_number ?? '—' }}</flux:table.cell>
                                    <flux:table.cell>{{ $details->student?->name ?? '—' }}</flux:table.cell>
                                    <flux:table.cell>
                                        <flux:input type="number" step="0.01" min="0"
                                            max="{{ $this->examination->total_marks }}" size="sm"
                                            wire:model="marks.{{ $details->student_id }}" />
                                        <flux:error name="marks.{{ $details->student_id }}"
                                            class="text-xs text-red-500" />
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
