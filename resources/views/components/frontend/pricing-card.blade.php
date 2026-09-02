@props([
    'name' => null,
    'slug' => null,
    'price' => null,
    'annualPrice' => null,
    'period' => null,
    'annualPeriod' => '/year',
    'description' => null,
    'featured' => false,
    'ctaLabel' => 'Get started',
    'ctaHref' => '#',
    'isCustom' => false,
    // A plan can grant every module the system has, which stacked in one
    // column ran the card far past the fold. Two columns keeps the price
    // and the button in view alongside what you get for it.
    'columns' => 2,
])

<div @click="selectedPlan = '{{ $slug }}'"
    :class="selectedPlan === '{{ $slug }}'
        ? 'border-2 border-[#00A3FF] bg-gradient-to-b from-[#00A3FF]/[0.08] via-white to-white dark:from-blue-950/40 dark:via-zinc-900 dark:to-zinc-950 shadow-xl shadow-[#00A3FF]/20 lg:-translate-y-1 lg:scale-[1.01] ring-1 ring-[#00A3FF]/30 z-10'
        : 'border-zinc-200 bg-white hover:-translate-y-0.5 hover:border-zinc-300 hover:shadow-md dark:border-white/10 dark:bg-white/[0.03] opacity-90 hover:opacity-100 cursor-pointer'"
    {{ $attributes->class([
        'relative flex flex-col justify-between rounded-2xl border p-5 sm:p-6 transition duration-200 select-none cursor-pointer',
    ]) }}>

    {{-- Interactive Top Badge --}}
    <template x-if="selectedPlan === '{{ $slug }}'">
        <span class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-gradient-to-r from-[#00A3FF] to-blue-600 px-3 py-0.5 text-[10px] font-extrabold whitespace-nowrap text-white shadow-md shadow-[#00A3FF]/30 ring-2 ring-white dark:ring-zinc-900 tracking-wide uppercase">
            {{ $featured ? '⭐ Most popular' : '✓ Selected' }}
        </span>
    </template>
    @if ($featured)
        <template x-if="selectedPlan !== '{{ $slug }}'">
            <span class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-zinc-200 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 px-2.5 py-0.5 text-[10px] font-bold whitespace-nowrap shadow-xs">
                ⭐ Most popular
            </span>
        </template>
    @endif

    <div>
        <div class="flex items-center justify-between gap-2">
            <h3 class="text-base sm:text-lg font-bold text-zinc-900 dark:text-white">{{ $name }}</h3>
            <span :class="selectedPlan === '{{ $slug }}' ? 'bg-[#00A3FF]/15 text-[#00A3FF] border-[#00A3FF]/30' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-500 border-zinc-200 dark:border-zinc-700'"
                class="text-[10px] font-bold px-2 py-0.5 rounded-full border transition-colors">
                {{ $featured ? 'All Modules' : ($isCustom ? 'Multi-Campus' : 'Core Suite') }}
            </span>
        </div>
        <p class="mt-1 text-xs text-zinc-600 dark:text-zinc-400 leading-relaxed min-h-[32px]">{{ $description }}</p>

        {{-- Dynamic Price (Reacts to Per Term vs Annual switch) --}}
        <div class="mt-3 flex items-baseline gap-1 text-zinc-900 dark:text-white border-b border-zinc-100 dark:border-zinc-800/80 pb-3">
            @if ($isCustom)
                <span class="text-2xl sm:text-3xl font-extrabold tracking-tight">Custom</span>
                <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Quotation</span>
            @elseif ($price === 'Free')
                <span class="text-2xl sm:text-3xl font-extrabold tracking-tight">Free</span>
                @if ($period)
                    <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ $period }}</span>
                @endif
            @else
                <span class="text-2xl sm:text-3xl font-extrabold tracking-tight">
                    <span x-show="billingPeriod === 'termly'">{{ $price }}</span>
                    <span x-show="billingPeriod === 'annual'" x-cloak>{{ $annualPrice ?? $price }}</span>
                </span>
                <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">
                    <span x-show="billingPeriod === 'termly'">{{ $period }}</span>
                    <span x-show="billingPeriod === 'annual'" x-cloak>{{ $annualPeriod }}</span>
                </span>
            @endif
        </div>

        {{-- Dynamic 2-column feature list --}}
        <ul @class([
            'mt-3.5 text-xs text-zinc-600 dark:text-zinc-400',
            'grid gap-x-3 gap-y-1.5 sm:grid-cols-2' => $columns > 1,
            'space-y-1.5' => $columns <= 1,
        ])>
            {{ $slot }}
        </ul>
    </div>

    {{-- Interactive CTA Button --}}
    <div class="mt-5">
        <template x-if="selectedPlan === '{{ $slug }}'">
            <a href="{{ $ctaHref }}" wire:navigate class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl font-bold text-xs sm:text-sm text-white bg-gradient-to-r from-[#00A3FF] to-blue-600 hover:from-[#0092e6] hover:to-blue-700 shadow-md shadow-[#00A3FF]/25 hover:shadow-lg transition-all cursor-pointer">
                <span>{{ $ctaLabel }}</span>
                <flux:icon icon="arrow-right" class="size-3.5" />
            </a>
        </template>
        <template x-if="selectedPlan !== '{{ $slug }}'">
            <a href="{{ $ctaHref }}" wire:navigate class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl font-semibold text-xs sm:text-sm text-zinc-700 dark:text-zinc-200 bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 border border-zinc-200/80 dark:border-zinc-700 transition cursor-pointer">
                <span>{{ $ctaLabel }}</span>
            </a>
        </template>
    </div>
</div>
