<?php

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Classes\Models\SchoolClass;
use Modules\Examinations\Models\Examination;
use Modules\Examinations\Services\ExamTimetableBuilder;

/**
 * Build an examination timetable for a term's sitting, check it on screen as
 * a week-by-week calendar grid, then print it. Leaving the class unset
 * builds every class, one grid (and one printed page) per class.
 */
new #[Title('Exam Timetable')] class extends Component {
    #[Url(as: 'sitting_id')]
    public string $sittingId = '';

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

        // Landed on one exam: let it speak for itself, no other filters.
        if ($this->sittingId !== '') {
            return;
        }

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
        // A chosen exam is the whole filter - term/year/type come from it;
        // a class can still narrow it to one page.
        if ($this->sittingId !== '') {
            return array_filter([
                'sitting_id' => $this->sittingId,
                'class_id' => $this->classId,
            ], fn ($value) => $value !== '');
        }

        return array_filter(
            [
                'term' => $this->term,
                'academic_year' => $this->academicYear,
                'exam_type' => $this->examType,
                'class_id' => $this->classId,
            ],
            fn ($value) => $value !== '',
        );
    }

    #[Computed]
    public function sittings(): Collection
    {
        return \Modules\Examinations\Models\ExamSitting::query()
            ->when(! isAdmin(), fn ($q) => $q->where('institution_id', currentInstitution()?->id ?? 0))
            ->orderByDesc('academic_year')
            ->orderByDesc('id')
            ->get();
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

    #[Computed]
    public function examDayCount(): int
    {
        return $this->groups
            ->flatMap(fn (array $group) => $group['examinations']->pluck('exam_date')->filter())
            ->map(fn (CarbonInterface $date) => $date->toDateString())
            ->unique()
            ->count();
    }

    /**
     * The longest single-class run, in weeks - the headline number for "this
     * is more than a week".
     */
    #[Computed]
    public function weekSpan(): int
    {
        return (int) $this->groups->max('week_count');
    }

    /**
     * The full sitting window across every class on screen.
     *
     * @return array{start: Carbon|null, end: Carbon|null}
     */
    #[Computed]
    public function dateRange(): array
    {
        $dates = $this->groups
            ->flatMap(fn (array $group) => $group['examinations']->pluck('exam_date')->filter())
            ->sort()
            ->values();

        return ['start' => $dates->first(), 'end' => $dates->last()];
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
                ? auth()->user()->teacherUserDetails?->institution_id ?? 0
                : currentInstitution()?->id ?? 0;

            $query->where('institution_id', $institutionId);
        }

        return $query->orderBy('name')->get();
    }
}; ?>

@php
    $groups = $this->groups;
    $range = $this->dateRange;
    $rangeLabel = $range['start']
        ? $range['start']->format('d M') . ' – ' . $range['end']->format('d M Y')
        : 'Not scheduled';
@endphp

<div class="flex flex-col gap-6 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8" x-data="{ viewMode: 'grid' }">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="space-y-1">
            <div class="flex items-center gap-2">
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold tracking-wide uppercase bg-amber-50 text-amber-700 border border-amber-200/60 dark:bg-amber-950/50 dark:text-amber-400 dark:border-amber-800/60 flex items-center gap-1.5">
                    <span class="size-1.5 rounded-full bg-amber-500"></span>
                    Examination Schedule
                </span>
                <span class="text-xs text-zinc-400">&bull;</span>
                <span class="text-xs text-zinc-500 font-medium"><span translate="no">{{ currentInstitution()?->name ?? __('Institution') }}</span></span>
            </div>

            <flux:heading size="xl" class="text-2xl font-black tracking-tight text-zinc-900 dark:text-white">
                Examination Timetable
            </flux:heading>
            <flux:text class="text-zinc-500 text-xs sm:text-sm">
                A week-by-week calendar of every paper each class sits. Runs longer than a week are split into weeks; each class prints on its own page.
            </flux:text>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            <flux:button href="{{ route('examinations.timetable.pdf', $this->filters()) }}" icon="printer"
                variant="primary" class="font-semibold text-xs shadow-sm" :disabled="$this->paperCount === 0">
                Print / Download PDF
            </flux:button>

            <flux:button href="{{ route('examinations.index') }}" wire:navigate.hover icon="arrow-left" variant="subtle"
                class="font-semibold text-xs">
                Back
            </flux:button>
        </div>
    </div>

    {{-- Metric tiles --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-kpi-card title="Papers" icon="document-text" color="amber" :count="$this->paperCount . ' Papers'"
            subtext="scheduled across all classes" />
        <x-kpi-card title="Classes" icon="rectangle-group" color="sky" :count="$groups->count() . ' Classes'"
            subtext="each on its own page" />
        <x-kpi-card title="Exam Days" icon="calendar-days" color="emerald" :count="$this->examDayCount . ' Days'"
            subtext="distinct sitting dates" />
        <x-kpi-card title="Longest Run" icon="clock" color="indigo"
            :count="$this->weekSpan . ' ' . Str::plural('Week', max($this->weekSpan, 1))" subtext="{{ $rangeLabel }}" />
    </div>

    {{-- Toolbar: view switch + filters --}}
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 rounded-2xl p-3 sm:p-4 flex flex-col gap-3 shadow-xs">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="inline-flex p-1 bg-zinc-100 dark:bg-zinc-800 rounded-xl">
                <button type="button" @click="viewMode = 'grid'"
                    :class="viewMode === 'grid' ? 'bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white shadow-2xs font-bold' : 'text-zinc-500 hover:text-zinc-900 dark:hover:text-white font-medium'"
                    class="px-3.5 py-1.5 rounded-lg text-xs transition-all inline-flex items-center gap-1.5">
                    <flux:icon icon="table-cells" class="size-3.5" />
                    Calendar Grid
                </button>
                <button type="button" @click="viewMode = 'list'"
                    :class="viewMode === 'list' ? 'bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white shadow-2xs font-bold' : 'text-zinc-500 hover:text-zinc-900 dark:hover:text-white font-medium'"
                    class="px-3.5 py-1.5 rounded-lg text-xs transition-all inline-flex items-center gap-1.5">
                    <flux:icon icon="list-bullet" class="size-3.5" />
                    List
                </button>
            </div>

            @if ($this->paperCount > 0)
                <flux:text class="text-xs text-zinc-500">
                    {{ $this->paperCount }} paper(s) · {{ $groups->count() }} class(es) · {{ $rangeLabel }}
                </flux:text>
            @endif
        </div>

        <div class="flex flex-wrap items-end gap-2 border-t border-zinc-100 dark:border-zinc-800 pt-3">
            @if ($this->sittings->isNotEmpty())
                <flux:select wire:model.live="sittingId" label="Exam" size="sm">
                    <flux:select.option value="">All exams</flux:select.option>
                    @foreach ($this->sittings as $sitting)
                        <flux:select.option value="{{ $sitting->id }}">
                            {{ $sitting->displayName() }} — {{ $sitting->periodLabel() }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <flux:select wire:model.live="academicYear" label="Academic Year" size="sm" :disabled="$sittingId !== ''">
                <flux:select.option value="">All years</flux:select.option>
                @foreach ($this->academicYears as $year)
                    <flux:select.option value="{{ $year }}">{{ $year }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="term" label="Term" size="sm" :disabled="$sittingId !== ''">
                <flux:select.option value="">All terms</flux:select.option>
                @foreach ($this->terms as $termOption)
                    <flux:select.option value="{{ $termOption }}">{{ $termOption }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="examType" label="Type" size="sm" :disabled="$sittingId !== ''">
                <flux:select.option value="">All types</flux:select.option>
                @foreach (\Modules\Examinations\Models\Examination::EXAM_TYPES as $value => $label)
                    <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="classId" label="Class" size="sm">
                <flux:select.option value="">All classes</flux:select.option>
                @foreach ($this->classes as $schoolClass)
                    <flux:select.option translate="no" value="{{ $schoolClass->id }}">{{ $schoolClass->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    {{-- Class grids --}}
    @forelse ($groups as $group)
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 rounded-3xl overflow-hidden shadow-xs"
            wire:key="class-{{ $group['class']?->id ?? $group['class_name'] }}" wire:loading.class="opacity-60">

            {{-- Class header band --}}
            <div class="px-6 py-4 border-b border-zinc-200/80 dark:border-zinc-800 bg-gradient-to-r from-amber-50/60 to-transparent dark:from-amber-950/20 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="space-y-0.5">
                    <flux:heading size="lg" class="font-bold">{{ $group['class_name'] }}</flux:heading>
                    <flux:text class="text-xs text-zinc-500">
                        {{ $group['examinations']->count() }} paper(s)
                        @if ($group['starts_on'])
                            · {{ $group['starts_on']->format('d M') }} – {{ $group['ends_on']->format('d M Y') }}
                        @endif
                        @if ($group['week_count'] > 1)
                            · <span class="font-semibold text-amber-700 dark:text-amber-400">{{ $group['week_count'] }} weeks</span>
                        @endif
                    </flux:text>
                </div>

                <flux:button size="sm" variant="subtle" icon="arrow-down-tray" class="font-semibold text-xs shrink-0"
                    href="{{ route('examinations.timetable.pdf', array_merge($this->filters(), ['class_id' => $group['class']?->id])) }}">
                    Download {{ $group['class_name'] }}
                </flux:button>
            </div>

            <div x-show="viewMode === 'grid'">
                @include('examinations::partials._grid', ['group' => $group])
            </div>

            <div x-show="viewMode === 'list'" style="display: none;">
                @include('examinations::partials._list', ['group' => $group])
            </div>
        </div>
    @empty
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 rounded-3xl overflow-hidden shadow-xs">
            <x-empty-state icon="calendar" color="amber" title="No papers scheduled yet"
                description="No examinations match this year, term and sitting. Adjust the filters above, or schedule a paper.">
                @can('create examination')
                    <flux:button href="{{ route('examinations.create') }}" wire:navigate.hover icon="plus" variant="primary">
                        Schedule an exam
                    </flux:button>
                @endcan
            </x-empty-state>
        </div>
    @endforelse
</div>
