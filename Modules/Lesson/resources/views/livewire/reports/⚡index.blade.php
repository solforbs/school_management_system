<?php

use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Classes\Models\SchoolClass;
use Modules\Lesson\Models\LessonReport;
use Modules\Lesson\Services\LessonReportService;
use Modules\Student\Models\StudentDetails;

new #[Title('Lesson Reports')] class extends Component
{
    /** @var string[] */
    public const SORTABLE = [
        'type', 'period_start', 'total_lessons',
        'attended_count', 'not_attended_count', 'recovered_count',
    ];

    #[Url(as: 'class_id')]
    public string $classId = '';

    #[Url]
    public string $sort = 'period_start';

    #[Url]
    public string $direction = 'desc';

    // Generator form
    public string $type = 'weekly';

    public string $date = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('view lesson'), 403);

        if ($this->date === '') {
            $this->date = Carbon::today()->toDateString();
        }

        if ($this->classId === '') {
            $this->classId = (string) ($this->classes->first()?->id ?? '');
        }
    }

    public function sortBy(string $column): void
    {
        if (! in_array($column, self::SORTABLE, true)) {
            return;
        }

        if ($this->sort === $column) {
            $this->direction = $this->direction === 'asc' ? 'desc' : 'asc';

            return;
        }

        $this->sort = $column;
        $this->direction = 'asc';
    }

    public function generate(LessonReportService $reportService): void
    {
        abort_unless(auth()->user()->can('export lesson'), 403);

        $validated = $this->validate([
            'type' => 'required|in:daily,weekly',
            'date' => 'required|date',
        ]);

        $class = $this->selectedClass;
        abort_unless($class, 403);

        $date = Carbon::parse($validated['date']);

        // A daily report covers the one day; a weekly one covers that day's
        // Monday to Friday.
        if ($validated['type'] === 'daily') {
            $start = $date->copy();
            $end = $date->copy();
        } else {
            $start = $date->copy()->startOfWeek(Carbon::MONDAY);
            $end = $start->copy()->addDays(4);
        }

        $stats = $reportService->compute($class, $start->copy(), $end->copy());

        $report = LessonReport::updateOrCreate(
            ['class_id' => $class->id, 'type' => $validated['type'], 'period_start' => $start->toDateString()],
            [
                'institution_id' => $class->institution_id,
                'period_end' => $end->toDateString(),
                'total_lessons' => $stats['total'],
                'attended_count' => $stats['attended'],
                'not_attended_count' => $stats['notAttended'],
                'recovered_count' => $stats['recovered'],
                'generated_by' => auth()->id(),
                'generated_at' => now(),
            ]
        );

        session()->flash('success', 'Report generated.');

        $this->redirectRoute('lesson.reports.show', $report->id, navigate: true);
    }

    /**
     * @return Collection<int, SchoolClass>
     */
    #[Computed]
    public function classes(): Collection
    {
        $user = auth()->user();

        if ($user->hasRole('Parent')) {
            $classIds = StudentDetails::where('parent_id', $user->id)
                ->pluck('class_id')->filter()->map(fn ($id) => (int) $id)->unique();

            return SchoolClass::whereIn('id', $classIds)->orderBy('name')->get();
        }

        if ($user->hasRole('Student')) {
            $classId = StudentDetails::where('student_id', $user->id)->value('class_id');

            return SchoolClass::whereIn('id', array_filter([(int) $classId]))->orderBy('name')->get();
        }

        $query = SchoolClass::query();

        if ($user->hasRole('Teacher')) {
            $query->where('institution_id', $user->teacherUserDetails?->institution_id ?? 0);
        } elseif (! isAdmin()) {
            $query->where('institution_id', currentInstitution()?->id ?? 0);
        }

        return $query->orderBy('name')->get();
    }

    #[Computed]
    public function selectedClass(): ?SchoolClass
    {
        return $this->classId === '' ? null : $this->classes->firstWhere('id', (int) $this->classId);
    }

    /**
     * @return Collection<int, LessonReport>
     */
    #[Computed]
    public function reports(): Collection
    {
        $class = $this->selectedClass;

        if (! $class) {
            return collect();
        }

        $sort = in_array($this->sort, self::SORTABLE, true) ? $this->sort : 'period_start';
        $direction = $this->direction === 'asc' ? 'asc' : 'desc';

        return LessonReport::where('class_id', $class->id)
            ->orderBy($sort, $direction)
            ->limit(30)
            ->get();
    }
}; ?>

<div class="p-4">
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <flux:select wire:model.live="classId" label="Class" class="max-w-xs">
            <flux:select.option value="">Select a class&hellip;</flux:select.option>
            @foreach ($this->classes as $schoolClass)
                <flux:select.option value="{{ $schoolClass->id }}">{{ $schoolClass->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:button href="{{ route('lesson.index', ['class_id' => $this->selectedClass?->id]) }}" icon="clock"
            variant="ghost" wire:navigate>
            Mark Attendance
        </flux:button>
    </div>

    @can('export lesson')
        @if ($this->selectedClass)
            <flux:card class="mb-6">
                <flux:heading size="lg" class="mb-4">Generate a Report</flux:heading>

                <form wire:submit="generate" class="grid grid-cols-1 items-end gap-3 md:grid-cols-4">
                    <flux:select label="Type" wire:model="type">
                        <flux:select.option value="daily">Daily</flux:select.option>
                        <flux:select.option value="weekly">Weekly</flux:select.option>
                    </flux:select>
                    <flux:input type="date" label="Date" wire:model="date"
                        description="A weekly report covers that date's Monday to Friday." />
                    <flux:button type="submit" variant="primary" icon="document-chart-bar">
                        <span wire:loading.remove wire:target="generate">Generate</span>
                        <span wire:loading wire:target="generate">Generating…</span>
                    </flux:button>
                </form>
            </flux:card>
        @endif
    @endcan

    @if (! $this->selectedClass)
        <flux:card class="py-10 text-center">
            <flux:text class="text-zinc-500">Select a class above to see its reports.</flux:text>
        </flux:card>
    @else
        <flux:card>
            <flux:heading size="lg" class="mb-4">{{ $this->selectedClass->name }} Reports</flux:heading>

            <flux:table wire:loading.class="opacity-60">
                <flux:table.columns>
                    <flux:table.column sortable :sorted="$sort === 'type'" :direction="$direction"
                        wire:click="sortBy('type')">Type</flux:table.column>
                    <flux:table.column sortable :sorted="$sort === 'period_start'" :direction="$direction"
                        wire:click="sortBy('period_start')">Period</flux:table.column>
                    <flux:table.column sortable :sorted="$sort === 'total_lessons'" :direction="$direction"
                        wire:click="sortBy('total_lessons')">Lessons</flux:table.column>
                    <flux:table.column sortable :sorted="$sort === 'attended_count'" :direction="$direction"
                        wire:click="sortBy('attended_count')">Attended</flux:table.column>
                    <flux:table.column sortable :sorted="$sort === 'not_attended_count'" :direction="$direction"
                        wire:click="sortBy('not_attended_count')">Missed</flux:table.column>
                    <flux:table.column sortable :sorted="$sort === 'recovered_count'" :direction="$direction"
                        wire:click="sortBy('recovered_count')">Recovered</flux:table.column>
                    <flux:table.column>Actions</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->reports as $report)
                        <flux:table.row :key="$report->id">
                            <flux:table.cell>{{ ucfirst($report->type) }}</flux:table.cell>
                            <flux:table.cell>
                                {{ $report->period_start?->format('d M Y') }}
                                @if ($report->period_end && ! $report->period_end->equalTo($report->period_start))
                                    – {{ $report->period_end->format('d M Y') }}
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>{{ $report->total_lessons }}</flux:table.cell>
                            <flux:table.cell>{{ $report->attended_count }}</flux:table.cell>
                            <flux:table.cell>{{ $report->not_attended_count }}</flux:table.cell>
                            <flux:table.cell>{{ $report->recovered_count }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:button href="{{ route('lesson.reports.show', $report->id) }}" icon="eye"
                                    size="sm" variant="ghost" wire:navigate>view</flux:button>
                                @can('export lesson')
                                    {{-- A file download, so this one stays an ordinary link. --}}
                                    <flux:button href="{{ route('lesson.reports.download', $report->id) }}"
                                        icon="arrow-down-tray" size="sm" variant="ghost">pdf</flux:button>
                                @endcan
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="7" class="text-center text-gray-500">
                                No reports for this class yet.
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endif
</div>
