<x-layouts::app :title="__('Edit Teacher')">
    <div class="flex flex-col gap-6 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">
        
        {{-- Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-2">
            <div>
                <flux:heading size="xl" class="font-bold tracking-tight text-zinc-900 dark:text-white">Edit Teacher Profile</flux:heading>
                <flux:text class="text-zinc-500 text-sm mt-1">
                    Update employment and contact information for <span class="font-semibold text-zinc-700 dark:text-zinc-300">{{ $teacher->teacher->name }}</span>.
                </flux:text>
            </div>
            
            <div class="flex items-center gap-3">
                <flux:button href="{{ route('teacher.show', $teacher->teacher_id) }}" icon="arrow-left" variant="subtle" class="font-semibold shadow-sm">
                    Back to Profile
                </flux:button>
            </div>
        </div>

        @if (isset($errors) && $errors->any())
            <div class="rounded-xl border border-red-200/50 bg-red-50/50 dark:bg-red-500/10 dark:border-red-500/20 p-4 flex items-start shadow-sm mb-6">
                <flux:icon icon="exclamation-circle" class="size-5 text-red-500 mr-3 mt-0.5" />
                <div class="text-sm text-red-800 dark:text-red-400">
                    <p class="font-semibold mb-1">{{ $errors->count() }} error(s) prevented this teacher from being updated:</p>
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

            <x-stepper.layout defaultActive="employment">
                
                <x-slot:sidebar>
                    <x-stepper.step id="employment" title="Employment" description="Institution and role details." isLast="false" />
                    <x-stepper.step id="profile" title="Profile Details" description="Contact info and notes." isLast="false" />
                    <x-stepper.step id="account-info" title="System Account" description="Update name or credentials." isLast="true" />
                </x-slot:sidebar>

                {{-- Step 1: Employment Details --}}
                <x-stepper.section id="employment" title="Employment Record" description="Update this teacher's institution and academic role.">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <flux:select name="institution_id" label="Assigned Institution" required>
                                @foreach ($institutions as $institution)
                                    <flux:select.option value="{{ $institution->id }}" :selected="old('institution_id', $teacher->institution_id) == $institution->id">
                                        {{ $institution->name }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                        
                        <flux:input type="text" name="employee_number" label="Employee Number / ID" value="{{ old('employee_number', $teacher->employee_number) }}" placeholder="e.g. EMP-00123" />
                        
                        <flux:input type="text" name="department" label="Primary Department / Subject" value="{{ old('department', $teacher->department) }}" placeholder="e.g. Mathematics, Sciences" />
                        
                        <flux:input type="text" name="qualification" label="Highest Qualification" value="{{ old('qualification', $teacher->qualification) }}" placeholder="e.g. BSc. Education, PhD Physics" />
                        
                        <flux:input type="date" name="hire_date" label="Hire Date" value="{{ old('hire_date', $teacher->hire_date?->format('Y-m-d')) }}" />
                    </div>
                </x-stepper.section>

                {{-- Step 2: Profile Details --}}
                <x-stepper.section id="profile" title="Profile Details" description="Update contact information and current status.">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <flux:input type="text" name="phone" label="Phone Number" value="{{ old('phone', $teacher->phone) }}" placeholder="+1 (555) 000-0000" />
                        
                        <flux:select name="status" label="Employment Status">
                            @foreach (['active', 'on_leave', 'suspended', 'resigned', 'terminated'] as $status)
                                <flux:select.option value="{{ $status }}" :selected="old('status', $teacher->status) === $status">
                                    {{ ucfirst(str_replace('_', ' ', $status)) }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>

                        <div class="md:col-span-2">
                            <flux:checkbox name="is_active" value="1" label="Account Active" description="Uncheck to temporarily disable system access for this teacher." :checked="old('is_active', $teacher->is_active)" />
                        </div>
                        
                        <div class="md:col-span-2">
                            <flux:textarea name="address" rows="2" label="Home Address" placeholder="Full residential address...">{{ old('address', $teacher->address) }}</flux:textarea>
                        </div>
                        
                        <div class="md:col-span-2">
                            <flux:textarea name="notes" rows="3" label="Administrative Notes" placeholder="Any internal notes... (Optional)">{{ old('notes', $teacher->notes) }}</flux:textarea>
                        </div>
                    </div>
                </x-stepper.section>

                {{-- Step 3: Account Info --}}
                <x-stepper.section id="account-info" title="System Account" description="Update login credentials. Leave password blank if you do not wish to change it.">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <flux:input type="text" name="name" label="Full Name" value="{{ old('name', $teacher->teacher->name) }}" required />
                        </div>
                        
                        <flux:input type="email" name="email" label="Email Address" value="{{ old('email', $teacher->teacher->email) }}" autocomplete="new-password" required />
                        
                        <flux:input type="password" name="password" label="New Password (Optional)" description="Leave blank to keep current password." autocomplete="new-password" />
                        
                        <div class="md:col-span-2">
                            <div class="bg-amber-50/50 dark:bg-amber-900/10 border border-amber-200/50 dark:border-amber-800/30 rounded-xl p-4 flex items-start gap-3">
                                <flux:icon icon="exclamation-triangle" class="size-5 text-amber-500 mt-0.5 shrink-0" />
                                <div class="text-sm text-amber-800 dark:text-amber-300">
                                    Changing the email or password here will immediately affect the teacher's ability to log in. Please ensure you communicate any new credentials to them securely.
                                </div>
                            </div>
                        </div>
                    </div>
                </x-stepper.section>

                <x-slot:actions>
                    <flux:button href="{{ route('teacher.show', $teacher->teacher_id) }}" variant="subtle" class="font-semibold shadow-sm">
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
