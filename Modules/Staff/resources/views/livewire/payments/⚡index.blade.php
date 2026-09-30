<?php

use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Institution\Models\Institution;
use Modules\Staff\Models\StaffPayment;

new #[Title('Staff Payroll & Payments')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $period = '';

    #[Url]
    public string $status = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('view payroll') || auth()->user()->can('view staff'), 403);
    }

    #[Computed]
    public function payments(): LengthAwarePaginator
    {
        return StaffPayment::with(['staff.user', 'institution', 'recordedBy'])
            ->when(! isAdmin(), fn ($q) => $q->where('institution_id', currentInstitutionId()))
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';

                $q->where(function ($sub) use ($term) {
                    $sub->where('reference', 'like', $term)
                        ->orWhereHas('staff.user', fn ($sq) => $sq->where('name', 'like', $term)->orWhere('email', 'like', $term))
                        ->orWhereHas('staff', fn ($sq) => $sq->where('staff_number', 'like', $term));
                });
            })
            ->when($this->period !== '', fn ($q) => $q->where('period', $this->period))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->orderBy('created_at', 'desc')
            ->paginate(15);
    }

    #[Computed]
    public function periods(): array
    {
        return StaffPayment::when(! isAdmin(), fn ($q) => $q->where('institution_id', currentInstitutionId()))
            ->distinct()
            ->pluck('period')
            ->filter()
            ->map(fn ($p) => $p instanceof \Carbon\CarbonInterface ? $p->format('Y-m-d') : (string) $p)
            ->sort()
            ->reverse()
            ->values()
            ->all();
    }

    #[Computed]
    public function currentInstitutionModel(): ?Institution
    {
        return currentInstitution();
    }
}; ?>

@php
    $payments = $this->payments;
    $periods = $this->periods;
    $institution = $this->currentInstitutionModel;

    $currentSearch = $this->search;
    $currentPeriod = $this->period;
    $currentStatus = $this->status;

    $totalDisbursed = $payments->where('status', 'paid')->sum('net_amount');
    $pendingDisbursements = $payments->where('status', 'pending')->count();
    $uniqueStaffPaid = $payments->pluck('staff_details_id')->unique()->count();

    $totalCount = method_exists($payments, 'total') ? $payments->total() : count($payments);
    $hasActiveFilters = !empty($currentSearch) || !empty($currentPeriod) || !empty($currentStatus);
@endphp

    <div class="flex flex-col gap-6 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">

        {{-- Top Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold tracking-wide uppercase bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400 border border-emerald-200/60">
                        Payroll Administration
                    </span>
                    <span class="text-zinc-300">&bull;</span>
                    <span class="text-xs text-zinc-500 font-medium">{{ $institution?->name ?? 'Campus Operations' }}</span>
                </div>
                <flux:heading size="xl" class="font-black tracking-tight text-zinc-900 dark:text-white">Staff Payroll &amp; Disbursements</flux:heading>
                <flux:text class="text-zinc-500 text-sm mt-0.5">
                    Track staff salary remittances, allowances, deductions, payment vouchers, and monthly pay stubs.
                </flux:text>
            </div>

            <div class="flex items-center gap-3">
                @can('create staff payments')
                    <flux:button href="{{ route('staff.payments.create') }}" wire:navigate.hover icon="plus" variant="primary" class="font-semibold shadow-sm px-4">
                        Process New Payout
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

        {{-- Top Executive Metrics Strip --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
            <x-kpi-card 
                title="Total Payroll" 
                icon="banknotes" 
                color="emerald" 
                :count="'KES ' . number_format($totalDisbursed, 2)" 
                subtext="Successfully disbursed funds" 
            />
            
            <x-kpi-card 
                title="Staff Personnel Paid" 
                icon="users" 
                color="indigo" 
                :count="$uniqueStaffPaid" 
                subtext="Distinct individuals remunerated" 
            />
            
            <x-kpi-card 
                title="Pending Vouchers" 
                icon="clock" 
                color="amber" 
                :count="$pendingDisbursements" 
                subtext="Awaiting authorization or release" 
                href="{{ route('staff.payments.index', ['status' => 'pending']) }}" 
            />
        </div>

        {{-- Reusable Single-Row Filter Toolbar --}}
        <div class="bg-white dark:bg-zinc-900 rounded-2xl p-3.5 sm:p-4 border border-zinc-200/80 dark:border-zinc-800 shadow-sm flex flex-col gap-3">
            <form method="GET" action="{{ route('staff.payments.index') }}" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">
                <div class="sm:col-span-4">
                    <select name="period" onchange="this.form.submit()"
                        class="w-full px-3 py-2 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs font-medium text-zinc-800 dark:text-zinc-200 focus:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
                        <option value="">All Payroll Cycles</option>
                        @foreach ($periods as $p)
                            <option value="{{ $p }}" {{ $currentPeriod == $p ? 'selected' : '' }}>
                                {{ Carbon::parse($p)->format('F Y') }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="sm:col-span-3">
                    <select name="status" onchange="this.form.submit()"
                        class="w-full px-3 py-2 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs font-medium text-zinc-800 dark:text-zinc-200 focus:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
                        <option value="">All Statuses</option>
                        <option value="paid" {{ $currentStatus == 'paid' ? 'selected' : '' }}>Paid / Disbursed</option>
                        <option value="pending" {{ $currentStatus == 'pending' ? 'selected' : '' }}>Pending Approval</option>
                        <option value="cancelled" {{ $currentStatus == 'cancelled' ? 'selected' : '' }}>Cancelled / Void</option>
                    </select>
                </div>

                <div class="sm:col-span-5 flex items-center gap-2">
                    <div class="relative w-full">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-zinc-400">
                            <flux:icon icon="magnifying-glass" class="size-4" />
                        </div>
                        <input type="text" name="search" value="{{ $currentSearch }}" placeholder="Search staff name, EMP no, ref..." 
                            class="w-full pl-10 pr-4 py-2 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs placeholder-zinc-400 focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
                    </div>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-white text-xs font-bold transition shrink-0">
                        Filter
                    </button>
                    @if($hasActiveFilters)
                        <a href="{{ route('staff.payments.index') }}" class="px-3 py-2 rounded-xl bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 text-zinc-600 dark:text-zinc-400 text-xs font-semibold transition shrink-0" title="Reset Filters">
                            ✕
                        </a>
                    @endif
                </div>
            </form>

            @if ($hasActiveFilters)
                <div class="flex flex-wrap items-center gap-2 pt-2.5 border-t border-zinc-100 dark:border-zinc-800 text-xs">
                    <span class="text-[11px] font-bold text-zinc-400 uppercase tracking-wider mr-1">Active Filter Scope:</span>

                    @if ($currentPeriod)
                        <a href="{{ route('staff.payments.index', ['status' => $currentStatus, 'search' => $currentSearch]) }}" 
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 text-zinc-800 dark:text-zinc-200 font-semibold transition border border-zinc-200/60"
                            title="Remove Period Filter">
                            <span>Period: <strong>{{ Carbon::parse($currentPeriod)->format('F Y') }}</strong></span>
                            <span class="text-zinc-400 hover:text-zinc-700 font-bold">✕</span>
                        </a>
                    @endif

                    @if ($currentStatus)
                        <a href="{{ route('staff.payments.index', ['period' => $currentPeriod, 'search' => $currentSearch]) }}" 
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 text-zinc-800 dark:text-zinc-200 font-semibold transition border border-zinc-200/60"
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

        {{-- Smart Grouped Payroll Ledger Table --}}
        <div class="bg-white dark:bg-zinc-900 shadow-sm border border-zinc-200/80 dark:border-zinc-800 rounded-2xl overflow-hidden flex flex-col">
            @if($payments->isNotEmpty())
                <div class="w-full overflow-x-auto">
                    <table class="w-full text-left text-sm table-fixed min-w-[720px]">
                        <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-500 border-b border-zinc-200/80 dark:border-zinc-700 text-xs font-bold uppercase tracking-wider">
                            <tr>
                                <th class="py-3.5 px-5 w-4/12">Staff Personnel &amp; Period</th>
                                <th class="py-3.5 px-5 w-3/12">Gross &amp; Breakdown</th>
                                <th class="py-3.5 px-5 w-2.5/12">Net Payout &amp; Method</th>
                                <th class="py-3.5 px-5 w-2.5/12 text-right">Status &amp; Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($payments as $payment)
                                @php
                                    $staffMember = $payment->staff;
                                    $staffUser = $staffMember?->user;
                                    $staffName = $staffUser?->name ?? 'Staff Member';
                                    $initials = strtoupper(substr($staffName, 0, 2));
                                    $staffNo = $staffMember?->staff_number ?? 'EMP-' . str_pad($payment->id, 3, '0', STR_PAD_LEFT);
                                    $grossAmount = (float) $payment->gross_amount;
                                    $allowances = (float) $payment->allowances;
                                    $deductions = (float) $payment->deductions;
                                    $netAmount = (float) $payment->net_amount;
                                    $periodFormatted = $payment->period ? Carbon::parse($payment->period)->format('M Y') : '—';
                                @endphp
                                <tr class="hover:bg-zinc-50/60 dark:hover:bg-zinc-800/40 transition-colors">
                                    <td class="py-4 px-5">
                                        <div class="flex items-center gap-3">
                                            <div class="size-10 rounded-xl bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300 font-bold text-xs flex items-center justify-center border border-emerald-200/80 shrink-0">
                                                {{ $initials }}
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <a href="{{ route('staff.show', $payment->staff_details_id) }}" wire:navigate.hover class="font-bold text-zinc-900 dark:text-white hover:text-emerald-700 transition truncate block">
                                                    {{ $staffName }}
                                                </a>
                                                <div class="text-xs text-zinc-400 mt-0.5 flex items-center gap-1.5 font-mono">
                                                    <span class="px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 font-semibold border border-zinc-200 dark:border-zinc-700 text-[11px]">{{ $staffNo }}</span>
                                                    <span class="text-zinc-300">&bull;</span>
                                                    <span class="text-[11px] text-zinc-600 dark:text-zinc-400 font-sans font-medium">{{ $periodFormatted }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="py-4 px-5">
                                        <div class="font-mono text-xs font-bold text-zinc-900 dark:text-zinc-100">
                                            KES {{ number_format($grossAmount, 2) }} <span class="text-zinc-400 font-normal font-sans text-[11px]">(Gross)</span>
                                        </div>
                                        <div class="text-[11px] text-zinc-500 dark:text-zinc-400 mt-0.5 font-medium flex items-center gap-1.5">
                                            <span class="text-emerald-700 dark:text-emerald-400 font-semibold">+{{ number_format($allowances, 2) }}</span>
                                            <span class="text-zinc-300">&bull;</span>
                                            <span class="text-rose-600 dark:text-rose-400 font-semibold">-{{ number_format($deductions, 2) }}</span>
                                        </div>
                                    </td>

                                    <td class="py-4 px-5">
                                        <div class="font-mono text-xs font-black text-emerald-800 dark:text-emerald-400">
                                            KES {{ number_format($netAmount, 2) }}
                                        </div>
                                        <div class="text-[11px] text-zinc-400 mt-0.5 flex items-center gap-1">
                                            <span class="px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 text-[10px] uppercase font-bold">{{ $payment->payment_method ?? 'Bank' }}</span>
                                            <span class="truncate font-mono">{{ $payment->reference ?? '—' }}</span>
                                        </div>
                                    </td>

                                    <td class="py-4 px-5 text-right">
                                        <div class="flex flex-col items-end gap-1.5">
                                            @if($payment->status === 'paid')
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400 border border-emerald-200">
                                                    <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                                    Disbursed
                                                </span>
                                            @elseif($payment->status === 'pending')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-50 text-amber-800 dark:bg-amber-950/50 dark:text-amber-400 border border-amber-200">
                                                    Pending
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-rose-50 text-rose-700 dark:bg-rose-950/50 dark:text-rose-400 border border-rose-200">
                                                    Failed
                                                </span>
                                            @endif

                                            <div class="flex items-center justify-end gap-1">
                                                <flux:button href="{{ route('staff.payments.show', $payment->id) }}" wire:navigate.hover size="sm" icon="document-text" variant="subtle" title="View Payslip" />
                                                @can('edit staff payments')
                                                    <flux:button href="{{ route('staff.payments.edit', $payment->id) }}" wire:navigate.hover size="sm" icon="pencil-square" variant="subtle" title="Edit Voucher" />
                                                @endcan
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($payments->hasPages())
                    <div class="px-5 py-4 border-t border-zinc-100 dark:border-zinc-800 flex items-center justify-between">
                        {{ $payments->links() }}
                    </div>
                @endif
            @else
                <div class="py-16 text-center">
                    <flux:icon icon="banknotes" class="size-12 mx-auto text-zinc-300 mb-3" />
                    <h4 class="text-base font-bold text-zinc-900 dark:text-white">No Payroll Disbursements Found</h4>
                    <p class="text-xs text-zinc-500 mt-1 mb-4">No records match your selected cycle or search filter.</p>
                    @can('create staff payments')
                        <flux:button href="{{ route('staff.payments.create') }}" variant="primary" size="sm">
                            Create First Voucher
                        </flux:button>
                    @endcan
                </div>
            @endif
        </div>
    </div>
