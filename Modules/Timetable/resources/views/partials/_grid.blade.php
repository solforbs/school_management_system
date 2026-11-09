@php
    $isDraft = $isDraftMode ?? false;
    $isEdit = $isEditMode ?? false;
    $canEdit = $canEdit ?? auth()->user()->can('edit timetable');
    $canDelete = $canDelete ?? auth()->user()->can('delete timetable');
@endphp

<div class="overflow-x-auto rounded-2xl border border-zinc-200/80 dark:border-zinc-800 shadow-2xs bg-white dark:bg-zinc-900" x-data="{ draggedEntryId: null, hoveredSlot: null }">
    <table class="min-w-max w-full text-left border-collapse">
        <thead>
            <tr class="border-b border-zinc-200 dark:border-zinc-800 bg-zinc-50/90 dark:bg-zinc-800/60 backdrop-blur-xs">
                {{-- Sticky Day Column Header --}}
                <th class="sticky left-0 z-20 p-2.5 sm:p-3 text-xs font-black uppercase tracking-wider text-zinc-500 dark:text-zinc-400 w-24 sm:w-28 min-w-[96px] border-r border-zinc-200/80 dark:border-zinc-800 bg-zinc-100 dark:bg-zinc-800 shadow-xs">
                    <div class="flex items-center gap-1.5">
                        <flux:icon icon="calendar" class="size-3.5 text-zinc-400 shrink-0" />
                        <span class="text-[11px] sm:text-xs">Day</span>
                    </div>
                </th>
                @foreach ($periods as $period)
                    @php
                        $isBreakSlot = is_array($period) && !empty($period['is_break']);
                        $slotName = is_array($period) ? ($period['name'] ?? $period['time']) : $period;
                        $slotTime = is_array($period) ? $period['time'] : $period;
                        $periodNumber = is_array($period) ? ($period['period_number'] ?? null) : null;
                    @endphp
                    <th class="p-2 text-center text-xs font-bold border-r border-zinc-200/60 dark:border-zinc-800/60 last:border-r-0 transition-colors {{ $isBreakSlot ? 'w-14 sm:w-16 min-w-[56px] max-w-[64px] bg-zinc-100/50 dark:bg-zinc-800/30' : 'min-w-[145px] sm:min-w-[160px] text-zinc-800 dark:text-zinc-200' }}">
                        @if ($isBreakSlot)
                            {{-- Break / Rest Header with Click-to-Edit --}}
                            <div 
                                wire:click="openEditBreakModal('{{ $slotTime }}', '{{ addslashes($slotName) }}', 'zinc')"
                                class="group relative cursor-pointer p-1 rounded-lg transition hover:bg-zinc-200/50 dark:hover:bg-zinc-700/50 {{ $isEdit ? 'border border-dashed border-zinc-400 dark:border-zinc-600' : '' }}"
                                title="Click to edit rest break duration"
                            >
                                <div class="flex items-center justify-center gap-0.5">
                                    <span class="inline-flex items-center gap-0.5 text-[9px] sm:text-[10px] font-bold uppercase tracking-tight px-1 py-0.5 rounded bg-zinc-200/70 dark:bg-zinc-700 text-zinc-700 dark:text-zinc-300 truncate max-w-full">
                                        {{ $slotName }}
                                    </span>
                                </div>
                                <div class="font-mono text-[8px] sm:text-[9px] text-zinc-400 font-semibold mt-0.5 whitespace-nowrap">
                                    {{ $slotTime }}
                                </div>
                            </div>
                        @else
                            {{-- Teaching Period Header with Click-to-Edit --}}
                            <div 
                                wire:click="openEditPeriodModal('{{ $slotTime }}', '{{ addslashes($slotName) }}')"
                                class="group relative cursor-pointer p-1 rounded-lg transition hover:bg-white dark:hover:bg-zinc-800/90 hover:shadow-2xs {{ $isEdit ? 'border border-dashed border-indigo-400/80 bg-indigo-50/20' : '' }}"
                                title="Click to adjust period start and end time"
                            >
                                <div class="flex items-center justify-center gap-1">
                                    <span class="text-[11px] sm:text-xs font-black text-zinc-900 dark:text-white truncate">
                                        {{ $slotName }}
                                    </span>
                                    <flux:icon icon="pencil-square" class="size-2.5 text-indigo-500 opacity-0 group-hover:opacity-100 transition-opacity shrink-0" />
                                </div>
                                <span class="inline-flex items-center gap-0.5 font-mono text-[9px] sm:text-[10px] font-semibold px-1.5 py-0.2 rounded-md bg-white dark:bg-zinc-800 border border-zinc-200/90 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300 shadow-2xs mt-0.5 whitespace-nowrap">
                                    <flux:icon icon="clock" class="size-2 text-zinc-400 shrink-0" />
                                    {{ $slotTime }}
                                </span>
                            </div>
                        @endif
                    </th>
                @endforeach
            </tr>
        </thead>

        <tbody class="divide-y divide-zinc-200/80 dark:divide-zinc-800 bg-white dark:bg-zinc-900">
            @foreach ($days as $day)
                @php 
                    $isCurrentDay = ($day === $currentDayName); 
                @endphp
                <tr class="transition-colors hover:bg-zinc-50/40 dark:hover:bg-zinc-800/20 {{ $isCurrentDay ? 'bg-indigo-50/15 dark:bg-indigo-950/10' : '' }}">
                    {{-- Sticky Day Row Header --}}
                    <td class="sticky left-0 z-10 p-2.5 sm:p-3 align-top font-bold text-xs text-zinc-900 dark:text-white bg-zinc-50/95 dark:bg-zinc-800/95 backdrop-blur-xs border-r border-zinc-200/80 dark:border-zinc-800 shadow-xs">
                        <div class="space-y-0.5">
                            <div class="flex items-center gap-1 flex-wrap">
                                <span class="font-black text-[11px] sm:text-xs text-zinc-900 dark:text-white">{{ $day }}</span>
                                @if ($isCurrentDay)
                                    <span class="inline-flex items-center gap-0.5 px-1 py-0.2 rounded-full text-[8px] font-extrabold bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">
                                        <span class="size-1 rounded-full bg-indigo-600 animate-pulse"></span>
                                        TODAY
                                    </span>
                                @endif
                            </div>
                            <div class="text-[9px] text-zinc-400 font-medium">
                                @php
                                    $dayEntries = $grid[$day] ?? [];
                                    $daySlotsCount = 0;
                                    foreach ($dayEntries as $slotKey => $slotEntry) {
                                        if ($slotEntry && ! str_starts_with((string)$slotKey, 'p_')) {
                                            $daySlotsCount++;
                                        }
                                    }
                                @endphp
                                {{ $daySlotsCount }} {{ Str::plural('slot', $daySlotsCount) }}
                            </div>
                        </div>
                    </td>

                    @foreach ($periods as $period)
                        @php 
                            $isBreakSlot = is_array($period) && !empty($period['is_break']);
                            $periodStr = is_array($period) ? $period['time'] : $period;
                            $breakName = is_array($period) ? ($period['name'] ?? 'Break') : 'Break';
                            $periodNumber = is_array($period) ? ($period['period_number'] ?? null) : null;
                            $periodParts = explode('-', $periodStr);
                            $slotStart = $periodParts[0] ?? '08:00';
                            $slotEnd = $periodParts[1] ?? '08:40';
                            $entry = $isBreakSlot ? null : ($grid[$day][$periodStr] ?? ($periodNumber ? ($grid[$day]['p_' . $periodNumber] ?? null) : null)); 
                        @endphp

                        <td 
                            class="p-1 sm:p-1.5 align-top border-r border-zinc-200/60 dark:border-zinc-800/60 last:border-r-0 transition-colors {{ $isBreakSlot ? 'w-14 sm:w-16 min-w-[56px] max-w-[64px] bg-zinc-50/40 dark:bg-zinc-900/40' : 'min-w-[145px] sm:min-w-[160px] hover:bg-zinc-50/60 dark:hover:bg-zinc-800/30' }}"
                            @if ($isDraft && ! $isBreakSlot)
                                @dragover.prevent="hoveredSlot = '{{ $day }}_{{ $periodStr }}'"
                                @dragleave.prevent="hoveredSlot = null"
                                @drop.prevent="
                                    if (draggedEntryId) {
                                        $wire.moveDraftEntry(draggedEntryId, '{{ $day }}', '{{ $slotStart }}', '{{ $slotEnd }}');
                                        draggedEntryId = null;
                                        hoveredSlot = null;
                                    }
                                "
                                :class="{ 'ring-2 ring-indigo-500 bg-indigo-50/40 dark:bg-indigo-950/40': hoveredSlot === '{{ $day }}_{{ $periodStr }}' }"
                            @endif
                        >
                            @if ($isBreakSlot)
                                {{-- Rest / Break Barrier Column --}}
                                <div 
                                    wire:click="openEditBreakModal('{{ $periodStr }}', '{{ addslashes($breakName) }}', 'zinc')"
                                    class="h-full min-h-[62px] sm:min-h-[70px] rounded-xl flex flex-col items-center justify-center p-1 text-center select-none cursor-pointer group transition bg-zinc-100/70 dark:bg-zinc-800/50 border border-zinc-200/70 dark:border-zinc-700/60 text-zinc-500 dark:text-zinc-400 hover:bg-zinc-200/50 dark:hover:bg-zinc-700/50"
                                    title="Rest Break: {{ $breakName }} ({{ $periodStr }})"
                                >
                                    <div class="size-5 rounded-full flex items-center justify-center mb-0.5 bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-700 text-zinc-400 shadow-2xs">
                                        @if(stripos($breakName, 'lunch') !== false)
                                            <flux:icon icon="clock" class="size-2.5 text-zinc-500" />
                                        @else
                                            <flux:icon icon="sparkles" class="size-2.5 text-zinc-500" />
                                        @endif
                                    </div>
                                    <span class="text-[9px] font-bold uppercase tracking-tight leading-tight text-zinc-600 dark:text-zinc-400 truncate max-w-full">
                                        {{ $breakName }}
                                    </span>
                                    <span class="text-[8px] font-mono text-zinc-400 mt-0.5 whitespace-nowrap">
                                        {{ $periodStr }}
                                    </span>
                                </div>
                            @elseif ($entry)
                                @php
                                    $isDouble = method_exists($entry, 'isDoubleLesson') && $entry->isDoubleLesson();
                                    $isPart1 = method_exists($entry, 'isDoubleLessonPart1') && $entry->isDoubleLessonPart1();
                                    $isPart2 = method_exists($entry, 'isDoubleLessonPart2') && $entry->isDoubleLessonPart2();
                                @endphp

                                @if ($isDraft)
                                    {{-- Draft Studio Card with Instant Drag-and-Drop --}}
                                    @php
                                        $subjectTitle = $entry->subject_title;
                                    @endphp
                                    <div 
                                        draggable="{{ $entry->is_locked ? 'false' : 'true' }}"
                                        @dragstart="draggedEntryId = {{ $entry->id }}"
                                        @dragend="draggedEntryId = null; hoveredSlot = null"
                                        class="relative p-2 rounded-xl border-l-3 sm:border-l-4 transition-all text-left group select-none shadow-2xs hover:shadow-xs {{ $isDouble ? 'border-l-violet-600 bg-violet-50/20 dark:bg-violet-950/20 border-zinc-200/90 dark:border-zinc-800' : 'border-l-indigo-600 bg-white dark:bg-zinc-900 border-zinc-200/90 dark:border-zinc-800' }} border-y border-r {{ $entry->is_locked ? 'ring-1 ring-amber-400 bg-amber-50/30' : 'cursor-grab active:cursor-grabbing' }}"
                                    >
                                        <div class="flex items-start justify-between gap-1">
                                            <div 
                                                wire:click="openLessonModal('{{ $day }}', '{{ $periodStr }}', {{ $entry->id }}, true)"
                                                class="font-black text-xs text-zinc-900 dark:text-white leading-tight truncate flex-1 cursor-pointer hover:text-indigo-600"
                                                title="Click to edit lesson"
                                            >
                                                {{ $subjectTitle }}
                                            </div>

                                            <div class="flex items-center gap-0.5 opacity-90 group-hover:opacity-100 transition-opacity shrink-0">
                                                {{-- Instant Quick Edit Lesson --}}
                                                <button type="button" wire:click="openLessonModal('{{ $day }}', '{{ $periodStr }}', {{ $entry->id }}, true)" title="Edit lesson" class="p-0.5 rounded text-zinc-400 hover:text-indigo-600 hover:bg-white dark:hover:bg-zinc-800 transition">
                                                    <flux:icon icon="pencil-square" class="size-3" />
                                                </button>
                                                {{-- Toggle Lock Slot --}}
                                                <button type="button" wire:click="toggleLockDraftEntry({{ $entry->id }})" title="{{ $entry->is_locked ? 'Locked (will not be moved)' : 'Lock slot' }}" class="p-0.5 rounded text-zinc-400 hover:text-amber-600 hover:bg-white dark:hover:bg-zinc-800 transition">
                                                    <flux:icon icon="{{ $entry->is_locked ? 'lock-closed' : 'lock-open' }}" class="size-3 {{ $entry->is_locked ? 'text-amber-600' : 'text-zinc-400' }}" />
                                                </button>
                                                {{-- Instant Delete Draft Slot (1-Click with wire:confirm) --}}
                                                <button type="button" wire:click="deleteDraftEntry({{ $entry->id }})" wire:confirm="Remove {{ addslashes($subjectTitle) }} from this draft slot?" class="p-0.5 rounded text-zinc-400 hover:text-rose-600 hover:bg-white dark:hover:bg-zinc-800 transition" title="Remove slot from draft">
                                                    <flux:icon icon="trash" class="size-3" />
                                                </button>
                                            </div>
                                        </div>

                                        {{-- Double Lesson Badge --}}
                                        @if ($isDouble)
                                            <div class="mt-1">
                                                <span class="inline-flex items-center gap-0.5 px-1 py-0.2 rounded text-[8px] sm:text-[9px] font-extrabold bg-violet-100 dark:bg-violet-950 text-violet-700 dark:text-violet-300 border border-violet-200 dark:border-violet-800/60 truncate max-w-full">
                                                    <flux:icon icon="link" class="size-2 shrink-0" />
                                                    {{ $isPart2 ? 'Double (2)' : '2x Double' }}
                                                </span>
                                            </div>
                                        @endif
                                        
                                        <div class="flex items-center justify-between gap-1 mt-1.5 pt-1 border-t border-zinc-100 dark:border-zinc-800 text-[10px]">
                                            <span class="font-medium text-zinc-600 dark:text-zinc-400 truncate flex-1" title="{{ $entry->teacher?->name ?? 'Teacher TBA' }}">
                                                <span translate="no">{{ $entry->teacher?->name ?? 'Teacher TBA' }}</span>
                                            </span>

                                            @if ($entry->room)
                                                <span class="inline-flex items-center gap-0.5 px-1 py-0.2 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-500 font-mono text-[9px] border border-zinc-200 dark:border-zinc-700 shrink-0">
                                                    <flux:icon icon="map-pin" class="size-2 text-zinc-400" />
                                                    {{ $entry->room }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                @else
                                    {{-- Standard Live Schedule View Card --}}
                                    @php
                                        $subjectTitle = $entry->subject;
                                    @endphp
                                    <div class="relative p-2 rounded-xl border-l-3 sm:border-l-4 transition-all text-left group shadow-2xs hover:shadow-xs {{ $isDouble ? 'border-l-violet-600 bg-violet-50/20 dark:bg-violet-950/20 border-zinc-200/90 dark:border-zinc-800' : 'border-l-indigo-600 bg-white dark:bg-zinc-900 border-zinc-200/80 dark:border-zinc-800' }} border-y border-r">
                                        <div class="flex items-start justify-between gap-1">
                                            <div 
                                                wire:click="openLessonModal('{{ $day }}', '{{ $periodStr }}', {{ $entry->id }}, false)"
                                                class="font-black text-xs text-zinc-900 dark:text-white leading-tight truncate flex-1 cursor-pointer hover:text-indigo-600 transition"
                                                title="Click to edit lesson"
                                            >
                                                {{ $entry->subject }}
                                            </div>

                                            <div class="flex items-center gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity shrink-0">
                                                @if ($canEdit)
                                                    <button type="button" wire:click="openLessonModal('{{ $day }}', '{{ $periodStr }}', {{ $entry->id }}, false)" class="p-0.5 rounded text-zinc-400 hover:text-indigo-600 hover:bg-white dark:hover:bg-zinc-800 transition" title="Edit lesson slot">
                                                        <flux:icon icon="pencil-square" class="size-3" />
                                                    </button>
                                                @endif
                                                @if ($canDelete)
                                                    <button type="button" wire:click="deleteEntry({{ $entry->id }})" wire:confirm="Remove {{ addslashes($entry->subject) }} on {{ $day }} ({{ $periodStr }})?" class="p-0.5 rounded text-zinc-400 hover:text-rose-600 hover:bg-white dark:hover:bg-zinc-800 transition" title="Remove lesson slot">
                                                        <flux:icon icon="trash" class="size-3" />
                                                    </button>
                                                @endif
                                            </div>
                                        </div>

                                        {{-- Double Lesson Badge --}}
                                        @if ($isDouble)
                                            <div class="mt-1">
                                                <span class="inline-flex items-center gap-0.5 px-1 py-0.2 rounded text-[8px] sm:text-[9px] font-extrabold bg-violet-100 dark:bg-violet-950 text-violet-700 dark:text-violet-300 border border-violet-200 dark:border-violet-800/60 truncate max-w-full">
                                                    <flux:icon icon="link" class="size-2 shrink-0" />
                                                    {{ $isPart2 ? 'Double (2)' : '2x Double' }}
                                                </span>
                                            </div>
                                        @endif
                                        
                                        <div class="flex items-center justify-between gap-1 mt-1.5 pt-1 border-t border-zinc-100 dark:border-zinc-800 text-[10px]">
                                            <span class="font-medium text-zinc-600 dark:text-zinc-400 truncate flex-1" title="{{ $entry->teacher?->name ?? ($entry->schoolClass?->name ?? $entry->class_name) }}">
                                                <span translate="no">{{ $entry->teacher?->name ?? ($entry->schoolClass?->name ?? $entry->class_name) }}</span>
                                            </span>

                                            @if ($entry->room)
                                                <span class="inline-flex items-center gap-0.5 px-1 py-0.2 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-500 font-mono text-[9px] border border-zinc-200 dark:border-zinc-700 shrink-0">
                                                    <flux:icon icon="map-pin" class="size-2 text-zinc-400" />
                                                    {{ $entry->room }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            @else
                                {{-- Empty Period Slot: 1-Click Direct Add --}}
                                <button 
                                    type="button" 
                                    wire:click="openLessonModal('{{ $day }}', '{{ $periodStr }}')"
                                    class="w-full h-full min-h-[62px] sm:min-h-[70px] rounded-xl border border-dashed border-zinc-200/90 dark:border-zinc-800 hover:border-indigo-400 dark:hover:border-indigo-600 hover:bg-indigo-50/40 dark:hover:bg-indigo-950/20 flex flex-col items-center justify-center gap-0.5 text-zinc-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition group p-1"
                                    title="Click to schedule a lesson in this slot"
                                >
                                    <div class="size-5 rounded-full bg-zinc-100 dark:bg-zinc-800 group-hover:bg-indigo-100 dark:group-hover:bg-indigo-900/60 flex items-center justify-center group-hover:scale-110 transition-all">
                                        <flux:icon icon="plus" class="size-3 text-zinc-400 group-hover:text-indigo-600 dark:group-hover:text-indigo-400" />
                                    </div>
                                    <span class="text-[9px] font-bold opacity-0 group-hover:opacity-100 transition-opacity">Add</span>
                                </button>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
