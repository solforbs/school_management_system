<x-stepper.layout defaultActive="section-details">
    
    {{-- Sidebar Navigation --}}
    <x-slot:sidebar>
        <x-stepper.step id="section-details" title="Class Details" description="Basic information" />
        <x-stepper.step id="section-assignment" title="Assignment" description="Class teacher" />
    </x-slot:sidebar>

    {{-- Main Content Sections --}}
    <x-stepper.section id="section-details" title="Class Details" description="Basic information about the class, including its name, level, and capacity.">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div class="sm:col-span-2">
                <flux:select name="institution_id" label="Institution" placeholder="Select Institution">
                    @foreach ($institutions as $institution)
                        <flux:select.option value="{{ $institution->id }}" :selected="old('institution_id', $schoolClass->institution_id ?? null) == $institution->id">
                            {{ $institution->name }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <flux:input type="text" name="name" label="Class Name" value="{{ old('name', $schoolClass->name ?? null) }}" placeholder="e.g. Grade 8 East" required />
            <flux:input type="text" name="level" label="Level / Grade" value="{{ old('level', $schoolClass->level ?? null) }}" placeholder="e.g. Grade 8" />
            
            <div class="sm:col-span-2">
                <flux:input type="number" name="capacity" label="Capacity" value="{{ old('capacity', $schoolClass->capacity ?? null) }}" min="1" placeholder="e.g. 30" />
            </div>
        </div>
    </x-stepper.section>

    <x-stepper.section id="section-assignment" title="Assignment" description="Assign a primary class teacher to manage this specific class.">
        <flux:select name="class_teacher_id" label="Class Teacher" placeholder="Unassigned">
            @foreach ($teachers as $teacher)
                <flux:select.option value="{{ $teacher->id }}" :selected="old('class_teacher_id', $schoolClass->class_teacher_id ?? null) == $teacher->id">
                    {{ $teacher->name }}
                </flux:select.option>
            @endforeach
        </flux:select>
    </x-stepper.section>

    {{-- Sticky Action Footer --}}
    <x-slot:actions>
        <flux:button href="{{ isset($schoolClass) ? route('classes.show', $schoolClass->id) : route('classes.index') }}" variant="subtle">
            Cancel
        </flux:button>
        <flux:button type="submit" variant="primary" class="px-6 font-semibold">
            {{ isset($schoolClass) ? 'Save Changes' : 'Create Class' }}
        </flux:button>
    </x-slot:actions>

</x-stepper.layout>
