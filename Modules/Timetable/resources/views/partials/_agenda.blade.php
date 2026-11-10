@php
    $canEdit = $canEdit ?? auth()->user()->can('edit timetable');
    $canDelete = $canDelete ?? auth()->user()->can('delete timetable');
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
                    <div class="block p-3 rounded-xl border border-zinc-200/70 dark:border-zinc-800 bg-zinc-50/70 dark:bg-zinc-800/50 hover:bg-indigo-50/40 hover:border-indigo-200 dark:hover:bg-zinc-800 transition-all hover:shadow-xs group">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-[11px] font-mono font-bold text-indigo-600 dark:text-indigo-400">
                                {{ $entry->start_time?->format('H:i') }} - {{ $entry->end_time?->format('H:i') }}
                            </span>
                            <div class="flex items-center gap-1">
                                @if ($entry->room)
                                    <span class="inline-flex items-center gap-1 text-[10px] text-zinc-500 bg-white dark:bg-zinc-900 px-2 py-0.5 rounded-md border border-zinc-200/60 dark:border-zinc-700 font-medium">
                                        <flux:icon icon="map-pin" class="size-3 text-zinc-400" />
                                        {{ $entry->room }}
                                    </span>
                                @endif
                                <div class="flex items-center gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity">
                                    @if ($canEdit)
                                        <a href="{{ route('timetable.edit', $entry->id) }}" wire:navigate.hover class="p-1 rounded text-zinc-400 hover:text-indigo-600 hover:bg-white dark:hover:bg-zinc-800 transition" title="Edit lesson">
                                            <flux:icon icon="pencil-square" class="size-3" />
                                        </a>
                                    @endif
                                    @if ($canDelete)
                                        <button type="button" wire:click="deleteEntry({{ $entry->id }})" wire:confirm="Remove {{ $entry->subject }} from {{ $day }}?" class="p-1 rounded text-zinc-400 hover:text-rose-600 hover:bg-white dark:hover:bg-zinc-800 transition" title="Remove lesson">
                                            <flux:icon icon="trash" class="size-3" />
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-1.5 flex-wrap">
                            <a href="{{ route('timetable.show', $entry->id) }}" wire:navigate.hover class="font-bold text-sm text-zinc-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                                {{ $entry->subject }}
                            </a>
                            @if ($entry->isDoubleLesson())
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold bg-violet-100 text-violet-700 dark:bg-violet-950/60 dark:text-violet-300">
                                    2x Double
                                </span>
                            @endif
                        </div>

                        <div class="flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400 mt-2 pt-2 border-t border-zinc-200/50 dark:border-zinc-800/60">
                            <span><span translate="no">{{ $entry->schoolClass?->name ?? $entry->class_name }}</span></span>
                            @if ($entry->teacher && !$isTeacher)
                                <span class="text-zinc-400"><span translate="no">{{ $entry->teacher->name }}</span></span>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center">
                        <p class="text-xs text-zinc-400">No lessons scheduled</p>
                    </div>
                @endforelse
            </div>
        </div>
    @endforeach
</div>
