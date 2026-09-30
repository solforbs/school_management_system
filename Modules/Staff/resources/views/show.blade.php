@php
    $initials = strtoupper(substr($staff->name, 0, 2));
    $phone = $staff->phone ?? '';
    $email = $staff->email ?? ($staff->user?->email ?? '');
    $staffNo = $staff->staff_number ?? 'EMP-S-' . str_pad($staff->id, 3, '0', STR_PAD_LEFT);
    $hireDate = $staff->hire_date ? $staff->hire_date->format('d M Y') : '—';
    $systemRole = $staff->user ? $staff->user->getRoleNames()->first() ?? 'System Login' : 'No System Access';
@endphp

<x-layouts::app :title="__('Staff Profile')">
    <div class="flex flex-col gap-8 w-full max-w-full mx-auto p-4 sm:p-6 lg:p-8">

        {{-- Top Navigation & Breadcrumbs --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1.5">
                    <a href="{{ route('staff.index') }}" wire:navigate.hover
                        class="inline-flex items-center gap-1.5 text-xs font-bold text-zinc-500 hover:text-zinc-900 transition">
                        <flux:icon icon="arrow-left" class="size-3.5" />
                        <span>Back to Staff Directory</span>
                    </a>
                    <span class="text-xs text-zinc-300">&bull;</span>
                    <span class="text-xs text-zinc-500 font-medium">Personnel Dossier</span>
                </div>
                <flux:heading size="xl" class="font-black tracking-tight text-zinc-900">{{ $staff->name }}
                </flux:heading>
                <flux:text class="text-zinc-500 text-sm mt-0.5">
                    Employment terms, departmental allocation, and monthly payroll history.
                </flux:text>
            </div>

            <div class="flex items-center gap-2">
                @can('edit staff')
                    <flux:button href="{{ route('staff.edit', $staff->id) }}" wire:navigate.hover icon="pencil-square"
                        variant="primary" class="font-semibold shadow-sm">
                        Edit Profile
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

        {{-- Employee Hero Profile Card --}}
        <div class="bg-white rounded-3xl p-5 sm:p-8 border border-zinc-200/80 shadow-sm relative overflow-hidden">
            <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-5 sm:gap-6">
                <div class="flex items-start gap-3.5 sm:gap-4">
                    <div
                        class="size-14 sm:size-16 rounded-2xl bg-zinc-100 border border-zinc-200 text-zinc-800 font-black text-lg sm:text-xl flex items-center justify-center shadow-sm shrink-0">
                        {{ $initials }}
                    </div>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-lg sm:text-xl font-black text-zinc-900 truncate">{{ $staff->name }}</h3>
                            <span
                                class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold whitespace-nowrap shrink-0
                                {{ $staff->is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-zinc-100 text-zinc-600 border border-zinc-200' }}">
                                <span
                                    class="size-1.5 rounded-full {{ $staff->is_active ? 'bg-emerald-500' : 'bg-zinc-400' }}"></span>
                                {{ ucfirst(str_replace('_', ' ', $staff->status)) }}
                            </span>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 sm:gap-3 mt-1.5 text-xs text-zinc-500 font-medium">
                            <span class="font-bold text-zinc-800">{{ $staff->job_title ?? 'Staff Member' }}</span>
                            <span class="text-zinc-300">&bull;</span>
                            <span class="font-mono text-zinc-600 font-bold whitespace-nowrap">{{ $staffNo }}</span>
                            <span class="text-zinc-300">&bull;</span>
                            <span class="truncate">{{ $staff->institution?->name ?? (currentInstitution()?->name ?? __('Institution')) }}</span>
                        </div>
                    </div>
                </div>

                {{-- Direct Rapid Outreach Actions --}}
                <div class="flex items-center gap-2 w-full sm:w-auto">
                    @if ($phone)
                        <a href="tel:{{ $phone }}"
                            class="flex-1 sm:flex-initial inline-flex items-center justify-center gap-2 px-3.5 py-2.5 rounded-xl bg-zinc-100 hover:bg-zinc-200 text-zinc-800 text-xs font-bold transition">
                            <flux:icon icon="phone" class="size-3.5" />
                            <span>Call Phone</span>
                        </a>
                    @endif
                    @if ($email)
                        <a href="mailto:{{ $email }}"
                            class="flex-1 sm:flex-initial inline-flex items-center justify-center gap-2 px-3.5 py-2.5 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-white text-xs font-bold transition shadow-sm">
                            <flux:icon icon="envelope" class="size-3.5" />
                            <span>Send Email</span>
                        </a>
                    @endif
                </div>
            </div>

            {{-- Employment & Contract Specification Grid (Compact 2-col on mobile, 3-col on desktop) --}}
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 sm:gap-4 mt-6 pt-6 border-t border-zinc-100 text-xs">
                <div class="p-3 sm:p-4 rounded-xl sm:rounded-2xl bg-zinc-50 border border-zinc-100">
                    <span
                        class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-0.5">Department</span>
                    <span
                        class="text-xs sm:text-sm font-black text-zinc-900 truncate block">{{ $staff->department ?? 'General Operations' }}</span>
                </div>

                <div class="p-3 sm:p-4 rounded-xl sm:rounded-2xl bg-zinc-50 border border-zinc-100">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-0.5">Contract &
                        Schedule</span>
                    <span
                        class="text-xs sm:text-sm font-black text-zinc-900 truncate block">{{ ucfirst(str_replace('_', ' ', $staff->employment_type ?? 'Full Time')) }}</span>
                </div>

                <div class="p-3 sm:p-4 rounded-xl sm:rounded-2xl bg-zinc-50 border border-zinc-100">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-0.5">Monthly
                        Salary</span>
                    <span class="text-xs sm:text-sm font-black text-zinc-900 font-mono truncate block">
                        {{ $staff->salary ? 'KES ' . number_format($staff->salary, 2) : '—' }}
                    </span>
                </div>

                <div class="p-3 sm:p-4 rounded-xl sm:rounded-2xl bg-zinc-50 border border-zinc-100">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-0.5">Appointment
                        Date</span>
                    <span class="text-xs sm:text-sm font-black text-zinc-900 truncate block">{{ $hireDate }}</span>
                </div>

                <div class="p-3 sm:p-4 rounded-xl sm:rounded-2xl bg-zinc-50 border border-zinc-100">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-0.5">Contact
                        Phone</span>
                    <span class="text-xs sm:text-sm font-black text-zinc-900 font-mono truncate block">{{ $phone ?: '—' }}</span>
                </div>

                <div class="p-3 sm:p-4 rounded-xl sm:rounded-2xl bg-zinc-50 border border-zinc-100">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-0.5">System
                        Privilege</span>
                    <span class="text-xs sm:text-sm font-black text-zinc-900 truncate block">{{ $systemRole }}</span>
                </div>
            </div>

            {{-- Optional Notes --}}
            @if ($staff->notes)
                <div class="mt-6 p-4 rounded-2xl bg-zinc-50 border border-zinc-100 text-xs">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-1">Administrative
                        Notes</span>
                    <p class="text-zinc-800 font-medium leading-relaxed italic whitespace-pre-line">
                        &ldquo;{{ $staff->notes }}&rdquo;</p>
                </div>
            @endif
        </div>

        {{-- Recent Payroll & Salary Disbursements --}}
        @can('view payroll')
            <div class="space-y-4">
                <div class="flex items-center justify-between flex-wrap gap-2">
                    <div>
                        <h3 class="text-base font-bold text-zinc-900">Salary Disbursements & Payroll History</h3>
                        <p class="text-xs text-zinc-500 mt-0.5">Historical compensation disbursements for
                            {{ $staff->name }}.</p>
                    </div>
                    @can('create payroll')
                        <flux:button href="{{ route('staff.payments.create') }}" icon="plus" size="sm" variant="subtle"
                            class="font-semibold">
                            Record Payment
                        </flux:button>
                    @endcan
                </div>

                {{-- Mobile Stacked Cards (sm:hidden) --}}
                <div class="block sm:hidden space-y-2.5">
                    @forelse ($payments as $payment)
                        <div class="bg-white p-4 rounded-2xl border border-zinc-200/80 shadow-xs flex flex-col gap-2">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-bold text-xs text-zinc-900">
                                    {{ $payment->period?->format('F Y') ?? 'Period' }}
                                </span>
                                <span
                                    class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-bold whitespace-nowrap shrink-0
                                    {{ $payment->status === 'paid'
                                        ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                                        : ($payment->status === 'cancelled'
                                            ? 'bg-rose-50 text-rose-700 border border-rose-200'
                                            : 'bg-amber-50 text-amber-700 border border-amber-200') }}">
                                    <span
                                        class="size-1.5 rounded-full {{ $payment->status === 'paid' ? 'bg-emerald-500' : ($payment->status === 'cancelled' ? 'bg-rose-500' : 'bg-amber-500') }}"></span>
                                    {{ ucfirst($payment->status) }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between text-xs pt-1.5 border-t border-zinc-100">
                                <div class="flex flex-col">
                                    <span class="text-[10px] uppercase font-bold text-zinc-400">Gross</span>
                                    <span class="font-mono text-zinc-700">KES {{ number_format($payment->gross_amount, 2) }}</span>
                                </div>
                                <div class="flex flex-col text-right">
                                    <span class="text-[10px] uppercase font-bold text-zinc-400">Net Disbursement</span>
                                    <span class="font-mono font-bold text-zinc-900">KES {{ number_format($payment->net_amount, 2) }}</span>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="bg-white p-6 rounded-2xl border border-zinc-200 text-center text-xs text-zinc-400 italic">
                            No payment disbursements logged yet.
                        </div>
                    @endforelse
                </div>

                {{-- Desktop Multi-Column Table (hidden sm:block) --}}
                <div class="hidden sm:flex bg-white shadow-sm border border-zinc-200/80 rounded-2xl overflow-hidden flex-col">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead
                                class="bg-zinc-50 text-zinc-500 border-b border-zinc-200/80 text-xs font-bold uppercase tracking-wider">
                                <tr>
                                    <th class="py-3.5 px-5">Payroll Period</th>
                                    <th class="py-3.5 px-5">Gross Amount</th>
                                    <th class="py-3.5 px-5">Net Disbursement</th>
                                    <th class="py-3.5 px-5">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100">
                                @forelse ($payments as $payment)
                                    <tr class="hover:bg-zinc-50/60 transition-colors">
                                        <td class="py-3.5 px-5 font-bold text-xs text-zinc-900">
                                            {{ $payment->period?->format('F Y') ?? 'Period' }}
                                        </td>
                                        <td class="py-3.5 px-5 font-mono text-xs text-zinc-700">
                                            KES {{ number_format($payment->gross_amount, 2) }}
                                        </td>
                                        <td class="py-3.5 px-5 font-mono text-xs font-bold text-zinc-900">
                                            KES {{ number_format($payment->net_amount, 2) }}
                                        </td>
                                        <td class="py-3.5 px-5">
                                            <span
                                                class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold
                                                {{ $payment->status === 'paid'
                                                    ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                                                    : ($payment->status === 'cancelled'
                                                        ? 'bg-rose-50 text-rose-700 border border-rose-200'
                                                        : 'bg-amber-50 text-amber-700 border border-amber-200') }}">
                                                <span
                                                    class="size-1.5 rounded-full {{ $payment->status === 'paid' ? 'bg-emerald-500' : ($payment->status === 'cancelled' ? 'bg-rose-500' : 'bg-amber-500') }}"></span>
                                                {{ ucfirst($payment->status) }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="py-8 text-center text-xs text-zinc-400 italic">
                                            No payment disbursements logged yet.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endcan

        {{-- Footer Controls --}}
        <div class="bg-zinc-50 rounded-2xl p-4 border border-zinc-200/80 flex items-center justify-between">
            <flux:button href="{{ route('staff.index') }}" variant="subtle" icon="arrow-left">
                Back to Staff Directory
            </flux:button>

            <div class="flex items-center gap-2">
                @can('edit staff')
                    <flux:button href="{{ route('staff.edit', $staff->id) }}" icon="pencil-square" variant="primary">
                        Edit Profile
                    </flux:button>
                @endcan

                @can('delete staff')
                    <x-confirm-form
                        :action="route('staff.destroy', $staff->id)"
                        title="Remove this staff member from employment?"
                        message="This deletes their staff record and system access."
                        confirm="Remove Staff"
                        label="Delete"
                        variant="subtle"
                        trigger-class="text-rose-600 hover:bg-rose-50"
                        size="base" />
                @endcan
            </div>
        </div>

    </div>
</x-layouts::app>
