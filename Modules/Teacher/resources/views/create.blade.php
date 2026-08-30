<x-layouts::app :title="__('Create Teacher')">
    <div class="flex flex-col gap-6 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">
        
        {{-- Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-2">
            <div>
                <flux:heading size="xl" class="font-bold tracking-tight text-zinc-900 dark:text-white">Onboard New Teacher</flux:heading>
                <flux:text class="text-zinc-500 text-sm mt-1">
                    Create a new teacher account and set up their employment profile.
                </flux:text>
            </div>
            
            <div class="flex items-center gap-3">
                <flux:button href="{{ route('teacher.index') }}" icon="arrow-left" variant="subtle" class="font-semibold shadow-sm">
                    Back to Directory
                </flux:button>
            </div>
        </div>

        @if (isset($errors) && $errors->any())
            <div class="rounded-xl border border-red-200/50 bg-red-50/50 dark:bg-red-500/10 dark:border-red-500/20 p-4 flex items-start shadow-sm mb-6">
                <flux:icon icon="exclamation-circle" class="size-5 text-red-500 mr-3 mt-0.5" />
                <div class="text-sm text-red-800 dark:text-red-400">
                    <p class="font-semibold mb-1">{{ $errors->count() }} error(s) prevented this teacher from being saved:</p>
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
                    <x-stepper.step id="account-info" title="Account Setup" description="Name, email, and password." isLast="false" />
                    <x-stepper.step id="employment" title="Employment" description="Institution and role details." isLast="false" />
                    <x-stepper.step id="profile" title="Profile Details" description="Contact info and notes." isLast="true" />
                </x-slot:sidebar>

                {{-- Step 1: Account Info --}}
                <x-stepper.section id="account-info" title="System Account" description="Set up the login credentials for this teacher.">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <flux:input type="text" name="name" label="Full Name" value="{{ old('name') }}" placeholder="e.g. Jane Doe" required />
                        </div>
                        
                        <flux:input type="email" name="email" label="Email Address" value="{{ old('email') }}" placeholder="jane@example.com" autocomplete="new-password" required />
                        
                        <flux:input type="password" name="password" label="Temporary Password" autocomplete="new-password" required />
                        
                        <div class="md:col-span-2">
                            <div class="bg-sky-50/50 dark:bg-sky-900/10 border border-sky-200/50 dark:border-sky-800/30 rounded-xl p-4 flex items-start gap-3">
                                <flux:icon icon="shield-check" class="size-5 text-sky-500 mt-0.5 shrink-0" />
                                <div class="text-sm text-sky-800 dark:text-sky-300">
                                    This creates a dedicated system user account. The teacher will use this email and password to log in and manage their classes and timetables.
                                </div>
                            </div>
                        </div>
                    </div>
                </x-stepper.section>

                {{-- Step 2: Employment Details --}}
                <x-stepper.section id="employment" title="Employment Record" description="Assign this teacher to an institution and define their academic role.">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <flux:select name="institution_id" label="Assigned Institution" required>
                                <flux:select.option value="">Select Institution...</flux:select.option>
                                @foreach ($institutions as $institution)
                                    <flux:select.option value="{{ $institution->id }}" :selected="old('institution_id') == $institution->id">
                                        {{ $institution->name }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                        
                        <flux:input type="text" name="employee_number" label="Employee Number / ID" value="{{ old('employee_number') }}" placeholder="e.g. EMP-00123" />
                        
                        <flux:input type="text" name="department" label="Primary Department / Subject" value="{{ old('department') }}" placeholder="e.g. Mathematics, Sciences" />
                        
                        <flux:input type="text" name="qualification" label="Highest Qualification" value="{{ old('qualification') }}" placeholder="e.g. BSc. Education, PhD Physics" />
                        
                        <flux:input type="date" name="hire_date" label="Hire Date" value="{{ old('hire_date') }}" />
                    </div>
                </x-stepper.section>

                {{-- Step 3: Profile Details --}}
                <x-stepper.section id="profile" title="Profile Details" description="Contact information and current status.">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <flux:input type="text" name="phone" label="Phone Number" value="{{ old('phone') }}" placeholder="+1 (555) 000-0000" />
                        
                        <flux:select name="status" label="Employment Status">
                            @foreach (['active', 'on_leave', 'suspended', 'resigned', 'terminated'] as $status)
                                <flux:select.option value="{{ $status }}" :selected="old('status', 'active') === $status">
                                    {{ ucfirst(str_replace('_', ' ', $status)) }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>

                        <div class="md:col-span-2">
                            <flux:checkbox name="is_active" value="1" label="Account Active" description="Uncheck to temporarily disable system access for this teacher." checked />
                        </div>
                        
                        <div class="md:col-span-2">
                            <flux:textarea name="address" rows="2" label="Home Address" placeholder="Full residential address...">{{ old('address') }}</flux:textarea>
                        </div>
                        
                        <div class="md:col-span-2">
                            <flux:textarea name="notes" rows="3" label="Administrative Notes" placeholder="Any internal notes... (Optional)">{{ old('notes') }}</flux:textarea>
                        </div>
                    </div>
                </x-stepper.section>

                <x-slot:actions>
                    <flux:button href="{{ route('teacher.index') }}" variant="subtle" class="font-semibold shadow-sm">
                        Cancel
                    </flux:button>
                    <flux:button type="submit" variant="primary" class="font-semibold shadow-sm px-6">
                        Save Teacher
                    </flux:button>
                </x-slot:actions>

            </x-stepper.layout>
        </form>
    </div>
</x-layouts::app>
