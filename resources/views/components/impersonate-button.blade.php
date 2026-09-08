@props(['user'])

@php
    $canImpersonate = $user instanceof \App\Models\User
        && auth()->check()
        && \App\Support\Impersonation::canImpersonate(auth()->user(), $user);
@endphp

@if ($canImpersonate)
    <form method="POST" action="{{ route('impersonate.start', $user) }}" class="inline"
        onclick="event.stopPropagation();">
        @csrf
        <flux:button type="submit" size="sm" variant="subtle" icon="arrow-right-end-on-rectangle"
            title="Log in as {{ $user->name }}" />
    </form>
@endif
