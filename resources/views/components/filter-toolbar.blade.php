@props([
    'action' => '',
    'search' => request('search'),
    'placeholder' => 'Search...',
    'hasActiveFilters' => false,
    'resetRoute' => '',
])

<div x-data="{ mobileFiltersOpen: {{ $hasActiveFilters ? 'true' : 'false' }} }"
    class="bg-white dark:bg-zinc-900 rounded-2xl p-3 sm:p-3.5 border border-zinc-200/80 dark:border-zinc-800 shadow-sm space-y-3">
    <form method="GET" action="{{ $action }}" class="flex flex-col sm:flex-row sm:flex-wrap items-stretch sm:items-center gap-2.5">
        
        {{-- Top Search & Mobile Toggle Row --}}
        <div class="flex items-center gap-2 w-full sm:w-auto sm:flex-1 sm:basis-60">
            {{-- Search Input --}}
            <div class="relative flex-1 items-center">
                <div class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 flex items-center text-zinc-400">
                    <flux:icon icon="magnifying-glass" class="size-4" />
                </div>
                <input
                    type="text"
                    name="search"
                    value="{{ $search }}"
                    placeholder="{{ $placeholder }}"
                    class="w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 py-2 pl-9 pr-3 text-xs text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 transition focus:bg-white dark:focus:bg-zinc-900 focus:outline-none focus:ring-2 focus:ring-zinc-900"
                >
            </div>

            {{-- Mobile Filter Trigger Button --}}
            <button type="button" @click="mobileFiltersOpen = !mobileFiltersOpen"
                class="sm:hidden shrink-0 inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold transition border shadow-xs"
                :class="mobileFiltersOpen || {{ $hasActiveFilters ? 'true' : 'false' }} 
                    ? 'bg-zinc-900 text-white border-zinc-900 dark:bg-white dark:text-zinc-900' 
                    : 'bg-zinc-100 text-zinc-700 border-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:border-zinc-700'">
                <flux:icon icon="adjustments-horizontal" class="size-4" />
                <span>Filters</span>
                @if($hasActiveFilters)
                    <span class="size-2 rounded-full bg-emerald-400 shrink-0"></span>
                @endif
            </button>
        </div>

        {{-- Dropdowns & Action Container (Collapsible on Mobile, Inline on Desktop) --}}
        <div class="w-full sm:w-auto sm:flex-1 sm:flex sm:items-center sm:gap-2.5"
             :class="mobileFiltersOpen ? 'flex flex-col gap-2.5 pt-2 border-t border-zinc-100 dark:border-zinc-800 sm:border-0 sm:pt-0' : 'hidden sm:flex'">
            
            {{-- Custom Dropdowns Slot --}}
            <div class="flex flex-col sm:flex-row sm:flex-wrap items-stretch sm:items-center gap-2 sm:gap-2.5 w-full sm:w-auto">
                {{ $slot }}
            </div>

            {{-- Filter & Reset Actions --}}
            <div class="flex items-center gap-2 w-full sm:w-auto pt-1 sm:pt-0">
                <button type="submit" class="flex-1 sm:flex-initial text-center justify-center shrink-0 rounded-xl bg-zinc-900 dark:bg-white px-4 py-2 text-xs font-bold text-white dark:text-zinc-900 transition hover:bg-zinc-800 dark:hover:bg-zinc-100 shadow-xs">
                    Apply Filters
                </button>
                @if($hasActiveFilters && $resetRoute)
                    <a href="{{ $resetRoute }}" class="shrink-0 inline-flex items-center justify-center rounded-xl bg-zinc-100 dark:bg-zinc-800 px-3 py-2 text-xs font-semibold text-zinc-600 dark:text-zinc-400 transition hover:bg-zinc-200 dark:hover:bg-zinc-700" title="Reset Filters">
                        ✕ Clear
                    </a>
                @endif
            </div>
        </div>
    </form>

    @if(isset($scopeTags) && !empty(trim((string)$scopeTags)))
        <div class="flex flex-wrap items-center gap-2 pt-2.5 border-t border-zinc-100 dark:border-zinc-800 text-xs">
            <span class="text-[11px] font-bold text-zinc-400 uppercase tracking-wider mr-1">Active Scope:</span>
            {{ $scopeTags }}
        </div>
    @endif
</div>