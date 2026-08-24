<x-layouts::app :title="__('Edit Student')">
    <div class="flex flex-col gap-6 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">
        
        {{-- Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-2">
            <div>
                <flux:heading size="xl" class="font-bold tracking-tight text-zinc-900 dark:text-white">Edit Student Profile</flux:heading>
                <flux:text class="text-zinc-500 text-sm mt-1">
                    Update personal, academic, and guardian details for <span class="font-semibold text-zinc-700 dark:text-zinc-300">{{ $student->name }}</span>.
                </flux:text>
            </div>
            
            <div class="flex items-center gap-3">
                <flux:button href="{{ route('student.show', $student->id) }}" icon="arrow-left" variant="subtle" class="font-semibold shadow-sm">
                    Back to Profile
                </flux:button>
            </div>
        </div>

        @if ($errors->any())
            <div class="rounded-xl border border-red-200/50 bg-red-50/50 dark:bg-red-500/10 dark:border-red-500/20 p-4 flex items-start shadow-sm mb-6">
                <flux:icon icon="exclamation-circle" class="size-5 text-red-500 mr-3 mt-0.5" />
                <div class="text-sm text-red-800 dark:text-red-400">
                    <p class="font-semibold mb-1">{{ $errors->count() }} error(s) prevented this student from being updated:</p>
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <form action="{{ route('student.update', $student->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <x-stepper.layout defaultActive="academic">
                
                <x-slot:sidebar>
                    <x-stepper.step id="academic" title="Academic Info" description="Account & school details." isLast="false" />
                    <x-stepper.step id="personal" title="Personal Info" description="Demographics & photo." isLast="false" />
                    <x-stepper.step id="guardian" title="Guardianship" description="Parent & emergency contacts." isLast="false" />
                    <x-stepper.step id="medical" title="Medical Profile" description="Allergies & special needs." isLast="true" />
                </x-slot:sidebar>

                {{-- Step 1: Academic --}}
                <x-stepper.section id="academic" title="Account & Academic Info" description="Core details required for system access and tracking.">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <div class="bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200/80 dark:border-zinc-800 rounded-xl p-4 flex items-start gap-4">
                                <flux:icon icon="information-circle" class="size-5 text-zinc-500 mt-0.5 shrink-0" />
                                <div class="text-sm text-zinc-700 dark:text-zinc-300">
                                    <span class="font-medium">System Account Details:</span> The name ({{ $student->name }}) and email ({{ $student->email }}) are managed globally via the User Management module.
                                </div>
                            </div>
                        </div>

                        <flux:input type="text" name="admission_number" label="Admission Number" value="{{ old('admission_number', $student->studentUserDetails?->admission_number) }}" placeholder="e.g. ADM-2023-001" />
                        <flux:input type="text" name="student_number" label="Student Number / ID" value="{{ old('student_number', $student->studentUserDetails?->student_number) }}" placeholder="e.g. STU-8492" />

                        <div class="md:col-span-2">
                            <flux:select name="enrollment_status" label="Enrollment Status">
                                @php $status = old('enrollment_status', $student->studentInstitution?->enrollment_status); @endphp
                                <flux:select.option value="active" :selected="$status == 'active'">Active</flux:select.option>
                                <flux:select.option value="inactive" :selected="$status == 'inactive'">Inactive</flux:select.option>
                                <flux:select.option value="graduated" :selected="$status == 'graduated'">Graduated</flux:select.option>
                                <flux:select.option value="suspended" :selected="$status == 'suspended'">Suspended</flux:select.option>
                                <flux:select.option value="expelled" :selected="$status == 'expelled'">Expelled</flux:select.option>
                                <flux:select.option value="transferred" :selected="$status == 'transferred'">Transferred</flux:select.option>
                                <flux:select.option value="withdrawn" :selected="$status == 'withdrawn'">Withdrawn</flux:select.option>
                                <flux:select.option value="dropped" :selected="$status == 'dropped'">Dropped</flux:select.option>
                            </flux:select>
                        </div>
                    </div>
                </x-stepper.section>

                {{-- Step 2: Personal --}}
                <x-stepper.section id="personal" title="Personal Information" description="Contact and demographic details.">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <flux:input type="text" name="phone" label="Phone Number (Optional)" value="{{ old('phone', $student->studentUserDetails?->phone) }}" placeholder="+1 (555) 000-0000" />
                        
                        @php 
                            $dob = old('date_of_birth', isset($student->studentUserDetails?->date_of_birth) ? \Carbon\Carbon::parse($student->studentUserDetails->date_of_birth)->format('Y-m-d') : '');
                        @endphp
                        <flux:input type="date" name="date_of_birth" label="Date of Birth" value="{{ $dob }}" />
                        
                        <div class="md:col-span-2">
                            <flux:select name="gender" label="Gender">
                                @php $gender = old('gender', $student->studentUserDetails?->gender); @endphp
                                <flux:select.option value="">Select Gender...</flux:select.option>
                                <flux:select.option value="male" :selected="$gender == 'male'">Male</flux:select.option>
                                <flux:select.option value="female" :selected="$gender == 'female'">Female</flux:select.option>
                                <flux:select.option value="other" :selected="$gender == 'other'">Other</flux:select.option>
                            </flux:select>
                        </div>

                        <div class="md:col-span-2 space-y-2" x-data="{ photoName: null, photoPreview: null }">
                            <flux:label>Profile Photo</flux:label>
                            
                            <div class="flex items-center gap-4">
                                @php $currentPhoto = $student->studentUserDetails?->profile_photo; @endphp
                                <div class="size-16 rounded-full shrink-0 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 flex items-center justify-center overflow-hidden">
                                    <div x-show="photoPreview !== null" x-cloak>
                                        <img x-bind:src="photoPreview" class="size-16 object-cover">
                                    </div>
                                    <div x-show="photoPreview === null && '{{ $currentPhoto }}' !== ''" x-cloak>
                                        <img src="{{ $currentPhoto ? Storage::disk('public')->url($currentPhoto) : '' }}" class="size-16 object-cover">
                                    </div>
                                    <div x-show="photoPreview === null && '{{ $currentPhoto }}' === ''" x-cloak>
                                        <flux:icon icon="user" class="size-6 text-zinc-400" />
                                    </div>
                                </div>
                                <div class="flex-1">
                                    <input type="file" name="profile_image" accept="image/*" class="block w-full text-sm text-zinc-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-sky-50 file:text-sky-700 hover:file:bg-sky-100 border border-zinc-200 dark:border-zinc-700 rounded-lg focus:outline-none cursor-pointer bg-white dark:bg-zinc-800 transition-colors"
                                        x-ref="photo"
                                        x-on:change="
                                            photoName = $refs.photo.files[0].name;
                                            const reader = new FileReader();
                                            reader.onload = (e) => { photoPreview = e.target.result; };
                                            reader.readAsDataURL($refs.photo.files[0]);
                                        "
                                    />
                                </div>
                            </div>
                        </div>

                        <div class="md:col-span-2">
                            <flux:textarea name="address" label="Home Address" rows="3" placeholder="Full residential address...">{{ old('address', $student->studentUserDetails?->address) }}</flux:textarea>
                        </div>
                    </div>
                </x-stepper.section>

                {{-- Step 3: Guardian --}}
                <x-stepper.section id="guardian" title="Guardian / Parent Details" description="Emergency contacts and billing entities.">
                    <div class="bg-zinc-50/50 dark:bg-zinc-900/50 border border-zinc-200/80 dark:border-zinc-800 rounded-xl p-6 mb-6">
                        <h4 class="text-sm font-semibold text-zinc-900 dark:text-white mb-4">Primary Parent</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <flux:input type="text" name="parent_name" label="Parent Name" value="{{ old('parent_name', $student->studentParent?->name) }}" placeholder="e.g. Richard Doe" />
                            <flux:input type="text" name="parent_phone" label="Parent Phone" value="{{ old('parent_phone', $student->studentParent?->parentUserDetails?->parent_phone) }}" />
                            <flux:input type="email" name="parent_email" label="Parent Email" value="{{ old('parent_email', $student->studentParent?->email) }}" autocomplete="new-password" />
                            <flux:input type="text" name="parent_occupation" label="Parent Occupation" value="{{ old('parent_occupation', $student->studentParent?->parentUserDetails?->parent_occupation) }}" />
                        </div>
                    </div>

                    <div class="pt-2">
                        <div class="flex items-center gap-2 mb-4">
                            <flux:icon icon="shield-exclamation" class="size-5 text-zinc-400" />
                            <h4 class="text-sm font-semibold text-zinc-900 dark:text-white">Secondary Guardian Details <span class="text-xs font-normal text-zinc-500">(Optional)</span></h4>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <flux:input type="text" name="guardian_name" label="Guardian Name" value="{{ old('guardian_name', $student->studentUserDetails?->guardian_name) }}" />
                            <flux:input type="text" name="guardian_phone" label="Guardian Phone" value="{{ old('guardian_phone', $student->studentUserDetails?->guardian_phone) }}" />
                            <flux:input type="email" name="guardian_email" label="Guardian Email" value="{{ old('guardian_email', $student->studentUserDetails?->guardian_email) }}" />
                            <flux:input type="text" name="guardian_relationship" label="Relationship to Student" value="{{ old('guardian_relationship', $student->studentUserDetails?->guardian_relationship) }}" placeholder="e.g. Uncle, Grandmother" />
                        </div>
                    </div>
                </x-stepper.section>

                {{-- Step 4: Medical --}}
                <x-stepper.section id="medical" title="Medical & Additional Info" description="Important health notes and final status.">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <flux:textarea name="medical_conditions" label="Medical Conditions" rows="3" placeholder="List any known medical conditions...">{{ old('medical_conditions', $student->studentUserDetails?->medical_conditions) }}</flux:textarea>
                        
                        <flux:textarea name="allergies" label="Allergies" rows="3" placeholder="List any allergies (food, medical, etc)...">{{ old('allergies', $student->studentUserDetails?->allergies) }}</flux:textarea>
                        
                        <div class="md:col-span-2">
                            <flux:textarea name="special_needs" label="Special Educational Needs" rows="3" placeholder="Details of any special educational support required...">{{ old('special_needs', $student->studentUserDetails?->special_needs) }}</flux:textarea>
                        </div>
                        
                        <div class="md:col-span-2">
                            <flux:textarea name="notes" label="General Internal Notes" rows="2" placeholder="Any other internal notes for the staff...">{{ old('notes', $student->studentUserDetails?->notes) }}</flux:textarea>
                        </div>
                        
                        <div class="md:col-span-2 pt-4 border-t border-zinc-200 dark:border-zinc-800">
                            <flux:checkbox name="is_active" value="1" label="Account is Active" :checked="old('is_active', $student->is_active)" />
                        </div>
                    </div>
                </x-stepper.section>

                <x-slot:actions>
                    <flux:button href="{{ route('student.show', $student->id) }}" variant="subtle" class="font-semibold shadow-sm">
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
