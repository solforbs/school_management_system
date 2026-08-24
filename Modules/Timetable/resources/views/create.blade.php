<x-layouts::app :title="__('Create Timetable Entry')">
    <div class="flex flex-col gap-6 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">
        
        {{-- Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-2">
            <div>
                <flux:heading size="xl" class="font-bold tracking-tight text-zinc-900 dark:text-white">Add Timetable Entry</flux:heading>
                <flux:text class="text-zinc-500 text-sm mt-1">
                    Schedule a single class session on the academic timetable.
                </flux:text>
            </div>
            
            <div class="flex items-center gap-3">
                <flux:button href="{{ route('timetable.index') }}" icon="arrow-left" variant="subtle" class="font-semibold shadow-sm">
                    Back to Timetable
                </flux:button>
            </div>
        </div>

        @if ($errors->any())
            <div class="rounded-xl border border-red-200/50 bg-red-50/50 dark:bg-red-500/10 dark:border-red-500/20 p-4 flex items-start shadow-sm mb-6">
                <flux:icon icon="exclamation-circle" class="size-5 text-red-500 mr-3 mt-0.5" />
                <div class="text-sm text-red-800 dark:text-red-400">
                    <p class="font-semibold mb-1">{{ $errors->count() }} error(s) prevented this entry from being saved:</p>
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <form action="{{ route('timetable.store') }}" method="POST">
            @csrf

            <x-stepper.layout defaultActive="academic-details">
                
                <x-slot:sidebar>
                    <x-stepper.step id="academic-details" title="Academic Details" description="Institution, class, subject." isLast="false" />
                    <x-stepper.step id="scheduling" title="Scheduling" description="Day, start, and end time." isLast="false" />
                    <x-stepper.step id="logistics" title="Logistics" description="Teacher and room assignment." isLast="true" />
                </x-slot:sidebar>

                {{-- Step 1: Academic Details --}}
                <x-stepper.section id="academic-details" title="Academic Details" description="Select the core academic context for this schedule entry.">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <flux:select name="institution_id" label="Institution" required>
                            <flux:select.option value="">Select Institution</flux:select.option>
                            @foreach ($institutions as $institution)
                                <flux:select.option value="{{ $institution->id }}" :selected="old('institution_id') == $institution->id">
                                    {{ $institution->name }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:select name="class_id" label="Class" required>
                            <flux:select.option value="">Select Class</flux:select.option>
                            @foreach ($classes as $schoolClass)
                                <flux:select.option value="{{ $schoolClass->id }}" :selected="old('class_id') == $schoolClass->id">
                                    {{ $schoolClass->name }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                        
                        <div class="md:col-span-2">
                            <flux:input type="text" name="subject" label="Subject Name" value="{{ old('subject') }}" placeholder="e.g. Mathematics, English Literature..." required />
                        </div>
                    </div>
                </x-stepper.section>

                {{-- Step 2: Scheduling --}}
                <x-stepper.section id="scheduling" title="Scheduling" description="When does this class take place?">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <flux:select name="day_of_week" label="Day of Week" required>
                                <flux:select.option value="">Select Day</flux:select.option>
                                @foreach ($days as $day)
                                    <flux:select.option value="{{ $day }}" :selected="old('day_of_week') === $day">
                                        {{ $day }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                        
                        <flux:input type="time" name="start_time" label="Start Time" value="{{ old('start_time') }}" required />
                        
                        <flux:input type="time" name="end_time" label="End Time" value="{{ old('end_time') }}" required />
                    </div>
                </x-stepper.section>

                {{-- Step 3: Logistics --}}
                <x-stepper.section id="logistics" title="Logistics" description="Assign a teacher and room to this schedule block.">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <flux:input type="text" name="room" label="Room / Location" value="{{ old('room') }}" placeholder="e.g. Room 402, Main Hall..." />
                        
                        <flux:select name="teacher_id" label="Teacher">
                            <flux:select.option value="">Unassigned (TBA)</flux:select.option>
                            @foreach ($teachers as $teacher)
                                <flux:select.option value="{{ $teacher->id }}" :selected="old('teacher_id') == $teacher->id">
                                    {{ $teacher->name }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                        
                        <div class="md:col-span-2">
                            <flux:textarea name="notes" rows="3" label="Notes" placeholder="Any additional information... (Optional)">{{ old('notes') }}</flux:textarea>
                        </div>
                    </div>
                </x-stepper.section>

                <x-slot:actions>
                    <flux:button href="{{ route('timetable.index') }}" variant="subtle" class="font-semibold shadow-sm">
                        Cancel
                    </flux:button>
                    <flux:button type="submit" variant="primary" class="font-semibold shadow-sm px-6">
                        Save Entry
                    </flux:button>
                </x-slot:actions>

            </x-stepper.layout>
        </form>
    </div>
</x-layouts::app>
