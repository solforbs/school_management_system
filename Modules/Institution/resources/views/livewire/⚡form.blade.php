<?php

use App\Models\Plan;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Institution\Actions\SaveInstitution;
use Modules\Institution\Models\Institution;

new #[Title('Institution')] class extends Component
{
    public ?Institution $institution = null;

    public function mount(?int $institutionId = null): void
    {
        if ($institutionId === null) {
            abort_if(isAdmin(), 403);

            return;
        }

        abort_unless(auth()->user()->can('edit institution'), 403);

        $this->institution = $this->scoped()->findOrFail($institutionId);
    }

    #[Computed]
    public function plans(): Collection
    {
        return Plan::where('is_active', true)->orderBy('name')->get();
    }

    private function scoped()
    {
        $query = Institution::query();

        if (! isAdmin()) {
            $query->where('user_id', auth()->id());
        }

        return $query;
    }
}; ?>
<div>
    @if($institution)
    <div x-data="{ 
            photoPreview: null,
            updatePhotoPreview() {
                const reader = new FileReader();
                reader.onload = (e) => {
                    this.photoPreview = e.target.result;
                };
                if (this.$refs.photo.files[0]) {
                    reader.readAsDataURL(this.$refs.photo.files[0]);
                }
            }
        }" 
        class="pb-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8"
    >
        {{-- Welcome Header --}}
        <div class="pt-2 pb-8 text-center px-4">
            <div class="inline-flex size-16 items-center justify-center rounded-2xl bg-blue-50 dark:bg-blue-500/10 mb-6 shadow-sm ring-1 ring-blue-500/20">
                <flux:icon icon="pencil-square" class="size-8 text-blue-600 dark:text-blue-400" />
            </div>
            <h1 class="text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl dark:text-white">
                Editing <span class="text-blue-600 dark:text-blue-400"><span translate="no">{{ $institution->name }}</span></span>
            </h1>
            <p class="mt-4 text-lg text-zinc-600 dark:text-zinc-400 max-w-2xl mx-auto">
                Update the workspace details and administrative controls below.
            </p>
        </div>

        {{-- Alerts --}}
        @if (session('error'))
            <div class="mb-8 rounded-2xl border border-rose-200 bg-rose-50/90 dark:bg-rose-950/40 dark:border-rose-800/60 p-4 flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-3">
                    <flux:icon icon="exclamation-triangle" class="size-5 text-rose-600 dark:text-rose-400 shrink-0" />
                    <span class="text-sm font-semibold text-rose-900 dark:text-rose-200">{{ session('error') }}</span>
                </div>
            </div>
        @elseif ($errors->any())
            <div class="mb-8 rounded-2xl border border-rose-200 bg-rose-50/90 dark:bg-rose-950/40 dark:border-rose-800/60 p-4 shadow-sm">
                <div class="flex items-start gap-3">
                    <flux:icon icon="exclamation-triangle" class="size-5 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" />
                    <div class="space-y-1">
                        <p class="text-sm font-semibold text-rose-900 dark:text-rose-200">Please review the errors below:</p>
                        <ul class="text-xs text-rose-800 dark:text-rose-300 list-disc list-inside space-y-0.5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        @if (session('success'))
            <div class="mb-8 rounded-2xl border border-emerald-200 bg-emerald-50/90 dark:bg-emerald-950/40 dark:border-emerald-800/60 p-4 flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-3">
                    <flux:icon icon="check-circle" class="size-5 text-emerald-600 dark:text-emerald-400 shrink-0" />
                    <span class="text-sm font-semibold text-emerald-900 dark:text-emerald-200">{{ session('success') }}</span>
                </div>
                <flux:button href="{{ route('institution.show', $institution->id) }}" wire:navigate.hover size="sm" variant="subtle" class="text-emerald-800 dark:text-emerald-300">
                    View Institution
                </flux:button>
            </div>
        @endif

        <form action="{{ route('institution.update', $institution->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <x-stepper.layout defaultActive="step-1">
                
                {{-- Sidebar --}}
                <x-slot:sidebar>
                    <x-stepper.step id="step-1" title="Basics & Branding" description="Name, Code, Logo" />
                    <x-stepper.step id="step-2" title="Contact Details" description="Email & Phone" />
                    <x-stepper.step id="step-3" title="Location" description="Address & Directions" />
                    @hasrole('Admin')
                        <x-stepper.step id="step-4" title="Admin Controls" description="Plan & Status" />
                    @endhasrole
                </x-slot:sidebar>

                {{-- Sections --}}
                <x-stepper.section id="step-1" title="Basics & Branding" description="Set up the primary identity of this institution.">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <flux:input type="text" name="name" label="School Name"
                                value="{{ old('name', $institution->name) }}" required placeholder="e.g. Riverside Academy" />
                        </div>

                        <div class="relative">
                            <flux:input type="text" name="code" label="Short Code"
                                description="Unique short code (e.g. RA) used on admission numbers and report cards."
                                x-on:input="$el.value = $el.value.toUpperCase()"
                                maxlength="5"
                                class="uppercase"
                                value="{{ old('code', $institution->code) }}" required placeholder="e.g. RA" />
                        </div>

                        <div>
                            <flux:select name="type" label="Institution Type">
                                <flux:select.option value="School" :selected="old('type', $institution->type) == 'School'">School</flux:select.option>
                                <flux:select.option value="College" :selected="old('type', $institution->type) == 'College'">College</flux:select.option>
                                <flux:select.option value="University" :selected="old('type', $institution->type) == 'University'">University</flux:select.option>
                                <flux:select.option value="Training Centre" :selected="old('type', $institution->type) == 'Training Centre'">Training Centre</flux:select.option>
                            </flux:select>
                        </div>
                        
                        <div>
                            <flux:input type="text" name="education_level" label="Education Level"
                                value="{{ old('education_level', $institution->education_level) }}" placeholder="e.g. Primary, Secondary" />
                        </div>

                        <div>
                            <flux:input type="text" name="timezone" label="Timezone"
                                description="Timezone for class schedules, attendance, and logs."
                                value="{{ old('timezone', $institution->timezone ?? 'Africa/Nairobi') }}" required />
                        </div>

                        <div>
                            <x-institution::locale-select :selected="$institution->locale" />
                        </div>

                        <div>
                            <flux:input type="number" name="min_electives" label="Minimum Electives"
                                description="Minimum elective subjects required per student (default 0)."
                                value="{{ old('min_electives', $institution->min_electives ?? 0) }}" />
                        </div>

                        <div>
                            <flux:input type="number" name="max_electives" label="Maximum Electives"
                                description="Maximum optional electives allowed. Leave blank if unlimited."
                                value="{{ old('max_electives', $institution->max_electives) }}" />
                        </div>

                        @unlessrole('Admin')
                            <input type="hidden" name="is_active" value="{{ $institution->is_active ? 1 : 0 }}">
                        @endunlessrole

                        {{-- Live Image Preview & Drag and Drop --}}
                        <div class="md:col-span-2 mt-2">
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">School Logo</label>
                            
                            <div class="flex flex-col sm:flex-row sm:items-center gap-4 sm:gap-6">
                                <div class="shrink-0">
                                    <span class="inline-block size-20 sm:size-24 rounded-2xl overflow-hidden bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 shadow-sm relative flex items-center justify-center">
                                        @if($institution->logo)
                                            <img x-show="!photoPreview" src="{{ Storage::url($institution->logo) }}" class="w-full h-full object-contain bg-white">
                                        @else
                                            <span x-show="!photoPreview" class="text-zinc-400">
                                                <flux:icon icon="photo" class="size-8" />
                                            </span>
                                        @endif
                                        <img x-show="photoPreview" :src="photoPreview" class="w-full h-full object-contain bg-white" style="display:none;">
                                    </span>
                                </div>
                                
                                <div class="flex-1">
                                    <div class="mt-1 flex justify-center px-4 sm:px-6 pt-4 sm:pt-5 pb-5 sm:pb-6 border-2 border-zinc-300 border-dashed rounded-xl dark:border-zinc-700 hover:border-blue-500 dark:hover:border-blue-500 transition bg-zinc-50 dark:bg-zinc-800/50 relative overflow-hidden group">
                                        <div class="space-y-1 text-center">
                                            <flux:icon icon="arrow-up-tray" class="mx-auto size-7 sm:size-8 text-zinc-400 group-hover:text-blue-500 transition" />
                                            <div class="flex text-xs sm:text-sm text-zinc-600 dark:text-zinc-400 justify-center">
                                                <label for="file-upload" class="relative cursor-pointer bg-transparent rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none">
                                                    <span>Upload a new file</span>
                                                    <input id="file-upload" name="logo" type="file" class="sr-only" x-ref="photo" x-on:change="updatePhotoPreview" accept="image/*">
                                                </label>
                                                <p class="pl-1">or drag and drop</p>
                                            </div>
                                            <p class="text-[11px] sm:text-xs text-zinc-500">PNG, JPG up to 2MB</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </x-stepper.section>

                <x-stepper.section id="step-2" title="Contact Details" description="How people can reach out to this institution.">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <flux:input label="Official Email" type="email" name="email"
                                description="Official email address for correspondence and notices."
                                value="{{ old('email', $institution->email) }}" />
                        </div>
                        <div>
                            <flux:input type="url" name="website" label="Website URL (Optional)"
                                value="{{ old('website', $institution->website) }}" placeholder="https://www.school.com" />
                        </div>
                        <div>
                            <flux:input type="text" name="phone" label="Main Phone (Optional)"
                                value="{{ old('phone', $institution->phone) }}" placeholder="+254 700 000 000" />
                        </div>
                        <div>
                            <flux:input type="text" name="alternate_phone" label="Alternative Phone (Optional)"
                                value="{{ old('alternate_phone', $institution->alternate_phone) }}" />
                        </div>
                    </div>
                </x-stepper.section>

                <x-stepper.section id="step-3" title="Location" description="The physical and postal address.">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <flux:input type="text" name="country" readonly label="Country"
                                class="bg-zinc-50"
                                value="{{ old('country', $institution->country ?? 'Kenya') }}" />
                        </div>
                        <div>
                            <flux:input type="text" name="county" label="State / County"
                                value="{{ old('county', $institution->county) }}" />
                        </div>
                        <div>
                            <flux:input type="text" name="city" label="City / Town"
                                value="{{ old('city', $institution->city) }}" />
                        </div>
                        <div class="md:col-span-3">
                            <flux:input type="text" name="postal_address" label="Postal Address"
                                value="{{ old('postal_address', $institution->postal_address) }}" />
                        </div>
                        <div class="md:col-span-3">
                            <flux:textarea name="physical_address" rows="2" label="Physical Address / Directions">{{ old('physical_address', $institution->physical_address) }}</flux:textarea>
                        </div>
                    </div>
                </x-stepper.section>

                @hasrole('Admin')
                <x-stepper.section id="step-4" title="Admin Controls" description="Internal administrative settings.">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <flux:input type="text" name="principal_name" label="Principal Name"
                                value="{{ old('principal_name', $institution->principal_name) }}" />
                        </div>
                        <div>
                            <flux:input type="tel" name="principal_phone" label="Principal Phone"
                                value="{{ old('principal_phone', $institution->principal_phone) }}" />
                        </div>
                        <div>
                            <flux:select name="subscription_plan" label="Subscription Plan">
                                <option value="">Select Plan</option>
                                @foreach ($this->plans as $plan)
                                    <option value="{{ $plan->id }}" {{ (string) old('subscription_plan', $institution->subscription_plan) === (string) $plan->id ? 'selected' : '' }}>
                                        {{ $plan->name }}
                                    </option>
                                @endforeach
                            </flux:select>
                        </div>
                        <div>
                            <flux:input type="date" name="subscription_expires_at" label="Subscription Expiry Date"
                                value="{{ old('subscription_expires_at', $institution->subscription_expires_at ? \Carbon\Carbon::parse($institution->subscription_expires_at)->format('Y-m-d') : '') }}" />
                        </div>
                        <div class="md:col-span-2">
                            <flux:textarea name="notes" rows="2" label="Notes">{{ old('notes', $institution->notes) }}</flux:textarea>
                        </div>
                        
                        <div class="md:col-span-2 p-5 bg-zinc-50 dark:bg-zinc-800/50 rounded-xl border border-zinc-200 dark:border-zinc-700">
                            <label class="text-sm font-medium text-zinc-900 dark:text-white mb-3 block">Institution Status</label>
                            <div class="flex items-center gap-6">
                                <label class="flex items-center cursor-pointer">
                                    <input type="radio" name="is_active" value="1"
                                        {{ old('is_active', $institution->is_active) == 1 ? 'checked' : '' }}
                                        class="size-4 text-blue-600 border-zinc-300 focus:ring-blue-600">
                                    <span class="ml-2 text-sm font-medium text-zinc-700 dark:text-zinc-300">Active</span>
                                </label>
                                <label class="flex items-center cursor-pointer">
                                    <input type="radio" name="is_active" value="0"
                                        {{ old('is_active', $institution->is_active) == 0 ? 'checked' : '' }}
                                        class="size-4 text-zinc-500 border-zinc-300 focus:ring-zinc-500">
                                    <span class="ml-2 text-sm text-zinc-500">Inactive</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </x-stepper.section>
                @endhasrole

                <x-slot:actions>
                    <flux:button href="{{ route('institution.index') }}" wire:navigate.hover variant="subtle" class="min-h-[38px]">Cancel</flux:button>
                    <flux:button type="submit" variant="primary" class="px-4 sm:px-6 font-semibold min-h-[38px]">Update Institution</flux:button>
                </x-slot:actions>

            </x-stepper.layout>
        </form>
    </div>

    @else
    <div x-data="{ 
            photoPreview: null,
            updatePhotoPreview() {
                const reader = new FileReader();
                reader.onload = (e) => {
                    this.photoPreview = e.target.result;
                };
                if (this.$refs.photo.files[0]) {
                    reader.readAsDataURL(this.$refs.photo.files[0]);
                }
            }
        }" 
        class="pb-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8"
    >
        {{-- Welcome Header --}}
        <div class="pt-2 pb-8 text-center px-4">
            <div class="inline-flex size-16 items-center justify-center rounded-2xl bg-violet-50 dark:bg-violet-500/10 mb-6 shadow-sm ring-1 ring-violet-500/20">
                <flux:icon icon="sparkles" class="size-8 text-violet-600 dark:text-violet-400" />
            </div>
            <h1 class="text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl dark:text-white">
                Welcome to <span class="text-violet-600 dark:text-violet-400">Solforbs!</span>
            </h1>
            <p class="mt-4 text-lg text-zinc-600 dark:text-zinc-400 max-w-2xl mx-auto">
                Let's set up your school's workspace. Follow the steps below to configure your institution.
            </p>
        </div>

        {{-- Alerts --}}
        @if (session('error'))
            <div class="mb-8 rounded-2xl border border-rose-200 bg-rose-50/90 dark:bg-rose-950/40 dark:border-rose-800/60 p-4 flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-3">
                    <flux:icon icon="exclamation-triangle" class="size-5 text-rose-600 dark:text-rose-400 shrink-0" />
                    <span class="text-sm font-semibold text-rose-900 dark:text-rose-200">{{ session('error') }}</span>
                </div>
            </div>
        @elseif ($errors->any())
            <div class="mb-8 rounded-2xl border border-rose-200 bg-rose-50/90 dark:bg-rose-950/40 dark:border-rose-800/60 p-4 shadow-sm">
                <div class="flex items-start gap-3">
                    <flux:icon icon="exclamation-triangle" class="size-5 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" />
                    <div class="space-y-1">
                        <p class="text-sm font-semibold text-rose-900 dark:text-rose-200">Please review the errors below:</p>
                        <ul class="text-xs text-rose-800 dark:text-rose-300 list-disc list-inside space-y-0.5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        @if (session('success'))
            <div class="mb-8 rounded-2xl border border-emerald-200 bg-emerald-50/90 dark:bg-emerald-950/40 dark:border-emerald-800/60 p-4 flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-3">
                    <flux:icon icon="check-circle" class="size-5 text-emerald-600 dark:text-emerald-400 shrink-0" />
                    <span class="text-sm font-semibold text-emerald-900 dark:text-emerald-200">{{ session('success') }}</span>
                </div>
            </div>
        @endif

        <form action="{{ route('institution.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <x-stepper.layout defaultActive="step-1">
                
                {{-- Sidebar --}}
                <x-slot:sidebar>
                    <x-stepper.step id="step-1" title="Basics & Branding" description="Name, Code, Logo" />
                    <x-stepper.step id="step-2" title="Contact Details" description="Email & Phone" />
                    <x-stepper.step id="step-3" title="Location" description="Address & Directions" />
                </x-slot:sidebar>

                {{-- Sections --}}
                <x-stepper.section id="step-1" title="Basics & Branding" description="Set up the primary identity of this institution.">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <flux:input type="text" name="name" label="School Name"
                                value="{{ old('name') }}" required placeholder="e.g. Riverside Academy" />
                        </div>

                        <div class="relative">
                            <flux:input type="text" name="code" label="Short Code (Optional)"
                                description="Up to 5 letters. Auto-generated from school name if left blank."
                                x-on:input="$el.value = $el.value.toUpperCase()"
                                maxlength="5"
                                class="uppercase"
                                value="{{ old('code') }}" placeholder="Auto-generated if left blank (e.g. RA)" />
                        </div>

                        <div>
                            <flux:select name="type" label="Institution Type (Optional)">
                                <flux:select.option value="School" :selected="old('type') == 'School'">School</flux:select.option>
                                <flux:select.option value="College" :selected="old('type') == 'College'">College</flux:select.option>
                                <flux:select.option value="University" :selected="old('type') == 'University'">University</flux:select.option>
                                <flux:select.option value="Training Centre" :selected="old('type') == 'Training Centre'">Training Centre</flux:select.option>
                            </flux:select>
                        </div>

                        <div>
                            <x-institution::locale-select />
                        </div>

                        {{-- Live Image Preview & Drag and Drop --}}
                        <div class="md:col-span-2 mt-2">
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">School Logo (Optional)</label>
                            
                            <div class="flex flex-col sm:flex-row sm:items-center gap-4 sm:gap-6">
                                <div class="shrink-0">
                                    <span class="inline-block size-20 sm:size-24 rounded-2xl overflow-hidden bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 shadow-sm relative flex items-center justify-center">
                                        <span x-show="!photoPreview" class="text-zinc-400">
                                            <flux:icon icon="photo" class="size-8" />
                                        </span>
                                        <img x-show="photoPreview" :src="photoPreview" class="w-full h-full object-contain bg-white" style="display:none;">
                                    </span>
                                </div>
                                
                                <div class="flex-1">
                                    <div class="mt-1 flex justify-center px-4 sm:px-6 pt-4 sm:pt-5 pb-5 sm:pb-6 border-2 border-zinc-300 border-dashed rounded-xl dark:border-zinc-700 hover:border-violet-500 dark:hover:border-violet-500 transition bg-zinc-50 dark:bg-zinc-800/50 relative overflow-hidden group">
                                        <div class="space-y-1 text-center">
                                            <flux:icon icon="arrow-up-tray" class="mx-auto size-7 sm:size-8 text-zinc-400 group-hover:text-violet-500 transition" />
                                            <div class="flex text-xs sm:text-sm text-zinc-600 dark:text-zinc-400 justify-center">
                                                <label for="file-upload" class="relative cursor-pointer bg-transparent rounded-md font-medium text-violet-600 hover:text-violet-500 focus-within:outline-none">
                                                    <span>Upload a file</span>
                                                    <input id="file-upload" name="logo" type="file" class="sr-only" x-ref="photo" x-on:change="updatePhotoPreview" accept="image/*">
                                                </label>
                                                <p class="pl-1">or drag and drop</p>
                                            </div>
                                            <p class="text-[11px] sm:text-xs text-zinc-500">PNG, JPG up to 2MB</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </x-stepper.section>

                <x-stepper.section id="step-2" title="Contact Details" description="How people can reach out to this institution.">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <flux:input label="Official Email" type="email" name="email"
                                description="Primary contact and notification address for your workspace."
                                value="{{ old('email') }}" required placeholder="admin@school.com" />
                        </div>
                        <div>
                            <flux:input type="url" name="website" label="Website URL (Optional)"
                                value="{{ old('website') }}" placeholder="https://www.school.com" />
                        </div>
                        <div>
                            <flux:input type="text" name="phone" label="Main Phone (Optional)"
                                value="{{ old('phone') }}" placeholder="+254 700 000 000" />
                        </div>
                        <div>
                            <flux:input type="text" name="alternate_phone" label="Alternative Phone (Optional)"
                                value="{{ old('alternate_phone') }}" />
                        </div>
                    </div>
                </x-stepper.section>

                <x-stepper.section id="step-3" title="Location" description="The physical and postal address.">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <flux:input type="text" name="country" readonly label="Country"
                                class="bg-zinc-50"
                                value="{{ old('country', 'Kenya') }}" />
                        </div>
                        <div>
                            <flux:input type="text" name="county" label="State / County (Optional)"
                                value="{{ old('county') }}" />
                        </div>
                        <div>
                            <flux:input type="text" name="city" label="City / Town (Optional)"
                                value="{{ old('city') }}" />
                        </div>
                        <div class="md:col-span-3">
                            <flux:input type="text" name="postal_address" label="Postal Address (Optional)"
                                value="{{ old('postal_address') }}" />
                        </div>
                        <div class="md:col-span-3">
                            <flux:textarea name="physical_address" rows="2" label="Physical Address / Directions (Optional)">{{ old('physical_address') }}</flux:textarea>
                        </div>
                    </div>
                </x-stepper.section>

                <x-slot:actions>
                    <flux:button href="{{ route('institution.index') }}" wire:navigate.hover variant="subtle" class="min-h-[38px]">Cancel</flux:button>
                    <flux:button type="submit" variant="primary" class="px-4 sm:px-6 font-semibold min-h-[38px]">Create Workspace</flux:button>
                </x-slot:actions>

            </x-stepper.layout>
        </form>
    </div>
    @endif
</div>
