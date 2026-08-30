@props([
    'title',
    'description' => '',
    'icon' => 'cube',
    'color' => 'indigo', // indigo, emerald, amber, sky, rose, zinc
])

@php
    $colorMap = [
        'indigo' => [
            'bg' => 'bg-indigo-50 dark:bg-indigo-900/20',
            'ring' => 'ring-indigo-500/20',
            'ping' => 'bg-indigo-400/20',
            'icon' => 'text-indigo-600 dark:text-indigo-400',
        ],
        'emerald' => [
            'bg' => 'bg-emerald-50 dark:bg-emerald-900/20',
            'ring' => 'ring-emerald-500/20',
            'ping' => 'bg-emerald-400/20',
            'icon' => 'text-emerald-600 dark:text-emerald-400',
        ],
        'amber' => [
            'bg' => 'bg-amber-50 dark:bg-amber-900/20',
            'ring' => 'ring-amber-500/20',
            'ping' => 'bg-amber-400/20',
            'icon' => 'text-amber-600 dark:text-amber-400',
        ],
        'sky' => [
            'bg' => 'bg-sky-50 dark:bg-sky-900/20',
            'ring' => 'ring-sky-500/20',
            'ping' => 'bg-sky-400/20',
            'icon' => 'text-sky-600 dark:text-sky-400',
        ],
        'rose' => [
            'bg' => 'bg-rose-50 dark:bg-rose-900/20',
            'ring' => 'ring-rose-500/20',
            'ping' => 'bg-rose-400/20',
            'icon' => 'text-rose-600 dark:text-rose-400',
        ],
        'zinc' => [
            'bg' => 'bg-zinc-50 dark:bg-zinc-800/50',
            'ring' => 'ring-zinc-500/20',
            'ping' => 'bg-zinc-400/20',
            'icon' => 'text-zinc-500 dark:text-zinc-400',
        ],
    ];

    $c = $colorMap[$color] ?? $colorMap['indigo'];
@endphp

<div class="flex flex-col items-center justify-center py-20 px-4">
    <div class="size-16 rounded-2xl {{ $c['bg'] }} flex items-center justify-center mb-5 ring-1 {{ $c['ring'] }} shadow-sm relative animate-bounce">
        <div class="absolute inset-0 rounded-2xl {{ $c['ping'] }} animate-ping opacity-20"></div>
        <flux:icon :icon="$icon" class="size-8 {{ $c['icon'] }}" />
    </div>
    
    <h3 class="text-lg font-bold text-zinc-900 dark:text-white mb-2 text-center">{{ $title }}</h3>
    
    @if($description)
        <p class="text-zinc-500 dark:text-zinc-400 text-sm max-w-sm leading-relaxed text-center mb-2">
            {{ $description }}
        </p>
    @endif

    @if ($slot->isNotEmpty())
        <div class="mt-4 flex flex-col sm:flex-row items-center justify-center gap-3 w-full">
            {{ $slot }}
        </div>
    @endif
</div>
