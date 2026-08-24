@props([
    'type' => 'card', // card, table, chart, list
    'rows' => 3
])

<div {{ $attributes->merge(['class' => 'w-full animate-pulse']) }}>
    @if($type === 'card')
        <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 p-6 flex flex-col gap-4 shadow-sm">
            <div class="h-5 bg-zinc-200 dark:bg-zinc-800 rounded-md w-1/3"></div>
            <div class="h-10 bg-zinc-200 dark:bg-zinc-800 rounded-md w-1/2"></div>
            <div class="flex items-center gap-2 mt-auto">
                <div class="w-4 h-4 bg-zinc-200 dark:bg-zinc-800 rounded-full"></div>
                <div class="h-3 bg-zinc-200 dark:bg-zinc-800 rounded-md w-1/4"></div>
            </div>
        </div>
    @elseif($type === 'table')
        <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-sm overflow-hidden">
            <div class="h-12 border-b border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-950 flex items-center px-6 gap-4">
                <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded-md w-1/4"></div>
                <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded-md w-1/4"></div>
                <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded-md w-1/4"></div>
                <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded-md w-1/4"></div>
            </div>
            @for ($i = 0; $i < $rows; $i++)
                <div class="h-16 border-b border-zinc-200 dark:border-zinc-800 flex items-center px-6 gap-4">
                    <div class="flex items-center gap-3 w-1/4">
                        <div class="w-8 h-8 rounded-full bg-zinc-200 dark:bg-zinc-800"></div>
                        <div class="flex flex-col gap-1 w-full">
                            <div class="h-3 bg-zinc-200 dark:bg-zinc-800 rounded-md w-2/3"></div>
                            <div class="h-2 bg-zinc-200 dark:bg-zinc-800 rounded-md w-1/3"></div>
                        </div>
                    </div>
                    <div class="h-3 bg-zinc-200 dark:bg-zinc-800 rounded-md w-1/5"></div>
                    <div class="h-3 bg-zinc-200 dark:bg-zinc-800 rounded-md w-1/5"></div>
                    <div class="h-6 bg-zinc-200 dark:bg-zinc-800 rounded-full w-16"></div>
                </div>
            @endfor
        </div>
    @elseif($type === 'chart')
        <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 p-6 flex flex-col gap-6 shadow-sm h-full">
            <div class="flex flex-col gap-2">
                <div class="h-6 bg-zinc-200 dark:bg-zinc-800 rounded-md w-1/3"></div>
                <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded-md w-1/2"></div>
            </div>
            <div class="flex-1 w-full flex items-end gap-2 h-48">
                @foreach ([60, 85, 40, 95, 55, 75] as $height)
                    <div class="w-full bg-zinc-200 dark:bg-zinc-800 rounded-t-md" style="height: {{ $height }}%"></div>
                @endforeach
            </div>
        </div>
    @elseif($type === 'list')
        <div class="space-y-4">
            @for ($i = 0; $i < $rows; $i++)
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-full bg-zinc-200 dark:bg-zinc-800"></div>
                    <div class="flex flex-col gap-2 flex-1">
                        <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded-md w-1/3"></div>
                        <div class="h-3 bg-zinc-200 dark:bg-zinc-800 rounded-md w-1/4"></div>
                    </div>
                </div>
            @endfor
        </div>
    @endif
</div>
