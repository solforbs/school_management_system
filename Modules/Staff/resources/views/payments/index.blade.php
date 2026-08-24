@php
    $totalCount = method_exists($payments, 'total') ? $payments->total() : count($payments);
    $currentPeriod = request('period');
    $currentStatus = request('status');
    $currentSearch = request('search');
    $hasActiveFilters = !empty($currentPeriod) || !empty($currentStatus) || !empty($currentSearch);
@endphp

<x-layouts::app :title="__('Staff Payroll Ledger')">
    <div class="flex flex-col gap-8 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">

        {{-- Top Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold tracking-wide uppercase bg-emerald-50 text-emerald-700 border border-emerald-200/60">
                        Financial Disbursements
                    </span>
                    <span class="text-xs text-zinc-400">&bull;</span>
                    <span class="text-xs text-zinc-500 font-medium">{{ $institution?->name ?? currentInstitution()?->name ?? 'Green Valley Academy' }}</span>
                </div>
                <flux:heading size="xl" class="font-black tracking-tight text-zinc-900">Staff Payroll Ledger</flux:heading>
                <flux:text class="text-zinc-500 text-sm mt-0.5">
                    Manage monthly compensation disbursements, allowances, statutory deductions, and payslip vouchers.
                </flux:text>
            </div>

            <div class="flex items-center gap-3">
                <flux:button href="{{ route('staff.index') }}" icon="users" variant="subtle" class="font-semibold shadow-sm">
                    Staff Directory
                </flux:button>
                @can('create payroll')
                    <flux:button href="{{ route('staff.payments.create') }}" icon="plus" variant="primary" class="font-semibold shadow-sm px-4">
                        Record Payment
                    </flux:button>
                @endcan
            </div>
        </div>

        {{-- Flash Messages --}}
        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50/80 p-4 flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-3">
                    <flux:icon icon="check-circle" class="size-5 text-emerald-600 shrink-0" />
                    <span class="text-sm font-semibold text-emerald-900">{{ session('success') }}</span>
                </div>
            </div>
        @endif

        {{-- Top Payroll Financial KPI Strip --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
            {{-- Card 1: Total Payroll Expected --}}
            <div class="bg-white rounded-2xl p-5 border border-zinc-200/80 shadow-sm hover:border-zinc-300 transition duration-150">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-semibold uppercase tracking-wider text-zinc-400">Total Payroll Commitment</span>
                    <div class="size-9 rounded-xl bg-zinc-100 flex items-center justify-center text-zinc-700">
                        <flux:icon icon="banknotes" class="size-4" />
                    </div>
                </div>
                <div class="text-2xl font-black text-zinc-900 tracking-tight">
                    KES {{ number_format($totalPayroll, 2) }}
                </div>
                <div class="text-xs text-zinc-500 mt-2 font-medium">
                    Total gross compensation indexed
                </div>
            </div>

            {{-- Card 2: Total Paid Out --}}
            <div class="bg-white rounded-2xl p-5 border border-zinc-200/80 shadow-sm hover:border-zinc-300 transition duration-150">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-semibold uppercase tracking-wider text-emerald-700">Completed Disbursements</span>
                    <div class="size-9 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-600">
                        <flux:icon icon="check-badge" class="size-4" />
                    </div>
                </div>
                <div class="text-2xl font-black text-emerald-600 tracking-tight">
                    KES {{ number_format($totalPaid, 2) }}
                </div>
                <div class="text-xs text-zinc-500 mt-2 font-medium">
                    Verified net funds transferred
                </div>
            </div>

            {{-- Card 3: Pending Balance --}}
            <div class="bg-white rounded-2xl p-5 border border-zinc-200/80 shadow-sm hover:border-zinc-300 transition duration-150">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-semibold uppercase tracking-wider text-amber-700">Pending Authorization</span>
                    <div class="size-9 rounded-xl bg-amber-50 flex items-center justify-center text-amber-600">
                        <flux:icon icon="clock" class="size-4" />
                    </div>
                </div>
                <div class="text-2xl font-black text-amber-600 tracking-tight">
                    KES {{ number_format($totalPending ?? 0, 2) }}
                </div>
                <div class="text-xs text-zinc-500 mt-2 font-medium">
                    Unsettled payroll vouchers
                </div>
            </div>
        </div>

        {{-- Filter & Search Toolbar --}}
        <div class="bg-white rounded-2xl p-4 border border-zinc-200/80 shadow-sm flex flex-col gap-3">
            <form method="GET" action="{{ route('staff.payments.index') }}" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">
                {{-- Period Selector --}}
                <div class="sm:col-span-4">
                    <input type="month" name="period" value="{{ $currentPeriod }}" onchange="this.form.submit()"
                        class="w-full px-3.5 py-2 bg-zinc-50 border border-zinc-200 rounded-xl text-xs font-medium text-zinc-800 focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
                </div>

                {{-- Status Selector --}}
                <div class="sm:col-span-3">
                    <select name="status" onchange="this.form.submit()"
                        class="w-full px-3.5 py-2 bg-zinc-50 border border-zinc-200 rounded-xl text-xs font-medium text-zinc-800 focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
                        <option value="">All Statuses</option>
                        <option value="paid" {{ $currentStatus === 'paid' ? 'selected' : '' }}>Paid</option>
                        <option value="pending" {{ $currentStatus === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="cancelled" {{ $currentStatus === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    </select>
                </div>

                {{-- Search Bar --}}
                <div class="sm:col-span-5 flex items-center gap-2">
                    <div class="relative w-full">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-zinc-400">
                            <flux:icon icon="magnifying-glass" class="size-4" />
                        </div>
                        <input type="text" name="search" value="{{ $currentSearch }}" placeholder="Search staff name, EMP no, ref..." 
                            class="w-full pl-10 pr-4 py-2 bg-zinc-50 border border-zinc-200 rounded-xl text-xs placeholder-zinc-400 focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
                    </div>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-white text-xs font-bold transition shrink-0">
                        Filter
                    </button>
                    @if($hasActiveFilters)
                        <a href="{{ route('staff.payments.index') }}" class="px-3 py-2 rounded-xl bg-zinc-100 hover:bg-zinc-200 text-zinc-600 text-xs font-semibold transition shrink-0" title="Reset Filters">
                            ✕
                        </a>
                    @endif
                </div>
            </form>

            {{-- Active Filter Scope Tags --}}
            @if ($hasActiveFilters)
                <div class="flex flex-wrap items-center gap-2 pt-2.5 border-t border-zinc-100 text-xs">
                    <span class="text-[11px] font-bold text-zinc-400 uppercase tracking-wider mr-1">Active Filter Scope:</span>

                    @if ($currentPeriod)
                        <a href="{{ route('staff.payments.index', ['status' => $currentStatus, 'search' => $currentSearch]) }}" 
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-zinc-100 hover:bg-zinc-200 text-zinc-800 font-semibold transition border border-zinc-200/60"
                            title="Remove Period Filter">
                            <span>Period: <strong>{{ \Carbon\Carbon::parse($currentPeriod . '-01')->format('F Y') }}</strong></span>
                            <span class="text-zinc-400 hover:text-zinc-700 font-bold">✕</span>
                        </a>
                    @endif

                    @if ($currentStatus)
                        <a href="{{ route('staff.payments.index', ['period' => $currentPeriod, 'search' => $currentSearch]) }}" 
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-zinc-100 hover:bg-zinc-200 text-zinc-800 font-semibold transition border border-zinc-200/60"
                            title="Remove Status Filter">
                            <span>Status: <strong>{{ ucfirst($currentStatus) }}</strong></span>
                            <span class="text-zinc-400 hover:text-zinc-700 font-bold">✕</span>
                        </a>
                    @endif

                    @if ($currentSearch)
                        <a href="{{ route('staff.payments.index', ['period' => $currentPeriod, 'status' => $currentStatus]) }}" 
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-800 font-semibold transition border border-blue-200/60"
                            title="Remove Search Keyword">
                            <span>Search: <strong>"{{ $currentSearch }}"</strong></span>
                            <span class="text-blue-500 hover:text-blue-800 font-bold">✕</span>
                        </a>
                    @endif

                    <a href="{{ route('staff.payments.index') }}" class="text-xs font-semibold text-rose-600 hover:text-rose-800 transition ml-auto">
                        Reset All Filters
                    </a>
                </div>
            @endif
        </div>

        {{-- Smart Grouped Payroll Ledger Table (Zero Horizontal Scroll) --}}
        <div class="bg-white shadow-sm border border-zinc-200/80 rounded-2xl overflow-hidden flex flex-col">
            @if($payments->isNotEmpty())
                <div class="w-full">
                    <table class="w-full text-left text-sm table-fixed">
                        <thead class="bg-zinc-50 text-zinc-500 border-b border-zinc-200/80 text-xs font-bold uppercase tracking-wider">
                            <tr>
                                <th class="py-3.5 px-5 w-4/12">Staff Personnel & Period</th>
                                <th class="py-3.5 px-5 w-3/12">Gross & Breakdown</th>
                                <th class="py-3.5 px-5 w-3/12">Net Payout & Method</th>
                                <th class="py-3.5 px-5 w-2/12 text-right">Status & Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100">
                            @foreach ($payments as $payment)
                                @php
                                    $staffMember = $payment->staff;
                                    $initials = $staffMember ? strtoupper(substr($staffMember->name, 0, 2)) : 'ST';
                                    $staffNo = $staffMember?->staff_number ?? 'EMP-S-' . str_pad($payment->id, 3, '0', STR_PAD_LEFT);
                                    $allowances = (float) $payment->allowances;
                                    $deductions = (float) $payment->deductions;
                                @endphp
                                <tr class="hover:bg-zinc-50/60 transition-colors">
                                    {{-- Col 1: Staff & Period --}}
                                    <td class="py-4 px-5">
                                        <div class="flex items-center gap-3">
                                            <div class="size-10 rounded-xl bg-zinc-100 border border-zinc-200 font-bold text-xs flex items-center justify-center text-zinc-800 shrink-0">
                                                {{ $initials }}
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <a href="{{ route('staff.payments.show', $payment->id) }}" class="font-bold text-zinc-900 hover:text-blue-600 transition truncate block">
                                                    {{ $staffMember?->name ?? 'Staff Member' }}
                                                </a>
                                                <div class="text-xs text-zinc-400 mt-0.5 flex flex-wrap items-center gap-1.5 font-mono">
                                                    <span class="px-1.5 py-0.5 rounded bg-zinc-100 text-zinc-700 font-semibold border border-zinc-200 text-[11px]">{{ $staffNo }}</span>
                                                    <span class="text-zinc-300">&bull;</span>
                                                    <span class="font-sans text-xs text-zinc-600 font-semibold">{{ $payment->period?->format('M Y') ?? '—' }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Col 2: Gross & Breakdown --}}
                                    <td class="py-4 px-5 font-mono text-xs">
                                        <div class="font-bold text-zinc-900">
                                            KES {{ number_format($payment->gross_amount, 2) }}
                                        </div>
                                        <div class="text-[11px] text-zinc-500 mt-1 flex items-center gap-2">
                                            <span class="text-emerald-700 font-medium">+{{ number_format($allowances, 2) }}</span>
                                            <span class="text-zinc-300">/</span>
                                            <span class="text-rose-600 font-medium">-{{ number_format($deductions, 2) }}</span>
                                        </div>
                                    </td>

                                    {{-- Col 3: Net Payout & Method --}}
                                    <td class="py-4 px-5">
                                        <div class="font-mono text-sm font-black text-zinc-900">
                                            KES {{ number_format($payment->net_amount, 2) }}
                                        </div>
                                        <div class="text-[11px] text-zinc-500 mt-1 flex items-center gap-1.5">
                                            <span class="px-2 py-0.5 rounded-md bg-zinc-100 text-zinc-700 font-medium border border-zinc-200/60">
                                                {{ ucfirst(str_replace('_', ' ', $payment->payment_method)) }}
                                            </span>
                                            @if($payment->reference)
                                                <span class="font-mono text-zinc-400 truncate max-w-[90px]" title="{{ $payment->reference }}">
                                                    {{ $payment->reference }}
                                                </span>
                                            @endif
                                        </div>
                                    </td>

                                    {{-- Col 4: Status & Actions --}}
                                    <td class="py-4 px-5 text-right">
                                        <div class="flex flex-col items-end gap-2">
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold
                                                {{ $payment->status === 'paid' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' :
                                                   ($payment->status === 'cancelled' ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-amber-50 text-amber-700 border border-amber-200') }}">
                                                <span class="size-1.5 rounded-full {{ $payment->status === 'paid' ? 'bg-emerald-500' : ($payment->status === 'cancelled' ? 'bg-rose-500' : 'bg-amber-500') }}"></span>
                                                {{ ucfirst($payment->status) }}
                                            </span>

                                            <div class="flex items-center justify-end gap-1">
                                                <flux:button href="{{ route('staff.payments.show', $payment->id) }}" size="sm" icon="eye" variant="subtle" tooltip="View Payslip Voucher" />
                                                @can('edit payroll')
                                                    <flux:button href="{{ route('staff.payments.edit', $payment->id) }}" size="sm" icon="pencil-square" variant="subtle" tooltip="Edit Payment" />
                                                @endcan
                                                @can('delete payroll')
                                                    <form action="{{ route('staff.payments.destroy', $payment->id) }}" method="POST" class="inline" onsubmit="return confirm('Remove this payroll disbursement?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <flux:button type="submit" size="sm" icon="trash" variant="subtle" class="text-rose-500 hover:text-rose-600 hover:bg-rose-50" tooltip="Delete" />
                                                    </form>
                                                @endcan
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Table Footer Summary & Pagination --}}
                <div class="border-t border-zinc-200/80 px-6 py-4 bg-zinc-50/50 flex flex-col sm:flex-row justify-between items-center text-xs text-zinc-500 gap-4">
                    <div class="flex items-center gap-3">
                        <span>Showing <strong class="text-zinc-800 font-semibold">{{ $payments->count() }}</strong> of <strong class="text-zinc-800 font-semibold">{{ number_format($totalCount) }}</strong> payment records</span>
                        <span class="text-zinc-300">&bull;</span>
                        <span>Page <strong class="text-zinc-800 font-semibold">{{ $payments->currentPage() }}</strong> of <strong class="text-zinc-800 font-semibold">{{ $payments->lastPage() }}</strong></span>
                    </div>
                    @if(method_exists($payments, 'links') && $payments->hasPages())
                        <div>
                            {{ $payments->links() }}
                        </div>
                    @endif
                </div>
            @else
                {{-- Empty State --}}
                <div class="flex flex-col items-center justify-center py-20 text-center">
                    <div class="size-14 rounded-2xl bg-zinc-100 flex items-center justify-center text-zinc-400 mb-3">
                        <flux:icon icon="banknotes" class="size-7" />
                    </div>
                    <h4 class="text-base font-bold text-zinc-900">No Payroll Records Found</h4>
                    <p class="text-zinc-500 text-xs max-w-sm mx-auto mt-1 mb-4">
                        There are no recorded salary payouts matching your active filter criteria.
                    </p>
                    @can('create payroll')
                        <flux:button href="{{ route('staff.payments.create') }}" variant="primary" icon="plus" size="sm">
                            Record Payment
                        </flux:button>
                    @endcan
                </div>
            @endif
        </div>
    </div>
</x-layouts::app>