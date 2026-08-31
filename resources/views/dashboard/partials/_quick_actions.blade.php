@php
    $isAccountant = auth()->user()->hasRole('Accountant');
@endphp

<div class="flex items-center gap-2 overflow-x-auto pb-1.5 scrollbar-none sm:overflow-visible sm:pb-0 sm:flex-wrap w-full sm:w-auto -mx-1 px-1 sm:mx-0 sm:px-0">
    @if ($isAccountant)
        @can('create feemanagement')
            <flux:button href="{{ route('feemanagement.create') }}" wire:navigate.hover icon="banknotes" variant="primary" class="font-semibold shadow-sm text-xs whitespace-nowrap shrink-0">
                Allocate Fee
            </flux:button>
        @endcan

        @can('create payroll')
            <flux:button href="{{ route('staff.payments.create') }}" wire:navigate.hover icon="credit-card" variant="subtle" class="font-semibold text-xs whitespace-nowrap shrink-0">
                Record Payroll
            </flux:button>
        @endcan

        @can('export report')
            <flux:button href="{{ route('report.export') }}" icon="arrow-down-tray" variant="ghost" class="font-semibold text-xs whitespace-nowrap shrink-0">
                Export Ledger
            </flux:button>
        @endcan
    @else
        @can('create student')
            <flux:button href="{{ route('student.create') }}" wire:navigate.hover icon="user-plus" variant="primary" class="font-semibold shadow-sm text-xs whitespace-nowrap shrink-0">
                Enroll Student
            </flux:button>
        @endcan

        @can('create feemanagement')
            <flux:button href="{{ route('feemanagement.create') }}" wire:navigate.hover icon="banknotes" variant="subtle" class="font-semibold text-xs whitespace-nowrap shrink-0">
                Allocate Fee
            </flux:button>
        @endcan

        @can('view attendance')
            <flux:button href="{{ route('attendance.index') }}" wire:navigate.hover icon="clock" variant="subtle" class="font-semibold text-xs whitespace-nowrap shrink-0">
                Attendance
            </flux:button>
        @endcan

        @can('view reportcard')
            <flux:button href="{{ route('reportcard.index') }}" wire:navigate.hover icon="document-chart-bar" variant="ghost" class="font-medium text-xs whitespace-nowrap shrink-0">
                Report Cards
            </flux:button>
        @endcan
    @endif
</div>
