<x-layouts::app :title="__('Add Staff')">
    <div class="flex flex-col gap-6 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">
        
        {{-- Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-2">
            <div>
                <flux:heading size="xl" class="font-bold tracking-tight text-zinc-900 dark:text-white">Add Staff Member</flux:heading>
                <flux:text class="text-zinc-500 text-sm mt-1">
                    Register a new staff member and configure their access rights.
                </flux:text>
            </div>
            
            <div class="flex items-center gap-3">
                <flux:button href="{{ route('staff.index') }}" icon="arrow-left" variant="subtle" class="font-semibold shadow-sm">
                    Back to Staff
                </flux:button>
            </div>
        </div>

        @if ($errors->any())
            <div class="rounded-xl border border-red-200/50 bg-red-50/50 dark:bg-red-500/10 dark:border-red-500/20 p-4 flex items-start shadow-sm mb-6">
                <flux:icon icon="exclamation-circle" class="size-5 text-red-500 mr-3 mt-0.5" />
                <div class="text-sm text-red-800 dark:text-red-400">
                    <p class="font-semibold mb-1">{{ $errors->count() }} error(s) prevented this staff member from being saved:</p>
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <form action="{{ route('staff.store') }}" method="POST">
            @csrf

            <x-stepper.layout defaultActive="personal-info">
                
                <x-slot:sidebar>
                    <x-stepper.step id="personal-info" title="Personal Info" description="Name, contact details." isLast="false" />
                    <x-stepper.step id="employment-details" title="Employment" description="Role, salary, status." isLast="false" />
                    <x-stepper.step id="system-access" title="System Access" description="Login credentials." isLast="true" />
                </x-slot:sidebar>

                {{-- Step 1: Personal Information --}}
                <x-stepper.section id="personal-info" title="Personal Information" description="Provide the basic contact information for the new staff member.">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <flux:input type="text" name="name" label="Full Name" value="{{ old('name') }}" placeholder="e.g. Jane Doe" required />
                        </div>
                        <flux:input type="email" name="email" label="Email Address" value="{{ old('email') }}" placeholder="e.g. jane@example.com" />
                        <flux:input type="text" name="phone" label="Phone Number" value="{{ old('phone') }}" placeholder="e.g. +1 234 567 8900" />
                        <div class="md:col-span-2">
                            <flux:textarea name="address" rows="3" label="Physical Address" placeholder="Enter full address...">{{ old('address') }}</flux:textarea>
                        </div>
                        <div class="md:col-span-2">
                            <flux:textarea name="notes" rows="2" label="Additional Notes" placeholder="Any private notes about this staff member...">{{ old('notes') }}</flux:textarea>
                        </div>
                    </div>
                </x-stepper.section>

                {{-- Step 2: Employment Details --}}
                <x-stepper.section id="employment-details" title="Employment Details" description="Define their role, department, and compensation within the institution.">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <flux:select name="institution_id" label="Institution">
                            <flux:select.option value="">Select Institution</flux:select.option>
                            @foreach ($institutions as $institution)
                                <flux:select.option value="{{ $institution->id }}" :selected="old('institution_id') == $institution->id">
                                    {{ $institution->name }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                        
                        <flux:input type="text" name="staff_number" label="Staff Number" value="{{ old('staff_number') }}" placeholder="e.g. STF-001" />
                        
                        <flux:input type="text" name="job_title" label="Job Title" value="{{ old('job_title') }}" placeholder="e.g. Senior Teacher" />
                        
                        <flux:input type="text" name="department" label="Department" value="{{ old('department') }}" placeholder="e.g. Science" />
                        
                        <flux:select name="employment_type" label="Employment Type">
                            @foreach (['full_time', 'part_time', 'contract', 'volunteer'] as $type)
                                <flux:select.option value="{{ $type }}" :selected="old('employment_type', 'full_time') === $type">
                                    {{ ucfirst(str_replace('_', ' ', $type)) }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                        
                        <flux:input type="date" name="hire_date" label="Hire Date" value="{{ old('hire_date') }}" />
                        
                        <flux:input type="number" step="0.01" min="0" name="salary" label="Monthly Salary" value="{{ old('salary') }}" placeholder="0.00" />
                        
                        <div class="flex gap-4">
                            <div class="flex-1">
                                <flux:select name="status" label="Status">
                                    @foreach (['active', 'on_leave', 'suspended', 'resigned', 'terminated'] as $status)
                                        <flux:select.option value="{{ $status }}" :selected="old('status', 'active') === $status">
                                            {{ ucfirst(str_replace('_', ' ', $status)) }}
                                        </flux:select.option>
                                    @endforeach
                                </flux:select>
                            </div>
                            <div class="flex items-end pb-2">
                                <flux:checkbox name="is_active" value="1" label="Is Active Profile" checked />
                            </div>
                        </div>
                    </div>
                </x-stepper.section>

                {{-- Step 3: System Access --}}
                <x-stepper.section id="system-access" title="System Access" description="Configure login credentials and permissions for the platform.">
                    <div class="bg-indigo-50/50 dark:bg-indigo-900/10 border border-indigo-200/50 dark:border-indigo-800/30 rounded-xl p-4 flex items-start gap-3 mb-6">
                        <flux:icon icon="shield-check" class="size-5 text-indigo-500 mt-0.5 shrink-0" />
                        <div class="text-sm text-indigo-800 dark:text-indigo-300">
                            <strong>Optional.</strong> Tick the box below to generate a user account for this staff member.
                            Different roles grant different permissions (e.g. Accountants can only manage payroll and fees).
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <flux:checkbox name="create_account" value="1" label="Give this staff member a system login" :checked="(bool) old('create_account')" />
                        </div>
                        
                        <flux:select name="system_role" label="System Role">
                            @foreach ($systemRoles as $role)
                                <flux:select.option value="{{ $role }}" :selected="old('system_role') === $role">
                                    {{ $role }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                        
                        <flux:input type="password" name="password" label="Temporary Password" description="Required when creating a login." placeholder="Enter a secure password..." />
                    </div>
                </x-stepper.section>

                <x-slot:actions>
                    <flux:button href="{{ route('staff.index') }}" variant="subtle" class="font-semibold shadow-sm">
                        Cancel
                    </flux:button>
                    <flux:button type="submit" variant="primary" class="font-semibold shadow-sm px-6">
                        Save Staff Member
                    </flux:button>
                </x-slot:actions>

            </x-stepper.layout>
        </form>
    </div>
</x-layouts::app>
