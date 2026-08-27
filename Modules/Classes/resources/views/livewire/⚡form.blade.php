<?php

use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Classes\Models\SchoolClass;
use Modules\Curriculum\Models\Curriculum;
use Modules\Institution\Models\Institution;

new #[Title('Class')] class extends Component
{
    public ?SchoolClass $schoolClass = null;

    public function mount(?int $classId = null): void
    {
        if ($classId === null) {
            abort_unless(auth()->user()->can('create classes'), 403);

            return;
        }

        abort_unless(auth()->user()->can('edit classes'), 403);

        $this->schoolClass = $this->scoped()->findOrFail($classId);
    }

    #[Computed]
    public function curricula(): Collection
    {
        $query = Curriculum::where('status', 'active');

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

    #[Computed]
    public function institutions(): Collection
    {
        return Institution::all();
    }

    private function scoped()
    {
        $query = SchoolClass::query();

        if (! isAdmin()) {
            $query->where('institution_id', currentInstitution()?->id ?? 0);
        }

        return $query;
    }
}; ?>
<div>
    @if($schoolClass)
<x-layouts::app :title="__('Edit Class')">
    <div class="flex flex-col gap-6 w-full max-w-4xl mx-auto p-4 sm:p-6 lg:p-8">

        {{-- Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 text-xs text-zinc-500 mb-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-zinc-800 dark:hover:text-zinc-200">Dashboard</a>
                    <span>/</span>
                    <a href="{{ route('classes.index') }}" class="hover:text-zinc-800 dark:hover:text-zinc-200">Classes</a>
                    <span>/</span>
                    <span class="text-zinc-800 dark:text-zinc-200 font-medium">Edit {{ $schoolClass->name }}</span>
                </div>
                <div class="flex items-center gap-3">
                    <div class="size-10 rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-400 ring-1 ring-inset ring-indigo-500/20 flex items-center justify-center font-bold text-xs shrink-0">
                        <flux:icon icon="pencil-square" class="size-5" />
                    </div>
                    <div>
                        <flux:heading size="xl" class="font-bold tracking-tight text-zinc-900 dark:text-white">Edit Class</flux:heading>
                        <flux:text class="text-zinc-500 text-sm mt-0.5">
                            Update classroom stream settings and assigned teacher.
                        </flux:text>
                    </div>
                </div>
            </div>
            
            <div>
                <flux:button href="{{ route('classes.index') }}" icon="arrow-left" variant="ghost" class="hover:bg-zinc-100 dark:hover:bg-zinc-800">
                    Back to Classes
                </flux:button>
            </div>
        </div>

        @if ($errors->any())
            <div class="rounded-xl border border-red-200/50 bg-red-50/50 dark:bg-red-500/10 dark:border-red-500/20 p-4 flex items-start shadow-sm">
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

        <form action="{{ route('classes.update', $schoolClass->id) }}" method="POST">
            @csrf
            @method('PUT')
            @include('classes::partials._form', ['institutions' => $this->institutions, 'teachers' => $this->teachers])
        </form>
    </div>
</x-layouts::app>

    @else
<x-layouts::app :title="__('Create Class')">
    <div class="flex flex-col gap-6 w-full max-w-4xl mx-auto p-4 sm:p-6 lg:p-8">

        {{-- Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 text-xs text-zinc-500 mb-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-zinc-800 dark:hover:text-zinc-200">Dashboard</a>
                    <span>/</span>
                    <a href="{{ route('classes.index') }}" class="hover:text-zinc-800 dark:hover:text-zinc-200">Classes</a>
                    <span>/</span>
                    <span class="text-zinc-800 dark:text-zinc-200 font-medium">Create</span>
                </div>
                <div class="flex items-center gap-3">
                    <div class="size-10 rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-400 ring-1 ring-inset ring-indigo-500/20 flex items-center justify-center font-bold text-xs shrink-0">
                        <flux:icon icon="academic-cap" class="size-5" />
                    </div>
                    <div>
                        <flux:heading size="xl" class="font-bold tracking-tight text-zinc-900 dark:text-white">Create Class</flux:heading>
                        <flux:text class="text-zinc-500 text-sm mt-0.5">
                            Add a new classroom stream and assign a class teacher.
                        </flux:text>
                    </div>
                </div>
            </div>
            
            <div>
                <flux:button href="{{ route('classes.index') }}" icon="arrow-left" variant="ghost" class="hover:bg-zinc-100 dark:hover:bg-zinc-800">
                    Back to Classes
                </flux:button>
            </div>
        </div>

        @if ($errors->any())
            <div class="rounded-xl border border-red-200/50 bg-red-50/50 dark:bg-red-500/10 dark:border-red-500/20 p-4 flex items-start shadow-sm">
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

        <form action="{{ route('classes.store') }}" method="POST">
            @csrf
            @include('classes::partials._form', ['institutions' => $this->institutions, 'teachers' => $this->teachers])
        </form>
    </div>
</x-layouts::app>

    @endif
</div>
