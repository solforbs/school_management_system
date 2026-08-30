@props([
    'data' => [],
    'series' => [],
    'height' => 128,
    'formatter' => null,
])

@php
    $seriesColors = [
        'emerald',
        'sky',
        'indigo',
        'amber',
        'rose',
    ];

    $max = 0;
    foreach ($data as $bucket) {
        foreach ($series as $key => $seriesLabel) {
            $max = max($max, $bucket[$key] ?? 0);
        }
    }
    $max = $max > 0 ? $max : 1;
    $count = count($data);
    $keys = array_keys($data);
    
    // To plot, we map 0 -> 100 width and 0 -> 100 height.
    $xStep = $count > 1 ? 100 / ($count - 1) : 100;
    
    // Build points for each series
    $seriesPoints = [];
    foreach($series as $key => $label) {
        $points = [];
        $i = 0;
        foreach($data as $bucket) {
            $val = $bucket[$key] ?? 0;
            $x = $i * $xStep;
            $y = 100 - ($val / $max * 100);
            $y = max(5, min(95, $y));
            $points[] = "$x,$y";
            $i++;
        }
        $seriesPoints[$key] = implode(' ', $points);
    }
@endphp

@if (count($series) > 1)
    <div class="mb-3 flex items-center gap-4 text-xs font-medium text-zinc-500 dark:text-zinc-400">
        @foreach ($series as $key => $seriesLabel)
            @php $color = $seriesColors[$loop->index] ?? 'indigo'; @endphp
            <span class="flex items-center gap-1.5">
                <span class="size-2.5 rounded-full" style="background-color: var(--color-{{ $color }}-500)"></span>
                {{ $seriesLabel }}
            </span>
        @endforeach
    </div>
@endif

<div class="relative w-full flex flex-col" style="height: {{ $height }}px">
    <div class="relative flex-1 w-full h-full">
        {{-- SVG Chart --}}
        <svg class="absolute inset-0 w-full h-full overflow-visible" viewBox="0 0 100 100" preserveAspectRatio="none">
            {{-- Draw Lines --}}
            @foreach ($series as $key => $seriesLabel)
                @php $color = $seriesColors[$loop->index] ?? 'indigo'; @endphp
                
                {{-- Line --}}
                <polyline points="{{ $seriesPoints[$key] }}" pathLength="100" stroke-dasharray="100" stroke-dashoffset="100" fill="none" stroke="var(--color-{{ $color }}-500)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke" class="drop-shadow-sm opacity-0 animate-[drawLine_2.5s_cubic-bezier(0.25,1,0.5,1)_forwards]" style="animation-delay: {{ $loop->index * 0.15 }}s" />
            @endforeach
        </svg>
        
        {{-- HTML Hover Targets & Tooltips --}}
        @php $i = 0; @endphp
        @foreach($data as $bucket)
            @php
                $x = $i * $xStep;
            @endphp
            
            <div class="absolute group" style="left: {{ $x }}%; top: 0; bottom: 0; width: 40px; margin-left: -20px; cursor: pointer; z-index: 10;">
                
                {{-- Invisible vertical hover trigger zone spanning full height --}}
                <div class="absolute inset-0 w-full h-full"></div>
                
                {{-- Vertical dashed line on hover --}}
                <div class="absolute inset-y-0 left-1/2 w-px border-l border-dashed border-zinc-300 dark:border-zinc-700 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                
                @foreach ($series as $key => $seriesLabel)
                    @php
                        $val = $bucket[$key] ?? 0;
                        $y = 100 - ($val / $max * 100);
                        $y = max(5, min(95, $y));
                        $color = $seriesColors[$loop->index] ?? 'indigo';
                    @endphp
                    {{-- Visible dot --}}
                    <div class="absolute left-1/2 -ml-[4px] opacity-0 group-hover:opacity-100 transition-all duration-200 w-2 h-2 rounded-full bg-white dark:bg-zinc-900 border-2 shadow-sm" style="top: {{ $y }}%; margin-top: -4px; border-color: var(--color-{{ $color }}-500)"></div>
                @endforeach
                
                {{-- Combined Tooltip --}}
                <div class="absolute top-1/2 -translate-y-1/2 left-full ml-2 hidden group-hover:block bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 text-[11px] font-medium px-3 py-2 rounded-md shadow-lg whitespace-nowrap z-20">
                    <div class="text-zinc-400 dark:text-zinc-500 mb-1 border-b border-zinc-700 dark:border-zinc-200 pb-1">{{ $keys[$i] }}</div>
                    @foreach ($series as $key => $seriesLabel)
                        @php
                            $val = $bucket[$key] ?? 0;
                            $color = $seriesColors[$loop->index] ?? 'indigo';
                        @endphp
                        <div class="flex items-center justify-between gap-3 my-0.5">
                            <span class="flex items-center gap-1.5">
                                <span class="size-2 rounded-full" style="background-color: var(--color-{{ $color }}-500)"></span>
                                {{ $seriesLabel }}
                            </span>
                            <span class="font-bold">{{ $formatter ? $formatter($val) : $val }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
            @php $i++; @endphp
        @endforeach
    </div>

    {{-- X Axis Labels --}}
    <div class="flex items-center justify-between mt-3 px-1">
        @foreach($keys as $label)
            <span class="text-[10px] font-medium text-zinc-400 dark:text-zinc-500">{{ $label }}</span>
        @endforeach
    </div>
</div>
