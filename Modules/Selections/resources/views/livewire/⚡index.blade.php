<?php

use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Institution\Models\Institution;
use Modules\Selections\Models\SubjectSelection;
use Modules\Student\Models\StudentDetails;
use Modules\Subject\Models\Subject;

/**
 * A student's own subject picker. Self-service, so it's gated by role
 * rather than a permission - a Director manages subjects elsewhere.
 */
new #[Title('My Subjects')] class extends Component
{
    /** @var array<int, string> */
    public array $selected = [];

    public function mount(): void
    {
        abort_unless(auth()->user()->hasRole('Student'), 403);

        $this->selected = SubjectSelection::where('student_id', auth()->id())
            ->pluck('subject_id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    public function save(): void
    {
        abort_unless(auth()->user()->hasRole('Student'), 403);

        $studentDetails = $this->studentDetails;
        abort_unless($studentDetails, 404);

        $institution = Institution::findOrFail($studentDetails->institution_id);

        $count = count($this->selected);

        if ($count < $institution->min_electives) {
            Flux::toast(text: "Please select at least {$institution->min_electives} subject(s).", variant: 'danger');

            return;
        }

        if ($institution->max_electives !== null && $count > $institution->max_electives) {
            Flux::toast(text: "Please select at most {$institution->max_electives} subject(s).", variant: 'danger');

            return;
        }

        // Only this institution's active electives may be picked - anything
        // else arriving here didn't come from the list on screen.
        $validSubjectIds = Subject::where('institution_id', $institution->id)
            ->where('is_compulsory', false)
            ->where('is_active', true)
            ->whereIn('id', $this->selected)
            ->pluck('id')
            ->all();

        if (count($validSubjectIds) !== $count) {
            Flux::toast(text: 'One or more selected subjects are not available for your institution.', variant: 'danger');

            return;
        }

        DB::transaction(function () use ($studentDetails, $institution, $validSubjectIds) {
            SubjectSelection::where('student_id', auth()->id())->delete();

            foreach ($validSubjectIds as $subjectId) {
                SubjectSelection::create([
                    'institution_id' => $institution->id,
                    'student_id' => auth()->id(),
                    'class_id' => (int) $studentDetails->class_id,
                    'subject_id' => $subjectId,
                ]);
            }
        });

        Flux::toast(text: 'Your subjects have been saved.', variant: 'success');
    }

    #[Computed]
    public function studentDetails(): ?StudentDetails
    {
        return StudentDetails::where('student_id', auth()->id())->first();
    }

    #[Computed]
    public function institution(): ?Institution
    {
        return $this->studentDetails
            ? Institution::find($this->studentDetails->institution_id)
            : null;
    }

    /**
     * @return Collection<int, Subject>
     */
    #[Computed]
    public function compulsorySubjects(): Collection
    {
        return $this->subjectsWhere(true);
    }

    /**
     * @return Collection<int, Subject>
     */
    #[Computed]
    public function electiveSubjects(): Collection
    {
        return $this->subjectsWhere(false);
    }

    private function subjectsWhere(bool $compulsory): Collection
    {
        if (! $this->studentDetails) {
            return collect();
        }

        return Subject::where('institution_id', $this->studentDetails->institution_id)
            ->where('is_compulsory', $compulsory)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }
}; ?>

<div class="p-4">
    @if (! $this->studentDetails)
        <flux:card class="py-10 text-center">
            <flux:text class="text-zinc-500">
                You are not enrolled at a school yet. Contact your school administrator.
            </flux:text>
        </flux:card>
    @else
        <flux:card class="mb-6">
            <flux:heading size="lg" class="mb-2">Compulsory Subjects</flux:heading>
            <flux:text class="mb-4 text-zinc-500">Everyone takes these - there's nothing to choose here.</flux:text>

            @if ($this->compulsorySubjects->isEmpty())
                <flux:text class="text-zinc-500">No compulsory subjects have been set up yet.</flux:text>
            @else
                <div class="flex flex-wrap gap-2">
                    @foreach ($this->compulsorySubjects as $subject)
                        <flux:badge color="amber">{{ $subject->name }}</flux:badge>
                    @endforeach
                </div>
            @endif
        </flux:card>

        <flux:card>
            <flux:heading size="lg" class="mb-2">Elective Subjects</flux:heading>
            <flux:text class="mb-4 text-zinc-500">
                Choose your favorite subjects &mdash;
                @if ($this->institution?->max_electives)
                    between {{ $this->institution->min_electives }} and {{ $this->institution->max_electives }}.
                @else
                    at least {{ $this->institution?->min_electives }}.
                @endif
                <span class="font-medium">{{ count($selected) }} selected.</span>
            </flux:text>

            @if ($this->electiveSubjects->isEmpty())
                <flux:text class="text-zinc-500">No elective subjects are available yet.</flux:text>
            @else
                <form wire:submit="save">
                    <div class="mb-4 grid grid-cols-1 gap-2 md:grid-cols-2">
                        @foreach ($this->electiveSubjects as $subject)
                            <flux:checkbox wire:model.live="selected" value="{{ $subject->id }}"
                                wire:key="subject-{{ $subject->id }}" label="{{ $subject->name }}" />
                        @endforeach
                    </div>

                    <div class="flex justify-end">
                        <flux:button type="submit" variant="primary">
                            <span wire:loading.remove wire:target="save">Save My Subjects</span>
                            <span wire:loading wire:target="save">Saving…</span>
                        </flux:button>
                    </div>
                </form>
            @endif
        </flux:card>
    @endif
</div>
