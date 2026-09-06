@props(['institution' => null])

@php
    $institution ??= currentInstitution();
    $status = $institution?->studentLimitStatus();
@endphp

{{-- Admins aren't scoped to a plan, and an offline synced device carries
     no plan to read a limit from. --}}
@if ($status && ! isAdmin() && ! syncClientMode() && $status['plan'])
    @php
        $planName = $status['plan']->name;

        if ($status['unlimited']) {
            $variant = 'secondary';
            $message = "Your {$planName} subscription supports unlimited students ({$status['used']} enrolled).";
        } elseif ($status['reached']) {
            $variant = 'danger';
            $message = "Your {$planName} subscription supports up to "
                . number_format($status['limit']) . ' students, and you have reached that limit ('
                . number_format($status['used']) . ' enrolled). Upgrade your plan to enrol more.';
        } else {
            $variant = $status['remaining'] !== null && $status['remaining'] <= 10 ? 'warning' : 'secondary';
            $message = 'Your ' . $planName . ' subscription supports up to '
                . number_format($status['limit']) . ' students — '
                . number_format($status['used']) . ' enrolled, '
                . number_format($status['remaining']) . ' remaining.';
        }
    @endphp

    <flux:callout :variant="$variant" icon="academic-cap" class="mb-4">
        <flux:callout.text>{{ $message }}</flux:callout.text>

        @if ($status['reached'])
            @can('view billing')
                <x-slot name="actions">
                    <flux:button :href="route('billing.show')" size="sm" variant="primary" wire:navigate>
                        View plans
                    </flux:button>
                </x-slot>
            @endcan
        @endif
    </flux:callout>
@endif
