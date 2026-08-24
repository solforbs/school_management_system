@props([
    'sidebar' => false,
])

@php
    $institution = function_exists('currentInstitution') ? currentInstitution() : null;
@endphp

@if($sidebar)
    <flux:sidebar.brand href="{{ route('dashboard') }}" class="px-2 pb-2" {{ $attributes }}>
        @if($institution && $institution->logo)
            <div class="flex flex-col items-center justify-center w-full">
                <img src="{{ Storage::url($institution->logo) }}" alt="{{ $institution->name }}" class="h-10 w-auto object-contain hover:scale-105 transition-transform duration-300 drop-shadow-sm" />
            </div>
        @else
            <span class="text-2xl font-bold bg-gradient-to-r from-[#00A3FF] to-[#0047FF] bg-clip-text text-transparent">solforbs</span>
        @endif
    </flux:sidebar.brand>
@else
    <flux:brand href="{{ route('home') }}" {{ $attributes }}>
        <span class="text-2xl font-bold bg-gradient-to-r from-[#00A3FF] to-[#0047FF] bg-clip-text text-transparent">solforbs</span>
    </flux:brand>
@endif
