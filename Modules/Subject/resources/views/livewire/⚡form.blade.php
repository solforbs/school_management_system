<?php

use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Subject\Actions\SaveSubject;
use Modules\Subject\Models\Subject;

new #[Title('Subject')] class extends Component
{
    public ?Subject $subject = null;

    public function mount(?int $subjectId = null): void
    {
        if ($subjectId === null) {
            abort_unless(auth()->user()->can('create subject'), 403);

            return;
        }

        abort_unless(auth()->user()->can('edit subject'), 403);

        $this->subject = $this->scoped()->findOrFail($subjectId);
    }

    private function scoped()
    {
        $query = Subject::query();

        if (! isAdmin()) {
            $query->where('institution_id', currentInstitution()?->id ?? 0);
        }

        return $query;
    }
}; ?>
<div>
    @if($subject)
<x-layouts::app :title="__('Edit Subject - ' . $subject->name)">
    <div class="flex flex-col gap-6 w-full max-w-3xl mx-auto p-4 sm:p-6 lg:p-8">
        
        {{-- Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-2">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <a href="{{ route('subject.show', $subject->id) }}" class="inline-flex items-center gap-1 text-xs font-bold text-zinc-500 hover:text-zinc-900 transition">
                        <flux:icon icon="arrow-left" class="size-3.5" />
                        <span>Back to Subject Dossier</span>
                    </a>
                </div>
                <flux:heading size="xl" class="font-black tracking-tight text-zinc-900">Edit Subject</flux:heading>
                <flux:text class="text-zinc-500 text-sm mt-0.5">
                    Update subject details for <strong class="text-zinc-800 font-semibold">{{ $subject->name }}</strong>.
                </flux:text>
            </div>
        </div>

        @if (isset($errors) && $errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50/80 p-4 flex items-start shadow-sm mb-4">
                <flux:icon icon="exclamation-circle" class="size-5 text-rose-500 mr-3 mt-0.5 shrink-0" />
                <div class="text-xs text-rose-800 font-medium">
                    <p class="font-bold mb-1">{{ $errors->count() }} error(s) prevented this subject from being updated:</p>
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-zinc-200/80 shadow-sm">
            <form action="{{ route('subject.update', $subject->id) }}" method="POST" class="space-y-6">
                @csrf
                @method('PUT')

                <div class="space-y-5">
                    @if(isAdmin())
                        <div>
                            <flux:select name="institution_id" label="Institution *" required>
                                @foreach ($institutions as $inst)
                                    <flux:select.option value="{{ $inst->id }}" :selected="old('institution_id', $subject->institution_id) == $inst->id">
                                        {{ $inst->name }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                    @endif
                    
                    <div>
                        <label class="text-xs font-bold text-zinc-700 block mb-1.5">Subject Name *</label>
                        <input type="text" name="name" value="{{ old('name', $subject->name) }}" required
                            class="w-full px-3.5 py-2.5 bg-zinc-50 border border-zinc-200 rounded-xl text-xs font-semibold text-zinc-900 focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
                    </div>
                    
                    <div>
                        <label class="text-xs font-bold text-zinc-700 block mb-1.5">Course Code</label>
                        <input type="text" name="code" value="{{ old('code', $subject->code) }}"
                            class="w-full px-3.5 py-2.5 bg-zinc-50 border border-zinc-200 rounded-xl text-xs font-mono font-bold text-zinc-900 focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
                    </div>

                    <div class="pt-4 border-t border-zinc-100 space-y-4">
                        <div class="flex items-start gap-3 p-4 rounded-2xl bg-zinc-50 border border-zinc-100">
                            <input type="checkbox" name="is_compulsory" id="is_compulsory" value="1" {{ old('is_compulsory', $subject->is_compulsory) ? 'checked' : '' }}
                                class="mt-0.5 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-900">
                            <label for="is_compulsory" class="text-xs">
                                <span class="font-bold text-zinc-900 block">Compulsory Core Subject</span>
                                <span class="text-zinc-500 block mt-0.5">If checked, this subject is automatically mandatory for all learners in enrolled classes.</span>
                            </label>
                        </div>

                        <div class="flex items-start gap-3 p-4 rounded-2xl bg-zinc-50 border border-zinc-100">
                            <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $subject->is_active) ? 'checked' : '' }}
                                class="mt-0.5 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-900">
                            <label for="is_active" class="text-xs">
                                <span class="font-bold text-zinc-900 block">Active Status</span>
                                <span class="text-zinc-500 block mt-0.5">Toggle whether this subject is currently available in the active curriculum.</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="pt-6 border-t border-zinc-100 flex items-center justify-end gap-3">
                    <flux:button href="{{ route('subject.show', $subject->id) }}" variant="subtle" class="font-semibold">
                        Cancel
                    </flux:button>
                    <flux:button type="submit" variant="primary" class="font-semibold px-6">
                        Save Changes
                    </flux:button>
                </div>
            </form>
        </div>
    </div>
</x-layouts::app>

    @else
<x-layouts::app :title="__('Add New Subject')">
    <div class="flex flex-col gap-6 w-full max-w-3xl mx-auto p-4 sm:p-6 lg:p-8">
        
        {{-- Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-2">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <a href="{{ route('subject.index') }}" class="inline-flex items-center gap-1 text-xs font-bold text-zinc-500 hover:text-zinc-900 transition">
                        <flux:icon icon="arrow-left" class="size-3.5" />
                        <span>Back to Subjects Directory</span>
                    </a>
                </div>
                <flux:heading size="xl" class="font-black tracking-tight text-zinc-900">Add Academic Subject</flux:heading>
                <flux:text class="text-zinc-500 text-sm mt-0.5">
                    Configure a new subject discipline and course code for the school curriculum.
                </flux:text>
            </div>
        </div>

        @if (isset($errors) && $errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50/80 p-4 flex items-start shadow-sm mb-4">
                <flux:icon icon="exclamation-circle" class="size-5 text-rose-500 mr-3 mt-0.5 shrink-0" />
                <div class="text-xs text-rose-800 font-medium">
                    <p class="font-bold mb-1">{{ $errors->count() }} error(s) prevented this subject from being created:</p>
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-zinc-200/80 shadow-sm">
            <form action="{{ route('subject.store') }}" method="POST" class="space-y-6">
                @csrf

                <div class="space-y-5">
                    @if(isAdmin())
                        <div>
                            <flux:select name="institution_id" label="Institution *" required>
                                <flux:select.option value="">Select Institution...</flux:select.option>
                                @foreach ($institutions as $inst)
                                    <flux:select.option value="{{ $inst->id }}" :selected="old('institution_id') == $inst->id">
                                        {{ $inst->name }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                    @endif
                    
                    <div>
                        <label class="text-xs font-bold text-zinc-700 block mb-1.5">Subject Name *</label>
                        <input type="text" name="name" value="{{ old('name') }}" placeholder="e.g. Advanced Mathematics" required
                            class="w-full px-3.5 py-2.5 bg-zinc-50 border border-zinc-200 rounded-xl text-xs font-semibold text-zinc-900 focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
                    </div>
                    
                    <div>
                        <label class="text-xs font-bold text-zinc-700 block mb-1.5">Course Code</label>
                        <input type="text" name="code" value="{{ old('code') }}" placeholder="e.g. MATH-401"
                            class="w-full px-3.5 py-2.5 bg-zinc-50 border border-zinc-200 rounded-xl text-xs font-mono font-bold text-zinc-900 focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
                    </div>

                    <div class="pt-4 border-t border-zinc-100 space-y-4">
                        <div class="flex items-start gap-3 p-4 rounded-2xl bg-zinc-50 border border-zinc-100">
                            <input type="checkbox" name="is_compulsory" id="is_compulsory" value="1" {{ old('is_compulsory') ? 'checked' : '' }}
                                class="mt-0.5 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-900">
                            <label for="is_compulsory" class="text-xs">
                                <span class="font-bold text-zinc-900 block">Compulsory Core Subject</span>
                                <span class="text-zinc-500 block mt-0.5">If checked, this subject is automatically mandatory for all learners in enrolled classes.</span>
                            </label>
                        </div>

                        <div class="flex items-start gap-3 p-4 rounded-2xl bg-zinc-50 border border-zinc-100">
                            <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}
                                class="mt-0.5 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-900">
                            <label for="is_active" class="text-xs">
                                <span class="font-bold text-zinc-900 block">Active Status</span>
                                <span class="text-zinc-500 block mt-0.5">Toggle whether this subject is currently available in the active curriculum.</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="pt-6 border-t border-zinc-100 flex items-center justify-end gap-3">
                    <flux:button href="{{ route('subject.index') }}" variant="subtle" class="font-semibold">
                        Cancel
                    </flux:button>
                    <flux:button type="submit" variant="primary" icon="plus" class="font-semibold px-6">
                        Save Subject
                    </flux:button>
                </div>
            </form>
        </div>
    </div>
</x-layouts::app>

    @endif
</div>
