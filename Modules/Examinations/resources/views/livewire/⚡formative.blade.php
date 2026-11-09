<?php

use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Classes\Models\SchoolClass;
use Modules\Examinations\Models\ExamSitting;
use Modules\Subject\Models\Subject;

new #[Title('Record Assessment')] class extends Component
{
    #[Url]
    public ?int $classId = null;

    #[Computed]
    public function classes(): Collection
    {
        $user = auth()->user();
        if ($user && $user->hasRole('Teacher')) {
            return \Modules\Result\Services\ResultAccessService::selectableClasses($user);
        }

        $query = SchoolClass::with('curriculum');

        if (! isAdmin()) {
            $query->where('institution_id', currentInstitutionId() ?? 0);
        }

        return $query->orderBy('name')->get();
    }

    #[Computed]
    public function subjects(): Collection
    {
        if (! $this->classId) {
            return collect();
        }

        $user = auth()->user();
        if ($user && $user->hasRole('Teacher')) {
            return \Modules\Result\Services\ResultAccessService::selectableSubjects($user, (int) $this->classId);
        }

        $query = Subject::where('is_active', true);

        if (! isAdmin()) {
            $query->where('institution_id', currentInstitutionId() ?? 0);
        }

        return $query->orderBy('name')->get();
    }

    #[Computed]
    public function assessmentCategories(): array
    {
        return collect(ExamSitting::ASSESSMENT_CATEGORIES)
            ->only(['classroom_assessment', 'sba'])
            ->all();
    }

    public function mount(): void
    {
        abort_unless(auth()->user()->can('create examination'), 403);
    }
}; ?>
<div>
    <div class="flex flex-col gap-6 w-full max-w-2xl mx-auto p-4 sm:p-6 lg:p-8">

        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 text-xs text-zinc-500 mb-1">
                    <a href="{{ route('dashboard') }}" wire:navigate.hover
                        class="hover:text-zinc-800 dark:hover:text-zinc-200">Dashboard</a>
                    <span>/</span>
                    <a href="{{ route('examinations.index') }}" wire:navigate.hover
                        class="hover:text-zinc-800 dark:hover:text-zinc-200">Assessments</a>
                    <span>/</span>
                    <span class="text-zinc-700 dark:text-zinc-300 font-medium">Record Assessment</span>
                </div>
                <flux:heading size="xl" class="font-black tracking-tight">Record Formative Assessment</flux:heading>
                <flux:text class="text-zinc-500 text-sm mt-0.5">
                    Record a Classroom Assessment (CA) or School-Based Assessment (SBA) for CBC learners.
                </flux:text>
            </div>
        </div>

        @if (session('error'))
            <div class="rounded-2xl border border-rose-200 bg-rose-50/80 p-4 flex items-start gap-3 shadow-sm">
                <flux:icon icon="exclamation-circle" class="size-5 text-rose-500 shrink-0 mt-0.5" />
                <p class="text-sm text-rose-800 font-medium">{{ session('error') }}</p>
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50/80 p-4 flex items-start gap-3 shadow-sm">
                <flux:icon icon="exclamation-circle" class="size-5 text-rose-500 shrink-0 mt-0.5" />
                <div class="text-sm text-rose-800">
                    <p class="font-bold mb-1">Please fix the following:</p>
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <div class="bg-white dark:bg-zinc-900 rounded-3xl p-6 sm:p-8 border border-zinc-200/80 dark:border-zinc-800 shadow-sm">
            <form action="{{ route('assessment.formative.store') }}" method="POST" class="space-y-6">
                @csrf

                {{-- Assessment title --}}
                <div>
                    <flux:input
                        name="title"
                        label="Assessment Title *"
                        placeholder="e.g. Science Project — Term 2, Oral Reading Week 3"
                        value="{{ old('title') }}"
                        required
                    />
                    <p class="mt-1 text-xs text-zinc-400">Give this task a clear, descriptive name.</p>
                </div>

                {{-- Category --}}
                <div>
                    <flux:select name="assessment_category" label="Assessment Type *" required>
                        <flux:select.option value="">— Select type —</flux:select.option>
                        @foreach ($this->assessmentCategories as $value => $label)
                            <flux:select.option value="{{ $value }}" :selected="old('assessment_category') === $value">
                                {{ $label }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <p class="mt-1 text-xs text-zinc-400">
                        CA = teacher-designed task. SBA = KNEC-provided tool.
                    </p>
                </div>

                {{-- Class --}}
                <div>
                    <flux:select
                        name="class_id"
                        label="Class *"
                        required
                        wire:model.live="classId"
                    >
                        <flux:select.option value="">— Select class —</flux:select.option>
                        @foreach ($this->classes as $class)
                            <flux:select.option value="{{ $class->id }}" :selected="old('class_id') == $class->id">
                                <span translate="no">{{ $class->name }}</span>
                                @if ($class->curriculum)
                                    ({{ $class->curriculum->systemLabel() }})
                                @endif
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                {{-- Subject — reactive, loads after class is picked --}}
                <div>
                    <flux:select name="subject_id" label="Learning Area / Subject *" required>
                        <flux:select.option value="">— Select subject —</flux:select.option>
                        @foreach ($this->subjects as $subject)
                            <flux:select.option value="{{ $subject->id }}" :selected="old('subject_id') == $subject->id">
                                {{ $subject->name }}
                                @if ($subject->learning_area && $subject->learning_area !== $subject->name)
                                    — {{ $subject->learning_area }}
                                @endif
                                @if ($subject->strand)
                                    · {{ $subject->strand }}
                                @endif
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    @if (! $this->classId)
                        <p class="mt-1 text-xs text-zinc-400">Select a class first.</p>
                    @endif
                </div>

                {{-- Date, Term, Year — 3-column on desktop --}}
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <flux:input
                            type="date"
                            name="assessment_date"
                            label="Assessment Date *"
                            value="{{ old('assessment_date', today()->toDateString()) }}"
                            required
                        />
                    </div>
                    <div>
                        <flux:input
                            name="term"
                            label="Term"
                            placeholder="e.g. Term 2"
                            value="{{ old('term') }}"
                        />
                    </div>
                    <div>
                        <flux:input
                            type="number"
                            name="academic_year"
                            label="Academic Year"
                            placeholder="{{ now()->year }}"
                            value="{{ old('academic_year', now()->year) }}"
                            min="2000"
                            max="2100"
                        />
                    </div>
                </div>

                {{-- Notes --}}
                <div>
                    <flux:textarea
                        name="notes"
                        label="Notes"
                        placeholder="Optional — task instructions, materials used, KNEC tool reference…"
                        rows="3"
                    >{{ old('notes') }}</flux:textarea>
                </div>

                {{-- Actions --}}
                <div class="flex flex-wrap items-center gap-3 pt-2 border-t border-zinc-100 dark:border-zinc-800">
                    <flux:button type="submit" variant="primary" class="flex-1 sm:flex-none">
                        Continue to Band Entry
                    </flux:button>
                    <a href="{{ route('examinations.index') }}" wire:navigate>
                        <flux:button type="button" variant="ghost">Cancel</flux:button>
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
