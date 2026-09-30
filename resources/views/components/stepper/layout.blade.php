@props(['defaultActive' => null])

<div class="flex flex-col md:flex-row gap-6 md:gap-8" 
    x-data="stepperScrollSpy('{{ $defaultActive }}')"
    x-init="initObserver()"
>
    
    {{-- Sticky Navigation (Horizontal Strip on Mobile, Vertical Sidebar on Desktop) --}}
    <div class="w-full md:w-1/4">
        <div class="sticky top-2 sm:top-4 md:top-8 z-30 bg-white/95 dark:bg-zinc-900/95 md:bg-transparent backdrop-blur-md md:backdrop-blur-none py-2 md:py-0 -mx-4 px-4 sm:mx-0 sm:px-0">
            <nav class="relative">
                <ol role="list" class="flex flex-row md:flex-col items-center md:items-stretch gap-2 md:gap-0 overflow-x-auto md:overflow-visible no-scrollbar pb-1 md:pb-0">
                    {{ $sidebar }}
                </ol>
            </nav>
        </div>
    </div>

    {{-- Scrollable Form Content --}}
    <div class="w-full md:w-3/4 flex flex-col gap-6 md:gap-8 pb-32">
        {{ $slot }}

        {{-- Action Footer (Sticky to bottom of screen for easy access) --}}
        @isset($actions)
            <div class="fixed bottom-0 left-0 right-0 z-50 bg-white/95 dark:bg-zinc-950/95 backdrop-blur-md border-t border-zinc-200 dark:border-zinc-800 px-4 py-3 sm:px-6 sm:py-4 flex items-center justify-between sm:justify-end gap-2.5 sm:gap-3 lg:left-64 shadow-[0_-4px_12px_rgba(0,0,0,0.06)]">
                {{ $actions }}
            </div>
        @endisset
    </div>
</div>

{{-- The `stepperScrollSpy` Alpine component is registered in
     resources/js/app.js so it survives wire:navigate (an inline
     `alpine:init` listener only fires on a full page load). --}}
