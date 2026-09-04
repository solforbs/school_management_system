<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Institution\Models\Institution;
use Modules\Teacher\Actions\SaveTeacher;
use Modules\Teacher\Models\TeacherDetails;

new #[Title('Teacher')] class extends Component {
    public ?TeacherDetails $teacher = null;
    public ?int $teacherId = null;
    public function mount(?int $teacherId = null): void
    {
        if ($teacherId === null) {
            abort_unless(auth()->user()->can('create teacher'), 403);

            return;
        }

        abort_unless(auth()->user()->can('edit teacher'), 403);

        $this->teacher = TeacherDetails::with('teacher')->where('teacher_id', $teacherId)->firstOrFail();
        $this->authorizeAccessTo($this->teacher);
    }

    #[Computed]
    public function institutions()
    {
        return isAdmin() ? Institution::all() : collect([currentInstitution()])->filter();
    }

    private function authorizeAccessTo(TeacherDetails $details): void
    {
        if (isAdmin()) {
            return;
        }

        abort_unless($details->institution_id === currentInstitution()?->id, 403);
    }
    public function teacherSubjectIds(): array
    {
        return teacherSubjects($this->teacherId)->pluck('subject_id')->toArray();
    }

    public function teacherClassIds(): array
    {
        return teacherClasses($this->teacherId)->pluck('class_id')->toArray();
    }
}; ?>
<div>
    @if ($teacher)
        <div class="flex flex-col gap-6 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">

            {{-- Header Section --}}
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-2">
                <div>
                    <flux:heading size="xl" class="font-bold tracking-tight text-zinc-900 dark:text-white">Edit
                        Teacher Profile</flux:heading>
                    <flux:text class="text-zinc-500 text-sm mt-1">
                        Update employment and contact information for <span
                            class="font-semibold text-zinc-700 dark:text-zinc-300">{{ $teacher->teacher?->name }}</span>.
                    </flux:text>
                </div>

                <div class="flex items-center gap-3">
                    <flux:button href="{{ route('teacher.show', $teacher->teacher_id) }}" wire:navigate.hover
                        icon="arrow-left" variant="subtle" class="font-semibold shadow-sm">
                        Back to Profile
                    </flux:button>
                </div>
            </div>

            @if (isset($errors) && $errors->any())
                <div
                    class="rounded-xl border border-red-200/50 bg-red-50/50 dark:bg-red-500/10 dark:border-red-500/20 p-4 flex items-start shadow-sm mb-6">
                    <flux:icon icon="exclamation-circle" class="size-5 text-red-500 mr-3 mt-0.5" />
                    <div class="text-sm text-red-800 dark:text-red-400">
                        <p class="font-semibold mb-1">{{ $errors->count() }} error(s) prevented this teacher from being
                            updated:</p>
                        <ul class="list-disc list-inside space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <form action="{{ route('teacher.update', $teacher->teacher_id) }}" method="POST">
                @csrf
                @method('PUT')

                <x-stepper.layout defaultActive="account-info">

                    <x-slot:sidebar>
                        <x-stepper.step id="account-info" title="Account Setup" description="Name, email, and password."
                            isLast="false" />
                        <x-stepper.step id="employment" title="Employment" description="Institution and role details."
                            isLast="false" />
                        <x-stepper.step id="profile" title="Profile Details" description="Contact info and notes."
                            isLast="true" />
                    </x-slot:sidebar>

                    {{-- Step 1: Account Info --}}
                    <x-stepper.section id="account-info" title="System Account"
                        description="Update login credentials for this teacher.">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="md:col-span-2">
                                <flux:input type="text" name="name" label="Full Name"
                                    value="{{ old('name', $teacher->teacher?->name) }}" placeholder="e.g. Jane Doe"
                                    required />
                            </div>

                            <flux:input type="email" name="email" label="Email Address"
                                value="{{ old('email', $teacher->teacher?->email) }}" placeholder="jane@example.com"
                                autocomplete="new-password" required />

                            <flux:input type="password" name="password" label="New Password (optional)"
                                placeholder="Leave blank to keep existing password" autocomplete="new-password" />
                        </div>
                    </x-stepper.section>

                    {{-- Step 2: Employment Details --}}
                    <x-stepper.section id="employment" title="Employment Record"
                        description="Manage institution and academic role details.">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="md:col-span-2">
                                <flux:select name="institution_id" label="Assigned Institution" required>
                                    <flux:select.option value="">Select Institution...</flux:select.option>
                                    @foreach ($this->institutions as $institution)
                                        <flux:select.option value="{{ $institution->id }}"
                                            :selected="old('institution_id', $teacher->institution_id) == $institution->id">
                                            {{ $institution->name }}
                                        </flux:select.option>
                                    @endforeach
                                </flux:select>
                            </div>

                            <flux:input type="text" name="employee_number" label="Employee Number / ID"
                                value="{{ old('employee_number', $teacher->employee_number) }}"
                                placeholder="e.g. EMP-00123" />

                            <flux:input type="text" name="department" label="Primary Department / Subject"
                                value="{{ old('department', $teacher->department) }}"
                                placeholder="e.g. Mathematics, Sciences" />
                            <flux:card
                                class="md:col-span-2 bg-sky-50/50 dark:bg-sky-900/10 border border-sky-200/50 dark:border-sky-800/30 rounded-xl p-4 flex flex-col gap-3">

                                <flux:icon icon="information-circle" class="size-5 text-sky-500 mt-0.5 shrink-0" />

                                <div class="text-sm text-sky-800 dark:text-sky-300">
                                    Select the subjects that this teacher will be responsible for teaching.
                                    This helps in organizing classes and timetables effectively.
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-2">
                                    @foreach (institutionSubjects() as $subject)
                                        <flux:checkbox name="subjects[]" value="{{ $subject->id }}"
                                            label="{{ $subject->name }}"
                                            :checked="in_array($subject->id, $this->teacherSubjectIds())" />
                                    @endforeach
                                </div>

                            </flux:card>
                            <flux:card
                                class="md:col-span-2 bg-emerald-50/50 dark:bg-emerald-900/10 border border-emerald-200/50 dark:border-emerald-800/30 rounded-xl p-4 flex flex-col gap-3">

                                <flux:icon icon="academic-cap" class="size-5 text-emerald-500 mt-0.5 shrink-0" />

                                <div class="text-sm text-emerald-800 dark:text-emerald-300">
                                    Select the classes this teacher takes. When entering results they'll
                                    pick one of these classes first, then one of their subjects.
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-2">
                                    @foreach (institutionClasses() as $schoolClass)
                                        <flux:checkbox name="classes[]" value="{{ $schoolClass->id }}"
                                            label="{{ $schoolClass->name }}"
                                            :checked="in_array($schoolClass->id, $this->teacherClassIds())" />
                                    @endforeach
                                </div>

                            </flux:card>
                            <flux:input type="text" name="qualification" label="Highest Qualification"
                                value="{{ old('qualification', $teacher->qualification) }}"
                                placeholder="e.g. BSc. Education, PhD Physics" />

                            <flux:input type="date" name="hire_date" label="Hire Date"
                                value="{{ old('hire_date', $teacher->hire_date ? \Carbon\Carbon::parse($teacher->hire_date)->format('Y-m-d') : '') }}" />
                        </div>
                    </x-stepper.section>

                    {{-- Step 3: Profile Details --}}
                    <x-stepper.section id="profile" title="Profile Details"
                        description="Contact information and current status.">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <flux:input type="text" name="phone" label="Phone Number"
                                value="{{ old('phone', $teacher->phone) }}" placeholder="+1 (555) 000-0000" />

                            <flux:select name="status" label="Employment Status">
                                @foreach (['active', 'on_leave', 'suspended', 'resigned', 'terminated'] as $status)
                                    <flux:select.option value="{{ $status }}"
                                        :selected="old('status', $teacher->status) === $status">
                                        {{ ucfirst(str_replace('_', ' ', $status)) }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>

                            <div class="md:col-span-2">
                                <flux:checkbox name="is_active" value="1" label="Account Active"
                                    description="Uncheck to temporarily disable system access for this teacher."
                                    :checked="old('is_active', $teacher->is_active)" />
                            </div>

                            <div class="md:col-span-2">
                                <flux:textarea name="address" rows="2" label="Home Address"
                                    placeholder="Full residential address...">{{ old('address', $teacher->address) }}
                                </flux:textarea>
                            </div>

                            <div class="md:col-span-2">
                                <flux:textarea name="notes" rows="3" label="Administrative Notes"
                                    placeholder="Any internal notes... (Optional)">{{ old('notes', $teacher->notes) }}
                                </flux:textarea>
                            </div>
                        </div>
                    </x-stepper.section>

                    <x-slot:actions>
                        <flux:button href="{{ route('teacher.show', $teacher->teacher_id) }}" wire:navigate.hover
                            variant="subtle" class="font-semibold shadow-sm">
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
                    <flux:heading size="xl" class="font-bold tracking-tight text-zinc-900 dark:text-white">
                        Onboard New Teacher</flux:heading>
                    <flux:text class="text-zinc-500 text-sm mt-1">
                        Create a new teacher account and set up their employment profile.
                    </flux:text>
                </div>

                <div class="flex items-center gap-3">
                    <flux:button href="{{ route('teacher.index') }}" wire:navigate.hover icon="arrow-left"
                        variant="subtle" class="font-semibold shadow-sm">
                        Back to Directory
                    </flux:button>
                </div>
            </div>

            @if (isset($errors) && $errors->any())
                <div
                    class="rounded-xl border border-red-200/50 bg-red-50/50 dark:bg-red-500/10 dark:border-red-500/20 p-4 flex items-start shadow-sm mb-6">
                    <flux:icon icon="exclamation-circle" class="size-5 text-red-500 mr-3 mt-0.5" />
                    <div class="text-sm text-red-800 dark:text-red-400">
                        <p class="font-semibold mb-1">{{ $errors->count() }} error(s) prevented this teacher from
                            being saved:</p>
                        <ul class="list-disc list-inside space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <form action="{{ route('teacher.store') }}" method="POST">
                @csrf

                <x-stepper.layout defaultActive="account-info">

                    <x-slot:sidebar>
                        <x-stepper.step id="account-info" title="Account Setup"
                            description="Name, email, and password." isLast="false" />
                        <x-stepper.step id="employment" title="Employment"
                            description="Institution and role details." isLast="false" />
                        <x-stepper.step id="profile" title="Profile Details" description="Contact info and notes."
                            isLast="true" />
                    </x-slot:sidebar>

                    {{-- Step 1: Account Info --}}
                    <x-stepper.section id="account-info" title="System Account"
                        description="Set up the login credentials for this teacher.">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="md:col-span-2">
                                <flux:input type="text" name="name" label="Full Name"
                                    value="{{ old('name') }}" placeholder="e.g. Jane Doe" required />
                            </div>

                            <flux:input type="email" name="email" label="Email Address"
                                value="{{ old('email') }}" placeholder="jane@example.com"
                                autocomplete="new-password" required />

                            <flux:input type="password" name="password" label="Temporary Password"
                                autocomplete="new-password" required />

                            <div class="md:col-span-2">
                                <div
                                    class="bg-sky-50/50 dark:bg-sky-900/10 border border-sky-200/50 dark:border-sky-800/30 rounded-xl p-4 flex items-start gap-3">
                                    <flux:icon icon="shield-check" class="size-5 text-sky-500 mt-0.5 shrink-0" />
                                    <div class="text-sm text-sky-800 dark:text-sky-300">
                                        This creates a dedicated system user account. The teacher will use this email
                                        and password to log in and manage their classes and timetables.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </x-stepper.section>

                    {{-- Step 2: Employment Details --}}
                    <x-stepper.section id="employment" title="Employment Record"
                        description="Assign this teacher to an institution and define their academic role.">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="md:col-span-2">
                                <flux:select name="institution_id" label="Assigned Institution" required>
                                    <flux:select.option value="">Select Institution...</flux:select.option>
                                    @foreach ($this->institutions as $institution)
                                        <flux:select.option value="{{ $institution->id }}"
                                            :selected="old('institution_id') == $institution->id">
                                            {{ $institution->name }}
                                        </flux:select.option>
                                    @endforeach
                                </flux:select>
                            </div>

                            <flux:input type="text" name="employee_number" label="Employee Number / ID"
                                value="{{ old('employee_number') }}" placeholder="e.g. EMP-00123" />

                            <flux:input type="text" name="department" label="Primary Department / Subject"
                                value="{{ old('department') }}" placeholder="e.g. Mathematics, Sciences" />
                            <flux:card
                                class="md:col-span-2 bg-sky-50/50 dark:bg-sky-900/10 border border-sky-200/50 dark:border-sky-800/30 rounded-xl p-4 flex flex-col gap-3">
                                <flux:icon icon="information-circle" class="size-5 text-sky-500 mt-0.5 shrink-0" />
                                <div class="text-sm text-sky-800 dark:text-sky-300">
                                    Select the subjects that this teacher will be responsible for teaching. This helps
                                    in
                                    organizing classes and timetables effectively.
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-2">
                                    @foreach (institutionSubjects() as $subject)
                                        {{-- create a checkbox for each subject that the teacher teaches --}}
                                        <flux:checkbox name="subjects[]" value="{{ $subject->id }}"
                                            label="{{ $subject->name }}"
                                            :checked="in_array($subject->id, old('subjects', []))" />
                                    @endforeach
                                </div>
                            </flux:card>
                            <flux:card
                                class="md:col-span-2 bg-emerald-50/50 dark:bg-emerald-900/10 border border-emerald-200/50 dark:border-emerald-800/30 rounded-xl p-4 flex flex-col gap-3">
                                <flux:icon icon="academic-cap" class="size-5 text-emerald-500 mt-0.5 shrink-0" />
                                <div class="text-sm text-emerald-800 dark:text-emerald-300">
                                    Select the classes this teacher takes. When entering results they'll
                                    pick one of these classes first, then one of their subjects.
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-2">
                                    @foreach (institutionClasses() as $schoolClass)
                                        {{-- create a checkbox for each class that the teacher teaches --}}
                                        <flux:checkbox name="classes[]" value="{{ $schoolClass->id }}"
                                            label="{{ $schoolClass->name }}"
                                            :checked="in_array($schoolClass->id, old('classes', []))" />
                                    @endforeach
                                </div>
                            </flux:card>

                            <flux:input type="text" name="qualification" label="Highest Qualification"
                                value="{{ old('qualification') }}" placeholder="e.g. BSc. Education, PhD Physics" />
                            <flux:input type="date" name="hire_date" label="Hire Date"
                                value="{{ old('hire_date') }}" />
                        </div>
                    </x-stepper.section>

                    {{-- Step 3: Profile Details --}}
                    <x-stepper.section id="profile" title="Profile Details"
                        description="Contact information and current status.">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <flux:input type="text" name="phone" label="Phone Number"
                                value="{{ old('phone') }}" placeholder="+1 (555) 000-0000" />

                            <flux:select name="status" label="Employment Status">
                                @foreach (['active', 'on_leave', 'suspended', 'resigned', 'terminated'] as $status)
                                    <flux:select.option value="{{ $status }}"
                                        :selected="old('status', 'active') === $status">
                                        {{ ucfirst(str_replace('_', ' ', $status)) }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>

                            <div class="md:col-span-2">
                                <flux:checkbox name="is_active" value="1" label="Account Active"
                                    description="Uncheck to temporarily disable system access for this teacher."
                                    checked />
                            </div>

                            <div class="md:col-span-2">
                                <flux:textarea name="address" rows="2" label="Home Address"
                                    placeholder="Full residential address...">{{ old('address') }}</flux:textarea>
                            </div>

                            <div class="md:col-span-2">
                                <flux:textarea name="notes" rows="3" label="Administrative Notes"
                                    placeholder="Any internal notes... (Optional)">{{ old('notes') }}</flux:textarea>
                            </div>
                        </div>
                    </x-stepper.section>

                    <x-slot:actions>
                        <flux:button href="{{ route('teacher.index') }}" wire:navigate.hover variant="subtle"
                            class="font-semibold shadow-sm">
                            Cancel
                        </flux:button>
                        <flux:button type="submit" variant="primary" class="font-semibold shadow-sm px-6">
                            Save Teacher
                        </flux:button>
                    </x-slot:actions>

                </x-stepper.layout>
            </form>
        </div>
    @endif
</div>
