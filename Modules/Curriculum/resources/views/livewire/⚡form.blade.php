<?php

use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Curriculum\Actions\SaveCurriculum;
use Modules\Curriculum\Models\Curriculum;
use Modules\Institution\Models\Institution;

new #[Title('Curriculum')] class extends Component {
    public ?Curriculum $curriculum = null;

    public function mount(?int $curriculumId = null): void
    {
        if ($curriculumId === null) {
            abort_unless(auth()->user()->can('create curriculum'), 403);

            return;
        }

        abort_unless(auth()->user()->can('edit curriculum'), 403);

        $this->curriculum = $this->scoped()->findOrFail($curriculumId);
    }

    #[Computed]
    public function institutions(): Collection
    {
        return isAdmin() ? Institution::all() : collect([currentInstitution()])->filter();
    }

    private function scoped()
    {
        $query = Curriculum::query();

        if (!isAdmin()) {
            $query->where('institution_id', currentInstitution()?->id ?? 0);
        }

        return $query;
    }
}; ?>
<div>
    @if ($curriculum)
        <div class="flex flex-col gap-6 w-full max-w-full mx-auto p-4 sm:p-6 lg:p-8">

            {{-- Header Section --}}
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 text-xs text-zinc-500 mb-1">
                        <a href="{{ route('dashboard') }}"
                            class="hover:text-zinc-800 dark:hover:text-zinc-200">Dashboard</a>
                        <span>/</span>
                        <a href="{{ route('curriculum.index') }}"
                            class="hover:text-zinc-800 dark:hover:text-zinc-200">Curricula</a>
                        <span>/</span>
                        <span class="text-zinc-800 dark:text-zinc-200 font-medium">Edit {{ $curriculum->name }}</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <div
                            class="size-10 rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-400 ring-1 ring-inset ring-indigo-500/20 flex items-center justify-center font-bold text-xs shrink-0">
                            <flux:icon icon="pencil-square" class="size-5" />
                        </div>
                        <div>
                            <flux:heading size="xl" class="font-bold tracking-tight text-zinc-900 dark:text-white">
                                Edit Curriculum</flux:heading>
                            <flux:text class="text-zinc-500 text-sm mt-0.5">
                                Update details and operational status for <span
                                    class="font-semibold">{{ $curriculum->name }}</span>.
                            </flux:text>
                        </div>
                    </div>
                </div>

                <div>
                    <flux:button href="{{ route('curriculum.index') }}" icon="arrow-left" variant="ghost"
                        class="hover:bg-zinc-100 dark:hover:bg-zinc-800">
                        Back to Curricula
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

            <form action="{{ route('curriculum.update', $curriculum->id) }}" method="POST">
                @csrf
                @method('PUT')
                @include('curriculum::partials._form', ['institutions' => $this->institutions])
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
                        <a href="{{ route('curriculum.index') }}"
                            class="hover:text-zinc-800 dark:hover:text-zinc-200">Curricula</a>
                        <span>/</span>
                        <span class="text-zinc-800 dark:text-zinc-200 font-medium">Create</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <div
                            class="size-10 rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-400 ring-1 ring-inset ring-indigo-500/20 flex items-center justify-center font-bold text-xs shrink-0">
                            <flux:icon icon="book-open" class="size-5" />
                        </div>
                        <div>
                            <flux:heading size="xl" class="font-bold tracking-tight text-zinc-900 dark:text-white">
                                Create Curriculum</flux:heading>
                            <flux:text class="text-zinc-500 text-sm mt-0.5">
                                Add a new educational framework to your institution.
                            </flux:text>
                        </div>
                    </div>
                </div>

                <div>
                    <flux:button href="{{ route('curriculum.index') }}" icon="arrow-left" variant="ghost"
                        class="hover:bg-zinc-100 dark:hover:bg-zinc-800">
                        Back to Curricula
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

            <form action="{{ route('curriculum.store') }}" method="POST">
                @csrf
                @include('curriculum::partials._form', ['institutions' => $this->institutions])
            </form>
        </div>
    @endif
</div>
