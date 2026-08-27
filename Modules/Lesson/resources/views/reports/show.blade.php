<x-layouts::app :title="__('Lesson Report')">
    <div class="p-4">

        <div class="mb-4 flex items-center justify-between">
            <div>
                <flux:heading size="lg">
                    {{ $report->schoolClass?->name }} &mdash; {{ ucfirst($report->type) }} Report
                </flux:heading>
                <flux:text class="text-zinc-500">
                    {{ $report->period_start->format('d M Y') }}
                    @if ($report->isWeekly())
                        &ndash; {{ $report->period_end->format('d M Y') }}
                    @endif
                </flux:text>
            </div>

            <div class="flex gap-2">
                <flux:button href="{{ route('lesson.reports.index', ['class_id' => $report->class_id]) }}"
                    variant="ghost" wire:navigate>Back</flux:button>
                <flux:button href="{{ route('lesson.reports.download', $report->id) }}" icon="arrow-down-tray"
                    variant="primary">Download PDF</flux:button>
            </div>
        </div>

        <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <flux:card class="text-center">
                <flux:text class="text-zinc-500">Total Lessons</flux:text>
                <flux:heading size="xl">{{ $report->total_lessons }}</flux:heading>
            </flux:card>
            <flux:card class="text-center">
                <flux:text class="text-zinc-500">Attended</flux:text>
                <flux:heading size="xl" class="text-emerald-600">{{ $report->attended_count }}</flux:heading>
            </flux:card>
            <flux:card class="text-center">
                <flux:text class="text-zinc-500">Not Attended</flux:text>
                <flux:heading size="xl" class="text-red-600">{{ $report->not_attended_count }}</flux:heading>
            </flux:card>
            <flux:card class="text-center">
                <flux:text class="text-zinc-500">Recovered</flux:text>
                <flux:heading size="xl" class="text-amber-600">{{ $report->recovered_count }}</flux:heading>
            </flux:card>
        </div>

        @foreach ($days as $day)
            <flux:card class="mb-4">
                <flux:heading size="lg" class="mb-3">{{ $day['date']->format('l, d M Y') }}</flux:heading>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-sm">
                        <thead>
                            <tr>
                                <th class="border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 px-3 py-2 text-left">
                                    Time</th>
                                <th class="border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 px-3 py-2 text-left">
                                    Subject</th>
                                <th class="border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 px-3 py-2 text-left">
                                    Teacher</th>
                                <th class="border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 px-3 py-2 text-left">
                                    Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($day['periods'] as $period)
                                <tr>
                                    <td class="border border-zinc-200 dark:border-zinc-700 px-3 py-2 whitespace-nowrap">
                                        {{ $period['entry']->start_time?->format('H:i') }}&ndash;{{ $period['entry']->end_time?->format('H:i') }}
                                    </td>
                                    <td class="border border-zinc-200 dark:border-zinc-700 px-3 py-2 font-medium">
                                        {{ $period['entry']->subject }}
                                    </td>
                                    <td class="border border-zinc-200 dark:border-zinc-700 px-3 py-2">
                                        {{ $period['entry']->teacher?->name ?? '—' }}
                                    </td>
                                    <td class="border border-zinc-200 dark:border-zinc-700 px-3 py-2">
                                        <flux:badge :color="$period['lesson']?->statusColor() ?? 'red'">
                                            {{ $period['lesson']?->statusLabel() ?? 'Not Attended' }}
                                        </flux:badge>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </flux:card>
        @endforeach
    </div>
</x-layouts::app>
