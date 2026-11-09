@php($isEdit = isset($examination))

@if ($isEdit)
    {{-- Editing one paper of a sitting: its schedule and scoring. --}}
    <x-stepper.layout defaultActive="section-schedule">
        <x-slot:sidebar>
            <x-stepper.step id="section-schedule" icon="clock" title="Schedule & Scoring" />
            <x-stepper.step id="section-notes" icon="document-text" title="Additional Notes" />
        </x-slot:sidebar>

        <x-stepper.section id="section-schedule" title="Schedule & Scoring"
            description="When {{ $examination->subject?->name ?? $examination->subject_name ?? 'this paper' }} sits for {{ $examination->schoolClass?->name ?? $examination->class_name ?? 'this class' }}, and how it's marked.">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div class="sm:col-span-2">
                    <flux:input type="text" name="title" label="Paper Title"
                        value="{{ old('title', $examination->title) }}" required />
                </div>
                <input type="hidden" name="institution_id" value="{{ $examination->institution_id }}">
                <input type="hidden" name="class_id" value="{{ $examination->class_id }}">
                <input type="hidden" name="subject_id" value="{{ $examination->subject_id }}">
                <input type="hidden" name="term" value="{{ $examination->term }}">
                <input type="hidden" name="exam_type" value="{{ $examination->exam_type }}">

                <div class="sm:col-span-2">
                    <flux:input type="date" name="exam_date" label="Exam Date"
                        value="{{ old('exam_date', $examination->exam_date?->format('Y-m-d')) }}" required />
                </div>
                <flux:input type="time" name="start_time" label="Start Time"
                    value="{{ old('start_time', $examination->start_time?->format('H:i')) }}" />
                <flux:input type="time" name="end_time" label="End Time"
                    value="{{ old('end_time', $examination->end_time?->format('H:i')) }}" />
                <flux:input type="number" name="total_marks" label="Total Marks"
                    value="{{ old('total_marks', $examination->total_marks ?? 100) }}" min="1" required />
                <flux:input type="number" name="passing_marks" label="Passing Marks"
                    value="{{ old('passing_marks', $examination->passing_marks) }}" min="0" />
            </div>
        </x-stepper.section>

        <x-stepper.section id="section-notes" title="Additional Notes" description="Any special instructions for this paper.">
            <flux:textarea name="notes" rows="4" label="Notes"
                placeholder="Enter notes...">{{ old('notes', $examination->notes) }}</flux:textarea>
        </x-stepper.section>

        <x-slot:actions>
            <flux:button href="{{ route('examinations.show', $examination->id) }}" wire:navigate.hover variant="subtle"
                class="hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors">
                Cancel
            </flux:button>
            <flux:button type="submit"
                class="bg-gradient-to-r from-blue-500 to-blue-600 text-white hover:opacity-90 hover:shadow-md transition-all border-none font-semibold px-6">
                Save Changes
            </flux:button>
        </x-slot:actions>
    </x-stepper.layout>
@else
    {{-- Creating a sitting: it fans out into a paper per subject per class. --}}
    <x-stepper.layout defaultActive="section-basic">
        <x-slot:sidebar>
            <x-stepper.step id="section-basic" icon="academic-cap" title="Exam Details" />
            <x-stepper.step id="section-scope" icon="user-group" title="Scope & Schedule" />
            <x-stepper.step id="section-notes" icon="document-text" title="Additional Notes" />
        </x-slot:sidebar>

        <x-stepper.section id="section-basic" title="Exam Details"
            description="Name the sitting. It will cover every subject automatically.">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div class="sm:col-span-2">
                    <flux:input type="text" name="title" label="Exam Name" value="{{ old('title') }}"
                        placeholder="e.g. Mock Exam, End Term Exam" required />
                </div>

                <div class="sm:col-span-2">
                    <flux:select id="institution_id" name="institution_id" label="Institution">
                        <flux:select.option value="">Select Institution</flux:select.option>
                        @foreach ($institutions as $institution)
                            <flux:select.option value="{{ $institution->id }}"
                                :selected="old('institution_id') == $institution->id">
                                <span translate="no">{{ $institution->name }}</span>
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <flux:input type="text" name="term" label="Academic Term" value="{{ old('term') }}"
                    placeholder="e.g. Term 1" />

                <div>
                    <input type="hidden" name="exam_type" value="">
                    <flux:select name="exam_type" label="Exam Type" placeholder="Select Exam Type">
                        <flux:select.option value="">General Assessment</flux:select.option>
                        @foreach ($examTypes ?? [] as $typeKey => $typeLabel)
                            <flux:select.option value="{{ $typeKey }}" :selected="old('exam_type') == $typeKey">
                                {{ $typeLabel }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <flux:input type="number" name="academic_year" label="Academic Year"
                    value="{{ old('academic_year', now()->year) }}" min="2000" max="2100" />
            </div>
        </x-stepper.section>

        <x-stepper.section id="section-scope" title="Scope & Schedule"
            description="Which classes sit it, and when the papers start.">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <flux:select id="class_id" name="class_id" label="Classes"
                    description="Leave on 'All classes' unless this exam is for one class only.">
                    <flux:select.option value="">All classes</flux:select.option>
                    @foreach ($classes as $schoolClass)
                        <flux:select.option value="{{ $schoolClass->id }}"
                            data-institution-id="{{ $schoolClass->institution_id }}"
                            :selected="old('class_id') == $schoolClass->id">
                            <span translate="no">{{ $schoolClass->name }}</span> only
                        </flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input type="date" name="starts_on" label="Papers Start On" value="{{ old('starts_on') }}"
                    description="Papers spread across weekdays from here. Defaults to next Monday." />
            </div>
            <flux:text class="mt-3 text-xs text-zinc-500">
                One paper is created per subject per class — three sittings a day, Monday to Friday. Edit an individual
                paper's date or marks afterwards from the exam's timetable.
            </flux:text>
        </x-stepper.section>

        <x-stepper.section id="section-notes" title="Additional Notes" description="Any special instructions or remarks.">
            <flux:textarea name="notes" rows="4" label="Notes"
                placeholder="Enter notes...">{{ old('notes') }}</flux:textarea>
        </x-stepper.section>

        <x-slot:actions>
            <flux:button href="{{ route('examinations.index') }}" wire:navigate.hover variant="subtle"
                class="hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors">
                Cancel
            </flux:button>
            <flux:button type="submit"
                class="bg-gradient-to-r from-blue-500 to-blue-600 text-white hover:opacity-90 hover:shadow-md transition-all border-none font-semibold px-6">
                Create Exam
            </flux:button>
        </x-slot:actions>
    </x-stepper.layout>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const institutionSelect = document.getElementById('institution_id');
            const classSelect = document.getElementById('class_id');

            if (!institutionSelect || !classSelect) {
                return;
            }

            function applyFilters() {
                const institutionId = institutionSelect.value;

                Array.from(classSelect.options).forEach(function(option) {
                    if (!option.value || option.value === classSelect.value) {
                        option.hidden = false;
                        return;
                    }
                    option.hidden = !!institutionId && option.dataset.institutionId !== String(institutionId);
                });
            }

            institutionSelect.addEventListener('change', applyFilters);
            applyFilters();
        });
    </script>
@endif
