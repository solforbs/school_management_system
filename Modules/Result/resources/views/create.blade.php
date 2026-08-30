<x-layouts::app :title="__('Add Result')">
    <div class="flex flex-col gap-6 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">
        
        {{-- Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-2">
            <div>
                <flux:heading size="xl" class="font-bold tracking-tight text-zinc-900 dark:text-white">Add Result</flux:heading>
                <flux:text class="text-zinc-500 text-sm mt-1">
                    Record a new examination result for a student.
                </flux:text>
            </div>
            
            <div class="flex items-center gap-3">
                <flux:button href="{{ route('result.index') }}" icon="arrow-left" variant="subtle" class="font-semibold shadow-sm">
                    Back to Results
                </flux:button>
            </div>
        </div>

        @if ($errors->any())
            <div class="rounded-xl border border-red-200/50 bg-red-50/50 dark:bg-red-500/10 dark:border-red-500/20 p-4 flex items-start shadow-sm mb-6">
                <flux:icon icon="exclamation-circle" class="size-5 text-red-500 mr-3 mt-0.5" />
                <div class="text-sm text-red-800 dark:text-red-400">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <form action="{{ route('result.store') }}" method="POST">
            @csrf

            <x-stepper.layout defaultActive="academic-details">
                
                <x-slot:sidebar>
                    <x-stepper.step id="academic-details" title="Academic Details" description="Select the student and examination." isLast="false" />
                    <x-stepper.step id="scoring-details" title="Scoring & Remarks" description="Enter the marks and optional remarks." isLast="true" />
                </x-slot:sidebar>

                {{-- Step 1: Academic Details --}}
                <x-stepper.section id="academic-details" title="Academic Details" description="Select the institution, class, student, and the specific examination this result belongs to.">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <flux:select id="institution_id" name="institution_id" label="Institution">
                            <flux:select.option value="">Select Institution</flux:select.option>
                            @foreach ($institutions as $institution)
                                <flux:select.option value="{{ $institution->id }}" :selected="old('institution_id') == $institution->id">
                                    {{ $institution->name }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:select id="class_id" name="class_id" label="Class">
                            <flux:select.option value="">Select Class</flux:select.option>
                            @foreach ($classes as $schoolClass)
                                <flux:select.option value="{{ $schoolClass->id }}" data-institution-id="{{ $schoolClass->institution_id }}" :selected="old('class_id') == $schoolClass->id">
                                    {{ $schoolClass->name }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:select id="student_id" name="student_id" label="Student">
                            <flux:select.option value="">Select Student</flux:select.option>
                            @foreach ($students as $studentDetail)
                                <flux:select.option value="{{ $studentDetail->student_id }}" data-class-id="{{ $studentDetail->class_id }}" :selected="old('student_id') == $studentDetail->student_id">
                                    {{ $studentDetail->student?->name }}
                                    @if ($studentDetail->admission_number)
                                        ({{ $studentDetail->admission_number }})
                                    @endif
                                </flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:select id="examination_id" name="examination_id" label="Examination">
                            <flux:select.option value="">Select Examination</flux:select.option>
                            @foreach ($examinations as $examination)
                                <flux:select.option value="{{ $examination->id }}" data-class-id="{{ $examination->class_id }}" :selected="old('examination_id') == $examination->id">
                                    {{ $examination->title }} ({{ $examination->subject?->name ?? $examination->subject_name }})
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                </x-stepper.section>

                {{-- Step 2: Scoring & Remarks --}}
                <x-stepper.section id="scoring-details" title="Scoring & Remarks" description="Enter the marks obtained by the student. The grade will be calculated automatically based on the grading scale.">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-1">
                            <flux:input type="number" step="0.01" name="marks_obtained" label="Marks Obtained" value="{{ old('marks_obtained') }}" min="0" required />
                        </div>
                        
                        <div class="md:col-span-2">
                            <div class="bg-blue-50/50 dark:bg-blue-900/10 border border-blue-200/50 dark:border-blue-800/30 rounded-xl p-4 flex items-start gap-3">
                                <flux:icon icon="information-circle" class="size-5 text-blue-500 mt-0.5 shrink-0" />
                                <div class="text-sm text-blue-800 dark:text-blue-300">
                                    Grade is computed automatically from the institution's grading scale once saved. 
                                    @can('edit reportcard')
                                        <a href="{{ route('reportcard.settings') }}" class="underline font-semibold hover:text-blue-600 transition-colors">Manage grading scale</a>.
                                    @endcan
                                </div>
                            </div>
                        </div>

                        <div class="md:col-span-2">
                            <flux:textarea name="remarks" rows="4" label="Remarks" placeholder="Optional comments on the student's performance...">{{ old('remarks') }}</flux:textarea>
                        </div>
                    </div>
                </x-stepper.section>

                <x-slot:actions>
                    <flux:button href="{{ route('result.index') }}" variant="subtle" class="font-semibold shadow-sm">
                        Cancel
                    </flux:button>
                    <flux:button type="submit" variant="primary" class="font-semibold shadow-sm px-6">
                        Save Result
                    </flux:button>
                </x-slot:actions>

            </x-stepper.layout>
        </form>
    </div>

    <x-create-result-scripts :classes="$classes" :students="$students" :examinations="$examinations" />
</x-layouts::app>
