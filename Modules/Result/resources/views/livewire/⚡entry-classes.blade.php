<?php

use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Examinations\Models\ExamSitting;
use Modules\Result\Services\ResultAccessService;

/**
 * Step two of recording marks: inside a sitting, pick the class.
 *
 * A teacher only sees the classes they teach in this sitting; a Director
 * sees every class it covers. Picking one leads to the mark sheet, where
 * the subject is chosen (or auto-selected when they teach just one).
 */
new #[Title('Pick a Class')] class extends Component {
    #[Url(as: 'sitting_id')]
    public string $sittingId = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('create result'), 403);

        // A sitting this viewer can't record for - a hand-typed id, a
        // crafted link - is refused rather than shown empty.
        abort_unless($this->sitting, 404);
    }

    #[Computed]
    public function sitting(): ?ExamSitting
    {
        if ($this->sittingId === '') {
            return null;
        }

        return ResultAccessService::selectableSittings(auth()->user())
            ->firstWhere('id', (int) $this->sittingId);
    }

    /**
     * @return Collection<int, \Modules\Classes\Models\SchoolClass>
     */
    #[Computed]
    public function classes(): Collection
    {
        return ResultAccessService::classesForSitting(auth()->user(), $this->sitting);
    }

    public function subjectCountFor(int $classId): int
    {
        return ResultAccessService::subjectsForSittingClass(auth()->user(), $this->sitting, $classId)->count();
    }
}; ?>

<div class="flex flex-col gap-6 w-full max-w-full mx-auto p-4 sm:p-6 lg:p-8">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="text-[11px] font-semibold uppercase tracking-wide text-blue-600 mb-0.5">
                {{ $this->sitting->displayName() }} · {{ $this->sitting->periodLabel() }}
            </div>
            <flux:heading size="xl" class="font-black tracking-tight text-zinc-900 dark:text-white">
                Pick a class
            </flux:heading>
            <flux:text class="text-zinc-500 text-sm mt-0.5">
                Choose the class you're entering marks for. You'll pick the subject on the next screen.
            </flux:text>
        </div>
        <div class="flex items-center gap-3">
            <flux:button href="{{ route('result.entry.exams') }}" wire:navigate icon="arrow-left" variant="subtle"
                class="font-semibold shadow-sm">
                Change exam
            </flux:button>
        </div>
    </div>

    @if ($this->classes->isEmpty())
        <div class="py-16 text-center bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 rounded-2xl">
            <flux:icon icon="user-group" class="size-12 mx-auto text-zinc-300 mb-3" />
            <h4 class="text-base font-bold text-zinc-900 dark:text-white">No classes for you here</h4>
            <p class="text-xs text-zinc-500 mt-1">
                This exam has no class you teach. Check with your school if that's not right.
            </p>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ($this->classes as $schoolClass)
                @php($subjectCount = $this->subjectCountFor($schoolClass->id))
                <a href="{{ route('result.entry.create', ['sitting_id' => $this->sitting->id, 'class_id' => $schoolClass->id]) }}"
                    wire:navigate
                    class="group flex items-center justify-between gap-3 rounded-2xl border border-zinc-200/80 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 shadow-xs transition hover:border-blue-300 hover:shadow-sm">
                    <div class="min-w-0">
                        <div class="font-bold text-zinc-900 dark:text-white truncate group-hover:text-blue-600 transition">
                            {{ $schoolClass->name }}
                        </div>
                        <div class="text-[11px] text-zinc-400 mt-0.5">
                            {{ $subjectCount }} {{ Str::plural('subject', $subjectCount) }} for you
                        </div>
                    </div>
                    <flux:icon icon="chevron-right" class="size-4 text-zinc-300 group-hover:text-blue-500 shrink-0" />
                </a>
            @endforeach
        </div>
    @endif
</div>
