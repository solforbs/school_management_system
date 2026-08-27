<?php

use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Classes\Models\SchoolClass;
use Modules\Examinations\Models\Examination;
use Modules\Examinations\Services\ExamTimetableBuilder;

/**
 * Build an examination timetable for a term's sitting, check it on screen,
 * then print it. Leaving the class unset prints every class, one per page.
 */
new #[Title('Exam Timetable')] class extends Component
{
    #[Url]
    public string $term = '';

    #[Url(as: 'year')]
    public string $academicYear = '';

    #[Url(as: 'type')]
    public string $examType = '';

    #[Url(as: 'class_id')]
    public string $classId = '';

    public function mount(ExamTimetableBuilder $builder): void
    {
        abort_unless(auth()->user()->can('view examination'), 403);

        // Open on the most recent year the school actually has papers in,
        // rather than an empty screen.
        if ($this->academicYear === '') {
            $this->academicYear = (string) ($builder->academicYears(auth()->user())->first() ?? '');
        }
    }

    /**
     * @return array<string, string>
     */
    public function filters(): array
    {
        return array_filter([
            'term' => $this->term,
            'academic_year' => $this->academicYear,
            'exam_type' => $this->examType,
            'class_id' => $this->classId,
        ], fn ($value) => $value !== '');
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function groups(): Collection
    {
        return app(ExamTimetableBuilder::class)->build(auth()->user(), $this->filters());
    }

    #[Computed]
    public function paperCount(): int
    {
        return $this->groups->sum(fn (array $group) => $group['examinations']->count());
    }

    /**
     * @return Collection<int, int>
     */
    #[Computed]
    public function academicYears(): Collection
    {
        return app(ExamTimetableBuilder::class)->academicYears(auth()->user());
    }

    /**
     * @return Collection<int, string>
     */
    #[Computed]
    public function terms(): Collection
    {
        return app(ExamTimetableBuilder::class)->terms(auth()->user());
    }

    /**
     * @return Collection<int, SchoolClass>
     */
    #[Computed]
    public function classes(): Collection
    {
        $query = SchoolClass::query();

        if (! isAdmin()) {
            $institutionId = auth()->user()->hasRole('Teacher')
                ? (auth()->user()->teacherUserDetails?->institution_id ?? 0)
                : (currentInstitution()?->id ?? 0);

            $query->where('institution_id', $institutionId);
        }

        return $query->orderBy('name')->get();
    }
}; ?>

<div class="p-4">
    <flux:card class="mb-4">
        <flux:heading size="lg" class="mb-2">Exam Timetable</flux:heading>
        <flux:text class="mb-4 text-zinc-500">
            Pick a term and sitting to build the timetable, then print it. Leave the class on "All classes" and
            every class prints on its own page.
        </flux:text>

        <div class="flex flex-wrap items-end gap-2">
            <flux:select wire:model.live="academicYear" label="Academic Year">
                <flux:select.option value="">All years</flux:select.option>
                @foreach ($this->academicYears as $year)
                    <flux:select.option value="{{ $year }}">{{ $year }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="term" label="Term">
                <flux:select.option value="">All terms</flux:select.option>
                @foreach ($this->terms as $termOption)
                    <flux:select.option value="{{ $termOption }}">{{ $termOption }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="examType" label="Sitting">
                <flux:select.option value="">All sittings</flux:select.option>
                @foreach (\Modules\Examinations\Models\Examination::EXAM_TYPES as $value => $label)
                    <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="classId" label="Class">
                <flux:select.option value="">All classes</flux:select.option>
                @foreach ($this->classes as $schoolClass)
                    <flux:select.option value="{{ $schoolClass->id }}">{{ $schoolClass->name }}</flux:select.option>
                @endforeach
            </flux:select>

            {{-- Generates the PDF on click, replacing the copy stored for
            these same filters. A file download, so no wire:navigate. --}}
            <flux:button href="{{ route('examinations.timetable.pdf', $this->filters()) }}" icon="arrow-down-tray"
                variant="primary" :disabled="$this->paperCount === 0">
                Download Timetable
            </flux:button>
        </div>

        @if ($this->paperCount > 0)
            <flux:text class="mt-3 block text-xs text-zinc-500">
                {{ $this->paperCount }} paper(s) across {{ $this->groups->count() }} class(es).
            </flux:text>
        @endif
    </flux:card>

    @forelse ($this->groups as $group)
        <flux:card class="mb-4" wire:key="class-{{ $group['class']?->id ?? $group['class_name'] }}"
            wire:loading.class="opacity-60">
            <flux:heading size="lg" class="mb-3">{{ $group['class_name'] }}</flux:heading>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Date</flux:table.column>
                    <flux:table.column>Time</flux:table.column>
                    <flux:table.column>Duration</flux:table.column>
                    <flux:table.column>Subject</flux:table.column>
                    <flux:table.column>Paper</flux:table.column>
                    <flux:table.column>Sitting</flux:table.column>
                    <flux:table.column>Marks</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($group['examinations'] as $examination)
                        <flux:table.row :key="$examination->id">
                            <flux:table.cell>
                                @if ($examination->exam_date)
                                    {{ $examination->exam_date->format('D, d M Y') }}
                                @else
                                    <flux:text class="text-zinc-500">Not scheduled</flux:text>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($examination->start_time && $examination->end_time)
                                    {{ $examination->start_time->format('H:i') }} –
                                    {{ $examination->end_time->format('H:i') }}
                                @elseif ($examination->start_time)
                                    From {{ $examination->start_time->format('H:i') }}
                                @else
                                    —
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>{{ $examination->durationLabel() ?? '—' }}</flux:table.cell>
                            <flux:table.cell>
                                {{ $examination->subject?->name ?? $examination->subject_name ?? '—' }}
                            </flux:table.cell>
                            <flux:table.cell>{{ $examination->title }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge :color="$examination->exam_type ? 'blue' : 'zinc'" size="sm">
                                    {{ $examination->examTypeLabel() }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>{{ $examination->total_marks }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @empty
        <flux:card class="py-10 text-center">
            <flux:text class="text-zinc-500">
                No examinations match this term and sitting yet.
                @can('create examination')
                    <a href="{{ route('examinations.create') }}" class="underline" wire:navigate>Schedule one</a>.
                @endcan
            </flux:text>
        </flux:card>
    @endforelse
</div>
