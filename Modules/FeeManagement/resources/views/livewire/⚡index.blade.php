<?php

use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\FeeManagement\Models\Fee;
use App\Services\FeeReminderService;

new #[Title('Fee Management')] class extends Component
{
    use WithPagination;

    /** @var string[] */
    public const SORTABLE = ['title', 'amount', 'amount_paid', 'due_date', 'created_at'];

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $fee_type = '';

    #[Url]
    public string $sort = 'created_at';

    #[Url]
    public string $direction = 'desc';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('view feemanagement'), 403);
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'status', 'fee_type'], true)) {
            $this->resetPage();
        }
    }

    public function sortBy(string $column): void
    {
        if (! in_array($column, self::SORTABLE, true)) {
            return;
        }

        if ($this->sort === $column) {
            $this->direction = $this->direction === 'asc' ? 'desc' : 'asc';

            return;
        }

        $this->sort = $column;
        $this->direction = 'asc';
    }

    public function delete(int $id): void
    {
        abort_unless(auth()->user()->can('delete feemanagement'), 403);

        $this->scoped()->findOrFail($id)->delete();

        Flux::toast(text: 'Fee record removed.', variant: 'success');
    }

    /**
     * Send a consolidated fee-balance reminder to every parent with an
     * outstanding fee, scoped the same way the listing is.
     */
    public function sendReminders(FeeReminderService $reminders): void
    {
        abort_unless(auth()->user()->can('create feemanagement'), 403);

        $query = Fee::query();
        $this->scopeToViewer($query);

        $result = $reminders->sendForDefaulters($query);

        if ($result['parents_notified'] === 0) {
            Flux::toast(text: 'No outstanding fee balances to remind anyone about.', variant: 'warning');

            return;
        }

        $message = "Reminders sent to {$result['parents_notified']} parent(s) - {$result['emails_sent']} email(s), {$result['sms_sent']} SMS.";

        if ($result['skipped_no_contact'] > 0) {
            $message .= " {$result['skipped_no_contact']} parent(s) skipped - no email or phone on file.";
        }

        Flux::toast(text: $message, variant: 'success');
    }

    #[Computed]
    public function fees()
    {
        $sort = in_array($this->sort, self::SORTABLE, true) ? $this->sort : 'created_at';
        $direction = $this->direction === 'asc' ? 'asc' : 'desc';

        return $this->filtered()
            ->with(['student.studentUserDetails', 'institution', 'parent'])
            ->orderBy($sort, $direction)
            ->paginate(15);
    }

    /**
     * Parents with at least one unpaid fee - who a reminder run would reach.
     */
    #[Computed]
    public function defaulterCount(): int
    {
        $query = Fee::query();
        $this->scopeToViewer($query);

        return $query->whereColumn('amount_paid', '<', 'amount')
            ->whereNotNull('parent_id')
            ->distinct()
            ->count('parent_id');
    }

    #[Computed]
    public function totalBilled(): float
    {
        return (float) $this->scoped()->sum('amount');
    }

    #[Computed]
    public function totalCollected(): float
    {
        return (float) $this->scoped()->sum('amount_paid');
    }

    #[Computed]
    public function totalOutstanding(): float
    {
        return max(0, $this->totalBilled - $this->totalCollected);
    }

    #[Computed]
    public function totalFeesCount(): int
    {
        return $this->scoped()->count();
    }

    #[Computed]
    public function feeTypes()
    {
        return $this->scoped()->whereNotNull('fee_type')->distinct()->pluck('fee_type');
    }

    #[Computed]
    public function paidCount(): int
    {
        return $this->scoped()->whereColumn('amount_paid', '>=', 'amount')->count();
    }

    #[Computed]
    public function partialCount(): int
    {
        return $this->scoped()->whereColumn('amount_paid', '<', 'amount')->where('amount_paid', '>', 0)->count();
    }

    #[Computed]
    public function overdueCount(): int
    {
        return $this->scoped()->whereColumn('amount_paid', '<', 'amount')->where('due_date', '<', now())->count();
    }

    #[Computed]
    public function institution()
    {
        return currentInstitution();
    }

    private function filtered()
    {
        $query = Fee::query();
        $this->scopeToViewer($query);

        return $query
            ->when($this->status !== '', function ($q) {
                if ($this->status === 'paid') {
                    $q->whereColumn('amount_paid', '>=', 'amount');
                } elseif ($this->status === 'overdue') {
                    $q->whereColumn('amount_paid', '<', 'amount')->where('due_date', '<', now());
                } elseif ($this->status === 'pending') {
                    $q->whereColumn('amount_paid', '<', 'amount');
                }
            })
            ->when($this->fee_type !== '', fn ($q) => $q->where('fee_type', $this->fee_type))
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';

                $q->where(function ($q2) use ($term) {
                    $q2->where('title', 'like', $term)
                        ->orWhere('fee_type', 'like', $term)
                        ->orWhereHas('student', fn ($q3) => $q3->where('name', 'like', $term));
                });
            });
    }

    private function scopeToViewer($query): void
    {
        $user = auth()->user();

        if (isAdmin()) {
            return;
        }

        if ($user->hasRole('Parent')) {
            $query->where('parent_id', $user->id);

            return;
        }

        if ($user->hasRole('Student')) {
            $query->where('student_id', $user->id);

            return;
        }

        $query->where('institution_id', currentInstitution()?->id ?? 0);
    }

    private function scoped()
    {
        $query = Fee::query();
        $this->scopeToViewer($query);

        return $query;
    }
}; ?>
@php
    $fees = $this->fees;
    $totalCount = method_exists($fees, 'total') ? $fees->total() : count($fees);
    $totalBilled = $this->totalBilled;
    $totalCollected = $this->totalCollected;
    $totalOutstanding = $this->totalOutstanding;
    $totalFeesCount = $this->totalFeesCount;
    $defaulterCount = $this->defaulterCount;
    $paidCount = $this->paidCount;
    $partialCount = $this->partialCount;
    $overdueCount = $this->overdueCount;
    $institution = $this->institution;
    $feeTypes = $this->feeTypes;
    $collectionRate = $totalBilled > 0 ? round(($totalCollected / $totalBilled) * 100, 1) : 0;
    $currentStatus = $this->status;
    $currentType = $this->fee_type;
    $currentSearch = $this->search;
    $hasActiveFilters = !empty($currentStatus) || !empty($currentType) || !empty($currentSearch);
@endphp

<x-layouts::app :title="__(config('feemanagement.name'))">
    <div class="flex flex-col gap-6 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">
        
        {{-- Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold tracking-wide uppercase bg-emerald-50 text-emerald-700 border border-emerald-200/60">
                        Financial Operations
                    </span>
                    <span class="text-xs text-zinc-400">&bull;</span>
                    <span class="text-xs text-zinc-500 font-medium">{{ $institution?->name ?? 'Green Valley Academy' }}</span>
                </div>
                <flux:heading size="xl" class="font-black tracking-tight text-zinc-900">Fee Management & Ledger</flux:heading>
                <flux:text class="text-zinc-500 text-xs mt-0.5">
                    Monitor student billings, track M-Pesa collections, and manage outstanding balances.
                </flux:text>
            </div>
            
            <div class="flex flex-wrap items-center gap-2.5">
                @can('create feemanagement')
                    @if (institutionHasFeature('ai_receipt_scanning'))
                        <flux:button href="{{ route('feemanagement.receipts.create') }}" icon="sparkles" variant="subtle" class="font-semibold text-xs">
                            Scan Receipt (AI)
                        </flux:button>
                    @endif

                    <form action="{{ route('feemanagement.send-reminders') }}" method="POST"
                        onsubmit="return confirm('Send automated SMS/Email balance reminders to {{ $defaulterCount }} parent(s) with outstanding balances?');">
                        @csrf
                        <flux:button type="submit" icon="bell-alert" class="{{ $defaulterCount > 0 ? 'bg-amber-500 hover:bg-amber-600 text-white font-semibold text-xs shadow-sm' : 'text-zinc-400 text-xs' }}" :disabled="$defaulterCount === 0">
                            Send Reminders{{ $defaulterCount > 0 ? " ({$defaulterCount})" : '' }}
                        </flux:button>
                    </form>

                    <flux:button href="{{ route('feemanagement.create') }}" icon="plus" variant="primary" class="font-semibold text-xs px-4">
                        Allocate Fee
                    </flux:button>
                @endcan
            </div>
        </div>

        {{-- Flash Messages --}}
        @if (session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50/80 p-3.5 flex items-center justify-between shadow-xs">
                <div class="flex items-center gap-2.5">
                    <flux:icon icon="check-circle" class="size-4 text-emerald-600 shrink-0" />
                    <span class="text-xs font-semibold text-emerald-900">{{ session('success') }}</span>
                </div>
            </div>
        @endif

        {{-- 4 Clean, Unified Financial KPI Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <x-kpi-card 
                title="Total Billed" 
                icon="document-text" 
                color="sky" 
                count="KES {{ number_format($totalBilled, 0) }}" 
                subtext="{{ number_format($totalFeesCount ?? $totalCount) }} allocations recorded" 
            />
            
            <x-kpi-card 
                title="Collected Revenue" 
                icon="banknotes" 
                color="emerald" 
                count="KES {{ number_format($totalCollected, 0) }}" 
                href="{{ route('feemanagement.index', ['status' => 'paid']) }}"
                subtext="{{ $collectionRate }}% rate ┬╖ {{ $paidCount }} settled" 
            />
            
            <x-kpi-card 
                title="Outstanding Debt" 
                icon="calculator" 
                color="rose" 
                count="KES {{ number_format($totalOutstanding, 0) }}" 
                href="{{ route('feemanagement.index', ['status' => 'overdue']) }}"
                subtext="{{ $overdueCount }} overdue ┬╖ {{ $partialCount }} partial" 
            />
            
            <x-kpi-card 
                title="Defaulter Parents" 
                icon="bell-alert" 
                color="amber" 
                :count="$defaulterCount" 
                countSuffix=" Parents"
                subtext="SMS & Email queues active" 
            />
        </div>

        {{-- Reusable Single-Row Filter Toolbar --}}
        <x-filter-toolbar 
            action="{{ route('feemanagement.index') }}"
            placeholder="Search student name, admission no, or fee title..."
            :search="$currentSearch"
            :hasActiveFilters="$hasActiveFilters"
            resetRoute="{{ route('feemanagement.index') }}"
        >
            {{-- Status Filter --}}
            <div style="flex: 0 1 180px;">
                <select name="status" onchange="this.form.submit()"
                    class="w-full px-3 py-2 bg-zinc-50 border border-zinc-200 rounded-xl text-xs font-medium text-zinc-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
                    <option value="">All Payment Statuses</option>
                    <option value="paid" {{ $currentStatus === 'paid' ? 'selected' : '' }}>Fully Settled (Paid)</option>
                    <option value="partial" {{ $currentStatus === 'partial' ? 'selected' : '' }}>Partially Paid</option>
                    <option value="overdue" {{ $currentStatus === 'overdue' ? 'selected' : '' }}>Overdue Balances</option>
                    <option value="pending" {{ $currentStatus === 'pending' ? 'selected' : '' }}>Pending Payment</option>
                </select>
            </div>

            {{-- Fee Category Filter --}}
            <div style="flex: 0 1 170px;">
                <select name="fee_type" onchange="this.form.submit()"
                    class="w-full px-3 py-2 bg-zinc-50 border border-zinc-200 rounded-xl text-xs font-medium text-zinc-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
                    <option value="">All Categories</option>
                    <option value="tuition" {{ $currentType === 'tuition' ? 'selected' : '' }}>Tuition Fees</option>
                    <option value="exam" {{ $currentType === 'exam' ? 'selected' : '' }}>Exam Fees</option>
                    <option value="transport" {{ $currentType === 'transport' ? 'selected' : '' }}>Transport</option>
                    <option value="boarding" {{ $currentType === 'boarding' ? 'selected' : '' }}>Boarding</option>
                    <option value="activity" {{ $currentType === 'activity' ? 'selected' : '' }}>Activities</option>
                    <option value="other" {{ $currentType === 'other' ? 'selected' : '' }}>Other</option>
                </select>
            </div>

            <x-slot:scopeTags>
                @if ($currentStatus)
                    <a href="{{ route('feemanagement.index', ['search' => $currentSearch, 'fee_type' => $currentType]) }}" 
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-zinc-100 hover:bg-zinc-200 text-zinc-800 font-semibold transition border border-zinc-200/60"
                        title="Remove Status Filter">
                        <span>Status: <strong>{{ ucfirst($currentStatus) }}</strong></span>
                        <span class="text-zinc-400 hover:text-zinc-700 font-bold">Γ£ò</span>
                    </a>
                @endif

                @if ($currentType)
                    <a href="{{ route('feemanagement.index', ['search' => $currentSearch, 'status' => $currentStatus]) }}" 
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-zinc-100 hover:bg-zinc-200 text-zinc-800 font-semibold transition border border-zinc-200/60"
                        title="Remove Fee Type Filter">
                        <span>Type: <strong>{{ ucfirst($currentType) }}</strong></span>
                        <span class="text-zinc-400 hover:text-zinc-700 font-bold">Γ£ò</span>
                    </a>
                @endif

                @if ($currentSearch)
                    <a href="{{ route('feemanagement.index', ['status' => $currentStatus, 'fee_type' => $currentType]) }}" 
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-800 font-semibold transition border border-blue-200/60"
                        title="Remove Search Keyword">
                        <span>Search: <strong>"{{ $currentSearch }}"</strong></span>
                        <span class="text-blue-500 hover:text-blue-800 font-bold">Γ£ò</span>
                    </a>
                @endif
            </x-slot:scopeTags>
        </x-filter-toolbar>

        {{-- Smart 4-Column High-Density Fee Ledger (Zero Horizontal Scroll) --}}
        <div class="bg-white shadow-xs border border-zinc-200/80 rounded-2xl overflow-hidden flex flex-col">
            @if($fees->isNotEmpty())
                <div class="w-full">
                    <table class="w-full text-left text-sm table-fixed">
                        <thead class="bg-zinc-50 text-zinc-500 border-b border-zinc-200/80 text-[11px] font-bold uppercase tracking-wider">
                            <tr>
                                <th class="py-3.5 px-5 w-4/12">Student & Identification</th>
                                <th class="py-3.5 px-5 w-3/12">Fee Allocation</th>
                                <th class="py-3.5 px-5 w-3/12">Collection Progress</th>
                                <th class="py-3.5 px-5 w-2/12 text-right">Status & Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100">
                            @foreach ($fees as $fee)
                                @php
    $fees = $this->fees;
    $totalCount = method_exists($fees, 'total') ? $fees->total() : count($fees);
    $totalBilled = $this->totalBilled;
    $totalCollected = $this->totalCollected;
    $totalOutstanding = $this->totalOutstanding;
    $totalFeesCount = $this->totalFeesCount;
    $defaulterCount = $this->defaulterCount;
    $institution = $this->institution;
    $feeTypes = $this->feeTypes;
    $collectionRate = $totalBilled > 0 ? round(($totalCollected / $totalBilled) * 100, 1) : 0;
    $currentStatus = $this->status;
    $currentType = $this->fee_type;
    $currentSearch = $this->search;
    $hasActiveFilters = !empty($currentStatus) || !empty($currentType) || !empty($currentSearch);
@endphp
                                <tr class="hover:bg-zinc-50/60 transition-colors">
                                    {{-- Col 1: Student Profile & ADM --}}
                                    <td class="py-3.5 px-5">
                                        <div class="flex items-center gap-3">
                                            <div class="size-9 rounded-xl bg-zinc-100 text-zinc-700 font-bold text-xs flex items-center justify-center shrink-0 border border-zinc-200">
                                                {{ $initials }}
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <a href="{{ route('feemanagement.show', $fee->id) }}" class="font-bold text-zinc-900 hover:text-blue-600 transition truncate block text-xs sm:text-sm">
                                                    {{ $studentName }}
                                                </a>
                                                <div class="text-[11px] text-zinc-400 mt-0.5 flex items-center gap-1.5 font-mono">
                                                    <span class="px-1.5 py-0.5 rounded bg-zinc-100 text-zinc-700 font-semibold border border-zinc-200/80 text-[10px]">{{ $admNum }}</span>
                                                    <span class="text-zinc-300">&bull;</span>
                                                    <span class="font-sans text-[11px] text-zinc-500">{{ $fee->institution?->name ?? 'Main Campus' }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Col 2: Fee Allocation & Category --}}
                                    <td class="py-3.5 px-5">
                                        <div class="font-bold text-xs text-zinc-900 truncate">{{ $fee->title }}</div>
                                        <div class="text-[11px] text-zinc-400 flex items-center gap-1.5 mt-1 font-medium">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-zinc-100 text-zinc-700 text-[10px] font-semibold uppercase border border-zinc-200/60">
                                                {{ ucfirst($fee->fee_type) }}
                                            </span>
                                            @if($fee->due_date)
                                                <span>&bull; Due {{ $fee->due_date->format('d M Y') }}</span>
                                            @endif
                                        </div>
                                    </td>

                                    {{-- Col 3: Collection Progress --}}
                                    <td class="py-3.5 px-5">
                                        <div class="flex flex-col gap-1">
                                            <div class="flex items-center justify-between text-xs font-mono">
                                                <span class="font-bold text-zinc-900">KES {{ number_format($fee->amount_paid, 0) }} <span class="text-zinc-400 font-normal">/ {{ number_format($fee->amount, 0) }}</span></span>
                                                <span class="text-[11px] font-semibold {{ $paidPct >= 100 ? 'text-emerald-600' : 'text-zinc-600' }}">{{ $paidPct }}%</span>
                                            </div>
                                            <div class="w-full bg-zinc-100 rounded-full h-1.5 overflow-hidden">
                                                <div class="h-1.5 rounded-full transition-all duration-500 {{ $paidPct >= 100 ? 'bg-emerald-500' : ($paidPct > 0 ? 'bg-zinc-900' : 'bg-zinc-300') }}"
                                                    style="width: {{ $paidPct }}%;"></div>
                                            </div>
                                            <div class="text-[10px] text-zinc-500 font-mono">
                                                Bal: <strong class="{{ $fee->balance > 0 ? 'text-rose-600' : 'text-emerald-600' }}">KES {{ number_format($fee->balance, 0) }}</strong>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Col 4: Status & Actions --}}
                                    <td class="py-3.5 px-5 text-right">
                                        <div class="flex flex-col items-end gap-1.5">
                                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wider
                                                {{ $fee->status === 'paid' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200/70' :
                                                   ($fee->status === 'partial' ? 'bg-amber-50 text-amber-700 border border-amber-200/70' :
                                                   'bg-rose-50 text-rose-700 border border-rose-200/70') }}">
                                                <span class="size-1.5 rounded-full {{ $fee->status === 'paid' ? 'bg-emerald-500' : ($fee->status === 'partial' ? 'bg-amber-500' : 'bg-rose-500') }}"></span>
                                                {{ ucfirst($fee->status) }}
                                            </span>

                                            <div class="flex items-center justify-end gap-1">
                                                <flux:button href="{{ route('feemanagement.show', $fee->id) }}" size="sm" icon="eye" variant="subtle" tooltip="View Ledger" />
                                                @can('edit feemanagement')
                                                    <flux:button href="{{ route('feemanagement.edit', $fee->id) }}" size="sm" icon="pencil-square" variant="subtle" tooltip="Edit Fee" />
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
                <div class="border-t border-zinc-200/80 px-5 py-3.5 bg-zinc-50/50 flex flex-col sm:flex-row justify-between items-center text-xs text-zinc-500 gap-3">
                    <div class="flex items-center gap-2">
                        <span>Showing <strong class="text-zinc-800 font-semibold">{{ $fees->count() }}</strong> of <strong class="text-zinc-800 font-semibold">{{ number_format($totalCount) }}</strong> allocations</span>
                        <span class="text-zinc-300">&bull;</span>
                        <span>Page <strong class="text-zinc-800 font-semibold">{{ $fees->currentPage() }}</strong> of <strong class="text-zinc-800 font-semibold">{{ $fees->lastPage() }}</strong></span>
                    </div>
                    @if(method_exists($fees, 'links') && $fees->hasPages())
                        <div>
                            {{ $fees->links() }}
                        </div>
                    @endif
                </div>
            @else
                {{-- Empty State --}}
                <div class="flex flex-col items-center justify-center py-16 text-center">
                    <div class="size-12 rounded-xl bg-zinc-100 flex items-center justify-center text-zinc-400 mb-2.5">
                        <flux:icon icon="banknotes" class="size-6" />
                    </div>
                    <h4 class="text-sm font-bold text-zinc-900">No Fee Records Found</h4>
                    <p class="text-zinc-500 text-xs max-w-sm mx-auto mt-0.5 mb-3">
                        There are no fee allocations matching your active filter criteria.
                    </p>
                    @can('create feemanagement')
                        <flux:button href="{{ route('feemanagement.create') }}" icon="plus" variant="primary" size="sm">
                            Allocate First Fee
                        </flux:button>
                    @endcan
                </div>
            @endif
        </div>

    </div>
</x-layouts::app>

