@php
    $isAccountant = auth()->user()->hasRole('Accountant');
@endphp

<div class="flex flex-wrap items-center gap-2">
    @if ($isAccountant)
        @can('create feemanagement')
            <flux:button href="{{ route('feemanagement.create') }}" icon="banknotes" variant="primary" class="font-semibold shadow-sm text-xs">
                Allocate Student Fee
            </flux:button>
        @endcan

        @can('create payroll')
            <flux:button href="{{ route('staff.payments.create') }}" icon="credit-card" variant="subtle" class="font-semibold text-xs">
                Record Payroll
            </flux:button>
        @endcan

        @can('export report')
            <flux:button href="{{ route('report.export') }}" icon="arrow-down-tray" variant="ghost" class="font-semibold text-xs">
                Export Ledger
            </flux:button>
        @endcan
    @else
        @can('create student')
            <flux:button href="{{ route('student.create') }}" icon="user-plus" variant="primary" class="font-semibold shadow-sm text-xs">
                Enroll Student
            </flux:button>
        @endcan

        @can('create feemanagement')
            <flux:button href="{{ route('feemanagement.create') }}" icon="banknotes" variant="subtle" class="font-semibold text-xs">
                Allocate Fee
            </flux:button>
        @endcan

        @can('create payroll')
            <flux:button href="{{ route('staff.payments.create') }}" icon="credit-card" variant="subtle" class="font-semibold text-xs">
                Record Payroll
            </flux:button>
        @endcan

        @can('view attendance')
            <flux:button href="{{ route('attendance.index') }}" icon="clock" variant="ghost" class="font-medium text-xs">
                Attendance
            </flux:button>
        @endcan
    @endif
</div>
