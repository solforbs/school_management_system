@php
    /** @var array<string, mixed> $group */
    $sessions = $group['sessions'];
    $days = $group['days'];
    $weeks = $days->groupBy('week');
    $span = $sessions->count() + 1;
    $box = 'border border-zinc-300 dark:border-zinc-700';
@endphp

@if ($sessions->isEmpty())
    <x-empty-state icon="calendar" color="amber" title="No papers for this class"
        description="This class has no examinations under the current filters." />
@else
    <div class="overflow-x-auto p-4 sm:p-5">
        <table class="w-full border-collapse text-center min-w-[640px]">
            <thead>
                <tr class="bg-zinc-100 dark:bg-zinc-800">
                    <th class="{{ $box }} w-36 px-2 py-2 text-left text-[11px] font-bold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                        Day / Date
                    </th>
                    @foreach ($sessions as $session)
                        <th class="{{ $box }} px-2 py-2 text-[11px] font-bold uppercase tracking-wide text-zinc-600 dark:text-zinc-300 min-w-[130px]">
                            {{ $session }}
                        </th>
                    @endforeach
                </tr>
            </thead>

            <tbody>
                @foreach ($weeks as $weekNumber => $weekDays)
                    @php $weekDated = $weekDays->pluck('date')->filter(); @endphp
                    <tr class="bg-zinc-50 dark:bg-zinc-800/50">
                        <td colspan="{{ $span }}"
                            class="{{ $box }} px-2 py-1.5 text-left text-[11px] font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300">
                            @if ($weekNumber > 0)
                                Week {{ $weekNumber }}
                                @if ($weekDated->isNotEmpty())
                                    <span class="font-medium normal-case tracking-normal text-zinc-400">
                                        &mdash; {{ $weekDated->first()->format('D d M') }} to {{ $weekDated->last()->format('D d M Y') }}
                                    </span>
                                @endif
                            @else
                                Not scheduled
                            @endif
                        </td>
                    </tr>

                    @foreach ($weekDays as $day)
                        <tr class="{{ $day['is_today'] ? 'bg-amber-50/60 dark:bg-amber-950/20' : '' }}">
                            <td class="{{ $box }} bg-zinc-100/70 dark:bg-zinc-800/40 px-2 py-2 text-left align-middle">
                                @if ($day['date'])
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-sm font-bold text-zinc-900 dark:text-white">{{ $day['date']->format('l') }}</span>
                                        @if ($day['is_today'])
                                            <span class="size-1.5 rounded-full bg-amber-500"></span>
                                        @endif
                                    </div>
                                    <span class="block font-mono text-[11px] text-zinc-500">{{ $day['date']->format('d M Y') }}</span>
                                @else
                                    <span class="text-sm font-bold text-zinc-500">Date TBA</span>
                                @endif
                            </td>

                            @foreach ($sessions as $session)
                                @php $cell = $day['cells']->get($session); @endphp
                                <td class="{{ $box }} px-2 py-2 align-middle">
                                    @forelse ($cell ?? [] as $examination)
                                        <div class="{{ ! $loop->last ? 'mb-2 border-b border-dashed border-zinc-300 pb-2 dark:border-zinc-700' : '' }}">
                                            <span class="block text-xs font-bold uppercase tracking-wide text-zinc-900 dark:text-white sm:text-[13px]">
                                                {{ $examination->subject?->name ?? $examination->subject_name ?? $examination->title }}
                                            </span>
                                            <span class="block text-[10px] text-zinc-500 dark:text-zinc-400">{{ $examination->title }}</span>
                                            <span class="mt-0.5 block text-[10px] text-zinc-400">
                                                {{ $examination->durationLabel() ? $examination->durationLabel().' · ' : '' }}{{ $examination->total_marks }} mks
                                            </span>
                                        </div>
                                    @empty
                                        <span class="text-zinc-300 dark:text-zinc-600">&mdash;</span>
                                    @endforelse
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>
@endif
