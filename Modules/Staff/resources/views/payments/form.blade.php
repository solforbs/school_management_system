@php
    $isEdit = !is_null($payment);
    $selectedStaffId = old('staff_details_id', $payment?->staff_details_id);
    $periodVal = old('period', $payment?->period ? $payment->period->format('Y-m') : now()->format('Y-m'));
    $grossVal = old('gross_amount', $payment?->gross_amount ?? '');
    $allowancesVal = old('allowances', $payment?->allowances ?? 0);
    $deductionsVal = old('deductions', $payment?->deductions ?? 0);
    $methodVal = old('payment_method', $payment?->payment_method ?? 'bank_transfer');
    $statusVal = old('status', $payment?->status ?? 'paid');
    $referenceVal = old('reference', $payment?->reference ?? '');
    $notesVal = old('notes', $payment?->notes ?? '');
@endphp

<div class="flex flex-col gap-6" x-data="{
    gross: {{ (float) ($grossVal ?: 0) }},
    allowances: {{ (float) ($allowancesVal ?: 0) }},
    deductions: {{ (float) ($deductionsVal ?: 0) }},
    staffSalaries: {
        @foreach ($staffMembers as $s)
            '{{ $s->id }}': {{ (float) ($s->salary ?: 0) }},
        @endforeach
    },
    onStaffChange(id) {
        if (this.staffSalaries[id] !== undefined && this.gross === 0) {
            this.gross = this.staffSalaries[id];
        }
    },
    get net() {
        return Math.max(0, (parseFloat(this.gross) || 0) + (parseFloat(this.allowances) || 0) - (parseFloat(this.deductions) || 0));
    }
}">

    {{-- Staff Selection & Period --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <div>
            <label class="text-xs font-bold text-zinc-700 block mb-1.5">Staff Personnel *</label>
            <select name="staff_details_id" required @change="onStaffChange($event.target.value)"
                class="w-full px-3.5 py-2.5 bg-white border border-zinc-200 rounded-xl text-xs font-semibold text-zinc-900 focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
                <option value="">Select Staff Member</option>
                @foreach ($staffMembers as $s)
                    <option value="{{ $s->id }}" {{ $selectedStaffId == $s->id ? 'selected' : '' }}>
                        {{ $s->name }} ({{ $s->staff_number ?? 'EMP-S-' . $s->id }}) - {{ $s->job_title ?? 'Staff' }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="text-xs font-bold text-zinc-700 block mb-1.5">Payroll Period (Month) *</label>
            <input type="month" name="period" value="{{ $periodVal }}" required
                class="w-full px-3.5 py-2.5 bg-white border border-zinc-200 rounded-xl text-xs font-semibold text-zinc-900 focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
        </div>
    </div>

    {{-- Compensation Calculation Box --}}
    <div class="p-5 rounded-2xl bg-zinc-50 border border-zinc-200/80">
        <h4 class="text-xs font-bold uppercase tracking-wider text-zinc-700 mb-4">Financial Computation</h4>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="text-xs font-bold text-zinc-600 block mb-1">Gross Salary (KES) *</label>
                <input type="number" step="0.01" min="0" name="gross_amount" x-model.number="gross" required
                    class="w-full px-3.5 py-2 bg-white border border-zinc-200 rounded-xl text-xs font-mono font-bold focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
            </div>

            <div>
                <label class="text-xs font-bold text-zinc-600 block mb-1">Allowances (KES)</label>
                <input type="number" step="0.01" min="0" name="allowances" x-model.number="allowances"
                    class="w-full px-3.5 py-2 bg-white border border-zinc-200 rounded-xl text-xs font-mono font-bold text-emerald-700 focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
            </div>

            <div>
                <label class="text-xs font-bold text-zinc-600 block mb-1">Deductions (KES)</label>
                <input type="number" step="0.01" min="0" name="deductions" x-model.number="deductions"
                    class="w-full px-3.5 py-2 bg-white border border-zinc-200 rounded-xl text-xs font-mono font-bold text-rose-600 focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
            </div>
        </div>

        {{-- Dynamic Live Net Preview --}}
        <div class="mt-4 pt-4 border-t border-zinc-200 flex items-center justify-between text-xs">
            <span class="font-bold text-zinc-600">Calculated Net Payout:</span>
            <span class="font-mono text-base font-black text-zinc-900">
                KES <span x-text="net.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })">0.00</span>
            </span>
        </div>
    </div>

    {{-- Method, Status, & Reference --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
            <label class="text-xs font-bold text-zinc-700 block mb-1.5">Disbursement Method *</label>
            <select name="payment_method" required
                class="w-full px-3.5 py-2 bg-white border border-zinc-200 rounded-xl text-xs font-semibold text-zinc-900 focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
                <option value="bank_transfer" {{ $methodVal === 'bank_transfer' ? 'selected' : '' }}>Bank Transfer</option>
                <option value="mobile_money" {{ $methodVal === 'mobile_money' ? 'selected' : '' }}>Mobile Money (M-Pesa)</option>
                <option value="cash" {{ $methodVal === 'cash' ? 'selected' : '' }}>Cash</option>
                <option value="cheque" {{ $methodVal === 'cheque' ? 'selected' : '' }}>Cheque</option>
            </select>
        </div>

        <div>
            <label class="text-xs font-bold text-zinc-700 block mb-1.5">Payment Status *</label>
            <select name="status" required
                class="w-full px-3.5 py-2 bg-white border border-zinc-200 rounded-xl text-xs font-semibold text-zinc-900 focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
                <option value="paid" {{ $statusVal === 'paid' ? 'selected' : '' }}>Paid</option>
                <option value="pending" {{ $statusVal === 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="cancelled" {{ $statusVal === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
            </select>
        </div>

        <div>
            <label class="text-xs font-bold text-zinc-700 block mb-1.5">Transaction Reference</label>
            <input type="text" name="reference" value="{{ $referenceVal }}" placeholder="e.g. TRN-8821992"
                class="w-full px-3.5 py-2 bg-white border border-zinc-200 rounded-xl text-xs font-mono focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
        </div>
    </div>

    {{-- Notes --}}
    <div>
        <label class="text-xs font-bold text-zinc-700 block mb-1.5">Remarks & Notes</label>
        <textarea name="notes" rows="2" placeholder="Optional notes on allowances or special adjustments..."
            class="w-full px-3.5 py-2 bg-white border border-zinc-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">{{ $notesVal }}</textarea>
    </div>
</div>