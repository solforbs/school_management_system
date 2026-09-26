<x-layouts::app :title="__('Create Workspace')">
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
        <div class="pt-2 pb-12 text-center px-4">
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
                {{-- Sections --}}
                <x-stepper.section id="step-1" title="Basics & Branding" description="Set up the primary identity of this institution.">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <flux:input type="text" name="name" label="School Name"
                                value="{{ old('name') }}" required placeholder="e.g. Riverside Academy" />
                        </div>

                        <div class="relative">
                            <flux:input type="text" name="code" label="Short Code (Optional)"
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
                                value="{{ old('email') }}" required placeholder="admin@school.com" />
                        </div>
                        <div>
                            <flux:input type="url" name="website" label="Website URL (Optional)"
                                value="{{ old('website') }}" placeholder="https://www.school.com" />
                        </div>
                        <div>
                            <flux:input type="text" name="phone" label="Main Phone (Optional)"
                                value="{{ old('phone') }}" placeholder="+254..." />
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
                    <flux:button href="{{ route('institution.index') }}" variant="subtle" class="min-h-[38px]">Cancel</flux:button>
                    <flux:button type="submit" variant="primary" class="px-4 sm:px-6 font-semibold min-h-[38px]">Create Workspace</flux:button>
                </x-slot:actions>

            </x-stepper.layout>
        </form>
    </div>
</x-layouts::app>
