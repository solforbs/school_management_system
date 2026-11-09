{{-- Personal Information --}}
<h5 class="text-md font-semibold text-gray-800 mb-3">Personal Information</h5>

<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div>
        <flux:input type="text" name="phone" label="Phone"
            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('phone') border-red-500 @enderror"
            value="{{ old('phone', $student->studentUserDetails->phone ?? '') }}" />
        @error('phone')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <flux:input type="date" name="date_of_birth" label="Date of Birth"
            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('date_of_birth') border-red-500 @enderror"
            value="{{ old('date_of_birth', isset($student->studentUserDetails->date_of_birth) ? \Carbon\Carbon::parse($student->studentUserDetails->date_of_birth)->format('Y-m-d') : '') }}" />
        @error('date_of_birth')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <flux:select name="gender" label="Gender"
            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('gender') border-red-500 @enderror">
            <flux:select.option value="">Select Gender</flux:select.option>
            <flux:select.option value="male" :selected="old('gender', $student->studentUserDetails->gender ?? '') == 'male'">Male</flux:select.option>
            <flux:select.option value="female" :selected="old('gender', $student->studentUserDetails->gender ?? '') == 'female'">Female</flux:select.option>
            <flux:select.option value="other" :selected="old('gender', $student->studentUserDetails->gender ?? '') == 'other'">Other</flux:select.option>
        </flux:select>
        @error('gender')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <flux:input type="text" name="admission_number" label="Admission Number"
            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('admission_number') border-red-500 @enderror"
            value="{{ old('admission_number', $student->studentUserDetails->admission_number ?? '') }}" />
        @error('admission_number')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <flux:input type="text" name="student_number" label="Student Number"
            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('student_number') border-red-500 @enderror"
            value="{{ old('student_number', $student->studentUserDetails->student_number ?? '') }}" />
        @error('student_number')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <flux:select name="institution_id" label="Institution"
            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('institution_id') border-red-500 @enderror">
            <flux:select.option value="">Select Institution</flux:select.option>
            @foreach ($institutions ?? [] as $institution)
                <flux:select.option value="{{ $institution->id }}"
                    :selected="old('institution_id', $student->studentUserDetails->institution_id ?? '') == $institution->id">
                    <span translate="no">{{ $institution->name }}</span>
                </flux:select.option>
            @endforeach
        </flux:select>
        @error('institution_id')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <flux:select name="enrollment_status" label="Enrollment Status"
            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('enrollment_status') border-red-500 @enderror">
            <flux:select.option value="active" :selected="old('enrollment_status', $student->studentUserDetails->enrollment_status ?? '') === 'active'">Active</flux:select.option>
            <flux:select.option value="transferred" :selected="old('enrollment_status', $student->studentUserDetails->enrollment_status ?? '') === 'transferred'">Transferred</flux:select.option>
            <flux:select.option value="graduated" :selected="old('enrollment_status', $student->studentUserDetails->enrollment_status ?? '') === 'graduated'">Graduated</flux:select.option>
            <flux:select.option value="dropped" :selected="old('enrollment_status', $student->studentUserDetails->enrollment_status ?? '') === 'dropped'">Dropped</flux:select.option>
            <flux:select.option value="suspended" :selected="old('enrollment_status', $student->studentUserDetails->enrollment_status ?? '') === 'suspended'">Suspended</flux:select.option>
            <flux:select.option value="expelled" :selected="old('enrollment_status', $student->studentUserDetails->enrollment_status ?? '') === 'expelled'">Expelled</flux:select.option>
            <flux:select.option value="withdrawn" :selected="old('enrollment_status', $student->studentUserDetails->enrollment_status ?? '') === 'withdrawn'">Withdrawn</flux:select.option>
        </flux:select>
        @error('enrollment_status')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>
</div>