<x-stepper.layout defaultActive="section-profile">
    
    {{-- Sidebar Navigation --}}
    <x-slot:sidebar>
        <x-stepper.step id="section-profile" icon="book-open" title="Curriculum Profile" />
        <x-stepper.step id="section-visibility" icon="eye" title="Visibility" />
    </x-slot:sidebar>

    {{-- Main Content Sections --}}
    <x-stepper.section id="section-profile" title="Curriculum Profile" description="Basic information about the curriculum and its associated institution.">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div class="sm:col-span-2">
                <flux:select name="institution_id" label="Institution" placeholder="Select Institution">
                    @foreach ($institutions as $institution)
                        <flux:select.option value="{{ $institution->id }}" :selected="old('institution_id', $curriculum->institution_id ?? null) == $institution->id">
                            {{ $institution->name }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="sm:col-span-2">
                <flux:input type="text" name="name" label="Curriculum Name" value="{{ old('name', $curriculum->name ?? null) }}" placeholder="e.g CBC/8.4.4" required />
            </div>
        </div>
    </x-stepper.section>

    <x-stepper.section id="section-visibility" title="Visibility" description="Determine whether this curriculum is currently active or archived.">
        <flux:select name="status" label="Status">
            <flux:select.option value="active" :selected="old('status', $curriculum->status ?? 'active') === 'active'">Active</flux:select.option>
            <flux:select.option value="dismissed" :selected="old('status', $curriculum->status ?? null) === 'dismissed'">Dismissed</flux:select.option>
        </flux:select>
    </x-stepper.section>

    {{-- Sticky Action Footer --}}
    <x-slot:actions>
        <flux:button href="{{ isset($curriculum) ? route('curriculum.show', $curriculum->id) : route('curriculum.index') }}" variant="subtle" class="hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors">
            Cancel
        </flux:button>
        <flux:button type="submit" class="bg-gradient-to-r from-blue-500 to-blue-600 text-white hover:opacity-90 hover:shadow-md transition-all border-none font-semibold px-6">
            {{ isset($curriculum) ? 'Save Changes' : 'Create Curriculum' }}
        </flux:button>
    </x-slot:actions>

</x-stepper.layout>
