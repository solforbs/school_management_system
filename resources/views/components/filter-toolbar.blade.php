@props([
    'action' => '',
    'search' => request('search'),
    'placeholder' => 'Search...',
    'hasActiveFilters' => false,
    'resetRoute' => '',
])

<div class="bg-white rounded-2xl p-3 sm:p-3.5 border border-zinc-200/80 shadow-sm">
    <form method="GET" action="{{ $action }}" class="flex flex-wrap items-center gap-2.5">
        {{-- Search Input --}}
        <div class="relative flex flex-1 basis-60 items-center">
            <div class="pointer-events-none absolute left-3.5 flex items-center text-zinc-400">
                <flux:icon icon="magnifying-glass" class="size-4" />
            </div>
            <input
                type="text"
                name="search"
                value="{{ $search }}"
                placeholder="{{ $placeholder }}"
                class="w-full rounded-xl border border-zinc-200 bg-zinc-50 py-2 pl-10 pr-4 text-xs placeholder-zinc-400 transition focus:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-900"
            >
        </div>

        {{-- Custom Dropdowns Slot --}}
        {{ $slot }}

        {{-- Filter & Reset Actions --}}
        <div class="flex items-center gap-2">
            <button type="submit" class="shrink-0 rounded-xl bg-zinc-900 px-4 py-2 text-xs font-bold text-white transition hover:bg-zinc-800">
                Filter
            </button>
            @if($hasActiveFilters && $resetRoute)
                <a href="{{ $resetRoute }}" class="shrink-0 rounded-xl bg-zinc-100 px-3 py-2 text-xs font-semibold text-zinc-600 transition hover:bg-zinc-200" title="Reset Filters">
                    ✕
                </a>
            @endif
        </div>
    </form>
</div>