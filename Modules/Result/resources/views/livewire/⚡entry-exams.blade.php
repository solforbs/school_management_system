<?php

use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Examinations\Models\Examination;

/**
 * Step one of recording marks: pick the exam sitting.
 *
 * A sitting is institution-wide - "Entry Exam", "Mid Term", "End Term" -
 * and the teacher then picks their subject and class on the next screen.
 * This page shows the latest sittings for the current term so the list is
 * short; an "earlier sittings" toggle lifts that filter.
 */
new #[Title('Record Exam Score')] class extends Component {
    #[Url]
    public string $all = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('create result'), 403);
    }

    private function institutionId(): int
    {
        $user = auth()->user();

        if ($user->hasRole('Teacher')) {
            return (int) ($user->teacherUserDetails?->institution_id ?? 0);
        }

        return (int) (currentInstitution()?->id ?? 0);
    }

    /**
     * The (year, term) the school is currently marking - read off the most
     * recent sitting rather than a calendar, since terms repeat yearly.
     *
     * @return array{year: int|null, term: string|null}
     */
    #[Computed]
    public function currentPeriod(): array
    {
        $base = Examination::query()
            ->when(! isAdmin(), fn ($q) => $q->where('institution_id', $this->institutionId()));

        $year = (clone $base)->max('academic_year');

        $term = $year
            ? (clone $base)->where('academic_year', $year)->orderByDesc('exam_date')->value('term')
            : null;

        return ['year' => $year ? (int) $year : null, 'term' => $term];
    }

    /**
     * @return Collection<int, Examination>
     */
    #[Computed]
    public function examinations(): Collection
    {
        $query = Examination::with(['subject', 'schoolClass'])
            ->when(! isAdmin(), fn ($q) => $q->where('institution_id', $this->institutionId()));

        if ($this->all !== '1') {
            ['year' => $year, 'term' => $term] = $this->currentPeriod;

            $query->when($year, fn ($q) => $q->where('academic_year', $year))
                ->when($term, fn ($q) => $q->where('term', $term));
        }

        return $query->orderByDesc('exam_date')->orderByDesc('id')->get();
    }
}; ?>

<div class="flex flex-col gap-6 w-full max-w-full mx-auto p-4 sm:p-6 lg:p-8">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" class="font-black tracking-tight text-zinc-900 dark:text-white">
                Record Exam Score
            </flux:heading>
            <flux:text class="text-zinc-500 text-sm mt-0.5">
                Pick the exam sitting you're entering marks for. You'll choose your subject and class next.
            </flux:text>
        </div>
        <div class="flex items-center gap-3">
            <flux:button href="{{ route('result.index') }}" wire:navigate icon="arrow-left" variant="subtle"
                class="font-semibold shadow-sm">
                Back to Results
            </flux:button>
        </div>
    </div>

    @php(['year' => $periodYear, 'term' => $periodTerm] = $this->currentPeriod)
    <div class="flex flex-wrap items-center justify-between gap-3">
        <flux:text class="text-sm text-zinc-500">
            @if ($all === '1')
                Showing every sitting.
            @elseif ($periodTerm)
                Showing <span class="font-semibold text-zinc-700 dark:text-zinc-300">{{ $periodTerm }} {{ $periodYear }}</span> sittings.
            @elseif ($periodYear)
                Showing <span class="font-semibold text-zinc-700 dark:text-zinc-300">{{ $periodYear }}</span> sittings.
            @endif
        </flux:text>
        @if ($all === '1')
            <flux:button size="sm" variant="ghost" wire:click="$set('all', '')">Show current term only</flux:button>
        @else
            <flux:button size="sm" variant="ghost" wire:click="$set('all', '1')">Show earlier sittings</flux:button>
        @endif
    </div>

    @if ($this->examinations->isEmpty())
        <div class="py-16 text-center bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 rounded-2xl">
            <flux:icon icon="academic-cap" class="size-12 mx-auto text-zinc-300 mb-3" />
            <h4 class="text-base font-bold text-zinc-900 dark:text-white">No exam sittings yet</h4>
            <p class="text-xs text-zinc-500 mt-1 mb-4">Schedule an examination first, then come back to record marks.</p>
            @can('create examination')
                <flux:button href="{{ route('examinations.create') }}" wire:navigate variant="primary" size="sm">
                    Schedule an examination
                </flux:button>
            @endcan
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @foreach ($this->examinations as $exam)
                <a href="{{ route('result.entry.create', ['examination_id' => $exam->id]) }}" wire:navigate
                    class="group flex flex-col gap-2 rounded-2xl border border-zinc-200/80 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 shadow-xs transition hover:border-blue-300 hover:shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="font-bold text-zinc-900 dark:text-white truncate group-hover:text-blue-600 transition">
                                {{ $exam->title }}
                            </div>
                            <div class="text-[11px] text-zinc-400 mt-0.5">
                                {{ $exam->term ?? 'General assessment' }}
                                @if ($exam->academic_year)
                                    &bull; {{ $exam->academic_year }}
                                @endif
                                @if ($exam->exam_date)
                                    &bull; {{ $exam->exam_date->format('d M Y') }}
                                @endif
                            </div>
                        </div>
                        <flux:icon icon="chevron-right" class="size-4 text-zinc-300 group-hover:text-blue-500 shrink-0" />
                    </div>
                    <div class="flex flex-wrap items-center gap-1.5 text-[11px]">
                        <span class="px-2 py-0.5 rounded-md bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 font-medium border border-zinc-200/60 dark:border-zinc-700">
                            Out of {{ $exam->total_marks }}
                        </span>
                        @if ($exam->schoolClass || $exam->class_name)
                            <span class="px-2 py-0.5 rounded-md bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-300 font-medium border border-amber-200/60">
                                Pinned: {{ $exam->schoolClass?->name ?? $exam->class_name }}
                            </span>
                        @endif
                        @if ($exam->subject || $exam->subject_name)
                            <span class="px-2 py-0.5 rounded-md bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-300 font-medium border border-amber-200/60">
                                {{ $exam->subject?->name ?? $exam->subject_name }}
                            </span>
                        @else
                            <span class="px-2 py-0.5 rounded-md bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-300 font-medium border border-emerald-200/60">
                                Whole-school
                            </span>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
