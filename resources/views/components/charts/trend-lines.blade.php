@props([
    'data' => [],
    'color' => 'accent', // uses the global accent by default
    'height' => 128,
    'formatter' => null,
])

@php
    $max = count($data) ? max(array_values($data)) : 0;
    $max = $max > 0 ? $max : 1;
    $count = count($data);
    $keys = array_keys($data);
    $values = array_values($data);

    // To plot, we map 0 -> 100 width and 0 -> 100 height.
    $xStep = $count > 1 ? 100 / ($count - 1) : 100;
    
    $points = [];
    foreach($values as $i => $val) {
        $x = $i * $xStep;
        $y = 100 - ($val / $max * 100);
        // keep y slightly bounded so it doesn't clip stroke
        $y = max(5, min(95, $y));
        $points[] = "$x,$y";
    }
    $polyline = implode(' ', $points);
    $polygon = "0,100 $polyline 100,100";

    $strokeColor = $color === 'accent' ? 'var(--color-accent)' : "var(--color-{$color}-500)";
@endphp

<div class="relative w-full flex flex-col" style="height: {{ $height }}px">
    <div class="relative flex-1 w-full h-full">
        {{-- SVG Chart --}}
        <svg class="absolute inset-0 w-full h-full overflow-visible" viewBox="0 0 100 100" preserveAspectRatio="none">
            <defs>
                <linearGradient id="gradient-{{ $color }}-{{ md5($polyline) }}" x1="0" x2="0" y1="0" y2="1">
                    <stop offset="0%" stop-color="{{ $strokeColor }}" stop-opacity="0.3" />
                    <stop offset="100%" stop-color="{{ $strokeColor }}" stop-opacity="0" />
                </linearGradient>
            </defs>
            
            {{-- Area Fill --}}
            <polygon points="{{ $polygon }}" fill="url(#gradient-{{ $color }}-{{ md5($polyline) }})" vector-effect="non-scaling-stroke" class="opacity-0 animate-[fadeArea_1.5s_ease-in_forwards_1s]" />
            
            {{-- Line --}}
            <polyline points="{{ $polyline }}" pathLength="100" stroke-dasharray="100" stroke-dashoffset="100" fill="none" stroke="{{ $strokeColor }}" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke" class="drop-shadow-sm animate-[drawLine_2.5s_cubic-bezier(0.25,1,0.5,1)_forwards]" />
        </svg>
        
        {{-- HTML Hover Targets & Tooltips --}}
        @foreach($values as $i => $val)
            @php
                $x = $i * $xStep;
                $y = 100 - ($val / $max * 100);
                $y = max(5, min(95, $y));
            @endphp
            <div class="absolute group" style="left: {{ $x }}%; top: {{ $y }}%; width: 24px; height: 24px; margin-left: -12px; margin-top: -12px; cursor: pointer; z-index: 10;">
                {{-- Visible dot --}}
                <div class="absolute inset-0 m-auto w-2.5 h-2.5 rounded-full bg-white dark:bg-zinc-900 border-2 group-hover:w-3.5 group-hover:h-3.5 transition-all duration-200 shadow-sm" style="border-color: {{ $strokeColor }}"></div>
                
                {{-- Tooltip --}}
                <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 hidden group-hover:block bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 text-[11px] font-bold px-2.5 py-1 rounded-md shadow-lg whitespace-nowrap">
                    {{ $formatter ? $formatter($val) : $val }}
                    
                    {{-- Tooltip Arrow --}}
                    <div class="absolute top-full left-1/2 -translate-x-1/2 -mt-[1px] border-[5px] border-transparent border-t-zinc-900 dark:border-t-white"></div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- X Axis Labels --}}
    <div class="flex items-center justify-between mt-3 px-1">
        @foreach($keys as $label)
            <span class="text-[10px] font-medium text-zinc-400 dark:text-zinc-500">{{ $label }}</span>
        @endforeach
    </div>
</div>
