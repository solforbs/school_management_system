<div class="overflow-x-auto">
    <table class="w-full text-left border-collapse min-w-[700px]">
        <thead>
            <tr class="border-b border-zinc-200 dark:border-zinc-800 bg-zinc-50/70 dark:bg-zinc-800/40">
                <th class="p-3.5 text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 w-32 border-r border-zinc-200/80 dark:border-zinc-800">
                    Day / Period
                </th>
                @foreach ($periods as $period)
                    <th class="p-3.5 text-center text-xs font-bold text-zinc-700 dark:text-zinc-300 min-w-[140px] border-r border-zinc-200/60 dark:border-zinc-800/60 last:border-r-0">
                        <span class="inline-block font-mono text-[11px] px-2 py-0.5 rounded-md bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-zinc-800 dark:text-zinc-200 shadow-2xs">
                            {{ $period }}
                        </span>
                    </th>
                @endforeach
            </tr>
        </thead>

        <tbody class="divide-y divide-zinc-200/80 dark:divide-zinc-800">
            @foreach ($days as $day)
                @continue(!isset($grid[$day]))
                @php $isCurrentDay = ($day === $currentDayName); @endphp
                <tr class="transition-colors hover:bg-zinc-50/50 dark:hover:bg-zinc-800/20 {{ $isCurrentDay ? 'bg-indigo-50/20 dark:bg-indigo-950/10' : '' }}">
                    <td class="p-3.5 align-top font-bold text-sm text-zinc-900 dark:text-white bg-zinc-50/60 dark:bg-zinc-800/30 border-r border-zinc-200/80 dark:border-zinc-800">
                        <div class="flex items-center gap-1.5">
                            <span>{{ $day }}</span>
                            @if ($isCurrentDay)
                                <span class="size-1.5 rounded-full bg-indigo-600 animate-pulse"></span>
                            @endif
                        </div>
                    </td>
                    @foreach ($periods as $period)
                        @php $entry = $grid[$day][$period] ?? null; @endphp
                        <td class="p-2.5 align-top text-center border-r border-zinc-200/60 dark:border-zinc-800/60 last:border-r-0">
                            @if ($entry)
                                <a href="{{ route('timetable.show', $entry->id) }}" class="block p-2.5 rounded-xl bg-indigo-50/60 hover:bg-indigo-100/70 dark:bg-indigo-950/40 dark:hover:bg-indigo-900/50 border border-indigo-200/70 dark:border-indigo-800/60 transition-all hover:shadow-xs text-left group">
                                    <span class="block font-bold text-xs text-indigo-900 dark:text-indigo-200 group-hover:text-indigo-600 dark:group-hover:text-indigo-300 leading-snug">
                                        {{ $entry->subject }}
                                    </span>
                                    
                                    <div class="flex items-center justify-between gap-1 mt-1.5 pt-1.5 border-t border-indigo-100/80 dark:border-indigo-900/40 text-[10px]">
                                        <span class="font-medium text-indigo-600 dark:text-indigo-400 truncate">
                                            {{ $entry->schoolClass?->name ?? $entry->class_name }}
                                        </span>

                                        @if ($entry->room)
                                            <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded bg-white dark:bg-zinc-900 text-zinc-500 font-mono text-[9px] border border-indigo-100 dark:border-zinc-700 shrink-0">
                                                <flux:icon icon="map-pin" class="size-2.5" />
                                                {{ $entry->room }}
                                            </span>
                                        @endif
                                    </div>
                                </a>
                            @else
                                <div class="h-14 flex items-center justify-center">
                                    <span class="text-zinc-200 dark:text-zinc-700">&mdash;</span>
                                </div>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
