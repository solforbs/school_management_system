@php
    $currency = fn($v) => number_format((float) $v, 2);
    $pct = fn($num, $den) => $den > 0 ? min(100, round(($num / $den) * 100)) : 0;
    $scope = $stats['scope'] ?? null;
    $roleName = auth()->user()->getRoleNames()->first();
@endphp

<x-layouts::app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">

        @include('dashboard.partials._header')

        @if ($scope === 'system')
            @include('report::partials._system')
        @elseif ($scope === 'institution')
            @include('dashboard.partials._institution')
        @elseif ($scope === 'parent')
            @include('dashboard.partials._parent')
        @elseif ($scope === 'student')
            @include('dashboard.partials._student')
        @elseif ($scope === 'teacher')
            @include('dashboard.partials._teacher')
        @else
            <flux:callout variant="warning" icon="information-circle">
                <flux:callout.heading>No dashboard configuration</flux:callout.heading>
                <flux:callout.text>Your account does not have a configured dashboard view yet.</flux:callout.text>
            </flux:callout>
        @endif

    </div>
</x-layouts::app>
