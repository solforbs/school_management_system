@php
    /** @var array<string, mixed> $group */
@endphp

<flux:table>
    <flux:table.columns>
        <flux:table.column>Date</flux:table.column>
        <flux:table.column>Time</flux:table.column>
        <flux:table.column>Duration</flux:table.column>
        <flux:table.column>Subject</flux:table.column>
        <flux:table.column>Paper</flux:table.column>
        <flux:table.column>Sitting</flux:table.column>
        <flux:table.column>Marks</flux:table.column>
    </flux:table.columns>
    <flux:table.rows>
        @foreach ($group['examinations'] as $examination)
            <flux:table.row :key="$examination->id">
                <flux:table.cell>
                    @if ($examination->exam_date)
                        {{ $examination->exam_date->format('D, d M Y') }}
                    @else
                        <flux:text class="text-zinc-500">Not scheduled</flux:text>
                    @endif
                </flux:table.cell>
                <flux:table.cell>
                    @if ($examination->start_time && $examination->end_time)
                        {{ $examination->start_time->format('H:i') }} – {{ $examination->end_time->format('H:i') }}
                    @elseif ($examination->start_time)
                        From {{ $examination->start_time->format('H:i') }}
                    @else
                        —
                    @endif
                </flux:table.cell>
                <flux:table.cell>{{ $examination->durationLabel() ?? '—' }}</flux:table.cell>
                <flux:table.cell>
                    {{ $examination->subject?->name ?? ($examination->subject_name ?? '—') }}
                </flux:table.cell>
                <flux:table.cell>{{ $examination->title }}</flux:table.cell>
                <flux:table.cell>
                    <flux:badge :color="$examination->exam_type ? 'blue' : 'zinc'" size="sm">
                        {{ $examination->examTypeLabel() }}
                    </flux:badge>
                </flux:table.cell>
                <flux:table.cell>{{ $examination->total_marks }}</flux:table.cell>
            </flux:table.row>
        @endforeach
    </flux:table.rows>
</flux:table>
