@php
    $paidPct = $fee->amount > 0 ? min(100, round(($fee->amount_paid / $fee->amount) * 100, 1)) : 0;
    $payments = \Modules\FeeManagement\Models\FeePayment::where('fee_id', $fee->id)->orderBy('paid_at', 'desc')->get();
    $studentName = $fee->student?->name ?? 'Student';
    $admNum = $fee->student?->studentUserDetails?->admission_number ?? 'ADM-—';
@endphp

<x-layouts::app :title="__('Fee Statement: ' . $fee->title)">
    <div class="flex flex-col gap-6 w-full max-w-full mx-auto p-4 sm:p-6 lg:p-8">

        {{-- Top Navigation & Actions --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('feemanagement.index') }}" wire:navigate.hover
                    class="p-2.5 rounded-xl bg-zinc-100 hover:bg-zinc-200 text-zinc-600 transition">
                    <flux:icon icon="arrow-left" class="size-5" />
                </a>
                <div>
                    <div class="flex items-center gap-2">
                        <flux:heading size="xl" class="font-black text-zinc-900">{{ $fee->title }}</flux:heading>
                        <span
                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider
                            {{ $fee->status === 'paid'
                                ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                                : ($fee->status === 'partial'
                                    ? 'bg-amber-50 text-amber-700 border border-amber-200'
                                    : 'bg-rose-50 text-rose-700 border border-rose-200') }}">
                            {{ ucfirst($fee->status) }}
                        </span>
                    </div>
                    <flux:text class="text-zinc-500 text-sm mt-0.5">
                        {{ $studentName }} &middot; {{ $admNum }} &middot; <span translate="no">{{ $fee->institution?->name }}</span>
                    </flux:text>
                </div>
            </div>

            <div class="flex items-center gap-3">
                @can('edit feemanagement')
                    <flux:button href="{{ route('feemanagement.edit', $fee->id) }}" wire:navigate.hover icon="pencil"
                        variant="primary" class="font-semibold shadow-sm px-4">
                        Edit Fee
                    </flux:button>
                @endcan
                <a href="{{ route('feemanagement.index') }}" wire:navigate.hover
                    class="px-4 py-2 bg-white border border-zinc-300 rounded-xl text-sm font-medium text-zinc-700 hover:bg-zinc-50 transition">
                    Back to List
                </a>
            </div>
        </div>

        {{-- Clean Light Financial Hero Card --}}
        <div class="bg-white rounded-2xl p-6 sm:p-8 border border-zinc-200/80 shadow-sm relative overflow-hidden">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 relative z-10">
                <div class="p-4 rounded-xl bg-zinc-50 border border-zinc-100">
                    <span class="text-xs font-bold uppercase tracking-wider text-zinc-400">Total Billed</span>
                    <div class="text-2xl font-black text-zinc-900 mt-1">KSh {{ number_format($fee->amount, 2) }}</div>
                    <span class="text-xs text-zinc-500 mt-1 block font-medium">Due:
                        {{ $fee->due_date ? $fee->due_date->format('d M Y') : 'No deadline' }}</span>
                </div>

                <div class="p-4 rounded-xl bg-emerald-50/60 border border-emerald-100">
                    <span class="text-xs font-bold uppercase tracking-wider text-emerald-700">Total Paid</span>
                    <div class="text-2xl font-black text-emerald-600 mt-1">KSh
                        {{ number_format($fee->amount_paid, 2) }}</div>
                    <span class="text-xs text-emerald-700 mt-1 block font-semibold">{{ $paidPct }}% Cleared</span>
                </div>

                <div class="p-4 rounded-xl bg-rose-50/60 border border-rose-100">
                    <span class="text-xs font-bold uppercase tracking-wider text-rose-700">Outstanding Balance</span>
                    <div class="text-2xl font-black text-rose-600 mt-1">KSh {{ number_format($fee->balance, 2) }}</div>
                    <span
                        class="text-xs text-rose-600 mt-1 block font-medium">{{ $fee->balance > 0 ? 'Payment Required' : 'Fully Settled' }}</span>
                </div>
            </div>

            {{-- Progress Bar --}}
            <div class="mt-6 pt-5 border-t border-zinc-100">
                <div class="flex items-center justify-between text-xs font-semibold mb-2">
                    <span class="text-zinc-600">Payment Progress</span>
                    <span class="text-emerald-600 font-bold">{{ $paidPct }}%</span>
                </div>
                <div class="w-full bg-zinc-100 rounded-full h-2.5 overflow-hidden">
                    <div class="h-2.5 rounded-full bg-gradient-to-r from-emerald-500 to-teal-400 transition-all duration-500"
                        style="width: {{ $paidPct }}%;"></div>
                </div>
            </div>
        </div>

        {{-- Associations & Breakdown Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

            {{-- Student & Parent Association --}}
            <div class="bg-white rounded-2xl border border-zinc-200/80 p-6 shadow-sm">
                <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-400 mb-4 flex items-center gap-2">
                    <flux:icon icon="user" class="size-4 text-blue-500" /> Linked Beneficiary
                </h3>
                <div class="space-y-4">
                    <div>
                        <p class="text-xs text-zinc-400 font-medium">Student Name</p>
                        <p class="text-base font-bold text-zinc-900 mt-0.5">{{ $studentName }}</p>
                        <p class="text-xs text-zinc-500 font-mono mt-0.5">{{ $admNum }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-zinc-400 font-medium">Parent / Guardian</p>
                        <p class="text-sm font-semibold text-zinc-800 mt-0.5">
                            <span translate="no">{{ $fee->parent?->name ?? 'Parent record not linked' }}</span></p>
                        @if ($fee->parent?->email)
                            <p class="text-xs text-zinc-500 mt-0.5"><span translate="no">{{ $fee->parent->email }}</span></p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Fee Particulars & Remarks --}}
            <div class="bg-white rounded-2xl border border-zinc-200/80 p-6 shadow-sm">
                <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-400 mb-4 flex items-center gap-2">
                    <flux:icon icon="tag" class="size-4 text-purple-500" /> Fee Classification
                </h3>
                <div class="space-y-4">
                    <div>
                        <p class="text-xs text-zinc-400 font-medium">Category</p>
                        <span
                            class="inline-flex items-center px-2.5 py-1 rounded-lg bg-zinc-100 text-xs font-bold uppercase text-zinc-700 mt-1">
                            {{ ucfirst($fee->fee_type) }}
                        </span>
                    </div>
                    <div>
                        <p class="text-xs text-zinc-400 font-medium">Additional Remarks / Notes</p>
                        <p class="text-sm text-zinc-600 mt-0.5 leading-relaxed">
                            {{ $fee->notes ?: 'No special remarks recorded.' }}</p>
                    </div>
                </div>
            </div>

        </div>

        {{-- Payment History Ledger Table --}}
        <div class="bg-white rounded-2xl border border-zinc-200/80 p-6 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-base font-bold text-zinc-900">Transaction History & Receipts</h3>
                    <p class="text-xs text-zinc-500 mt-0.5">Audited log of all M-Pesa and bank transfer payments
                        received for this fee.</p>
                </div>
                <span class="px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 text-xs font-bold">
                    {{ count($payments) }} Transactions
                </span>
            </div>

            <div class="border border-zinc-200/80 rounded-xl overflow-x-auto">
                <table class="w-full text-left text-sm min-w-[560px]">
                    <thead class="bg-zinc-50 text-zinc-500 text-xs font-bold uppercase">
                        <tr>
                            <th class="py-3 px-4">Receipt Ref</th>
                            <th class="py-3 px-4">Payment Method</th>
                            <th class="py-3 px-4">Date & Time</th>
                            <th class="py-3 px-4">Amount Paid</th>
                            <th class="py-3 px-4">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @forelse ($payments as $pay)
                            <tr class="hover:bg-zinc-50/50">
                                <td class="py-3 px-4 font-mono font-bold text-zinc-900 text-xs">
                                    {{ $pay->reference ?? 'REF-MANUAL' }}
                                </td>
                                <td class="py-3 px-4 font-semibold text-zinc-700 text-xs uppercase">
                                    <span class="inline-flex items-center gap-1.5">
                                        <span class="size-2 rounded-full bg-emerald-500"></span>
                                        {{ $pay->payment_method ?? 'M-Pesa' }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-xs text-zinc-500">
                                    {{ $pay->paid_at ? \Carbon\Carbon::parse($pay->paid_at)->format('d M Y, h:i A') : '—' }}
                                </td>
                                <td class="py-3 px-4 font-bold text-emerald-600 text-sm">
                                    KSh {{ number_format($pay->amount, 2) }}
                                </td>
                                <td class="py-3 px-4">
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700">
                                        Confirmed
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center text-xs text-zinc-400 font-medium">
                                    No recorded payment transactions yet for this fee.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</x-layouts::app>
