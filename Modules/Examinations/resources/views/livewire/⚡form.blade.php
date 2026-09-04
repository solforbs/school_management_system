<?php

use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Classes\Models\SchoolClass;
use Modules\Examinations\Actions\SaveExamination;
use Modules\Examinations\Models\Examination;
use Modules\Institution\Models\Institution;
use Modules\Subject\Models\Subject;

new #[Title('Examination')] class extends Component {
    public ?Examination $examination = null;

    public function mount(?int $examinationId = null): void
    {
        if ($examinationId === null) {
            abort_unless(auth()->user()->can('create examination'), 403);

            return;
        }

        abort_unless(auth()->user()->can('edit examination'), 403);

        $this->examination = $this->scoped()->findOrFail($examinationId);
    }

    #[Computed]
    public function classes(): Collection
    {
        $query = SchoolClass::query();

        if (!isAdmin()) {
            $query->where('institution_id', currentInstitution()?->id ?? 0);
        }

        return $query->get();
    }

    #[Computed]
    public function subjects(): Collection
    {
        $query = Subject::where('is_active', true);

        if (!isAdmin()) {
            $query->where('institution_id', currentInstitution()?->id ?? 0);
        }

        return $query->get();
    }

    #[Computed]
    public function institutions(): Collection
    {
        return isAdmin() ? Institution::all() : collect([currentInstitution()])->filter();
    }

    private function scoped()
    {
        $query = Examination::query();

        if (!isAdmin()) {
            $query->where('institution_id', currentInstitution()?->id ?? 0);
        }

        return $query;
    }
}; ?>
<div>
    @if ($examination)
        <div class="flex flex-col gap-6 w-full max-w-full mx-auto p-4 sm:p-6 lg:p-8">

            {{-- Header Section --}}
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 text-xs text-zinc-500 mb-1">
                        <a href="{{ route('dashboard') }}"
                            class="hover:text-zinc-800 dark:hover:text-zinc-200">Dashboard</a>
                        <span>/</span>
                        <a href="{{ route('examinations.index') }}"
                            class="hover:text-zinc-800 dark:hover:text-zinc-200">Examinations</a>
                        <span>/</span>
                        <span class="text-zinc-800 dark:text-zinc-200 font-medium">Edit {{ $examination->title }}</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <div
                            class="size-10 rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-400 ring-1 ring-inset ring-indigo-500/20 flex items-center justify-center font-bold text-xs shrink-0">
                            <flux:icon icon="pencil-square" class="size-5" />
                        </div>
                        <div>
                            <flux:heading size="xl" class="font-bold tracking-tight text-zinc-900 dark:text-white">
                                Edit Examination</flux:heading>
                            <flux:text class="text-zinc-500 text-sm mt-0.5">
                                Update details, timetable schedule, and scoring for <span
                                    class="font-semibold">{{ $examination->title }}</span>.
                            </flux:text>
                        </div>
                    </div>
                </div>

                <div>
                    <flux:button href="{{ route('examinations.index') }}" icon="arrow-left" variant="ghost"
                        class="hover:bg-zinc-100 dark:hover:bg-zinc-800">
                        Back to Exams
                    </flux:button>
                </div>
            </div>

            @if ($errors->any())
                <div
                    class="rounded-xl border border-red-200/50 bg-red-50/50 dark:bg-red-500/10 dark:border-red-500/20 p-4 flex items-start shadow-sm">
                    <flux:icon icon="exclamation-circle" class="size-5 text-red-500 mr-3 mt-0.5 shrink-0" />
                    <div class="text-sm text-red-800 dark:text-red-400">
                        <ul class="list-disc list-inside space-y-0.5 text-xs">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <form action="{{ route('examinations.update', $examination->id) }}" method="POST">
                @csrf
                @method('PUT')
                @include('examinations::partials._form', [
                    'institutions' => $this->institutions,
                    'subjects' => $this->subjects,
                    'classes' => $this->classes,
                    'examTypes' => \Modules\Examinations\Models\Examination::EXAM_TYPES,
                ])
            </form>
        </div>
    @else
        <div class="flex flex-col gap-6 w-full max-w-full mx-auto p-4 sm:p-6 lg:p-8">

            {{-- Header Section --}}
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 text-xs text-zinc-500 mb-1">
                        <a href="{{ route('dashboard') }}"
                            class="hover:text-zinc-800 dark:hover:text-zinc-200">Dashboard</a>
                        <span>/</span>
                        <a href="{{ route('examinations.index') }}"
                            class="hover:text-zinc-800 dark:hover:text-zinc-200">Examinations</a>
                        <span>/</span>
                        <span class="text-zinc-800 dark:text-zinc-200 font-medium">Create</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <div
                            class="size-10 rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-400 ring-1 ring-inset ring-indigo-500/20 flex items-center justify-center font-bold text-xs shrink-0">
                            <flux:icon icon="academic-cap" class="size-5" />
                        </div>
                        <div>
                            <flux:heading size="xl" class="font-bold tracking-tight text-zinc-900 dark:text-white">
                                Create Examination</flux:heading>
                            <flux:text class="text-zinc-500 text-sm mt-0.5">
                                Configure a new academic assessment, schedule, and scoring rules.
                            </flux:text>
                        </div>
                    </div>
                </div>

                <div>
                    <flux:button href="{{ route('examinations.index') }}" icon="arrow-left" variant="ghost"
                        class="hover:bg-zinc-100 dark:hover:bg-zinc-800">
                        Back to Exams
                    </flux:button>
                </div>
            </div>

            @if ($errors->any())
                <div
                    class="rounded-xl border border-red-200/50 bg-red-50/50 dark:bg-red-500/10 dark:border-red-500/20 p-4 flex items-start shadow-sm">
                    <flux:icon icon="exclamation-circle" class="size-5 text-red-500 mr-3 mt-0.5 shrink-0" />
                    <div class="text-sm text-red-800 dark:text-red-400">
                        <ul class="list-disc list-inside space-y-0.5 text-xs">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <form action="{{ route('examinations.store') }}" method="POST">
                @csrf
                @include('examinations::partials._form', [
                    'institutions' => $this->institutions,
                    'subjects' => $this->subjects,
                    'classes' => $this->classes,
                    'examTypes' => \Modules\Examinations\Models\Examination::EXAM_TYPES,
                ])
            </form>
        </div>
    @endif
</div>
