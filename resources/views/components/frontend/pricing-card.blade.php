@props([
    'name' => null,
    'price' => null,
    'period' => null,
    'description' => null,
    'featured' => false,
    'ctaLabel' => 'Get started',
    'ctaHref' => '#',
    // A plan can grant every module the system has, which stacked in one
    // column ran the card far past the fold. Two columns keeps the price
    // and the button in view alongside what you get for it.
    'columns' => 2,
])

<div {{ $attributes->class([
        'relative flex flex-col rounded-3xl border p-8 transition duration-300',
        $featured
            ? 'dark border-transparent bg-zinc-900 shadow-2xl shadow-zinc-900/20 lg:-translate-y-2 lg:scale-105'
            : 'border-zinc-200 bg-white hover:-translate-y-1 hover:border-zinc-300 hover:shadow-lg dark:border-white/10 dark:bg-white/[0.03]',
    ]) }}>
    @if ($featured)
        <span class="absolute -top-3.5 left-1/2 -translate-x-1/2 rounded-full bg-indigo-600 px-3 py-1 text-xs font-semibold whitespace-nowrap text-white shadow-lg">
            Most popular
        </span>
    @endif

    <h3 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ $name }}</h3>
    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $description }}</p>

    <div class="mt-6 flex items-baseline gap-1 text-zinc-900 dark:text-white">
        <span class="text-4xl font-semibold tracking-tight">{{ $price }}</span>
        @if ($period)
            <span class="text-sm font-normal text-zinc-500 dark:text-zinc-400">{{ $period }}</span>
        @endif
    </div>

    {{-- Grid rather than CSS columns: multi-column flows items top-to-bottom
         and can split one across a column break, which looks broken next to
         a tick. A grid keeps each item whole and reads left-to-right. Single
         column below sm, where two would be too narrow to read. --}}
    <ul @class([
        'mt-8 flex-1 text-sm text-zinc-600 dark:text-zinc-400',
        'grid gap-x-5 gap-y-3 sm:grid-cols-2' => $columns > 1,
        'space-y-3' => $columns <= 1,
    ])>
        {{ $slot }}
    </ul>

    <flux:button :href="$ctaHref" :variant="$featured ? 'primary' : 'outline'" class="mt-8 w-full" wire:navigate>
        {{ $ctaLabel }}
    </flux:button>
</div>
