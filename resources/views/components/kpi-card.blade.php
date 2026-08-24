@props([
    'title',
    'icon',
    'color' => 'zinc',
    'count' => 0,
    'countSuffix' => '',
    'subtext' => '',
    'href' => null,
])

@php
    $colorMap = [
        'indigo' => [
            'bg' => 'bg-indigo-50 dark:bg-indigo-900/20',
            'iconBg' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300',
            'text' => 'text-indigo-700 dark:text-indigo-400',
            'hoverText' => 'group-hover:text-indigo-600',
        ],
        'sky' => [
            'bg' => 'bg-sky-50 dark:bg-sky-900/20',
            'iconBg' => 'bg-sky-100 text-sky-700 dark:bg-sky-900/50 dark:text-sky-300',
            'text' => 'text-sky-700 dark:text-sky-400',
            'hoverText' => 'group-hover:text-sky-600',
        ],
        'emerald' => [
            'bg' => 'bg-emerald-50 dark:bg-emerald-900/20',
            'iconBg' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300',
            'text' => 'text-emerald-700 dark:text-emerald-400',
            'hoverText' => 'group-hover:text-emerald-600',
        ],
        'violet' => [
            'bg' => 'bg-violet-50 dark:bg-violet-900/20',
            'iconBg' => 'bg-violet-100 text-violet-700 dark:bg-violet-900/50 dark:text-violet-300',
            'text' => 'text-violet-700 dark:text-violet-400',
            'hoverText' => 'group-hover:text-violet-600',
        ],
        'amber' => [
            'bg' => 'bg-amber-50 dark:bg-amber-900/20',
            'iconBg' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300',
            'text' => 'text-amber-800 dark:text-amber-400',
            'hoverText' => 'group-hover:text-amber-600',
        ],
        'rose' => [
            'bg' => 'bg-rose-50 dark:bg-rose-900/20',
            'iconBg' => 'bg-rose-100 text-rose-700 dark:bg-rose-900/50 dark:text-rose-300',
            'text' => 'text-rose-700 dark:text-rose-400',
            'hoverText' => 'group-hover:text-rose-600',
        ],
        'red' => [
            'bg' => 'bg-red-50 dark:bg-red-900/20',
            'iconBg' => 'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300',
            'text' => 'text-red-700 dark:text-red-400',
            'hoverText' => 'group-hover:text-red-600',
        ],
        'teal' => [
            'bg' => 'bg-teal-50 dark:bg-teal-900/20',
            'iconBg' => 'bg-teal-100 text-teal-700 dark:bg-teal-900/50 dark:text-teal-300',
            'text' => 'text-teal-700 dark:text-teal-400',
            'hoverText' => 'group-hover:text-teal-600',
        ],
        'zinc' => [
            'bg' => 'bg-zinc-100 dark:bg-zinc-800',
            'iconBg' => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
            'text' => 'text-zinc-700 dark:text-zinc-300',
            'hoverText' => 'group-hover:text-zinc-900',
        ],
    ];

    $colors = $colorMap[$color] ?? $colorMap['zinc'];

    // Robust KPI Parsing
    $rawStr = trim((string)$count);
    $hasSlash = str_contains($rawStr, '/');

    $prefix = '';
    if (str_starts_with($rawStr, 'KES ')) { $prefix = 'KES '; }
    elseif (str_starts_with($rawStr, 'KSh ')) { $prefix = 'KSh '; }
    elseif (str_starts_with($rawStr, '$')) { $prefix = '$'; }
    elseif (str_starts_with($rawStr, '€')) { $prefix = '€'; }

    $hasPercent = str_ends_with($rawStr, '%');
    $suffix = $hasPercent ? '%' : '';

    $withoutPrefixSuffix = $rawStr;
    if ($prefix) $withoutPrefixSuffix = substr($withoutPrefixSuffix, strlen($prefix));
    if ($suffix) $withoutPrefixSuffix = substr($withoutPrefixSuffix, 0, -strlen($suffix));
    $withoutPrefixSuffix = trim($withoutPrefixSuffix);

    $unformatted = str_replace(',', '', $withoutPrefixSuffix);
    $isNumeric = !$hasSlash && is_numeric($unformatted) && strlen($unformatted) > 0;
    
    $numericValue = $isNumeric ? (float)$unformatted : 0;
    $decimals = 0;
    if ($isNumeric && str_contains($unformatted, '.')) {
        $decimals = strlen(substr(strrchr($unformatted, '.'), 1));
    }

    // Default formatted server fallback
    if ($isNumeric) {
        $formattedInitial = $decimals > 0
            ? number_format($numericValue, $decimals)
            : number_format($numericValue);
        $displayFallback = ($prefix ? $prefix : '') . $formattedInitial . ($suffix ? $suffix : ($countSuffix ?: ''));
    } else {
        $displayFallback = $rawStr . ($countSuffix ?: '');
    }
@endphp

@if ($href)
<a href="{{ $href }}" class="group outline-none block h-full">
@else
<div class="group h-full">
@endif

    <flux:card class="space-y-3 hover:shadow-xl hover:-translate-y-1.5 hover:rotate-1 hover:scale-[1.02] transition-all duration-300 dark:hover:border-zinc-500 ring-1 ring-transparent hover:ring-zinc-900/5 cursor-pointer flex flex-col justify-between h-full relative overflow-hidden group">
        <div class="absolute -right-4 -top-4 w-16 h-16 rounded-full {{ $colors['bg'] }} transition-transform group-hover:scale-[2.5] duration-500 ease-out"></div>
        
        <div class="relative flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <div class="flex items-center justify-center size-8 rounded-lg {{ $colors['iconBg'] }} font-semibold shrink-0">
                    <flux:icon icon="{{ $icon }}" class="size-4" />
                </div>
                <flux:text class="font-bold text-xs uppercase tracking-wider text-zinc-600 dark:text-zinc-300">{{ $title }}</flux:text>
            </div>
            @if ($href)
                <flux:icon icon="chevron-right" class="size-4 text-zinc-300 {{ $colors['hoverText'] }} group-hover:translate-x-1 transition-all shrink-0" />
            @endif
        </div>
        
        <div class="relative mt-2" 
            @if($isNumeric) 
                x-data="animatedCounter({{ $numericValue }}, {{ $decimals }}, '{{ $prefix }}', '{{ $suffix ?: ($countSuffix ?: '') }}')" 
            @endif>
            <flux:heading size="xl" class="font-black text-2xl tracking-tight text-zinc-900 dark:text-white flex items-baseline gap-1 tabular-nums">
                @if ($isNumeric)
                    <span x-text="displayCount" class="tabular-nums select-all">{{ $displayFallback }}</span>
                @else
                    <span class="tabular-nums select-all">{{ $displayFallback }}</span>
                @endif
            </flux:heading>
            
            <div class="text-xs mt-1">
                {{ $slot }}
                @if ($subtext && $slot->isEmpty())
                    <flux:text class="text-zinc-400 text-xs font-medium">{{ $subtext }}</flux:text>
                @endif
            </div>
        </div>
    </flux:card>

@if ($href)
</a>
@else
</div>
@endif