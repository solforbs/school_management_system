<?php

use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Classes\Models\SchoolClass;
use Modules\Lesson\Models\Lesson;
use Modules\Student\Models\StudentDetails;
use Modules\Timetable\Models\TimetableEntry;

/**
 * Lesson attendance: was each timetabled period actually taught?
 *
 * One class, one day, every period on screen - marked and saved in place,
 * which is how it's filled in at the end of a school day.
 */
new #[Title('Lesson Attendance')] class extends Component
{
    #[Url(as: 'class_id')]
    public string $classId = '';

    #[Url]
    public string $date = '';

    /** @var array<int, string> status keyed by timetable entry id */
    public array $statuses = [];

    /** @var array<int, string> remarks keyed by timetable entry id */
    public array $remarks = [];

    public function mount(): void
    {
        abort_unless(auth()->user()->can('view lesson'), 403);

        if ($this->date === '') {
            $this->date = Carbon::today()->toDateString();
        }

        if ($this->classId === '') {
            $this->classId = (string) ($this->classes->first()?->id ?? '');
        }

        $this->loadDay();
    }

    public function updatedClassId(): void
    {
        $this->loadDay();
    }

    public function updatedDate(): void
    {
        $this->loadDay();
    }

    public function save(): void
    {
        abort_unless(
            auth()->user()->can('create lesson') || auth()->user()->can('edit lesson'),
            403
        );

        $class = $this->selectedClass;
        abort_unless($class, 403);

        $entryIds = $this->rows->pluck('entry.id');

        $marked = collect($this->statuses)
            ->only($entryIds)
            ->reject(fn ($status) => blank($status));

        if ($marked->isEmpty()) {
            Flux::toast(text: 'Nothing marked yet, so nothing was saved.', variant: 'warning');

            return;
        }

        foreach ($marked as $entryId => $status) {
            abort_unless(in_array($status, ['attended', 'not_attended', 'recovered'], true), 422);

            Lesson::updateOrCreate(
                [
                    'timetable_entry_id' => $entryId,
                    'lesson_date' => $this->date,
                ],
                [
                    'institution_id' => $class->institution_id,
                    'class_id' => $class->id,
                    'status' => $status,
                    'remarks' => ($this->remarks[$entryId] ?? null) ?: null,
                    'marked_by' => auth()->id(),
                ]
            );
        }

        unset($this->lessons, $this->rows, $this->recent);

        Flux::toast(text: $marked->count().' period(s) saved.', variant: 'success');
    }

    /**
     * Classes this viewer may mark: a parent their children's, a student
     * their own, everyone else the school's.
     *
     * @return Collection<int, SchoolClass>
     */
    #[Computed]
    public function classes(): Collection
    {
        $user = auth()->user();

        if ($user->hasRole('Parent')) {
            $classIds = StudentDetails::where('parent_id', $user->id)
                ->pluck('class_id')->filter()->map(fn ($id) => (int) $id)->unique();

            return SchoolClass::whereIn('id', $classIds)->orderBy('name')->get();
        }

        if ($user->hasRole('Student')) {
            $classId = StudentDetails::where('student_id', $user->id)->value('class_id');

            return SchoolClass::whereIn('id', array_filter([(int) $classId]))->orderBy('name')->get();
        }

        $query = SchoolClass::query();

        if ($user->hasRole('Teacher')) {
            $query->where('institution_id', $user->teacherUserDetails?->institution_id ?? 0);
        } elseif (! isAdmin()) {
            $query->where('institution_id', currentInstitution()?->id ?? 0);
        }

        return $query->orderBy('name')->get();
    }

    #[Computed]
    public function selectedClass(): ?SchoolClass
    {
        return $this->classId === '' ? null : $this->classes->firstWhere('id', (int) $this->classId);
    }

    /**
     * Lessons already recorded for the chosen class and day, keyed by the
     * timetable entry they belong to.
     *
     * @return Collection<int, Lesson>
     */
    #[Computed]
    public function lessons(): Collection
    {
        $class = $this->selectedClass;

        if (! $class) {
            return collect();
        }

        return Lesson::where('class_id', $class->id)
            ->whereDate('lesson_date', $this->date)
            ->get()
            ->keyBy('timetable_entry_id');
    }

    /**
     * Every period timetabled for that class on that weekday, paired with
     * whatever has been recorded against it.
     *
     * @return Collection<int, array{entry: TimetableEntry, lesson: Lesson|null}>
     */
    #[Computed]
    public function rows(): Collection
    {
        $class = $this->selectedClass;

        if (! $class) {
            return collect();
        }

        $weekday = Carbon::parse($this->date)->format('l');

        return TimetableEntry::with('teacher')
            ->where('class_id', $class->id)
            ->where('day_of_week', $weekday)
            ->orderBy('start_time')
            ->get()
            ->map(fn (TimetableEntry $entry) => [
                'entry' => $entry,
                'lesson' => $this->lessons->get($entry->id),
            ]);
    }

    /**
     * @return Collection<int, Lesson>
     */
    #[Computed]
    public function recent(): Collection
    {
        $class = $this->selectedClass;

        if (! $class) {
            return collect();
        }

        return Lesson::with('timetableEntry')
            ->where('class_id', $class->id)
            ->orderByDesc('lesson_date')
            ->limit(10)
            ->get();
    }

    /**
     * Prefill the day's marks from whatever is already recorded.
     */
    private function loadDay(): void
    {
        unset($this->selectedClass, $this->lessons, $this->rows, $this->recent);

        $this->reset(['statuses', 'remarks']);

        foreach ($this->rows as $row) {
            if ($row['lesson']) {
                $this->statuses[$row['entry']->id] = $row['lesson']->status;
                $this->remarks[$row['entry']->id] = (string) $row['lesson']->remarks;
            }
        }
    }
}; ?>

<div class="p-4">
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div class="flex flex-wrap items-end gap-2">
            <flux:select wire:model.live="classId" label="Class">
                <flux:select.option value="">Select a class&hellip;</flux:select.option>
                @foreach ($this->classes as $schoolClass)
                    <flux:select.option value="{{ $schoolClass->id }}">{{ $schoolClass->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input type="date" wire:model.live="date" label="Date" />
        </div>

        @can('export lesson')
            <flux:button href="{{ route('lesson.reports.index') }}" icon="document-chart-bar" variant="ghost"
                wire:navigate>Lesson Reports</flux:button>
        @endcan
    </div>

    @if (! $this->selectedClass)
        <flux:card class="py-10 text-center">
            <flux:text class="text-zinc-500">Select a class above to mark its lessons.</flux:text>
        </flux:card>
    @else
        <flux:card class="mb-6" wire:loading.class="opacity-60">
            <div class="mb-4">
                <flux:heading size="lg">
                    {{ $this->selectedClass->name }} &mdash;
                    {{ \Illuminate\Support\Carbon::parse($date)->format('l, d M Y') }}
                </flux:heading>
            </div>

            @if ($this->rows->isEmpty())
                <flux:text class="text-zinc-500">
                    Nothing is timetabled for this class on that day.
                </flux:text>
            @else
                <form wire:submit="save">
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>Time</flux:table.column>
                            <flux:table.column>Subject</flux:table.column>
                            <flux:table.column>Teacher</flux:table.column>
                            <flux:table.column>Status</flux:table.column>
                            <flux:table.column>Remarks</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($this->rows as $row)
                                @php($entry = $row['entry'])
                                <flux:table.row :key="$entry->id">
                                    <flux:table.cell class="whitespace-nowrap">
                                        {{ $entry->start_time?->format('H:i') }} –
                                        {{ $entry->end_time?->format('H:i') }}
                                    </flux:table.cell>
                                    <flux:table.cell>{{ $entry->subject }}</flux:table.cell>
                                    <flux:table.cell>{{ $entry->teacher?->name ?? '—' }}</flux:table.cell>
                                    <flux:table.cell>
                                        <flux:select size="sm" wire:model="statuses.{{ $entry->id }}">
                                            <flux:select.option value="">Not marked</flux:select.option>
                                            <flux:select.option value="attended">Attended</flux:select.option>
                                            <flux:select.option value="not_attended">Not attended</flux:select.option>
                                            <flux:select.option value="recovered">Recovered</flux:select.option>
                                        </flux:select>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <flux:input size="sm" wire:model="remarks.{{ $entry->id }}" />
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>

                    @canany(['create lesson', 'edit lesson'])
                        <div class="mt-4 flex items-center justify-between gap-3">
                            <flux:text class="text-xs text-zinc-500">
                                Leave a period unmarked to skip it - saving never clears a mark already recorded.
                            </flux:text>
                            <flux:button type="submit" variant="primary" icon="check">
                                <span wire:loading.remove wire:target="save">Save Attendance</span>
                                <span wire:loading wire:target="save">Saving…</span>
                            </flux:button>
                        </div>
                    @endcanany
                </form>
            @endif
        </flux:card>

        <flux:card>
            <flux:heading size="lg" class="mb-4">Recently Marked</flux:heading>

            @if ($this->recent->isEmpty())
                <flux:text class="text-zinc-500">Nothing marked for this class yet.</flux:text>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Date</flux:table.column>
                        <flux:table.column>Subject</flux:table.column>
                        <flux:table.column>Status</flux:table.column>
                        <flux:table.column>Remarks</flux:table.column>
                        <flux:table.column>Actions</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->recent as $lesson)
                            <flux:table.row :key="'recent-'.$lesson->id">
                                <flux:table.cell>{{ $lesson->lesson_date?->format('d M Y') }}</flux:table.cell>
                                <flux:table.cell>{{ $lesson->timetableEntry?->subject ?? '—' }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge
                                        :color="match ($lesson->status) {
                                            'attended' => 'emerald',
                                            'not_attended' => 'red',
                                            default => 'amber',
                                        }">
                                        {{ ucfirst(str_replace('_', ' ', $lesson->status)) }}
                                    </flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>{{ $lesson->remarks ?? '—' }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:button href="{{ route('lesson.show', $lesson->id) }}" icon="eye" size="sm"
                                        variant="ghost" wire:navigate>view</flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>
    @endif
</div>
