@php
    $staff = $payment->staff;
    $initials = $staff ? strtoupper(substr($staff->name, 0, 2)) : 'ST';
    $staffNo = $staff?->staff_number ?? 'EMP-S-' . str_pad($payment->id, 3, '0', STR_PAD_LEFT);
    $periodName = $payment->period?->format('F Y') ?? 'Period';
@endphp

<x-layouts::app :title="__('Payslip Voucher')">
    <div class="flex flex-col gap-8 w-full max-w-full mx-auto p-4 sm:p-6 lg:p-8">

        {{-- Top Navigation & Breadcrumbs --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1.5">
                    <a href="{{ route('staff.payments.index') }}"
                        class="inline-flex items-center gap-1.5 text-xs font-bold text-zinc-500 hover:text-zinc-900 transition">
                        <flux:icon icon="arrow-left" class="size-3.5" />
                        <span>Back to Payroll Ledger</span>
                    </a>
                    <span class="text-xs text-zinc-300">&bull;</span>
                    <span class="text-xs text-zinc-500 font-medium">Payment Voucher</span>
                </div>
                <flux:heading size="xl" class="font-black tracking-tight text-zinc-900">Salary Disbursement Voucher
                </flux:heading>
                <flux:text class="text-zinc-500 text-sm mt-0.5">
                    Official payslip and compensation breakdown for {{ $periodName }}.
                </flux:text>
            </div>

            <div class="flex items-center gap-2">
                <button onclick="window.print()"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-zinc-100 hover:bg-zinc-200 text-zinc-800 text-xs font-bold transition">
                    <flux:icon icon="printer" class="size-3.5" />
                    <span>Print Payslip</span>
                </button>
                @can('edit payroll')
                    <flux:button href="{{ route('staff.payments.edit', $payment->id) }}" icon="pencil-square"
                        variant="primary" class="font-semibold shadow-sm">
                        Edit Voucher
                    </flux:button>
                @endcan
            </div>
        </div>

        {{-- Flash Messages --}}
        @if (session('success'))
            <div
                class="rounded-2xl border border-emerald-200 bg-emerald-50/80 p-4 flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-3">
                    <flux:icon icon="check-circle" class="size-5 text-emerald-600 shrink-0" />
                    <span class="text-sm font-semibold text-emerald-900">{{ session('success') }}</span>
                </div>
            </div>
        @endif

        {{-- Official Payslip Voucher Card --}}
        <div class="bg-white rounded-3xl p-6 sm:p-10 border border-zinc-200/80 shadow-sm relative overflow-hidden">
            {{-- Header with Institution & Voucher Info --}}
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-zinc-100">
                <div class="flex items-center gap-3.5">
                    <div
                        class="size-12 rounded-2xl bg-zinc-900 text-white flex items-center justify-center font-black text-base shadow-sm">
                        {{ strtoupper(substr($payment->institution?->name ?? (currentInstitution()?->name ?? 'SC'), 0, 2)) }}
                    </div>
                    <div>
                        <h3 class="text-base font-black text-zinc-900">
                            {{ $payment->institution?->name ?? (currentInstitution()?->name ?? __('Institution')) }}</h3>
                        <p class="text-xs text-zinc-500">Monthly Compensation & Statutory Disbursement Voucher</p>
                    </div>
                </div>

                <div class="flex flex-col sm:items-end text-xs">
                    <span class="font-mono font-bold text-zinc-900">Voucher
                        #PAY-{{ str_pad($payment->id, 5, '0', STR_PAD_LEFT) }}</span>
                    <span class="text-zinc-400 mt-0.5">Period: <strong
                            class="text-zinc-700 font-semibold">{{ $periodName }}</strong></span>
                </div>
            </div>

            {{-- Employee Identification Block --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 my-6 p-5 rounded-2xl bg-zinc-50 border border-zinc-100">
                <div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-1">Employee
                        Name</span>
                    <div class="font-black text-zinc-900 text-sm">
                        @if ($staff)
                            <a href="{{ route('staff.show', $staff->id) }}" class="hover:text-blue-600 transition">
                                {{ $staff->name }}
                            </a>
                        @else
                            <span>Staff Member</span>
                        @endif
                    </div>
                    <span class="text-xs text-zinc-500 font-mono">{{ $staffNo }}</span>
                </div>

                <div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-1">Designation &
                        Department</span>
                    <div class="font-bold text-zinc-900 text-xs">{{ $staff?->job_title ?? 'Staff Personnel' }}</div>
                    <span class="text-xs text-zinc-500">{{ $staff?->department ?? 'General' }}</span>
                </div>

                <div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-1">Payment Method
                        & Status</span>
                    <div class="font-bold text-zinc-900 text-xs">
                        {{ ucfirst(str_replace('_', ' ', $payment->payment_method)) }}</div>
                    <span
                        class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-bold mt-1
                        {{ $payment->status === 'paid' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200' }}">
                        <span
                            class="size-1.5 rounded-full {{ $payment->status === 'paid' ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                        {{ ucfirst($payment->status) }}
                    </span>
                </div>
            </div>

            {{-- Financial Breakdown Table --}}
            <div class="space-y-3">
                <h4 class="text-xs font-bold uppercase tracking-wider text-zinc-700">Financial Breakdown</h4>

                <div class="border border-zinc-200/80 rounded-2xl overflow-hidden text-xs">
                    <div
                        class="p-3.5 bg-zinc-50 border-b border-zinc-200/80 flex items-center justify-between font-bold text-zinc-600">
                        <span>Earnings / Allowances</span>
                        <span>Amount (KES)</span>
                    </div>

                    <div class="p-3.5 flex items-center justify-between border-b border-zinc-100">
                        <span class="text-zinc-800 font-medium">Basic Gross Salary</span>
                        <span class="font-mono font-bold text-zinc-900">KES
                            {{ number_format($payment->gross_amount, 2) }}</span>
                    </div>

                    <div class="p-3.5 flex items-center justify-between border-b border-zinc-100">
                        <span class="text-zinc-800 font-medium">Allowances (Housing, Commuter, Responsibility)</span>
                        <span class="font-mono font-bold text-emerald-700">+KES
                            {{ number_format($payment->allowances, 2) }}</span>
                    </div>

                    <div class="p-3.5 flex items-center justify-between border-b border-zinc-100">
                        <span class="text-zinc-800 font-medium">Statutory Deductions (PAYE, NSSF, SHIF, Housing
                            Levy)</span>
                        <span class="font-mono font-bold text-rose-600">-KES
                            {{ number_format($payment->deductions, 2) }}</span>
                    </div>

                    <div class="p-4 bg-zinc-50 flex items-center justify-between text-sm font-black text-zinc-900">
                        <span>Net Pay (Take-Home Disbursement)</span>
                        <span class="font-mono text-base text-zinc-900">KES
                            {{ number_format($payment->net_amount, 2) }}</span>
                    </div>
                </div>
            </div>

            {{-- Reference & Authorization --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-6 pt-6 border-t border-zinc-100 text-xs text-zinc-500">
                <div>
                    <span class="text-zinc-400 block mb-0.5">Payment Reference:</span>
                    <strong class="text-zinc-800 font-mono">{{ $payment->reference ?: 'System Internal Ref' }}</strong>
                </div>
                <div class="sm:text-right">
                    <span class="text-zinc-400 block mb-0.5">Authorized & Logged By:</span>
                    <strong class="text-zinc-800">{{ $payment->recordedBy?->name ?? 'Accounts Officer' }}</strong>
                </div>
            </div>

            @if ($payment->notes)
                <div class="mt-6 p-4 rounded-2xl bg-zinc-50 border border-zinc-100 text-xs">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-1">Payroll
                        Remarks</span>
                    <p class="text-zinc-800 font-medium leading-relaxed italic whitespace-pre-line">
                        &ldquo;{{ $payment->notes }}&rdquo;</p>
                </div>
            @endif
        </div>

        {{-- Footer Controls --}}
        <div class="bg-zinc-50 rounded-2xl p-4 border border-zinc-200/80 flex items-center justify-between">
            <flux:button href="{{ route('staff.payments.index') }}" variant="subtle" icon="arrow-left">
                Back to Payroll Ledger
            </flux:button>

            <div class="flex items-center gap-2">
                @can('edit payroll')
                    <flux:button href="{{ route('staff.payments.edit', $payment->id) }}" icon="pencil-square"
                        variant="primary">
                        Edit Voucher
                    </flux:button>
                @endcan

                @can('delete payroll')
                    <x-confirm-form
                        :action="route('staff.payments.destroy', $payment->id)"
                        title="Remove this payroll disbursement record?"
                        message="This permanently deletes the disbursement entry."
                        confirm="Delete Record"
                        label="Delete"
                        variant="subtle"
                        trigger-class="text-rose-600 hover:bg-rose-50"
                        size="base" />
                @endcan
            </div>
        </div>

    </div>
</x-layouts::app>
