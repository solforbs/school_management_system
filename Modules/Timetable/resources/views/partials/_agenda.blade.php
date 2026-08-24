@php
    $groupedByDay = $entries->groupBy('day_of_week');
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 p-4 sm:p-6">
    @foreach ($days as $day)
        @php
            $dayEntries = ($groupedByDay->get($day) ?? collect())->sortBy('start_time');
            $isCurrentDay = ($day === $currentDayName);
        @endphp

        <div class="flex flex-col rounded-2xl border {{ $isCurrentDay ? 'border-indigo-500/40 bg-indigo-50/20 dark:bg-indigo-950/20 ring-2 ring-indigo-500/20' : 'border-zinc-200/80 dark:border-zinc-800 bg-white dark:bg-zinc-900' }} p-4 shadow-xs">
            <div class="flex items-center justify-between pb-3 mb-3 border-b border-zinc-100 dark:border-zinc-800/80">
                <div class="flex items-center gap-2">
                    <span class="font-bold text-sm text-zinc-900 dark:text-white">{{ $day }}</span>
                    @if ($isCurrentDay)
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-indigo-600 text-white">
                            Today
                        </span>
                    @endif
                </div>
                <span class="text-xs text-zinc-400 font-medium">
                    {{ $dayEntries->count() }} {{ Str::plural('lesson', $dayEntries->count()) }}
                </span>
            </div>

            <div class="flex-1 space-y-2.5">
                @forelse ($dayEntries as $entry)
                    <a href="{{ route('timetable.show', $entry->id) }}" class="block p-3 rounded-xl border border-zinc-200/70 dark:border-zinc-800 bg-zinc-50/70 dark:bg-zinc-800/50 hover:bg-indigo-50/40 hover:border-indigo-200 dark:hover:bg-zinc-800 transition-all hover:shadow-xs group">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-[11px] font-mono font-bold text-indigo-600 dark:text-indigo-400">
                                {{ $entry->start_time?->format('H:i') }} - {{ $entry->end_time?->format('H:i') }}
                            </span>
                            @if ($entry->room)
                                <span class="inline-flex items-center gap-1 text-[10px] text-zinc-500 bg-white dark:bg-zinc-900 px-2 py-0.5 rounded-md border border-zinc-200/60 dark:border-zinc-700 font-medium">
                                    <flux:icon icon="map-pin" class="size-3 text-zinc-400" />
                                    {{ $entry->room }}
                                </span>
                            @endif
                        </div>

                        <div class="font-bold text-sm text-zinc-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                            {{ $entry->subject }}
                        </div>

                        <div class="flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400 mt-2 pt-2 border-t border-zinc-200/50 dark:border-zinc-800/60">
                            <span>{{ $entry->schoolClass?->name ?? $entry->class_name }}</span>
                            @if ($entry->teacher && !$isTeacher)
                                <span class="text-zinc-400">{{ $entry->teacher->name }}</span>
                            @endif
                        </div>
                    </a>
                @empty
                    <div class="py-8 text-center">
                        <p class="text-xs text-zinc-400">No lessons scheduled</p>
                    </div>
                @endforelse
            </div>
        </div>
    @endforeach
</div>
