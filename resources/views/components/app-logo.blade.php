@props([
    'sidebar' => false,
])

@php
    $institution = function_exists('currentInstitution') ? currentInstitution() : null;
@endphp

@if ($sidebar)
    <flux:sidebar.brand href="{{ route('dashboard') }}" class="px-2 pb-2" {{ $attributes }}>
        @if ($institution && $institution->logo)
            <div class="flex flex-col items-center justify-center w-full">
                <img src="{{ Storage::url($institution->logo) }}" alt="{{ $institution->name }}"
                    class="h-10 w-auto object-contain hover:scale-105 transition-transform duration-300 drop-shadow-sm" />
            </div>
        @else
            <div class="flex flex-col items-center justify-center w-full">
                <img src="{{ asset('logos/solforbs-logo.png') }}" alt="{{ env('APP_NAME') }}"
                    class="h-10 w-auto object-contain hover:scale-105 transition-transform duration-300 drop-shadow-sm" /> <span>{{ auth()->user()->role }}</span>
            </div>
        @endif
    </flux:sidebar.brand>
@else
    <flux:brand href="{{ route('home') }}" {{ $attributes }}>
        <div class="flex flex-col items-center justify-center w-full">
            <img src="{{ asset('logos/solforbs-logo.png') }}" alt="{{ env('APP_NAME') }}"
                class="h-10 w-auto object-contain hover:scale-105 transition-transform duration-300 drop-shadow-sm" /> <span>{{ auth()->user()->role }}
        </div>
    </flux:brand>
@endif
