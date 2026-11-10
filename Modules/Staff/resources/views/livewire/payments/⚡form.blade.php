<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Staff\Actions\SaveStaffPayment;
use Modules\Staff\Models\StaffDetails;
use Modules\Staff\Models\StaffPayment;

new #[Title('Staff Payment')] class extends Component
{
    public ?StaffPayment $payment = null;

    public function mount(?int $paymentId = null): void
    {
        if ($paymentId === null) {
            abort_unless(auth()->user()->can('create payroll'), 403);

            return;
        }

        abort_unless(auth()->user()->can('edit payroll'), 403);

        $this->payment = $this->scoped()->findOrFail($paymentId);
    }

    #[Computed]
    public function staffMembers(): Collection
    {
        $query = StaffDetails::query()->orderBy('name');

        if (! isAdmin()) {
            $query->where('institution_id', currentInstitution()?->id ?? 0);
        }

        return $query->get();
    }

    private function scoped()
    {
        $query = StaffPayment::query();

        if (! isAdmin()) {
            $query->where('institution_id', currentInstitution()?->id ?? 0);
        }

        return $query;
    }
}; ?>
<div>
    @if($payment)
    <div class="flex flex-col gap-6 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">
        
        {{-- Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-2">
            <div>
                <flux:heading size="xl" class="font-bold tracking-tight text-zinc-900 dark:text-white">Edit Payment</flux:heading>
                <flux:text class="text-zinc-500 text-sm mt-1">
                    Update the payroll transaction for <span class="font-semibold text-zinc-700 dark:text-zinc-300"><span translate="no">{{ $payment->staff?->name }}</span></span>.
                </flux:text>
            </div>
            
            <div class="flex items-center gap-3">
                <flux:button href="{{ route('staff.payments.show', $payment) }}" wire:navigate.hover icon="arrow-left" variant="subtle" class="font-semibold shadow-sm">
                    Back to Receipt
                </flux:button>
            </div>
        </div>

        @if ($errors->any())
            <div class="rounded-xl border border-red-200/50 bg-red-50/50 dark:bg-red-500/10 dark:border-red-500/20 p-4 flex items-start shadow-sm mb-6">
                <flux:icon icon="exclamation-circle" class="size-5 text-red-500 mr-3 mt-0.5" />
                <div class="text-sm text-red-800 dark:text-red-400">
                    <p class="font-semibold mb-1">{{ $errors->count() }} error(s) prevented this payment from being updated:</p>
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <form action="{{ route('staff.payments.update', $payment) }}" method="POST">
            @csrf
            @method('PUT')

            <x-stepper.layout defaultActive="payment-details">
                
                <x-slot:sidebar>
                    <x-stepper.step id="payment-details" title="Payment Details" description="Staff and period." isLast="false" />
                    <x-stepper.step id="financials" title="Financials" description="Gross, allowances, deductions." isLast="false" />
                    <x-stepper.step id="processing" title="Processing" description="Method, status, reference." isLast="true" />
                </x-slot:sidebar>

                {{-- Step 1: Payment Details --}}
                <x-stepper.section id="payment-details" title="Payment Details" description="Update the staff member or the payroll period for this transaction.">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <flux:select name="staff_details_id" label="Staff Member" required>
                                @foreach ($this->staffMembers as $member)
                                    <flux:select.option value="{{ $member->id }}" :selected="old('staff_details_id', $payment->staff_details_id) == $member->id">
                                        <span translate="no">{{ $member->name }}</span>{{ $member->job_title ? ' — '.$member->job_title : '' }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                        
                        <div class="md:col-span-2">
                            <flux:input type="month" name="period" label="Payroll Period" value="{{ old('period', $payment->period?->format('Y-m')) }}" required />
                        </div>
                    </div>
                </x-stepper.section>

                {{-- Step 2: Financials --}}
                <x-stepper.section id="financials" title="Financial Breakdown" description="Modify the gross amount and any adjustments. Net pay will be recalculated automatically.">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <flux:input type="number" step="0.01" min="0" name="gross_amount" label="Gross Amount" value="{{ old('gross_amount', $payment->gross_amount) }}" required />
                        </div>
                        
                        <flux:input type="number" step="0.01" min="0" name="allowances" label="Allowances (+)" value="{{ old('allowances', $payment->allowances) }}" />
                        
                        <flux:input type="number" step="0.01" min="0" name="deductions" label="Deductions (-)" value="{{ old('deductions', $payment->deductions) }}" description="Tax, loans, or salary advances." />
                        
                        <div class="md:col-span-2">
                            <div class="bg-indigo-50/50 dark:bg-indigo-900/10 border border-indigo-200/50 dark:border-indigo-800/30 rounded-xl p-4 flex items-start gap-3">
                                <flux:icon icon="calculator" class="size-5 text-indigo-500 mt-0.5 shrink-0" />
                                <div class="text-sm text-indigo-800 dark:text-indigo-300">
                                    Current Net Pay: <strong class="font-bold">{{ number_format($payment->net_amount, 2) }}</strong>. 
                                    This will be recalculated automatically if you change the values above.
                                </div>
                            </div>
                        </div>
                    </div>
                </x-stepper.section>

                {{-- Step 3: Processing --}}
                <x-stepper.section id="processing" title="Processing Details" description="Update the transaction status and method.">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <flux:select name="payment_method" label="Payment Method">
                            @foreach (['bank_transfer', 'cash', 'mobile_money', 'cheque'] as $paymentMethod)
                                <flux:select.option value="{{ $paymentMethod }}" :selected="old('payment_method', $payment->payment_method) === $paymentMethod">
                                    {{ ucfirst(str_replace('_', ' ', $paymentMethod)) }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                        
                        <flux:select name="status" label="Status">
                            @foreach (['pending', 'paid', 'cancelled'] as $status)
                                <flux:select.option value="{{ $status }}" :selected="old('status', $payment->status) === $status">
                                    {{ ucfirst($status) }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                        
                        <div class="md:col-span-2">
                            <flux:input type="text" name="reference" label="Transaction Reference" value="{{ old('reference', $payment->reference) }}" description="Cheque or bank transaction number." />
                        </div>
                        
                        <div class="md:col-span-2">
                            <flux:textarea name="notes" rows="3" label="Notes">{{ old('notes', $payment->notes) }}</flux:textarea>
                        </div>
                    </div>
                </x-stepper.section>

                <x-slot:actions>
                    <flux:button href="{{ route('staff.payments.show', $payment) }}" wire:navigate.hover variant="subtle" class="font-semibold shadow-sm">
                        Cancel
                    </flux:button>
                    <flux:button type="submit" variant="primary" class="font-semibold shadow-sm px-6">
                        Save Changes
                    </flux:button>
                </x-slot:actions>

            </x-stepper.layout>
        </form>
    </div>

    @else
    <div class="flex flex-col gap-6 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">
        
        {{-- Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-2">
            <div>
                <flux:heading size="xl" class="font-bold tracking-tight text-zinc-900 dark:text-white">Record Payment</flux:heading>
                <flux:text class="text-zinc-500 text-sm mt-1">
                    Process a new payroll or record a completed transaction.
                </flux:text>
            </div>
            
            <div class="flex items-center gap-3">
                <flux:button href="{{ route('staff.payments.index') }}" wire:navigate.hover icon="arrow-left" variant="subtle" class="font-semibold shadow-sm">
                    Back to Payroll
                </flux:button>
            </div>
        </div>

        @if ($errors->any())
            <div class="rounded-xl border border-red-200/50 bg-red-50/50 dark:bg-red-500/10 dark:border-red-500/20 p-4 flex items-start shadow-sm mb-6">
                <flux:icon icon="exclamation-circle" class="size-5 text-red-500 mr-3 mt-0.5" />
                <div class="text-sm text-red-800 dark:text-red-400">
                    <p class="font-semibold mb-1">{{ $errors->count() }} error(s) prevented this payment from being saved:</p>
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <form action="{{ route('staff.payments.store') }}" method="POST">
            @csrf

            <x-stepper.layout defaultActive="payment-details">
                
                <x-slot:sidebar>
                    <x-stepper.step id="payment-details" title="Payment Details" description="Staff and period." isLast="false" />
                    <x-stepper.step id="financials" title="Financials" description="Gross, allowances, deductions." isLast="false" />
                    <x-stepper.step id="processing" title="Processing" description="Method, status, reference." isLast="true" />
                </x-slot:sidebar>

                {{-- Step 1: Payment Details --}}
                <x-stepper.section id="payment-details" title="Payment Details" description="Select the staff member and the payroll period for this transaction.">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <flux:select name="staff_details_id" label="Staff Member" required>
                                <flux:select.option value="">Select Staff Member...</flux:select.option>
                                @foreach ($this->staffMembers as $member)
                                    <flux:select.option value="{{ $member->id }}" :selected="old('staff_details_id') == $member->id">
                                        <span translate="no">{{ $member->name }}</span>{{ $member->job_title ? ' — '.$member->job_title : '' }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                        
                        <div class="md:col-span-2">
                            <flux:input type="month" name="period" label="Payroll Period" value="{{ old('period', now()->format('Y-m')) }}" required />
                        </div>
                    </div>
                </x-stepper.section>

                {{-- Step 2: Financials --}}
                <x-stepper.section id="financials" title="Financial Breakdown" description="Enter the gross amount and any adjustments. Net pay will be calculated automatically.">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <flux:input type="number" step="0.01" min="0" name="gross_amount" label="Gross Amount" value="{{ old('gross_amount') }}" placeholder="0.00" required />
                        </div>
                        
                        <flux:input type="number" step="0.01" min="0" name="allowances" label="Allowances (+)" value="{{ old('allowances', '0.00') }}" placeholder="0.00" />
                        
                        <flux:input type="number" step="0.01" min="0" name="deductions" label="Deductions (-)" value="{{ old('deductions', '0.00') }}" placeholder="0.00" description="Tax, loans, or salary advances." />
                        
                        <div class="md:col-span-2">
                            <div class="bg-indigo-50/50 dark:bg-indigo-900/10 border border-indigo-200/50 dark:border-indigo-800/30 rounded-xl p-4 flex items-start gap-3">
                                <flux:icon icon="calculator" class="size-5 text-indigo-500 mt-0.5 shrink-0" />
                                <div class="text-sm text-indigo-800 dark:text-indigo-300">
                                    The <strong>Net Pay</strong> is calculated automatically for you when you save: 
                                    <br><code>Gross Amount + Allowances - Deductions</code>
                                </div>
                            </div>
                        </div>
                    </div>
                </x-stepper.section>

                {{-- Step 3: Processing --}}
                <x-stepper.section id="processing" title="Processing Details" description="How is this payment being handled?">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <flux:select name="payment_method" label="Payment Method">
                            @foreach (['bank_transfer', 'cash', 'mobile_money', 'cheque'] as $paymentMethod)
                                <flux:select.option value="{{ $paymentMethod }}" :selected="old('payment_method', 'bank_transfer') === $paymentMethod">
                                    {{ ucfirst(str_replace('_', ' ', $paymentMethod)) }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                        
                        <flux:select name="status" label="Status">
                            @foreach (['pending', 'paid', 'cancelled'] as $status)
                                <flux:select.option value="{{ $status }}" :selected="old('status', 'pending') === $status">
                                    {{ ucfirst($status) }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                        
                        <div class="md:col-span-2">
                            <flux:input type="text" name="reference" label="Transaction Reference" value="{{ old('reference') }}" placeholder="e.g. TR-298374..." description="Cheque or bank transaction number." />
                        </div>
                        
                        <div class="md:col-span-2">
                            <flux:textarea name="notes" rows="3" label="Notes" placeholder="Any additional context about this payment...">{{ old('notes') }}</flux:textarea>
                        </div>
                    </div>
                </x-stepper.section>

                <x-slot:actions>
                    <flux:button href="{{ route('staff.payments.index') }}" wire:navigate.hover variant="subtle" class="font-semibold shadow-sm">
                        Cancel
                    </flux:button>
                    <flux:button type="submit" variant="primary" class="font-semibold shadow-sm px-6">
                        Save Payment
                    </flux:button>
                </x-slot:actions>

            </x-stepper.layout>
        </form>
    </div>
    @endif
</div>
