@php
    $currentFee = $fee ?? $feeManagement ?? null;
@endphp
<x-stepper.layout defaultActive="section-target">
    
    {{-- Sidebar Navigation --}}
    <x-slot:sidebar>
        <x-stepper.step id="section-target" icon="user" title="Target & Type" />
        <x-stepper.step id="section-financials" icon="banknotes" title="Financial Details" />
        <x-stepper.step id="section-notes" icon="document-text" title="Additional Notes" />
    </x-slot:sidebar>

    {{-- Main Content Sections --}}
    <x-stepper.section id="section-target" title="Target & Type" description="Select the student and categorize the fee.">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div class="sm:col-span-2">
                <flux:select name="student_id" label="Student" placeholder="Select Student" required>
                    @foreach ($students as $studentDetails)
                        <flux:select.option value="{{ $studentDetails->student_id }}" :selected="old('student_id', $currentFee?->student_id) == $studentDetails->student_id">
                            <span translate="no">{{ $studentDetails->student?->name }}</span> 
                            @if ($studentDetails->admission_number)
                                (<span translate="no">{{ $studentDetails->admission_number }}</span>)
                            @endif
                            &mdash; <span translate="no">{{ $studentDetails->institution?->name }}</span>
                        </flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="sm:col-span-2">
                <flux:select name="fee_type" label="Fee Type" required>
                    @foreach (['tuition', 'transport', 'boarding', 'exam', 'uniform', 'activity', 'other'] as $type)
                        <flux:select.option value="{{ $type }}" :selected="old('fee_type', $currentFee?->fee_type) === $type">
                            {{ ucfirst($type) }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>
    </x-stepper.section>

    <x-stepper.section id="section-financials" title="Financial Details" description="Set the fee amount, track payments, and set deadlines.">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div class="sm:col-span-2">
                <flux:input type="text" name="title" label="Title" value="{{ old('title', $currentFee?->title) }}" placeholder="e.g. Term 1 Tuition" required />
            </div>

            <flux:input type="number" step="0.01" min="0" name="amount" label="Amount" value="{{ old('amount', $currentFee?->amount) }}" required />
            <flux:input type="number" step="0.01" min="0" name="amount_paid" label="Amount Paid" value="{{ old('amount_paid', $currentFee?->amount_paid ?? 0) }}" />

            <div class="sm:col-span-2">
                <flux:input type="date" name="due_date" label="Due Date" value="{{ old('due_date', $currentFee?->due_date?->format('Y-m-d')) }}" />
            </div>
        </div>
    </x-stepper.section>

    <x-stepper.section id="section-notes" title="Additional Notes" description="Provide any special instructions or remarks regarding this fee.">
        <flux:textarea name="notes" rows="4" label="Notes" placeholder="Enter notes...">{{ old('notes', $currentFee?->notes) }}</flux:textarea>
    </x-stepper.section>

    {{-- Sticky Action Footer --}}
    <x-slot:actions>
        <flux:button href="{{ $currentFee ? route('feemanagement.show', $currentFee->id) : route('feemanagement.index') }}" wire:navigate.hover variant="subtle" class="hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors">
            Cancel
        </flux:button>
        <flux:button type="submit" variant="primary" class="px-6 font-semibold">
            {{ $currentFee ? 'Save Changes' : 'Create Fee' }}
        </flux:button>
    </x-slot:actions>

</x-stepper.layout>