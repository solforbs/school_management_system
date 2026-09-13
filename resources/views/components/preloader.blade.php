{{--
    Full-screen loading overlay. Rendered visible so it covers the very
    first paint; resources/js/preloader.js fades it out once the page is
    ready and brings it back for wire:navigate and full-page loads.
    Tailwind's animate-pulse is a 2s cycle.
--}}
<div data-preloader role="status" aria-live="polite" aria-busy="true"
    class="fixed inset-0 z-[9999] flex items-center justify-center bg-white/70 backdrop-blur-md transition-opacity duration-300 ease-out dark:bg-zinc-950/70">
    <div class="relative flex size-28 items-center justify-center">
        {{-- soft glow --}}
        <span class="absolute inset-0 rounded-full bg-solforbs-cyan/20 blur-xl animate-pulse motion-reduce:animate-none"></span>
        {{-- spinning ring --}}
        <span class="absolute inset-0 rounded-full border-[3px] border-solforbs-cyan/20 border-t-solforbs-blue border-r-solforbs-cyan animate-spin motion-reduce:animate-none"></span>
        {{-- pulsing logo tile --}}
        <div class="flex size-16 items-center justify-center rounded-2xl bg-white shadow-lg ring-1 ring-zinc-200/70 animate-pulse motion-reduce:animate-none dark:bg-zinc-900 dark:ring-zinc-800">
            <img src="{{ asset('logos/solforbs-logo.png') }}" alt="" class="size-12 object-contain" draggable="false">
        </div>
    </div>
    <span class="sr-only">{{ __('Loading…') }}</span>
</div>
