<?php

use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Parent\Actions\SaveParent;
use Modules\Student\Models\StudentDetails;

new #[Title('Parent')] class extends Component
{
    public ?User $parent = null;

    public function mount(?int $parentId = null): void
    {
        if ($parentId === null) {
            abort_unless(auth()->user()->can('create parent'), 403);

            return;
        }

        abort_unless(auth()->user()->can('edit parent'), 403);

        $this->parent = User::role('Parent')->with(['parent', 'children.student', 'children.institution'])->findOrFail($parentId);
        $this->authorizeAccessTo($this->parent);
    }

    /**
     * Students not currently linked to any parent, plus this parent's own
     * children - so an already-linked child still shows up (checked, badged
     * "Currently Linked") rather than silently dropping off the list and
     * being unlinked the moment the form is saved.
     */
    #[Computed]
    public function availableStudents(): Collection
    {
        return $this->linkableStudentsQuery()->with(['student', 'institution'])->get();
    }

    /**
     * The children already linked to this parent - unchecking one of these
     * on save is what unlinks it. Empty while creating, since there's no
     * parent yet to be linked to anything.
     */
    #[Computed]
    public function linkedStudentIds(): Collection
    {
        return $this->parent
            ? StudentDetails::where('parent_id', $this->parent->id)->pluck('student_id')
            : collect();
    }

    private function linkableStudentsQuery()
    {
        $parentId = $this->parent?->id;

        $query = StudentDetails::where(function ($q) use ($parentId) {
            $q->whereNull('parent_id');

            if ($parentId) {
                $q->orWhere('parent_id', $parentId);
            }
        });

        if (! isAdmin()) {
            $query->where('institution_id', currentInstitution()?->id ?? 0);
        }

        return $query;
    }

    private function authorizeAccessTo(User $parent): void
    {
        if (isAdmin()) {
            return;
        }

        $children = StudentDetails::where('parent_id', $parent->id)->get();

        $accessible = $children->isEmpty()
            || $children->where('institution_id', currentInstitution()?->id)->isNotEmpty();

        abort_unless($accessible, 403);
    }
}; ?>
<div>
    @if($parent)
    <div class="flex flex-col gap-6 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">
        
        {{-- Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-2">
            <div>
                <flux:heading size="xl" class="font-bold tracking-tight text-zinc-900 dark:text-white">Edit Parent</flux:heading>
                <flux:text class="text-zinc-500 text-sm mt-1">
                    Update information and linked students for <span translate="no">{{ $parent->name }}</span>.
                </flux:text>
            </div>
            
            <div class="flex items-center gap-3">
                <flux:button href="{{ route('parent.show', $parent->id) }}" wire:navigate.hover icon="arrow-left" variant="subtle" class="font-semibold shadow-sm">
                    Back to Details
                </flux:button>
            </div>
        </div>

        @if ($errors->any())
            <div class="rounded-xl border border-red-200/50 bg-red-50/50 dark:bg-red-500/10 dark:border-red-500/20 p-4 flex items-start shadow-sm mb-6">
                <flux:icon icon="exclamation-circle" class="size-5 text-red-500 mr-3 mt-0.5" />
                <div class="text-sm text-red-800 dark:text-red-400">
                    <p class="font-semibold mb-1">{{ $errors->count() }} error(s) prevented this parent from being saved:</p>
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <form action="{{ route('parent.update', $parent->id) }}" method="POST">
            @csrf
            @method('PUT')

            <x-stepper.layout defaultActive="account-info">
                
                <x-slot:sidebar>
                    <x-stepper.step id="account-info" title="Account Information" description="Name, email, and password." isLast="false" />
                    <x-stepper.step id="contact-info" title="Contact Details" description="Phone number and occupation." isLast="false" />
                    <x-stepper.step id="link-children" title="Link Children" description="Select students for this parent." isLast="true" />
                </x-slot:sidebar>

                <x-stepper.section id="account-info" title="Account Information" description="Update the basic account details for the parent.">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <flux:input type="text" name="name" label="Full Name" value="{{ old('name', $parent->name) }}" required />
                        <flux:input type="email" name="email" label="Email" value="{{ old('email', $parent->email) }}" required />
                        <div class="md:col-span-2">
                            <flux:input type="password" name="password" label="New Password (optional)" placeholder="Leave blank to keep existing password" />
                        </div>
                    </div>
                </x-stepper.section>

                <x-stepper.section id="contact-info" title="Contact Details" description="Update contact and occupation details for the parent.">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <flux:input type="text" name="parent_phone" label="Phone" value="{{ old('parent_phone', $parent->parent?->parent_phone) }}" />
                        <flux:input type="text" name="parent_occupation" label="Occupation" value="{{ old('parent_occupation', $parent->parent?->parent_occupation) }}" />
                    </div>
                </x-stepper.section>

                <x-stepper.section id="link-children" title="Link Children" description="Select the students you want to link to this parent account. Only students from your institution are shown.">
                    <div class="bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6">
                        @if ($this->availableStudents->isEmpty())
                            <div class="flex flex-col items-center justify-center py-8">
                                <flux:icon icon="users" class="size-10 text-zinc-300 dark:text-zinc-600 mb-3" />
                                <p class="text-sm font-medium text-zinc-900 dark:text-zinc-200">No unlinked students available.</p>
                                <p class="text-xs text-zinc-500 mt-1">All current students are already linked to a parent.</p>
                            </div>
                        @else
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 max-h-80 overflow-y-auto pr-2">
                                @foreach ($this->availableStudents as $studentDetails)
                                    @php
                                        $isLinked = $this->linkedStudentIds->contains($studentDetails->student_id);
                                        $isChecked = collect(old('children', $this->linkedStudentIds->all()))->contains($studentDetails->student_id);
                                    @endphp
                                    <label class="flex items-start gap-3 p-3 rounded-lg border {{ $isLinked ? 'border-blue-200 dark:border-blue-900/40 bg-blue-50/30 dark:bg-blue-950/10' : 'border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900' }} cursor-pointer hover:border-blue-500 dark:hover:border-blue-500 transition-colors">
                                        <input type="checkbox" name="children[]" value="{{ $studentDetails->student_id }}"
                                            @checked($isChecked)
                                            class="mt-1 border-zinc-300 text-blue-600 focus:ring-blue-500 rounded">
                                        <div class="flex flex-col">
                                            <div class="flex items-center gap-2">
                                                <span class="text-sm font-medium text-zinc-900 dark:text-white"><span translate="no">{{ $studentDetails->student?->name }}</span></span>
                                                @if ($isLinked)
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300">Currently Linked</span>
                                                @endif
                                            </div>
                                            <span class="text-xs text-zinc-500">
                                                @if ($studentDetails->admission_number)
                                                    <span translate="no">{{ $studentDetails->admission_number }}</span>
                                                @endif
                                                @if (isAdmin())
                                                    &mdash; <span translate="no">{{ $studentDetails->institution?->name }}</span>
                                                @endif
                                            </span>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </x-stepper.section>

                <x-slot:actions>
                    <flux:button href="{{ route('parent.show', $parent->id) }}" wire:navigate.hover variant="subtle" class="font-semibold shadow-sm">
                        Cancel
                    </flux:button>
                    <flux:button type="submit" variant="primary" class="font-semibold shadow-sm px-6">
                        Save Changes
                    </flux:button>
                </x-slot:actions>

            </x-stepper.layout>
        </form>
    </div>

    @else
    <div class="flex flex-col gap-6 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">
        
        {{-- Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-2">
            <div>
                <flux:heading size="xl" class="font-bold tracking-tight text-zinc-900 dark:text-white">Create Parent</flux:heading>
                <flux:text class="text-zinc-500 text-sm mt-1">
                    Add a parent/guardian account and optionally link existing students to them.
                </flux:text>
            </div>
            
            <div class="flex items-center gap-3">
                <flux:button href="{{ route('parent.index') }}" wire:navigate.hover icon="arrow-left" variant="subtle" class="font-semibold shadow-sm">
                    Back to Parents
                </flux:button>
            </div>
        </div>

        @if ($errors->any())
            <div class="rounded-xl border border-red-200/50 bg-red-50/50 dark:bg-red-500/10 dark:border-red-500/20 p-4 flex items-start shadow-sm mb-6">
                <flux:icon icon="exclamation-circle" class="size-5 text-red-500 mr-3 mt-0.5" />
                <div class="text-sm text-red-800 dark:text-red-400">
                    <p class="font-semibold mb-1">{{ $errors->count() }} error(s) prevented this parent from being saved:</p>
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <form action="{{ route('parent.store') }}" method="POST">
            @csrf
            
            <x-stepper.layout defaultActive="account-info">
                
                <x-slot:sidebar>
                    <x-stepper.step id="account-info" title="Account Information" description="Name, email, and password." isLast="false" />
                    <x-stepper.step id="contact-info" title="Contact Details" description="Phone number and occupation." isLast="false" />
                    <x-stepper.step id="link-children" title="Link Children" description="Select students for this parent." isLast="true" />
                </x-slot:sidebar>

                <x-stepper.section id="account-info" title="Account Information" description="Set up the basic account details for the parent.">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <flux:input type="text" name="name" label="Full Name" value="{{ old('name') }}" required />
                        <flux:input type="email" name="email" label="Email" value="{{ old('email') }}" required />
                        <div class="md:col-span-2">
                            <flux:input type="password" name="password" label="Password" required />
                        </div>
                    </div>
                </x-stepper.section>

                <x-stepper.section id="contact-info" title="Contact Details" description="Provide contact and occupation details for the parent.">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <flux:input type="text" name="parent_phone" label="Phone" value="{{ old('parent_phone') }}" />
                        <flux:input type="text" name="parent_occupation" label="Occupation" value="{{ old('parent_occupation') }}" />
                    </div>
                </x-stepper.section>

                <x-stepper.section id="link-children" title="Link Children" description="Select the students you want to link to this parent account. Only unlinked students are shown.">
                    <div class="bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6">
                        @if ($this->availableStudents->isEmpty())
                            <div class="flex flex-col items-center justify-center py-8">
                                <flux:icon icon="users" class="size-10 text-zinc-300 dark:text-zinc-600 mb-3" />
                                <p class="text-sm font-medium text-zinc-900 dark:text-zinc-200">No unlinked students available.</p>
                                <p class="text-xs text-zinc-500 mt-1">All current students are already linked to a parent.</p>
                            </div>
                        @else
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 max-h-80 overflow-y-auto pr-2">
                                @foreach ($this->availableStudents as $studentDetails)
                                    <label class="flex items-start gap-3 p-3 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 cursor-pointer hover:border-blue-500 dark:hover:border-blue-500 transition-colors">
                                        <input type="checkbox" name="children[]" value="{{ $studentDetails->student_id }}"
                                            @checked(collect(old('children'))->contains($studentDetails->student_id))
                                            class="mt-1 border-zinc-300 text-blue-600 focus:ring-blue-500 rounded">
                                        <div class="flex flex-col">
                                            <span class="text-sm font-medium text-zinc-900 dark:text-white"><span translate="no">{{ $studentDetails->student?->name }}</span></span>
                                            <span class="text-xs text-zinc-500">
                                                @if ($studentDetails->admission_number)
                                                    <span translate="no">{{ $studentDetails->admission_number }}</span>
                                                @endif
                                                @if (isAdmin())
                                                    &mdash; <span translate="no">{{ $studentDetails->institution?->name }}</span>
                                                @endif
                                            </span>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </x-stepper.section>

                <x-slot:actions>
                    <flux:button href="{{ route('parent.index') }}" wire:navigate.hover variant="subtle" class="font-semibold shadow-sm">
                        Cancel
                    </flux:button>
                    <flux:button type="submit" variant="primary" class="font-semibold shadow-sm px-6">
                        Save Parent
                    </flux:button>
                </x-slot:actions>

            </x-stepper.layout>
        </form>
    </div>
    @endif
</div>
