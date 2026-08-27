@props(['institution' => null])

@php
    $institution ??= currentInstitution();
    $status = $institution?->billingStatus();
@endphp

{{-- Admins own the platform rather than a school, and a synced offline
     device has no billing state of its own to report. --}}
@if ($status && ! isAdmin() && ! syncClientMode())
    @php
        $planName = $status['plan']?->name;
        $date = $status['date']?->format('j M Y');
        $days = $status['days'];

        // Quiet until renewal is close: a banner shown every day for a
        // month stops being read long before the month is up.
        $show = match ($status['state']) {
            'subscribed' => $days !== null && $days <= 14,
            'trial', 'lapsed' => true,
            default => false,
        };

        [$variant, $message] = match ($status['state']) {
            'subscribed' => [
                $days !== null && $days <= 3 ? 'danger' : 'warning',
                $days === 0
                    ? "Your {$planName} plan renews today."
                    : "Your {$planName} plan renews on {$date}.",
            ],
            'trial' => [
                $days !== null && $days <= 3 ? 'warning' : 'secondary',
                $planName
                    ? "Your free trial of {$planName} runs until {$date}. Subscribe before then to keep access."
                    : "Your free trial runs until {$date}.",
            ],
            'lapsed' => [
                'danger',
                $date
                    ? "Your subscription ended on {$date}. Renew to restore full access."
                    : 'Your free trial has ended. Subscribe to restore full access.',
            ],
            default => ['secondary', ''],
        };
    @endphp

    @if ($show && $message)
        <flux:callout :variant="$variant" class="mb-4">
            <flux:callout.text>{{ $message }}</flux:callout.text>

            @can('view billing')
                <x-slot name="actions">
                    <flux:button :href="route('billing.show')" size="sm" variant="primary" wire:navigate>
                        {{ $status['state'] === 'subscribed' ? 'Renew now' : 'View plans' }}
                    </flux:button>
                </x-slot>
            @endcan
        </flux:callout>
    @endif
@endif
