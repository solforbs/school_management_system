<x-layouts::app :title="__('Edit Parent')">
    <div class="flex flex-col gap-6 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">
        
        {{-- Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-2">
            <div>
                <flux:heading size="xl" class="font-bold tracking-tight text-zinc-900 dark:text-white">Edit Parent</flux:heading>
                <flux:text class="text-zinc-500 text-sm mt-1">
                    Update information and linked students for {{ $parent->name }}.
                </flux:text>
            </div>
            
            <div class="flex items-center gap-3">
                <flux:button href="{{ route('parent.show', $parent->id) }}" icon="arrow-left" variant="subtle" class="font-semibold shadow-sm">
                    Back to Details
                </flux:button>
            </div>
        </div>

        @if ($errors->any())
            <div class="rounded-xl border border-red-200/50 bg-red-50/50 dark:bg-red-500/10 dark:border-red-500/20 p-4 flex items-start shadow-sm mb-6">
                <flux:icon icon="exclamation-circle" class="size-5 text-red-500 mr-3 mt-0.5" />
                <div class="text-sm text-red-800 dark:text-red-400">
                    <p class="font-semibold mb-1">{{ $errors->count() }} error(s) prevented these changes from being saved:</p>
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
                    <x-stepper.step id="account-info" title="Account Information" description="Name and email." isLast="false" />
                    <x-stepper.step id="contact-info" title="Contact Details" description="Phone number and occupation." isLast="false" />
                    <x-stepper.step id="link-children" title="Link Children" description="Manage linked students." isLast="true" />
                </x-slot:sidebar>

                <x-stepper.section id="account-info" title="Account Information" description="Update the basic account details for the parent.">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <flux:input type="text" name="name" label="Full Name" value="{{ old('name', $parent->name) }}" required />
                        <flux:input type="email" name="email" label="Email" value="{{ old('email', $parent->email) }}" required />
                    </div>
                </x-stepper.section>

                <x-stepper.section id="contact-info" title="Contact Details" description="Update contact and occupation details for the parent.">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <flux:input type="text" name="parent_phone" label="Phone" value="{{ old('parent_phone', $parent->parent?->parent_phone) }}" />
                        <flux:input type="text" name="parent_occupation" label="Occupation" value="{{ old('parent_occupation', $parent->parent?->parent_occupation) }}" />
                    </div>
                </x-stepper.section>

                <x-stepper.section id="link-children" title="Link Children" description="Manage the students linked to this parent account. Unchecking a student unlinks them from this parent.">
                    <div class="bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6">
                        @php
                            $linkedIds = old('children', $parent->children->pluck('student_id')->all());
                        @endphp

                        @if ($parent->children->isEmpty() && $availableStudents->isEmpty())
                            <div class="flex flex-col items-center justify-center py-8">
                                <flux:icon icon="users" class="size-10 text-zinc-300 dark:text-zinc-600 mb-3" />
                                <p class="text-sm font-medium text-zinc-900 dark:text-zinc-200">No students available to link.</p>
                                <p class="text-xs text-zinc-500 mt-1">There are no unlinked students in the system.</p>
                            </div>
                        @else
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 max-h-80 overflow-y-auto pr-2">
                                
                                {{-- Currently linked students --}}
                                @foreach ($parent->children as $child)
                                    <label class="flex items-start gap-3 p-3 rounded-lg border border-blue-200 dark:border-blue-900/50 bg-blue-50/50 dark:bg-blue-900/10 cursor-pointer hover:border-blue-500 dark:hover:border-blue-500 transition-colors">
                                        <input type="checkbox" name="children[]" value="{{ $child->student_id }}"
                                            @checked(in_array($child->student_id, $linkedIds))
                                            class="mt-1 border-zinc-300 text-blue-600 focus:ring-blue-500 rounded">
                                        <div class="flex flex-col">
                                            <span class="text-sm font-semibold text-blue-900 dark:text-blue-100">{{ $child->student?->name }}</span>
                                            <span class="text-xs text-blue-700 dark:text-blue-300">
                                                @if ($child->admission_number)
                                                    {{ $child->admission_number }}
                                                @endif
                                            </span>
                                        </div>
                                    </label>
                                @endforeach

                                {{-- Available unlinked students --}}
                                @foreach ($availableStudents as $studentDetails)
                                    <label class="flex items-start gap-3 p-3 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 cursor-pointer hover:border-blue-500 dark:hover:border-blue-500 transition-colors">
                                        <input type="checkbox" name="children[]" value="{{ $studentDetails->student_id }}"
                                            @checked(in_array($studentDetails->student_id, $linkedIds))
                                            class="mt-1 border-zinc-300 text-blue-600 focus:ring-blue-500 rounded">
                                        <div class="flex flex-col">
                                            <span class="text-sm font-medium text-zinc-900 dark:text-white">{{ $studentDetails->student?->name }}</span>
                                            <span class="text-xs text-zinc-500">
                                                @if ($studentDetails->admission_number)
                                                    {{ $studentDetails->admission_number }}
                                                @endif
                                                @if (isAdmin())
                                                    &mdash; {{ $studentDetails->institution?->name }}
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
                    <flux:button href="{{ route('parent.show', $parent->id) }}" variant="subtle" class="font-semibold shadow-sm">
                        Cancel
                    </flux:button>
                    <flux:button type="submit" variant="primary" class="font-semibold shadow-sm px-6">
                        Save Changes
                    </flux:button>
                </x-slot:actions>

            </x-stepper.layout>
        </form>
    </div>
</x-layouts::app>
