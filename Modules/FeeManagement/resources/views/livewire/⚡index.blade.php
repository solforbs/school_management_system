<?php

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Classes\Models\SchoolClass;
use Modules\FeeManagement\Models\Fee;
use Modules\Institution\Models\Institution;

new #[Title('Fee Invoices')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $fee_type = '';

    #[Url]
    public string $class_id = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('view feemanagement'), 403);
    }

    public function delete(int $id): void
    {
        abort_unless(auth()->user()->can('delete feemanagement'), 403);
        $fee = Fee::query()->visibleTo(auth()->user())->findOrFail($id);
        $fee->delete();
    }

    #[Computed]
    public function fees(): LengthAwarePaginator
    {
        return Fee::with(['student.studentUserDetails', 'institution'])
            ->visibleTo(auth()->user())
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';

                $q->where(function ($sub) use ($term) {
                    $sub->where('title', 'like', $term)
                        ->orWhereHas('student', fn ($sq) => $sq->where('name', 'like', $term)->orWhere('email', 'like', $term));
                });
            })
            ->when($this->status !== '', fn ($q) => $q->withStatus($this->status))
            ->when($this->fee_type !== '', fn ($q) => $q->where('fee_type', $this->fee_type))
            ->when($this->class_id !== '', fn ($q) => $q->whereHas('student.studentUserDetails', fn ($sq) => $sq->where('class_id', $this->class_id)))
            ->orderBy('due_date', 'asc')
            ->paginate(15);
    }

    #[Computed]
    public function classes(): array
    {
        return SchoolClass::when(! isAdmin(), fn ($q) => $q->where('institution_id', currentInstitutionId()))
            ->orderBy('name')
            ->get()
            ->all();
    }

    #[Computed]
    public function institution(): ?Institution
    {
        return currentInstitution();
    }
}; ?>

@php
    $fees = $this->fees;
    $classes = $this->classes;
    $institution = $this->institution;

    $currentSearch = $this->search;
    $currentStatus = $this->status;
    $currentType = $this->fee_type;
    $currentClass = $this->class_id;

    $totalCount = method_exists($fees, 'total') ? $fees->total() : count($fees);
    $hasActiveFilters = !empty($currentSearch) || !empty($currentStatus) || !empty($currentType) || !empty($currentClass);

    $totalInvoiced = $fees->sum('amount');
    $totalCollected = $fees->sum('amount_paid');
    $outstandingBalance = max(0, $totalInvoiced - $totalCollected);
    $collectionRate = $totalInvoiced > 0 ? round(($totalCollected / $totalInvoiced) * 100, 1) : 0;
@endphp

    <div class="flex flex-col gap-6 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">

        {{-- Top Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold tracking-wide uppercase bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-400 border border-blue-200/60">
                        Financial Treasury
                    </span>
                    <span class="text-zinc-300">&bull;</span>
                    <span class="text-xs text-zinc-500 font-medium">{{ $institution?->name ?? 'Campus Accounts' }}</span>
                </div>
                <flux:heading size="xl" class="font-black tracking-tight text-zinc-900 dark:text-white">Fee Management &amp; Invoicing</flux:heading>
                <flux:text class="text-zinc-500 text-sm mt-0.5">
                    Track student fee allocations, invoice statuses, outstanding balances, and payment collections.
                </flux:text>
            </div>

            <div class="flex items-center gap-3">
                <x-export-buttons
                    base-route="feemanagement.export"
                    module="feemanagement"
                    :params="['search' => $currentSearch, 'status' => $currentStatus, 'fee_type' => $currentType, 'class_id' => $currentClass]" />
                @can('create feemanagement')
                    <flux:button href="{{ route('feemanagement.receipts.create') }}" wire:navigate.hover icon="camera" variant="subtle" class="font-semibold shadow-sm">
                        Scan Receipt
                    </flux:button>
                    <x-confirm-form
                        :action="route('feemanagement.send-reminders')"
                        method="POST"
                        title="Send balance reminders?"
                        message="Every parent with an outstanding balance receives an SMS and email. This may incur messaging charges."
                        confirm="Send Reminders"
                        confirm-variant="primary"
                        label="Send Reminders"
                        icon="bell"
                        variant="subtle"
                        trigger-class="font-semibold shadow-sm" />
                    <flux:button href="{{ route('feemanagement.create') }}" wire:navigate.hover icon="plus" variant="primary" class="font-semibold shadow-sm px-4">
                        Issue Fee Invoice
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

        {{-- Top Executive Financial KPI Strip --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
            <x-kpi-card 
                title="Total Invoiced" 
                icon="banknotes" 
                color="sky" 
                :count="'KES ' . number_format($totalInvoiced, 2)" 
                subtext="Total allocated fees" 
            />
            
            <x-kpi-card 
                title="Total Collected" 
                icon="check-badge" 
                color="emerald" 
                :count="'KES ' . number_format($totalCollected, 2)" 
                subtext="Collected revenue ({{ $collectionRate }}%)" 
                href="{{ route('feemanagement.index', ['status' => 'paid']) }}" 
            />
            
            <x-kpi-card 
                title="Outstanding Balance" 
                icon="exclamation-circle" 
                color="rose" 
                :count="'KES ' . number_format($outstandingBalance, 2)" 
                subtext="Pending student balances" 
                href="{{ route('feemanagement.index', ['status' => 'pending']) }}" 
            />
        </div>

        {{-- Reusable Single-Row Filter Toolbar --}}
        <x-filter-toolbar 
            action="{{ route('feemanagement.index') }}"
            placeholder="Search student name, admission no, fee title..."
            :search="$currentSearch"
            :hasActiveFilters="$hasActiveFilters"
            resetRoute="{{ route('feemanagement.index') }}"
        >
            {{-- Status Filter --}}
            <div style="flex: 0 1 150px;">
                <select name="status" onchange="this.form.submit()"
                    class="w-full px-3 py-2 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs font-medium text-zinc-800 dark:text-zinc-200 focus:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
                    <option value="">All Statuses</option>
                    <option value="paid" {{ $currentStatus == 'paid' ? 'selected' : '' }}>Fully Paid</option>
                    <option value="partial" {{ $currentStatus == 'partial' ? 'selected' : '' }}>Partial Payment</option>
                    <option value="pending" {{ $currentStatus == 'pending' ? 'selected' : '' }}>Unpaid / Pending</option>
                    <option value="overdue" {{ $currentStatus == 'overdue' ? 'selected' : '' }}>Overdue</option>
                </select>
            </div>

            {{-- Fee Type Filter --}}
            <div style="flex: 0 1 160px;">
                <select name="fee_type" onchange="this.form.submit()"
                    class="w-full px-3 py-2 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs font-medium text-zinc-800 dark:text-zinc-200 focus:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
                    <option value="">All Fee Types</option>
                    <option value="tuition" {{ $currentType == 'tuition' ? 'selected' : '' }}>Tuition</option>
                    <option value="exam" {{ $currentType == 'exam' ? 'selected' : '' }}>Exam Fee</option>
                    <option value="transport" {{ $currentType == 'transport' ? 'selected' : '' }}>Transport</option>
                    <option value="hostel" {{ $currentType == 'hostel' ? 'selected' : '' }}>Boarding / Hostel</option>
                    <option value="uniform" {{ $currentType == 'uniform' ? 'selected' : '' }}>Uniform &amp; Books</option>
                    <option value="other" {{ $currentType == 'other' ? 'selected' : '' }}>Other Fees</option>
                </select>
            </div>

            {{-- Class Filter --}}
            <div style="flex: 0 1 180px;">
                <select name="class_id" onchange="this.form.submit()"
                    class="w-full px-3 py-2 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs font-medium text-zinc-800 dark:text-zinc-200 focus:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
                    <option value="">All Grade Levels</option>
                    @foreach ($this->classes as $c)
                        <option value="{{ $c->id }}" {{ $currentClass == $c->id ? 'selected' : '' }}>
                            {{ $c->name }} ({{ $c->level }})
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Filter Scope Removal Tags --}}
            <x-slot:scopeTags>
                @if ($currentStatus)
                    <a href="{{ route('feemanagement.index', ['search' => $currentSearch, 'fee_type' => $currentType, 'class_id' => $currentClass]) }}" 
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 text-zinc-800 dark:text-zinc-200 font-semibold transition border border-zinc-200/60"
                        title="Remove Status Filter">
                        <span>Status: <strong>{{ ucfirst($currentStatus) }}</strong></span>
                        <span class="text-zinc-400 hover:text-zinc-700 font-bold">✕</span>
                    </a>
                @endif

                @if ($currentType)
                    <a href="{{ route('feemanagement.index', ['search' => $currentSearch, 'status' => $currentStatus, 'class_id' => $currentClass]) }}" 
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 text-zinc-800 dark:text-zinc-200 font-semibold transition border border-zinc-200/60"
                        title="Remove Fee Type Filter">
                        <span>Type: <strong>{{ ucfirst($currentType) }}</strong></span>
                        <span class="text-zinc-400 hover:text-zinc-700 font-bold">✕</span>
                    </a>
                @endif

                @if ($currentClass)
                    <a href="{{ route('feemanagement.index', ['search' => $currentSearch, 'status' => $currentStatus, 'fee_type' => $currentType]) }}" 
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 text-zinc-800 dark:text-zinc-200 font-semibold transition border border-zinc-200/60"
                        title="Remove Class Filter">
                        <span>Class Filter Active</span>
                        <span class="text-zinc-400 hover:text-zinc-700 font-bold">✕</span>
                    </a>
                @endif

                @if ($currentSearch)
                    <a href="{{ route('feemanagement.index', ['status' => $currentStatus, 'fee_type' => $currentType, 'class_id' => $currentClass]) }}" 
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-800 font-semibold transition border border-blue-200/60"
                        title="Remove Search Keyword">
                        <span>Search: <strong>"{{ $currentSearch }}"</strong></span>
                        <span class="text-blue-500 hover:text-blue-800 font-bold">✕</span>
                    </a>
                @endif
            </x-slot:scopeTags>
        </x-filter-toolbar>

        {{-- Smart Grouped 4-Column Financial Ledger Table --}}
        <div class="bg-white dark:bg-zinc-900 shadow-sm border border-zinc-200/80 dark:border-zinc-800 rounded-2xl overflow-hidden flex flex-col">
            @if($fees->isNotEmpty())
                <div class="w-full overflow-x-auto">
                    <table class="w-full text-left text-sm table-fixed min-w-[720px]">
                        <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-500 border-b border-zinc-200/80 dark:border-zinc-700 text-xs font-bold uppercase tracking-wider">
                            <tr>
                                <th class="py-3.5 px-5 w-4/12">Student Profile &amp; ADM</th>
                                <th class="py-3.5 px-5 w-3/12">Fee Allocation &amp; Type</th>
                                <th class="py-3.5 px-5 w-3/12">Financials &amp; Balance</th>
                                <th class="py-3.5 px-5 w-2/12 text-right">Status &amp; Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($fees as $fee)
                                @php
                                    $studentName = $fee->student?->name ?? 'Unknown Student';
                                    $admNum = $fee->student?->studentUserDetails?->admission_number ?? 'ADM-' . str_pad($fee->student_id, 3, '0', STR_PAD_LEFT);
                                    $initials = strtoupper(substr($studentName, 0, 2));
                                @endphp
                                <tr class="hover:bg-zinc-50/60 dark:hover:bg-zinc-800/40 transition-colors">
                                    <td class="py-3.5 px-5">
                                        <div class="flex items-center gap-3">
                                            <div class="size-9 rounded-xl bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 font-bold text-xs flex items-center justify-center shrink-0 border border-zinc-200 dark:border-zinc-700">
                                                {{ $initials }}
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <a href="{{ route('feemanagement.show', $fee->id) }}" wire:navigate.hover class="font-bold text-zinc-900 dark:text-white hover:text-blue-600 transition truncate block text-xs sm:text-sm">
                                                    {{ $studentName }}
                                                </a>
                                                <div class="text-[11px] text-zinc-400 mt-0.5 flex items-center gap-1.5 font-mono">
                                                    <span class="px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 font-semibold border border-zinc-200/80 dark:border-zinc-700 text-[10px]">{{ $admNum }}</span>
                                                    <span class="text-zinc-300">&bull;</span>
                                                    <span class="font-sans text-[11px] text-zinc-500">{{ $fee->institution?->name ?? 'Main Campus' }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="py-3.5 px-5">
                                        <div class="font-bold text-xs text-zinc-900 dark:text-white truncate">{{ $fee->title }}</div>
                                        <div class="text-[11px] text-zinc-400 flex items-center gap-1.5 mt-1 font-medium">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 text-[10px] font-semibold uppercase border border-zinc-200/60 dark:border-zinc-700">
                                                {{ ucfirst($fee->fee_type) }}
                                            </span>
                                            @if($fee->due_date)
                                                <span>Due {{ \Carbon\Carbon::parse($fee->due_date)->format('M d, Y') }}</span>
                                            @endif
                                        </div>
                                    </td>

                                    <td class="py-3.5 px-5">
                                        <div class="flex items-center gap-2">
                                            <div class="font-mono text-xs font-bold text-zinc-900 dark:text-zinc-100">
                                                KES {{ number_format((float) ($fee->amount ?? 0), 2) }}
                                            </div>
                                        </div>
                                        <div class="text-[11px] text-zinc-500 dark:text-zinc-400 mt-1 flex items-center gap-1.5 font-medium">
                                            <span class="text-emerald-700 dark:text-emerald-400 font-semibold">Paid: {{ number_format((float) ($fee->amount_paid ?? 0), 2) }}</span>
                                            <span class="text-zinc-300">&bull;</span>
                                            <span class="{{ $fee->balance > 0 ? 'text-rose-600 dark:text-rose-400 font-semibold' : 'text-emerald-600 dark:text-emerald-400 font-medium' }}">
                                                Bal: {{ number_format((float) $fee->balance, 2) }}
                                            </span>
                                        </div>
                                    </td>

                                    <td class="py-3.5 px-5 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            @if($fee->status === 'paid')
                                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400 border border-emerald-200">
                                                    <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                                    Paid
                                                </span>
                                            @elseif($fee->status === 'partial')
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-800 dark:bg-amber-950/50 dark:text-amber-400 border border-amber-200">
                                                    Partial
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 dark:bg-rose-950/50 dark:text-rose-400 border border-rose-200">
                                                    Pending
                                                </span>
                                            @endif

                                            <div class="flex items-center gap-1">
                                                <flux:button href="{{ route('feemanagement.show', $fee->id) }}" wire:navigate.hover size="sm" icon="eye" variant="subtle" title="View Details" />
                                                @can('edit feemanagement')
                                                    <flux:button href="{{ route('feemanagement.edit', $fee->id) }}" wire:navigate.hover size="sm" icon="pencil-square" variant="subtle" title="Edit Allocation" />
                                                @endcan
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($fees->hasPages())
                    <div class="px-5 py-4 border-t border-zinc-100 dark:border-zinc-800 flex items-center justify-between">
                        {{ $fees->links() }}
                    </div>
                @endif
            @else
                <div class="py-16 text-center">
                    <flux:icon icon="banknotes" class="size-12 mx-auto text-zinc-300 mb-3" />
                    <h4 class="text-base font-bold text-zinc-900 dark:text-white">No Fee Records Found</h4>
                    <p class="text-xs text-zinc-500 mt-1 mb-4">No billing allocations match your search or filter parameters.</p>
                    @can('create feemanagement')
                        <flux:button href="{{ route('feemanagement.create') }}" variant="primary" size="sm">
                            Create First Allocation
                        </flux:button>
                    @endcan
                </div>
            @endif
        </div>
    </div>
