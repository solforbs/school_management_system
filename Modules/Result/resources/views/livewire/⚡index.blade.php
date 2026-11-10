<?php

use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Institution\Models\Institution;
use Modules\Result\Models\Result;
use Modules\Result\Services\ResultAccessService;
use Modules\Student\Models\StudentDetails;

/**
 * The results landing page is a class picker, not the whole-school ledger
 * it used to be. A teacher sees a card per class they teach; a Director
 * sees every class. Clicking one opens that class's results only.
 */
new #[Title('Academic Performance')] class extends Component {
    public function mount(): void
    {
        abort_unless(auth()->user()->can('view result'), 403);
    }

    /**
     * The classes this viewer may open.
     *
     * @return Collection<int, \Modules\Classes\Models\SchoolClass>
     */
    #[Computed]
    public function classes(): Collection
    {
        return ResultAccessService::selectableClasses(auth()->user());
    }

    /**
     * Per-class headline figures, keyed by class id.
     *
     * @return Collection<int, array{results: int, mean: float, students: int}>
     */
    #[Computed]
    public function stats(): Collection
    {
        $classIds = $this->classes->pluck('id');

        if ($classIds->isEmpty()) {
            return collect();
        }

        $results = Result::query()
            ->selectRaw('class_id, count(*) as results_count, avg(marks_obtained) as mean_mark')
            ->whereIn('class_id', $classIds)
            ->groupBy('class_id')
            ->get()
            ->keyBy('class_id');

        $students = StudentDetails::query()
            ->selectRaw('class_id, count(*) as students_count')
            ->whereIn('class_id', $classIds)
            ->groupBy('class_id')
            ->get()
            ->keyBy('class_id');

        return $classIds->mapWithKeys(fn ($id) => [(int) $id => [
            'results' => (int) ($results[$id]->results_count ?? 0),
            'mean' => round((float) ($results[$id]->mean_mark ?? 0), 1),
            'students' => (int) ($students[$id]->students_count ?? 0),
        ]]);
    }

    /**
     * A light institution-wide overview across the classes on show.
     *
     * @return array{records: int, mean: float, pass_rate: float}
     */
    #[Computed]
    public function overview(): array
    {
        $classIds = $this->classes->pluck('id');

        if ($classIds->isEmpty()) {
            return ['records' => 0, 'mean' => 0.0, 'pass_rate' => 0.0];
        }

        $row = Result::query()
            ->selectRaw('count(*) as records, avg(marks_obtained) as mean_mark, sum(case when marks_obtained >= 50 then 1 else 0 end) as passes')
            ->whereIn('class_id', $classIds)
            ->first();

        $records = (int) ($row->records ?? 0);

        return [
            'records' => $records,
            'mean' => round((float) ($row->mean_mark ?? 0), 1),
            'pass_rate' => $records > 0 ? round(((int) ($row->passes ?? 0) / $records) * 100, 1) : 0.0,
        ];
    }

    #[Computed]
    public function institution(): ?Institution
    {
        return currentInstitution();
    }
}; ?>

<div class="flex flex-col gap-6 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold tracking-wide uppercase bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-400 border border-blue-200/60">
                    Academic Performance
                </span>
                <span class="text-zinc-300">&bull;</span>
                <span class="text-xs text-zinc-500 font-medium"><span translate="no">{{ $this->institution?->name ?? 'Campus Records' }}</span></span>
            </div>
            <flux:heading size="xl" class="font-black tracking-tight text-zinc-900 dark:text-white">Exam Results</flux:heading>
            <flux:text class="text-zinc-500 text-sm mt-0.5">
                Pick a class to view and manage its recorded marks.
            </flux:text>
        </div>

        <div class="flex items-center gap-3">
            <x-export-buttons base-route="result.export" module="result" />
            @can('create result')
                <flux:button href="{{ route('result.create') }}" wire:navigate.hover icon="plus" variant="subtle"
                    class="font-semibold shadow-sm">
                    Add single result
                </flux:button>
                <flux:button href="{{ route('result.entry.exams') }}" wire:navigate.hover icon="clipboard-document-check"
                    variant="primary" class="font-semibold shadow-sm px-4">
                    Record Exam Score
                </flux:button>
            @endcan
        </div>
    </div>

    @if (session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50/80 p-4 flex items-center gap-3 shadow-sm">
            <flux:icon icon="check-circle" class="size-5 text-emerald-600 shrink-0" />
            <span class="text-sm font-semibold text-emerald-900">{{ session('success') }}</span>
        </div>
    @endif

    {{-- Overview across the classes on show --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        <x-kpi-card title="Recorded Marks" icon="academic-cap" color="sky" :count="$this->overview['records']"
            subtext="across your classes" />
        <x-kpi-card title="Average Score" icon="chart-bar" color="indigo"
            :count="number_format($this->overview['mean'], 1)" subtext="mean mark recorded" />
        <x-kpi-card title="Pass Rate" icon="check-badge" color="emerald"
            :count="$this->overview['pass_rate'] . '%'" subtext="scoring 50% and above" />
    </div>

    {{-- Class cards --}}
    @if ($this->classes->isEmpty())
        <div class="py-16 text-center bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 rounded-2xl">
            <flux:icon icon="academic-cap" class="size-12 mx-auto text-zinc-300 mb-3" />
            <h4 class="text-base font-bold text-zinc-900 dark:text-white">No classes to show</h4>
            <p class="text-xs text-zinc-500 mt-1">
                You have no classes assigned yet, so there are no results to view.
            </p>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ($this->classes as $schoolClass)
                @php($stat = $this->stats[$schoolClass->id] ?? ['results' => 0, 'mean' => 0, 'students' => 0])
                <a href="{{ route('result.class', $schoolClass->id) }}" wire:navigate
                    class="group flex flex-col gap-3 rounded-2xl border border-zinc-200/80 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 shadow-xs transition hover:border-blue-300 hover:shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="font-bold text-zinc-900 dark:text-white truncate group-hover:text-blue-600 transition">
                                <span translate="no">{{ $schoolClass->name }}</span>
                            </div>
                            <div class="text-[11px] text-zinc-400 mt-0.5">
                                {{ $schoolClass->level ?? 'Class' }} &bull; {{ $stat['students'] }} student(s)
                            </div>
                        </div>
                        <flux:icon icon="chevron-right" class="size-4 text-zinc-300 group-hover:text-blue-500 shrink-0" />
                    </div>
                    <div class="flex items-center gap-4 text-xs">
                        <div>
                            <div class="font-bold text-zinc-900 dark:text-white text-sm">{{ $stat['results'] }}</div>
                            <div class="text-[11px] text-zinc-400">marks recorded</div>
                        </div>
                        <div>
                            <div class="font-bold text-zinc-900 dark:text-white text-sm">{{ number_format($stat['mean'], 1) }}</div>
                            <div class="text-[11px] text-zinc-400">mean score</div>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
