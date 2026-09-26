@props(['defaultActive' => null])

<div class="flex flex-col md:flex-row gap-8" 
    x-data="stepperScrollSpy('{{ $defaultActive }}')"
    x-init="initObserver()"
>
    
    {{-- Sticky Sidebar Navigation --}}
    <div class="w-full md:w-1/4">
        <div class="sticky top-8 flex flex-col gap-6">
            <nav class="relative">
                
                <ol role="list" class="overflow-hidden">
                    {{ $sidebar }}
                </ol>
            </nav>
        </div>
    </div>

    {{-- Scrollable Form Content --}}
    <div class="w-full md:w-3/4 flex flex-col gap-8 pb-32">
        {{ $slot }}

        {{-- Action Footer (Sticky to bottom of screen for easy access) --}}
        @isset($actions)
            <div class="fixed bottom-0 left-0 right-0 z-50 bg-white/90 dark:bg-zinc-950/90 backdrop-blur-md border-t border-zinc-200 dark:border-zinc-800 px-4 py-3 sm:px-6 sm:py-4 flex items-center justify-between sm:justify-end gap-2.5 sm:gap-3 lg:left-64 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)]">
                {{ $actions }}
            </div>
        @endisset
    </div>
</div>

{{-- The `stepperScrollSpy` Alpine component is registered in
     resources/js/app.js so it survives wire:navigate (an inline
     `alpine:init` listener only fires on a full page load). --}}
