<x-stepper.layout defaultActive="section-basic">
    
    {{-- Sidebar Navigation --}}
    <x-slot:sidebar>
        <x-stepper.step id="section-basic" icon="academic-cap" title="Basic Information" />
        <x-stepper.step id="section-schedule" icon="clock" title="Schedule & Scoring" />
        <x-stepper.step id="section-notes" icon="document-text" title="Additional Notes" />
    </x-slot:sidebar>

    {{-- Main Content Sections --}}
    <x-stepper.section id="section-basic" title="Basic Information" description="Set up the core details of the examination.">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div class="sm:col-span-2">
                <flux:input type="text" name="title" label="Title" value="{{ old('title', $examination->title ?? null) }}" placeholder="e.g. Mid Term Exam" required />
            </div>

            <div class="sm:col-span-2">
                <flux:select id="institution_id" name="institution_id" label="Institution">
                    <flux:select.option value="">Select Institution</flux:select.option>
                    @foreach ($institutions as $institution)
                        <flux:select.option value="{{ $institution->id }}" :selected="old('institution_id', $examination->institution_id ?? null) == $institution->id">
                            {{ $institution->name }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <flux:select id="subject_id" name="subject_id" label="Subject">
                <flux:select.option value="">Select Subject</flux:select.option>
                @foreach ($subjects as $subject)
                    <flux:select.option value="{{ $subject->id }}" data-institution-id="{{ $subject->institution_id }}" :selected="old('subject_id', $examination->subject_id ?? null) == $subject->id">
                        {{ $subject->name }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:select id="class_id" name="class_id" label="Class">
                <flux:select.option value="">Select Class</flux:select.option>
                @foreach ($classes as $schoolClass)
                    <flux:select.option value="{{ $schoolClass->id }}" data-institution-id="{{ $schoolClass->institution_id }}" :selected="old('class_id', $examination->class_id ?? null) == $schoolClass->id">
                        {{ $schoolClass->name }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <div class="sm:col-span-2">
                <flux:input type="text" name="term" label="Term" value="{{ old('term', $examination->term ?? null) }}" placeholder="e.g. Term 1 2026" />
            </div>
        </div>
    </x-stepper.section>

    <x-stepper.section id="section-schedule" title="Schedule & Scoring" description="Define when the examination takes place and grading rules.">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div class="sm:col-span-2">
                <flux:input type="date" name="exam_date" label="Exam Date" value="{{ old('exam_date', isset($examination) ? $examination->exam_date?->format('Y-m-d') : null) }}" required />
            </div>

            <flux:input type="time" name="start_time" label="Start Time" value="{{ old('start_time', isset($examination) ? $examination->start_time?->format('H:i') : null) }}" />
            <flux:input type="time" name="end_time" label="End Time" value="{{ old('end_time', isset($examination) ? $examination->end_time?->format('H:i') : null) }}" />

            <flux:input type="number" name="total_marks" label="Total Marks" value="{{ old('total_marks', $examination->total_marks ?? 100) }}" min="1" required />
            <flux:input type="number" name="passing_marks" label="Passing Marks" value="{{ old('passing_marks', $examination->passing_marks ?? null) }}" min="0" />
        </div>
    </x-stepper.section>

    <x-stepper.section id="section-notes" title="Additional Notes" description="Provide any special instructions or remarks.">
        <flux:textarea name="notes" rows="4" label="Notes" placeholder="Enter notes...">{{ old('notes', $examination->notes ?? null) }}</flux:textarea>
    </x-stepper.section>

    {{-- Sticky Action Footer --}}
    <x-slot:actions>
        <flux:button href="{{ isset($examination) ? route('examinations.show', $examination->id) : route('examinations.index') }}" variant="subtle" class="hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors">
            Cancel
        </flux:button>
        <flux:button type="submit" class="bg-gradient-to-r from-blue-500 to-blue-600 text-white hover:opacity-90 hover:shadow-md transition-all border-none font-semibold px-6">
            {{ isset($examination) ? 'Save Changes' : 'Create Examination' }}
        </flux:button>
    </x-slot:actions>

</x-stepper.layout>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const institutionSelect = document.getElementById('institution_id');
        const classSelect = document.getElementById('class_id');
        const subjectSelect = document.getElementById('subject_id');

        function filterByInstitution(select) {
            const institutionId = institutionSelect.value;
            const currentValue = select.value;

            Array.from(select.options).forEach(function(option) {
                if (!option.value || option.value === currentValue) {
                    option.hidden = false;
                    return;
                }

                option.hidden = !!institutionId && option.dataset.institutionId !== String(institutionId);
            });
        }

        function applyFilters() {
            filterByInstitution(classSelect);
            filterByInstitution(subjectSelect);
        }

        if(institutionSelect) {
            institutionSelect.addEventListener('change', applyFilters);
            applyFilters();
        }
    });
</script>
