@props([
    'data' => [], // [['label' => string, 'value' => int, 'color' => string], ...]
])

@php
    $max = count($data) ? max(array_column($data, 'value')) : 0;
    $max = $max > 0 ? $max : 1;
    $total = array_sum(array_column($data, 'value'));

    $colorClass = fn ($color) => match ($color) {
        'emerald' => 'from-emerald-400 to-emerald-500 shadow-emerald-500/20',
        'amber' => 'from-amber-400 to-amber-500 shadow-amber-500/20',
        'red' => 'from-rose-400 to-rose-500 shadow-rose-500/20',
        'sky' => 'from-sky-400 to-sky-500 shadow-sky-500/20',
        'violet' => 'from-violet-400 to-violet-500 shadow-violet-500/20',
        'rose' => 'from-pink-400 to-pink-500 shadow-pink-500/20',
        'zinc' => 'from-zinc-400 to-zinc-500 shadow-zinc-500/20',
        default => 'from-indigo-400 to-indigo-500 shadow-indigo-500/20',
    };
@endphp

<div class="space-y-1">
    @forelse ($data as $item)
        <div class="group relative rounded-xl p-2 transition-all hover:bg-zinc-50 dark:hover:bg-zinc-800/50 cursor-default">
            <div class="mb-2 flex items-center justify-between text-sm">
                <div class="flex items-center gap-2">
                    <span class="size-2 rounded-full bg-gradient-to-r {{ $colorClass($item['color'] ?? 'indigo') }}"></span>
                    <span class="font-medium text-zinc-700 dark:text-zinc-300 group-hover:text-zinc-900 dark:group-hover:text-white transition-colors">{{ $item['label'] }}</span>
                </div>
                <span class="font-semibold text-zinc-700 dark:text-zinc-300">
                    {{ $item['value'] }}
                    @if ($total > 0)
                        <span class="text-xs font-normal text-zinc-400 dark:text-zinc-500 ml-1">({{ round($item['value'] / $total * 100) }}%)</span>
                    @endif
                </span>
            </div>
            <div class="h-2 w-full rounded-full bg-zinc-100 dark:bg-white/5 overflow-hidden">
                <div class="h-2 rounded-full bg-gradient-to-r {{ $colorClass($item['color'] ?? 'indigo') }} shadow-sm transition-all duration-1000 ease-out"
                    style="width: 0%;" x-data x-init="setTimeout(() => { $el.style.width = '{{ max(2, round($item['value'] / $max * 100)) }}%' }, 100)"></div>
            </div>
        </div>
    @empty
        <div class="flex flex-col items-center justify-center py-6">
            <div class="rounded-full bg-zinc-50 dark:bg-zinc-800 p-3 mb-2">
                <flux:icon icon="chart-bar" class="size-5 text-zinc-400" />
            </div>
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">No data available yet</p>
        </div>
    @endforelse
</div>
