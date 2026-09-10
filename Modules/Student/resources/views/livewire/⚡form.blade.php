<?php

use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Institution\Models\Institution;
use Modules\Parent\Models\ParentDetails;
use Modules\Student\Models\StudentDetails;
use Modules\Classes\Models\SchoolClass;

new #[Title('Student Form')] class extends Component
{
    public ?User $student = null;

    public function mount(?int $studentId = null): void
    {
        if ($studentId === null) {
            abort_unless(auth()->user()->can('create student'), 403);
            return;
        }

        abort_unless(auth()->user()->can('edit student'), 403);

        $this->student = User::with('studentUserDetails')->findOrFail($studentId);
        
        $user = auth()->user();

        if ($user->hasRole('Parent')) {
            abort_unless($this->student->id === $user->id, 403);
            return;
        }

        $studentInstitutionId = StudentDetails::where('student_id', $this->student->id)->value('institution_id');
        abort_unless($studentInstitutionId && $studentInstitutionId === currentInstitution()?->id, 403);
    }

    #[Computed]
    public function parents()
    {
        return User::role('Parent')
            ->when(! isAdmin(), function ($q) {
                // Owned by this school, or with a child already enrolled here.
                $institutionId = currentInstitutionId();

                $q->where(function ($sq) use ($institutionId) {
                    $sq->whereHas('parent', fn ($pq) => $pq->where('institution_id', $institutionId))
                        ->orWhereHas('children', fn ($cq) => $cq->where('institution_id', $institutionId));
                });
            })
            ->with(['parent', 'children.student.studentUserDetails'])
            ->orderBy('name')
            ->get();
    }
    
    #[Computed]
    public function classes()
    {
        return SchoolClass::when(! isAdmin(), fn ($q) => $q->where('institution_id', currentInstitutionId()))
            ->orderBy('name')
            ->get();
    }
    
    #[Computed]
    public function institutions()
    {
        return isAdmin() ? Institution::all() : collect([currentInstitution()])->filter();
    }
}; ?>
<div>
@if($student)
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
                <flux:button href="{{ route('student.show', $student->id) }}" wire:navigate.hover icon="arrow-left" variant="subtle" class="font-semibold shadow-sm">
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
                    <x-stepper.step id="medical" title="Health & Medical" description="Allergies & extra notes." isLast="true" />
                </x-slot:sidebar>

                {{-- Step 1: Academic --}}
                <x-stepper.section id="academic" title="Academic & Account Setup" description="Core student credentials and classroom allocation.">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <flux:input type="text" name="name" label="Full Legal Name" value="{{ old('name', $student->name) }}" placeholder="e.g. Jane Doe" required />
                        
                        <flux:input type="email" name="email" label="School Email Address" value="{{ old('email', $student->email) }}" placeholder="jane.doe@school.edu" autocomplete="new-password" required />
                        
                        <flux:input type="password" name="password" label="New Password (optional)" placeholder="Leave blank to keep existing password" autocomplete="new-password" />

                        <flux:input type="text" name="admission_number" label="Admission Number" value="{{ old('admission_number', $student->studentUserDetails?->admission_number) }}" placeholder="e.g. ADM-2024-001" />

                        <flux:input type="text" name="student_number" label="Student Number" value="{{ old('student_number', $student->studentUserDetails?->student_number) }}" placeholder="e.g. STU-10492" />

                        <input type="hidden" name="class_id" value="">
                        <flux:select name="class_id" label="Class / Grade" placeholder="Choose a classroom...">
                            @foreach ($this->classes as $schoolClass)
                                <flux:select.option value="{{ $schoolClass->id }}" :selected="old('class_id', $student->studentUserDetails?->class_id) == $schoolClass->id">
                                    {{ $schoolClass->name }} ({{ $schoolClass->level }})
                                </flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:select name="enrollment_status" label="Enrollment Status">
                            @php $status = old('enrollment_status', $student->studentUserDetails?->enrollment_status); @endphp
                            <flux:select.option value="active" :selected="$status == 'active'">Active</flux:select.option>
                            <flux:select.option value="inactive" :selected="$status == 'inactive'">Inactive</flux:select.option>
                            <flux:select.option value="graduated" :selected="$status == 'graduated'">Graduated</flux:select.option>
                            <flux:select.option value="suspended" :selected="$status == 'suspended'">Suspended</flux:select.option>
                            <flux:select.option value="transferred" :selected="$status == 'transferred'">Transferred</flux:select.option>
                            <flux:select.option value="expelled" :selected="$status == 'expelled'">Expelled</flux:select.option>
                            <flux:select.option value="withdrawn" :selected="$status == 'withdrawn'">Withdrawn</flux:select.option>
                            <flux:select.option value="dropped" :selected="$status == 'dropped'">Dropped</flux:select.option>
                        </flux:select>
                    </div>
                </x-stepper.section>

                {{-- Step 2: Personal --}}
                <x-stepper.section id="personal" title="Personal Details" description="Demographics, background information, and profile media.">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <flux:input type="text" name="phone" label="Phone Number (Optional)" value="{{ old('phone', $student->studentUserDetails?->phone) }}" placeholder="+1 (555) 000-0000" />

                        <flux:input type="date" name="date_of_birth" label="Date of Birth" value="{{ old('date_of_birth', $student->studentUserDetails?->date_of_birth ? \Carbon\Carbon::parse($student->studentUserDetails->date_of_birth)->format('Y-m-d') : '') }}" />

                        <flux:select name="gender" label="Gender">
                            @php $gender = old('gender', $student->studentUserDetails?->gender); @endphp
                            <flux:select.option value="">Select Gender...</flux:select.option>
                            <flux:select.option value="male" :selected="$gender == 'male'">Male</flux:select.option>
                            <flux:select.option value="female" :selected="$gender == 'female'">Female</flux:select.option>
                            <flux:select.option value="other" :selected="$gender == 'other'">Other</flux:select.option>
                        </flux:select>

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
                                    <p class="text-xs text-zinc-400 mt-1">Supported formats: JPG, PNG, WEBP. Max size 2MB.</p>
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
                            <flux:input type="text" name="parent_name" label="Parent Name" value="{{ old('parent_name', $student->parentUserDetails?->parent_name ?? $student->studentUserDetails?->parent_name) }}" placeholder="e.g. Richard Doe" />
                            <flux:input type="text" name="parent_phone" label="Parent Phone" value="{{ old('parent_phone', $student->parentUserDetails?->parent_phone ?? $student->studentUserDetails?->parent_phone) }}" />
                            <flux:input type="email" name="parent_email" label="Parent Email" value="{{ old('parent_email', $student->parentUserDetails?->email ?? $student->studentUserDetails?->parent_email) }}" autocomplete="new-password" />
                            <flux:input type="text" name="parent_occupation" label="Parent Occupation" value="{{ old('parent_occupation', $student->parentUserDetails?->parent_occupation ?? $student->studentUserDetails?->parent_occupation) }}" />
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
                            <flux:checkbox name="is_active" value="1" label="Account is Active" :checked="old('is_active', $student->studentUserDetails?->is_active ?? true)" />
                        </div>
                    </div>
                </x-stepper.section>

                <x-slot:actions>
                    <flux:button href="{{ route('student.show', $student->id) }}" wire:navigate.hover variant="subtle" class="font-semibold shadow-sm">
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
                <flux:heading size="xl" class="font-bold tracking-tight text-zinc-900 dark:text-white">Enroll New Student</flux:heading>
                <flux:text class="text-zinc-500 text-sm mt-1">
                    Enter the student's personal, academic, and guardian details.
                </flux:text>
            </div>
            
            <div class="flex items-center gap-3">
                <flux:button href="{{ route('student.index') }}" wire:navigate.hover icon="arrow-left" variant="subtle" class="font-semibold shadow-sm">
                    Back to Directory
                </flux:button>
            </div>
        </div>

        {{-- How many students this school's plan allows, and how many are left. --}}
        <x-student-limit-banner />

        @if ($errors->any())
            <div class="rounded-xl border border-red-200/50 bg-red-50/50 dark:bg-red-500/10 dark:border-red-500/20 p-4 flex items-start shadow-sm mb-6">
                <flux:icon icon="exclamation-circle" class="size-5 text-red-500 mr-3 mt-0.5" />
                <div class="text-sm text-red-800 dark:text-red-400">
                    <p class="font-semibold mb-1">{{ $errors->count() }} error(s) prevented this student from being saved:</p>
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <form action="{{ route('student.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

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
                            <flux:input type="text" name="name" label="Full Name" value="{{ old('name') }}" placeholder="e.g. John Doe" required />
                        </div>
                        
                        <flux:input type="email" name="email" label="Student Email" value="{{ old('email') }}" placeholder="john.doe@school.edu" autocomplete="new-password" required />
                        <flux:input type="password" name="password" label="Temporary Password" placeholder="Minimum 8 characters..." autocomplete="new-password" required />
                        
                        <flux:input type="text" name="admission_number" label="Admission Number" value="{{ old('admission_number') }}" placeholder="e.g. ADM-2026-001" />
                        <flux:input type="text" name="student_number" label="Student Number / ID" value="{{ old('student_number') }}" placeholder="e.g. STU-8492" />
                        
                        <input type="hidden" name="class_id" value="">
                        <flux:select name="class_id" label="Class / Grade" placeholder="Select classroom...">
                            <flux:select.option value="">Unassigned Class</flux:select.option>
                            @foreach ($this->classes as $schoolClass)
                                <flux:select.option value="{{ $schoolClass->id }}" :selected="old('class_id') == $schoolClass->id">
                                    {{ $schoolClass->name }} ({{ $schoolClass->level }})
                                </flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:select name="enrollment_status" label="Enrollment Status">
                            <flux:select.option value="active" :selected="old('enrollment_status', 'active') == 'active'">Active</flux:select.option>
                            <flux:select.option value="inactive" :selected="old('enrollment_status', 'inactive') == 'inactive'">Inactive</flux:select.option>
                            <flux:select.option value="graduated" :selected="old('enrollment_status', 'graduated') == 'graduated'">Graduated</flux:select.option>
                            <flux:select.option value="suspended" :selected="old('enrollment_status', 'suspended') == 'suspended'">Suspended</flux:select.option>
                            <flux:select.option value="transferred" :selected="old('enrollment_status', 'transferred') == 'transferred'">Transferred</flux:select.option>
                        </flux:select>
                    </div>
                </x-stepper.section>

                {{-- Step 2: Personal --}}
                <x-stepper.section id="personal" title="Personal Information" description="Contact and demographic details.">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <flux:input type="text" name="phone" label="Phone Number (Optional)" value="{{ old('phone') }}" placeholder="+254 700 000 000" />
                        <flux:input type="date" name="date_of_birth" label="Date of Birth" value="{{ old('date_of_birth') }}" />
                        
                        <flux:select name="gender" label="Gender">
                            <flux:select.option value="">Select Gender...</flux:select.option>
                            <flux:select.option value="male" :selected="old('gender') == 'male'">Male</flux:select.option>
                            <flux:select.option value="female" :selected="old('gender') == 'female'">Female</flux:select.option>
                            <flux:select.option value="other" :selected="old('gender') == 'other'">Other</flux:select.option>
                        </flux:select>

                        <div class="md:col-span-2 space-y-2">
                            <flux:label>Profile Photo <span class="text-rose-500">*</span></flux:label>
                            <input type="file" name="profile_image" required accept="image/*" class="block w-full text-sm text-zinc-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-sky-50 file:text-sky-700 hover:file:bg-sky-100 border border-zinc-200 dark:border-zinc-700 rounded-lg focus:outline-none cursor-pointer bg-white dark:bg-zinc-800 transition-colors" />
                            <p class="text-xs text-zinc-500">Required for biometric sync. Supported formats: JPG, PNG, WEBP. Max size 2MB.</p>
                        </div>

                        <div class="md:col-span-2">
                            <flux:textarea name="address" label="Home Address" rows="3" placeholder="Full residential address...">{{ old('address') }}</flux:textarea>
                        </div>
                    </div>
                </x-stepper.section>

                {{-- Step 3: Guardian --}}
                <x-stepper.section id="guardian" title="Guardian / Parent Details" description="Emergency contacts and billing entities.">
                    <div class="bg-zinc-50/50 dark:bg-zinc-900/50 border border-zinc-200/80 dark:border-zinc-800 rounded-xl p-6 mb-6">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                            <div>
                                <h4 class="text-sm font-semibold text-zinc-900 dark:text-white">Primary Parent</h4>
                                <p class="text-xs text-zinc-500 mt-1">Select how you want to link this student's parent.</p>
                            </div>
                            <div class="flex items-center gap-2 bg-white dark:bg-zinc-800 p-1 rounded-lg border border-zinc-200 dark:border-zinc-700 shadow-sm">
                                <label class="flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-md cursor-pointer transition-colors has-[:checked]:bg-sky-50 has-[:checked]:text-sky-700 dark:has-[:checked]:bg-sky-900/30 dark:has-[:checked]:text-sky-400 text-zinc-600 dark:text-zinc-400">
                                    <input type="radio" name="parent_mode" value="new" class="hidden" checked> Add New Parent
                                </label>
                                <label class="flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-md cursor-pointer transition-colors has-[:checked]:bg-sky-50 has-[:checked]:text-sky-700 dark:has-[:checked]:bg-sky-900/30 dark:has-[:checked]:text-sky-400 text-zinc-600 dark:text-zinc-400">
                                    <input type="radio" name="parent_mode" value="existing" class="hidden"> Link Existing
                                </label>
                            </div>
                        </div>

                        <div id="existing-parent-fields" class="hidden">
                            <flux:select name="parent_id" label="Select Existing Parent" class="w-full">
                                <flux:select.option value="">Choose a parent...</flux:select.option>
                                @foreach ($this->parents as $parent)
                                    <flux:select.option value="{{ $parent->id }}" :selected="old('parent_id') == $parent->id">
                                        {{ $parent->name }} ({{ $parent->email }})
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                            <p class="text-xs text-zinc-500 mt-2">Only parents registered at your institution will be shown.</p>
                        </div>

                        <div id="new-parent-fields" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <flux:input type="text" name="parent_name" label="Parent Name" value="{{ old('parent_name') }}" placeholder="e.g. Richard Doe" />
                            <flux:input type="text" name="parent_phone" label="Parent Phone" value="{{ old('parent_phone') }}" placeholder="e.g. 0700000000" />
                            <flux:input type="email" name="parent_email" label="Parent Email" value="{{ old('parent_email') }}" autocomplete="new-password" placeholder="e.g. parent@example.com" />
                            <flux:input type="text" name="parent_occupation" label="Parent Occupation" value="{{ old('parent_occupation') }}" placeholder="e.g. Engineer" />
                        </div>
                    </div>

                    <div class="pt-2">
                        <div class="flex items-center gap-2 mb-4">
                            <flux:icon icon="shield-exclamation" class="size-5 text-zinc-400" />
                            <h4 class="text-sm font-semibold text-zinc-900 dark:text-white">Secondary Guardian Details <span class="text-xs font-normal text-zinc-500">(Optional)</span></h4>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <flux:input type="text" name="guardian_name" label="Guardian Name" value="{{ old('guardian_name') }}" />
                            <flux:input type="text" name="guardian_phone" label="Guardian Phone" value="{{ old('guardian_phone') }}" />
                            <flux:input type="email" name="guardian_email" label="Guardian Email" value="{{ old('guardian_email') }}" />
                            <flux:input type="text" name="guardian_relationship" label="Relationship to Student" value="{{ old('guardian_relationship') }}" placeholder="e.g. Uncle, Grandmother" />
                        </div>
                    </div>
                </x-stepper.section>

                {{-- Step 4: Medical --}}
                <x-stepper.section id="medical" title="Medical & Additional Info" description="Important health notes and final status.">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <flux:textarea name="medical_conditions" label="Medical Conditions" rows="3" placeholder="List any known medical conditions...">{{ old('medical_conditions') }}</flux:textarea>
                        
                        <flux:textarea name="allergies" label="Allergies" rows="3" placeholder="List any allergies (food, medical, etc)...">{{ old('allergies') }}</flux:textarea>
                        
                        <div class="md:col-span-2">
                            <flux:textarea name="special_needs" label="Special Educational Needs" rows="3" placeholder="Details of any special educational support required...">{{ old('special_needs') }}</flux:textarea>
                        </div>
                        
                        <div class="md:col-span-2">
                            <flux:textarea name="notes" label="General Internal Notes" rows="2" placeholder="Any other internal notes for the staff...">{{ old('notes') }}</flux:textarea>
                        </div>
                        
                        <div class="md:col-span-2 pt-4 border-t border-zinc-200 dark:border-zinc-800">
                            <flux:checkbox name="is_active" value="1" label="Mark student account as active immediately" checked />
                        </div>
                    </div>
                </x-stepper.section>

                <x-slot:actions>
                    <flux:button href="{{ route('student.index') }}" wire:navigate.hover variant="subtle" class="font-semibold shadow-sm">
                        Cancel
                    </flux:button>
                    <flux:button type="submit" variant="primary" class="font-semibold shadow-sm px-6">
                        Create Student
                    </flux:button>
                </x-slot:actions>

            </x-stepper.layout>
        </form>
    </div>

    {{-- Interactive Parent Mode Toggle Script --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const newFields = document.getElementById('new-parent-fields');
            const existingFields = document.getElementById('existing-parent-fields');
            const modeRadios = document.querySelectorAll('input[name="parent_mode"]');

            function applyMode() {
                const checked = document.querySelector('input[name="parent_mode"]:checked');
                const mode = checked ? checked.value : 'new';
                if (newFields && existingFields) {
                    newFields.classList.toggle('hidden', mode !== 'new');
                    existingFields.classList.toggle('hidden', mode !== 'existing');
                }
            }

            modeRadios.forEach(radio => radio.addEventListener('change', applyMode));
            applyMode();
        });
    </script>
@endif
</div>
