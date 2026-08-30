<x-layouts::app :title="__('Review Receipt')">
    <div class="flex flex-col gap-6 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">

        {{-- Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-zinc-200 dark:border-zinc-800 pb-6 mb-2">
            <div class="flex items-center gap-4">
                <div class="hidden sm:flex size-12 shrink-0 items-center justify-center rounded-xl bg-amber-50 dark:bg-amber-500/10 ring-1 ring-amber-500/20">
                    <flux:icon icon="check-badge" class="size-6 text-amber-600 dark:text-amber-400" />
                </div>
                <div>
                    <flux:heading size="xl" class="font-bold tracking-tight text-zinc-900 dark:text-white">Review & Confirm</flux:heading>
                    <flux:text class="text-zinc-500 text-sm mt-1 leading-relaxed">
                        @if ($matchedStudent)
                            We found a match! Check the extracted details below before saving.
                        @else
                            We couldn't automatically match the admission number. Please select the student below.
                        @endif
                    </flux:text>
                </div>
            </div>
            
            <div class="flex items-center gap-3">
                <flux:button href="{{ route('feemanagement.index') }}" variant="subtle" icon="x-mark" class="hidden sm:flex">
                    Cancel
                </flux:button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            
            {{-- Left Column: The Receipt Viewer --}}
            <div class="flex flex-col gap-4">
                <div class="bg-zinc-100 dark:bg-zinc-900/50 rounded-2xl border border-zinc-200 dark:border-zinc-800 p-2 overflow-hidden shadow-inner">
                    @if ($isPdf)
                        <embed src="{{ $receiptUrl }}" type="application/pdf" class="w-full h-[600px] rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white">
                        <div class="mt-3 px-2 flex justify-end">
                            <flux:button href="{{ $receiptUrl }}" target="_blank" variant="subtle" size="sm" icon="arrow-top-right-on-square">
                                Open PDF
                            </flux:button>
                        </div>
                    @else
                        <img src="{{ $receiptUrl }}" alt="Uploaded receipt" class="w-full rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700">
                    @endif
                </div>
            </div>

            {{-- Right Column: The Form --}}
            <div class="bg-white dark:bg-zinc-900 shadow-sm rounded-2xl border border-zinc-200 dark:border-zinc-800 overflow-hidden h-fit">
                
                <div class="px-6 sm:px-8 py-5 border-b border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-800/30">
                    <h3 class="text-base font-semibold text-zinc-900 dark:text-white flex items-center gap-2">
                        <flux:icon icon="pencil-square" class="size-5 text-amber-500" /> Verify Extracted Data
                    </h3>
                </div>

                <form action="{{ route('feemanagement.receipts.store') }}" method="POST">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">

                    <div class="p-6 sm:p-8 space-y-6">
                        <div>
                            <flux:select name="student_id" id="student_id" label="Student" required>
                                <flux:select.option value="">Select student</flux:select.option>
                                @foreach ($students as $studentDetails)
                                    <flux:select.option value="{{ $studentDetails->student_id }}"
                                        :selected="old('student_id', $matchedStudent?->student_id) == $studentDetails->student_id">
                                        {{ $studentDetails->student?->name }} ({{ $studentDetails->admission_number ?? '—' }})
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                            @error('student_id')
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400 font-medium">{{ $message }}</p>
                            @enderror
                            @if ($extracted['admission_number'] && ! $matchedStudent)
                                <div class="mt-3 flex items-start gap-2 text-sm text-amber-600 dark:text-amber-500 bg-amber-50 dark:bg-amber-500/10 p-3 rounded-lg border border-amber-200/50 dark:border-amber-500/20">
                                    <flux:icon icon="exclamation-triangle" class="size-5 shrink-0" />
                                    <span>OCR detected admission number <strong>"{{ $extracted['admission_number'] }}"</strong> but no exact match was found.</span>
                                </div>
                            @endif
                        </div>

                        <div>
                            <flux:select name="fee_id" id="fee_id" label="Apply payment to">
                                @forelse ($fees as $fee)
                                    <flux:select.option value="{{ $fee->id }}">
                                        {{ $fee->title }} — balance {{ number_format($fee->balance, 2) }}
                                    </flux:select.option>
                                @empty
                                    <flux:select.option value="">No fee records for this student yet</flux:select.option>
                                @endforelse
                            </flux:select>
                            @error('fee_id')
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400 font-medium">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="bg-zinc-50 dark:bg-zinc-800/30 p-5 rounded-xl border border-zinc-100 dark:border-zinc-800 grid grid-cols-2 gap-5">
                            <div class="col-span-2">
                                <flux:input type="number" step="0.01" min="0.01" name="amount" label="Amount"
                                    value="{{ old('amount', $extracted['amount']) }}" required class="text-lg font-semibold text-green-600" />
                                @error('amount')
                                    <p class="mt-2 text-sm text-red-600 dark:text-red-400 font-medium">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="col-span-2 sm:col-span-1">
                                <flux:input type="date" name="paid_at" label="Date Paid"
                                    value="{{ old('paid_at', $extracted['paid_at']) }}" />
                            </div>
                            <div class="col-span-2 sm:col-span-1">
                                <flux:input type="text" name="payment_method" label="Method"
                                    value="{{ old('payment_method', $extracted['payment_method']) }}" />
                            </div>

                            <div class="col-span-2">
                                <flux:input type="text" name="reference" label="Reference / Transaction ID"
                                    value="{{ old('reference', $extracted['reference']) }}" />
                            </div>
                        </div>

                        @if ($extracted['student_name'])
                            <p class="text-sm text-zinc-500 dark:text-zinc-400 flex items-center gap-2">
                                <flux:icon icon="information-circle" class="size-4" /> 
                                OCR Extracted Name: <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $extracted['student_name'] }}</span>
                            </p>
                        @endif
                    </div>

                    <div class="border-t border-zinc-200 dark:border-zinc-800 px-6 sm:px-8 py-4 bg-zinc-50 dark:bg-zinc-800/50 flex justify-end gap-3 rounded-b-2xl">
                        <flux:button href="{{ route('feemanagement.index') }}" variant="subtle" class="hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors">Cancel</flux:button>
                        <flux:button type="submit" icon="check-circle" variant="primary" class="px-6">
                            Save Payment
                        </flux:button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const studentSelect = document.getElementById('student_id');
            const feeSelect = document.getElementById('fee_id');
            
            if (studentSelect && feeSelect) {
                studentSelect.addEventListener('change', function () {
                    feeSelect.innerHTML = '';
                    
                    if (! this.value) {
                        feeSelect.innerHTML = '<option value="">No fee records for this student yet</option>';
                        return;
                    }
                    
                    fetch(@json(route('feemanagement.receipts.student-fees')) + '?student_id=' + encodeURIComponent(this.value))
                        .then(response => response.json())
                        .then(fees => {
                            if (fees.length === 0) {
                                feeSelect.innerHTML = '<option value="">No fee records for this student yet</option>';
                                return;
                            }
                            
                            fees.forEach(fee => {
                                const option = document.createElement('option');
                                option.value = fee.id;
                                option.textContent = fee.label;
                                feeSelect.appendChild(option);
                            });
                        });
                });
            }
        });
    </script>
</x-layouts::app>
