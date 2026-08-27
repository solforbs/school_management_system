<?php

use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Classes\Models\SchoolClass;
use Modules\Subject\Models\Subject;
use Modules\Subject\Models\SubjectTeacher;

/**
 * Who teaches what, to whom. A Director assigns a teacher to a subject in a
 * class here, and that assignment is what lets the teacher enter results
 * for it.
 */
new #[Title('Subject Teachers')] class extends Component
{
    /** Fields of the assignment being built. */
    public string $classId = '';

    public string $subjectId = '';

    /** @var array<int, string> */
    public array $selected = [];

    public string $teacherSearch = '';

    #[Url(as: 'class_id')]
    public string $filterClassId = '';

    #[Url(as: 'teacher_id')]
    public string $filterTeacherId = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('view subject'), 403);
    }

    public function toggle(string $teacherId): void
    {
        $this->selected = in_array($teacherId, $this->selected, true)
            ? array_values(array_diff($this->selected, [$teacherId]))
            : [...$this->selected, $teacherId];
    }

    public function selectVisible(): void
    {
        $this->selected = $this->visibleTeachers->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->merge($this->selected)
            ->unique()
            ->values()
            ->all();
    }

    public function clearSelection(): void
    {
        $this->selected = [];
    }

    public function assign(): void
    {
        abort_unless(auth()->user()->can('edit subject'), 403);

        $this->validate([
            'classId' => 'required|exists:classes,id',
            'subjectId' => 'required|exists:subjects,id',
            // Several teachers can be handed the same subject in one go -
            // co-teaching a class is normal, and doing it one row at a time
            // is not.
            'selected' => 'required|array|min:1',
        ], attributes: [
            'classId' => 'class',
            'subjectId' => 'subject',
            'selected' => 'teachers',
        ]);

        $institutionId = $this->institutionId();

        // 'exists' alone would let a crafted request wire up another
        // school's class, subject or staff.
        $schoolClass = SchoolClass::findOrFail($this->classId);
        $subject = Subject::findOrFail($this->subjectId);

        abort_unless($schoolClass->institution_id === $institutionId, 403);
        abort_unless($subject->institution_id === $institutionId, 403);

        $eligible = $this->teachers->pluck('id');
        $assigned = 0;

        foreach ($this->selected as $teacherId) {
            abort_unless($eligible->contains((int) $teacherId), 403);

            $assignment = SubjectTeacher::firstOrCreate([
                'class_id' => $schoolClass->id,
                'subject_id' => $subject->id,
                'teacher_id' => (int) $teacherId,
            ], [
                'institution_id' => $institutionId,
                'assigned_by' => auth()->id(),
            ]);

            if ($assignment->wasRecentlyCreated) {
                $assigned++;
            }
        }

        $this->reset(['selected', 'teacherSearch']);
        unset($this->assignments, $this->alreadyAssigned, $this->teacherLoad);

        Flux::toast(
            text: $assigned > 0
                ? $assigned.' teacher(s) assigned to '.$subject->name.' for '.$schoolClass->name.'.'
                : 'Those teachers already teach '.$subject->name.' in '.$schoolClass->name.'.',
            variant: $assigned > 0 ? 'success' : 'warning',
        );
    }

    public function remove(int $id): void
    {
        abort_unless(auth()->user()->can('edit subject'), 403);

        $this->scoped()->findOrFail($id)->delete();

        unset($this->assignments, $this->alreadyAssigned, $this->teacherLoad);

        Flux::toast(text: 'Assignment removed.', variant: 'success');
    }

    /**
     * Whether this teacher already teaches the chosen class/subject pair -
     * said on the card as soon as both are picked, rather than after the
     * assignment silently does nothing.
     */
    public function isAssigned(string $teacherId): bool
    {
        if ($this->classId === '' || $this->subjectId === '') {
            return false;
        }

        return in_array($teacherId, $this->alreadyAssigned[$this->classId.'-'.$this->subjectId] ?? [], true);
    }

    #[Computed]
    public function assignments(): Collection
    {
        $query = SubjectTeacher::with(['schoolClass', 'subject', 'teacher']);
        $this->scopeToViewer($query);

        return $query
            ->when($this->filterClassId !== '', fn ($q) => $q->where('class_id', $this->filterClassId))
            ->when($this->filterTeacherId !== '', fn ($q) => $q->where('teacher_id', $this->filterTeacherId))
            ->get()
            ->sortBy(fn ($assignment) => [$assignment->schoolClass?->name, $assignment->subject?->name])
            ->values();
    }

    #[Computed]
    public function grouped(): Collection
    {
        return $this->assignments->groupBy(fn ($assignment) => $assignment->schoolClass?->name ?? 'Unassigned class');
    }

    #[Computed]
    public function classes(): Collection
    {
        $query = SchoolClass::query();

        if (! isAdmin()) {
            $query->where('institution_id', currentInstitution()?->id ?? 0);
        }

        return $query->orderBy('name')->get();
    }

    #[Computed]
    public function subjects(): Collection
    {
        $query = Subject::where('is_active', true);

        if (! isAdmin()) {
            $query->where('institution_id', currentInstitution()?->id ?? 0);
        }

        return $query->orderBy('name')->get();
    }

    #[Computed]
    public function teachers(): Collection
    {
        $query = User::role('Teacher')->with('teacherUserDetails');

        if (! isAdmin()) {
            $institutionId = currentInstitution()?->id ?? 0;
            $query->whereHas('teacherUserDetails', fn ($q) => $q->where('institution_id', $institutionId));
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Teachers matching the current search, by name or by their
     * department/staff number.
     */
    #[Computed]
    public function visibleTeachers(): Collection
    {
        $needle = mb_strtolower(trim($this->teacherSearch));

        if ($needle === '') {
            return $this->teachers;
        }

        return $this->teachers->filter(function (User $teacher) use ($needle) {
            $haystack = mb_strtolower(implode(' ', [
                $teacher->name,
                $teacher->teacherUserDetails?->department,
                $teacher->teacherUserDetails?->employee_number,
            ]));

            return str_contains($haystack, $needle);
        })->values();
    }

    /**
     * How many subjects each teacher already carries.
     *
     * @return Collection<int, int>
     */
    #[Computed]
    public function teacherLoad(): Collection
    {
        return SubjectTeacher::query()
            ->when(! isAdmin(), fn ($query) => $query->where('institution_id', currentInstitution()?->id ?? 0))
            ->get(['teacher_id'])
            ->countBy('teacher_id');
    }

    /**
     * Who already teaches each (class, subject), keyed "classId-subjectId".
     *
     * @return array<string, array<int, string>>
     */
    #[Computed]
    public function alreadyAssigned(): array
    {
        if (! auth()->user()->can('edit subject')) {
            return [];
        }

        return SubjectTeacher::query()
            ->when(! isAdmin(), fn ($query) => $query->where('institution_id', currentInstitution()?->id ?? 0))
            ->get(['class_id', 'subject_id', 'teacher_id'])
            ->groupBy(fn ($assignment) => $assignment->class_id.'-'.$assignment->subject_id)
            ->map(fn ($group) => $group->pluck('teacher_id')->map(fn ($id) => (string) $id)->values()->all())
            ->all();
    }

    private function institutionId(): int
    {
        $institutionId = currentInstitution()?->id;

        abort_unless($institutionId, 422, 'No institution selected.');

        return $institutionId;
    }

    private function scopeToViewer($query): void
    {
        $user = auth()->user();

        if (isAdmin()) {
            return;
        }

        if ($user->hasRole('Teacher')) {
            // A teacher sees their own assignments - what they've been put
            // down to teach - and not the whole staff's.
            $query->where('institution_id', $user->teacherUserDetails?->institution_id ?? 0)
                ->where('teacher_id', $user->id);

            return;
        }

        $query->where('institution_id', currentInstitution()?->id ?? 0);
    }

    private function scoped()
    {
        $query = SubjectTeacher::query();
        $this->scopeToViewer($query);

        return $query;
    }
}; ?>

<div class="p-4">
    @can('edit subject')
        <flux:card class="mb-6">
            <flux:heading size="lg" class="mb-2">Assign a Subject Teacher</flux:heading>
            <flux:text class="mb-6 text-zinc-500">
                A teacher can enter results for the subjects they're assigned here - and only those. A class
                teacher can enter results for every subject in their own class.
            </flux:text>

            <form wire:submit="assign">
                <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <flux:select label="Class" wire:model.live="classId">
                        <flux:select.option value="">Select Class</flux:select.option>
                        @foreach ($this->classes as $schoolClass)
                            <flux:select.option value="{{ $schoolClass->id }}">{{ $schoolClass->name }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select label="Subject" wire:model.live="subjectId">
                        <flux:select.option value="">Select Subject</flux:select.option>
                        @foreach ($this->subjects as $subject)
                            <flux:select.option value="{{ $subject->id }}">
                                {{ $subject->name }}{{ $subject->code ? ' (' . $subject->code . ')' : '' }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <div class="rounded-xl border border-zinc-200 dark:border-zinc-700">
                    <div
                        class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                        <div class="flex items-center gap-2">
                            <flux:heading>Teachers</flux:heading>
                            <flux:badge size="sm" :color="count($selected) > 0 ? 'emerald' : 'zinc'">
                                {{ count($selected) > 0 ? count($selected).' selected' : 'None selected' }}
                            </flux:badge>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <flux:input type="search" size="sm" icon="magnifying-glass"
                                placeholder="Search teachers" wire:model.live.debounce.300ms="teacherSearch"
                                class="w-56" />
                            <flux:button type="button" size="sm" variant="ghost" wire:click="selectVisible">
                                Select all
                            </flux:button>
                            @if (count($selected) > 0)
                                <flux:button type="button" size="sm" variant="ghost" wire:click="clearSelection">
                                    Clear
                                </flux:button>
                            @endif
                        </div>
                    </div>

                    {{-- What's been picked so far, so a long list never hides
                    the answer to "who did I choose?". --}}
                    @if (count($selected) > 0)
                        <div class="flex flex-wrap gap-2 border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                            @foreach ($selected as $teacherId)
                                @php($teacher = $this->teachers->firstWhere('id', (int) $teacherId))
                                <button type="button" wire:click="toggle('{{ $teacherId }}')"
                                    class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-sm font-medium text-emerald-700 transition hover:bg-emerald-100 dark:bg-emerald-500/15 dark:text-emerald-300 dark:hover:bg-emerald-500/25">
                                    {{ $teacher?->name }}
                                    <span aria-hidden="true">&times;</span>
                                    <span class="sr-only">Remove</span>
                                </button>
                            @endforeach
                        </div>
                    @endif

                    <div class="p-4">
                        @if ($this->teachers->isEmpty())
                            <flux:text class="text-zinc-500">
                                No teachers on staff yet.
                                <a href="{{ route('teacher.index') }}" class="underline" wire:navigate>Add one
                                    first</a>.
                            </flux:text>
                        @elseif ($this->visibleTeachers->isEmpty())
                            <flux:text class="block text-center text-sm text-zinc-500">
                                No teacher matches <span class="font-medium">{{ $teacherSearch }}</span>.
                            </flux:text>
                        @else
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                @foreach ($this->visibleTeachers as $teacher)
                                    @php($teacherId = (string) $teacher->id)
                                    @php($isSelected = in_array($teacherId, $selected, true))
                                    <button type="button" wire:click="toggle('{{ $teacherId }}')"
                                        wire:key="teacher-{{ $teacher->id }}"
                                        @class([
                                            'flex items-start gap-3 rounded-lg border p-3 text-start transition',
                                            'border-emerald-500 bg-emerald-50/60 ring-1 ring-emerald-500 dark:border-emerald-500 dark:bg-emerald-500/10' => $isSelected,
                                            'border-zinc-200 bg-white hover:border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-600' => ! $isSelected,
                                        ])>
                                        <span
                                            @class([
                                                'flex size-9 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                                                'bg-emerald-500 text-white' => $isSelected,
                                                'bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300' => ! $isSelected,
                                            ])>
                                            {{ $teacher->initials() }}
                                        </span>

                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-sm font-medium text-zinc-900 dark:text-white">
                                                {{ $teacher->name }}
                                            </span>
                                            <span class="block truncate text-xs text-zinc-500">
                                                @php($meta = collect([
                                                    $teacher->teacherUserDetails?->department,
                                                    $teacher->teacherUserDetails?->employee_number,
                                                ])->filter()->implode(' · '))
                                                {{ $meta ?: 'Teacher' }}
                                                @php($load = (int) ($this->teacherLoad[$teacher->id] ?? 0))
                                                @if ($load > 0)
                                                    · {{ $load }} subject{{ $load === 1 ? '' : 's' }}
                                                @endif
                                            </span>
                                            @if ($this->isAssigned($teacherId))
                                                <span
                                                    class="mt-1 inline-flex text-xs font-medium text-amber-600 dark:text-amber-400">
                                                    Already teaches this
                                                </span>
                                            @endif
                                        </span>

                                        <span
                                            @class([
                                                'flex size-5 shrink-0 items-center justify-center rounded-md border',
                                                'border-emerald-500 bg-emerald-500 text-white' => $isSelected,
                                                'border-zinc-300 text-transparent dark:border-zinc-600' => ! $isSelected,
                                            ])>
                                            <svg class="size-3.5" viewBox="0 0 20 20" fill="none"
                                                stroke="currentColor" stroke-width="3" stroke-linecap="round"
                                                stroke-linejoin="round">
                                                <path d="M4 10.5 8 14.5 16 6" />
                                            </svg>
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <flux:error name="classId" class="mt-2 text-sm text-red-500" />
                <flux:error name="subjectId" class="mt-2 text-sm text-red-500" />
                <flux:error name="selected" class="mt-2 text-sm text-red-500" />

                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <flux:text class="text-xs text-zinc-500">
                        Pick as many teachers as share the subject - assigning one never removes another.
                    </flux:text>
                    <flux:button type="submit" variant="primary" icon="plus" :disabled="count($selected) === 0">
                        <span wire:loading.remove wire:target="assign">Assign</span>
                        <span wire:loading wire:target="assign">Assigning…</span>
                    </flux:button>
                </div>
            </form>
        </flux:card>
    @endcan

    <flux:card>
        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <flux:heading size="lg">Current Assignments</flux:heading>

            <div class="flex items-end gap-2">
                <flux:select wire:model.live="filterClassId" label="Class">
                    <flux:select.option value="">All classes</flux:select.option>
                    @foreach ($this->classes as $schoolClass)
                        <flux:select.option value="{{ $schoolClass->id }}">{{ $schoolClass->name }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="filterTeacherId" label="Teacher">
                    <flux:select.option value="">All teachers</flux:select.option>
                    @foreach ($this->teachers as $teacher)
                        <flux:select.option value="{{ $teacher->id }}">{{ $teacher->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>

        @forelse ($this->grouped as $className => $classAssignments)
            <div class="mb-6 last:mb-0" wire:key="class-group-{{ $className }}">
                <div class="mb-2 flex items-center gap-2">
                    <flux:heading>{{ $className }}</flux:heading>
                    <flux:badge size="sm" color="zinc">
                        {{ $classAssignments->count() }} assignment{{ $classAssignments->count() === 1 ? '' : 's' }}
                    </flux:badge>
                </div>

                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Subject</flux:table.column>
                        <flux:table.column>Teacher</flux:table.column>
                        <flux:table.column>Actions</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($classAssignments as $assignment)
                            <flux:table.row :key="$assignment->id">
                                <flux:table.cell>{{ $assignment->subject?->name ?? '—' }}</flux:table.cell>
                                <flux:table.cell>{{ $assignment->teacher?->name ?? '—' }}</flux:table.cell>
                                <flux:table.cell>
                                    @can('edit subject')
                                        <flux:button type="button" size="sm" icon="trash" variant="ghost"
                                            wire:click="remove({{ $assignment->id }})"
                                            wire:confirm="Remove this assignment?">remove</flux:button>
                                    @endcan
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @empty
            <flux:text class="text-zinc-500">No subject teachers assigned yet.</flux:text>
        @endforelse
    </flux:card>
</div>
