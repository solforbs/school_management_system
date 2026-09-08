<?php

use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Result\Services\ResultAccessService;

/**
 * Step one of recording marks: pick the exam sitting.
 *
 * A sitting is the whole event - "Mock Exam", "End Term" - covering every
 * subject and (unless it was targeted at one class) every class. The
 * teacher picks one of their classes next, then the subject.
 *
 * The list shows the current term's sittings so it stays short; an
 * "earlier sittings" toggle lifts that filter.
 */
new #[Title('Record Exam Score')] class extends Component {
    #[Url]
    public string $all = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('create result'), 403);
    }

    /**
     * Every sitting this viewer may record marks in, newest first.
     *
     * @return Collection<int, \Modules\Examinations\Models\ExamSitting>
     */
    #[Computed]
    public function allSittings(): Collection
    {
        return ResultAccessService::selectableSittings(auth()->user());
    }

    /**
     * The sittings actually shown: the current term's, unless "earlier
     * sittings" is toggled on.
     *
     * @return Collection<int, \Modules\Examinations\Models\ExamSitting>
     */
    #[Computed]
    public function sittings(): Collection
    {
        $sittings = $this->allSittings;

        if ($this->all === '1' || $sittings->isEmpty()) {
            return $sittings;
        }

        $latest = $sittings->first();

        return $sittings
            ->filter(fn ($sitting) => (int) $sitting->academic_year === (int) $latest->academic_year
                && $sitting->term === $latest->term)
            ->values();
    }

    #[Computed]
    public function currentPeriod(): ?string
    {
        $latest = $this->allSittings->first();

        if (! $latest) {
            return null;
        }

        return trim(collect([$latest->term, $latest->academic_year])->filter()->implode(' ')) ?: null;
    }
}; ?>

<div class="flex flex-col gap-6 w-full max-w-full mx-auto p-4 sm:p-6 lg:p-8">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" class="font-black tracking-tight text-zinc-900 dark:text-white">
                Record Exam Score
            </flux:heading>
            <flux:text class="text-zinc-500 text-sm mt-0.5">
                Pick the exam you're entering marks for. You'll choose your class, then your subject, next.
            </flux:text>
        </div>
        <div class="flex items-center gap-3">
            <flux:button href="{{ route('result.index') }}" wire:navigate icon="arrow-left" variant="subtle"
                class="font-semibold shadow-sm">
                Back to Results
            </flux:button>
        </div>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <flux:text class="text-sm text-zinc-500">
            @if ($all === '1')
                Showing every exam.
            @elseif ($this->currentPeriod)
                Showing <span class="font-semibold text-zinc-700 dark:text-zinc-300">{{ $this->currentPeriod }}</span> exams.
            @endif
        </flux:text>
        @if ($all === '1')
            <flux:button size="sm" variant="ghost" wire:click="$set('all', '')">Show current term only</flux:button>
        @else
            <flux:button size="sm" variant="ghost" wire:click="$set('all', '1')">Show earlier exams</flux:button>
        @endif
    </div>

    @if ($this->sittings->isEmpty())
        <div class="py-16 text-center bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 rounded-2xl">
            <flux:icon icon="academic-cap" class="size-12 mx-auto text-zinc-300 mb-3" />
            <h4 class="text-base font-bold text-zinc-900 dark:text-white">No exams yet</h4>
            <p class="text-xs text-zinc-500 mt-1 mb-4">Create an examination first, then come back to record marks.</p>
            @can('create examination')
                <flux:button href="{{ route('examinations.create') }}" wire:navigate variant="primary" size="sm">
                    Create an examination
                </flux:button>
            @endcan
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @foreach ($this->sittings as $sitting)
                <a href="{{ route('result.entry.classes', ['sitting_id' => $sitting->id]) }}" wire:navigate
                    class="group flex flex-col gap-2 rounded-2xl border border-zinc-200/80 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 shadow-xs transition hover:border-blue-300 hover:shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="font-bold text-zinc-900 dark:text-white truncate group-hover:text-blue-600 transition">
                                {{ $sitting->displayName() }}
                            </div>
                            <div class="text-[11px] text-zinc-400 mt-0.5">{{ $sitting->periodLabel() }}</div>
                        </div>
                        <flux:icon icon="chevron-right" class="size-4 text-zinc-300 group-hover:text-blue-500 shrink-0" />
                    </div>
                    <div class="flex flex-wrap items-center gap-1.5 text-[11px]">
                        <span class="px-2 py-0.5 rounded-md bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 font-medium border border-zinc-200/60 dark:border-zinc-700">
                            {{ $sitting->papers_count }} {{ Str::plural('paper', $sitting->papers_count) }}
                        </span>
                        @if ($sitting->class_id)
                            <span class="px-2 py-0.5 rounded-md bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-300 font-medium border border-amber-200/60">
                                Targeted: {{ $sitting->schoolClass?->name ?? 'one class' }}
                            </span>
                        @else
                            <span class="px-2 py-0.5 rounded-md bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-300 font-medium border border-emerald-200/60">
                                All classes
                            </span>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
