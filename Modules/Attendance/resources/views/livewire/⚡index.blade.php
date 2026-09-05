<?php

use Athwari\LaravelZktecoAdms\Enums\AttendanceStatus;
use Athwari\LaravelZktecoAdms\Models\ZktecoAttendanceLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Attendance\Services\AttendanceLogQuery;
use Modules\Classes\Models\SchoolClass;
use Modules\Institution\Models\Institution;

new #[Title('Attendance Logs')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $class_id = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    /** @var array<int, int> */
    public array $selected = [];

    public bool $selectAllOnPage = false;

    public ?int $confirmingId = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->can('view attendance'), 403);
    }

    public function updated($property): void
    {
        if (in_array($property, ['search', 'status', 'class_id', 'from', 'to'], true)) {
            $this->resetPage();
            $this->selected = [];
            $this->selectAllOnPage = false;
        }
    }

    public function setDateRange(string $from = '', string $to = ''): void
    {
        $this->from = $from;
        $this->to = $to;
        $this->resetPage();
    }

    #[Computed]
    public function institution(): ?Institution
    {
        return currentInstitution();
    }

    /**
     * The shared, permission-scoped query every count/list below narrows
     * further - never hand-roll institution/pin scoping here again (that
     * used to live duplicated in this component, which is how Admin ended
     * up able to see every institution's attendance with no filter at all).
     */
    #[Computed]
    public function baseQuery(): ?Builder
    {
        $institutionId = $this->institution?->id;

        if (! $institutionId) {
            return null;
        }

        return AttendanceLogQuery::build($institutionId, auth()->user(), [
            'search' => $this->search,
            'from' => $this->from ?: null,
            'to' => $this->to ?: null,
        ]);
    }

    private function filteredQuery(): ?Builder
    {
        $query = $this->baseQuery;

        if ($query === null) {
            return null;
        }

        $query = clone $query;

        if ($this->status !== '') {
            $case = collect(AttendanceStatus::cases())->first(fn ($c) => $c->name === $this->status);
            if ($case !== null) {
                $query->where('status', $case->value);
            }
        }

        if ($this->class_id !== '') {
            $query->whereHas(
                'zktecoUser.appUser.studentUserDetails',
                fn ($q) => $q->where('class_id', $this->class_id)
            );
        }

        return $query;
    }

    #[Computed]
    public function logs(): LengthAwarePaginator
    {
        $query = $this->filteredQuery();

        if ($query === null) {
            return new LengthAwarePaginator([], 0, 15);
        }

        $rawLogs = $query->orderByDesc('occurred_at')->paginate(15);

        $rawLogs->getCollection()->transform(
            fn (ZktecoAttendanceLog $log) => AttendanceLogQuery::toDisplayRow($log)
        );

        return $rawLogs;
    }

    #[Computed]
    public function classes(): array
    {
        $institutionId = $this->institution?->id;

        if (! $institutionId) {
            return [];
        }

        return SchoolClass::where('institution_id', $institutionId)->orderBy('name')->get()->all();
    }

    #[Computed]
    public function totalLogs(): int
    {
        $query = $this->baseQuery;

        return $query === null ? 0 : (clone $query)->count();
    }

    #[Computed]
    public function todayLogs(): int
    {
        $query = $this->baseQuery;

        return $query === null ? 0 : (clone $query)->whereDate('occurred_at', today())->count();
    }

    #[Computed]
    public function presentToday(): int
    {
        $query = $this->baseQuery;

        return $query === null ? 0 : (clone $query)
            ->whereDate('occurred_at', today())
            ->where('status', AttendanceStatus::CheckIn->value)
            ->count();
    }

    /**
     * Toggle every row on the current page. Only ids from this page are
     * ever added, so "select all" never silently reaches beyond what the
     * Director can actually see confirmed on screen.
     */
    public function updatedSelectAllOnPage(bool $value): void
    {
        $pageIds = $this->logs->pluck('id')->all();

        $this->selected = $value
            ? array_values(array_unique([...$this->selected, ...$pageIds]))
            : array_values(array_diff($this->selected, $pageIds));
    }

    public function confirmDelete(int $id): void
    {
        $this->confirmingId = $id;
    }

    public function deleteLog(): void
    {
        abort_unless(auth()->user()->can('delete attendance'), 403);

        if ($this->confirmingId === null) {
            return;
        }

        $this->deleteIds([$this->confirmingId]);
        $this->confirmingId = null;
    }

    public function deleteSelected(): void
    {
        abort_unless(auth()->user()->can('delete attendance'), 403);

        $this->deleteIds($this->selected);
        $this->selected = [];
        $this->selectAllOnPage = false;
    }

    /**
     * Delete only ids that fall inside this user's own permission-scoped
     * query - a crafted id for another institution's log simply never
     * matches and is silently skipped, rather than trusted at face value.
     *
     * @param  array<int, int>  $ids
     */
    private function deleteIds(array $ids): void
    {
        $query = $this->baseQuery;

        if ($query === null || $ids === []) {
            return;
        }

        (clone $query)->whereIn('id', $ids)->delete();

        unset($this->logs, $this->totalLogs, $this->todayLogs, $this->presentToday);
    }
}; ?>

@php
    $logs = $this->logs;
    $classes = $this->classes;
    $totalLogs = $this->totalLogs;
    $todayLogs = $this->todayLogs;
    $presentToday = $this->presentToday;

    $attendanceRate = $todayLogs > 0 ? round(($presentToday / $todayLogs) * 100) : 100;

    $canTakeAttendance = Route::has('attendance.take') && auth()->user()->can('create attendance');
    $canDelete = auth()->user()->can('delete attendance');

    $todayStr = now()->toDateString();
    $yesterdayStr = now()->subDay()->toDateString();
    $startOfWeekStr = now()->startOfWeek()->toDateString();
    $endOfWeekStr = now()->endOfWeek()->toDateString();
    $startOfMonthStr = now()->startOfMonth()->toDateString();
    $endOfMonthStr = now()->endOfMonth()->toDateString();

    $isToday = $from === $todayStr && $to === $todayStr;
    $isYesterday = $from === $yesterdayStr && $to === $yesterdayStr;
    $isThisWeek = $from === $startOfWeekStr && $to === $endOfWeekStr;
    $isThisMonth = $from === $startOfMonthStr && $to === $endOfMonthStr;
    $isAllTime = $from === '' && $to === '';
@endphp

<div class="flex flex-col gap-6 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8" x-data
    x-on:keydown.window.slash.prevent="$refs.searchInput?.focus()">

    {{-- Header Section --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <flux:heading size="xl" class="font-black tracking-tight text-zinc-900 dark:text-white">Attendance
                    Stream</flux:heading>
                <flux:badge size="sm" color="emerald" class="font-semibold">
                    <span class="size-1.5 rounded-full bg-emerald-500 mr-1.5 animate-pulse"></span>
                    Live Feed
                </flux:badge>
            </div>
            <flux:text class="text-zinc-500 text-sm mt-1">
                Real-time biometric scans, gate check-in logs, and classroom presence tracking.
            </flux:text>
        </div>

        <div class="flex items-center gap-3">
            <x-export-buttons base-route="attendance.export" module="attendance" label="Export Logs" :params="[
                'search' => $search,
                'status' => $status,
                'class_id' => $class_id,
                'from' => $from,
                'to' => $to,
            ]" />
            @if ($canTakeAttendance)
                <flux:button href="{{ route('attendance.take') }}" wire:navigate.hover icon="clipboard-document-check"
                    variant="primary" class="shadow-sm hover:shadow-md transition-all">
                    Take Roll Call
                </flux:button>
            @endif
        </div>
    </div>

    @if (! $this->institution)
        <div class="bg-amber-50 dark:bg-amber-500/10 border-l-4 border-amber-500 p-4 rounded-r-xl flex items-start shadow-sm">
            <flux:icon icon="exclamation-triangle" class="size-5 text-amber-500 mr-3 mt-0.5 shrink-0" />
            <div>
                <h3 class="text-sm font-bold text-amber-800 dark:text-amber-400">No active institution found</h3>
                <p class="text-xs text-amber-700 dark:text-amber-500 mt-1">
                    Attendance records could not be loaded because no institution is currently assigned to your account.
                </p>
            </div>
        </div>
    @else
        {{-- Animated KPI Metrics Grid --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-kpi-card title="Today's Check-ins" icon="clock" color="violet" :count="$todayLogs" subtext="total daily logs" />
            <x-kpi-card title="Present On Campus" icon="check-circle" color="emerald" :count="$presentToday"
                subtext="marked in attendance" />
            <x-kpi-card title="Attendance Rate" icon="chart-bar" color="indigo" :count="$attendanceRate" countSuffix="%"
                subtext="daily turn-out percentage" />
            <x-kpi-card title="Total Record Archive" icon="document-text" color="sky" :count="$totalLogs"
                subtext="lifetime attendance events" />
        </div>

        {{-- Filter Toolbar --}}
        <div class="bg-white dark:bg-zinc-900 shadow-[0_1px_3px_0_rgba(0,0,0,0.05)] border border-zinc-200 dark:border-zinc-800 rounded-xl p-4 space-y-3.5">
            {{-- Quick Date Preset Pills --}}
            <div class="flex flex-wrap items-center gap-1.5 text-xs">
                <span class="text-zinc-400 font-medium mr-1 flex items-center gap-1">
                    <flux:icon icon="calendar" class="size-3.5" />
                    Preset:
                </span>
                <button type="button" wire:click="setDateRange"
                    class="px-2.5 py-1 rounded-lg font-medium transition-all {{ $isAllTime ? 'bg-indigo-600 text-white shadow-xs' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700' }}">
                    All Time
                </button>
                <button type="button" wire:click="setDateRange('{{ $todayStr }}', '{{ $todayStr }}')"
                    class="px-2.5 py-1 rounded-lg font-medium transition-all {{ $isToday ? 'bg-indigo-600 text-white shadow-xs' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700' }}">
                    ⚡ Today
                </button>
                <button type="button" wire:click="setDateRange('{{ $yesterdayStr }}', '{{ $yesterdayStr }}')"
                    class="px-2.5 py-1 rounded-lg font-medium transition-all {{ $isYesterday ? 'bg-indigo-600 text-white shadow-xs' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700' }}">
                    Yesterday
                </button>
                <button type="button" wire:click="setDateRange('{{ $startOfWeekStr }}', '{{ $endOfWeekStr }}')"
                    class="px-2.5 py-1 rounded-lg font-medium transition-all {{ $isThisWeek ? 'bg-indigo-600 text-white shadow-xs' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700' }}">
                    This Week
                </button>
                <button type="button" wire:click="setDateRange('{{ $startOfMonthStr }}', '{{ $endOfMonthStr }}')"
                    class="px-2.5 py-1 rounded-lg font-medium transition-all {{ $isThisMonth ? 'bg-indigo-600 text-white shadow-xs' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700' }}">
                    This Month
                </button>
            </div>

            <div class="flex flex-col lg:flex-row items-stretch lg:items-center gap-3 pt-1 border-t border-zinc-100 dark:border-zinc-800">
                <div class="flex-1 relative">
                    <flux:input x-ref="searchInput" wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                        placeholder="Search student name, ADM... (Press '/' to focus)" />
                </div>

                <flux:select wire:model.live="status" placeholder="All Statuses" class="w-full sm:w-40">
                    <flux:select.option value="">All Statuses</flux:select.option>
                    <flux:select.option value="CheckIn">Check In</flux:select.option>
                    <flux:select.option value="CheckOut">Check Out</flux:select.option>
                    <flux:select.option value="OvertimeIn">Overtime In</flux:select.option>
                    <flux:select.option value="OvertimeOut">Overtime Out</flux:select.option>
                    <flux:select.option value="BreakIn">Break In</flux:select.option>
                    <flux:select.option value="BreakOut">Break Out</flux:select.option>
                </flux:select>

                <flux:select wire:model.live="class_id" placeholder="All Classes" class="w-full sm:w-44">
                    <flux:select.option value="">All Classes</flux:select.option>
                    @foreach ($classes as $class)
                        <flux:select.option value="{{ $class->id }}">{{ $class->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <div class="flex items-center gap-2">
                    <flux:input type="date" wire:model.live="from" class="w-full sm:w-40" />
                    <span class="text-zinc-400 text-xs font-medium">to</span>
                    <flux:input type="date" wire:model.live="to" class="w-full sm:w-40" />
                </div>
            </div>
        </div>

        {{-- Bulk action bar --}}
        @if ($canDelete && count($selected) > 0)
            <div class="flex items-center justify-between bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 rounded-xl px-4 py-2.5">
                <flux:text class="text-red-700 dark:text-red-400 text-sm font-medium">
                    {{ count($selected) }} {{ Str::plural('record', count($selected)) }} selected
                </flux:text>
                <flux:modal.trigger name="confirm-delete-selected">
                    <flux:button type="button" size="sm" variant="danger" icon="trash">Delete Selected</flux:button>
                </flux:modal.trigger>
            </div>
        @endif

        {{-- Attendance Table Card --}}
        <div class="bg-white dark:bg-zinc-900 shadow-[0_1px_3px_0_rgba(0,0,0,0.05)] border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden flex flex-col">
            <div class="overflow-x-auto">
                <flux:table class="p-2">
                    <flux:table.columns>
                        @if ($canDelete)
                            <flux:table.column class="w-8">
                                <input type="checkbox" wire:model.live="selectAllOnPage"
                                    class="rounded border-zinc-300 dark:border-zinc-600" />
                            </flux:table.column>
                        @endif
                        <flux:table.column>Learner Profile</flux:table.column>
                        <flux:table.column>Access Point / Device</flux:table.column>
                        <flux:table.column>Status</flux:table.column>
                        <flux:table.column>Verify Mode</flux:table.column>
                        <flux:table.column>Timestamp</flux:table.column>
                        @if ($canDelete)
                            <flux:table.column>Actions</flux:table.column>
                        @endif
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($logs as $log)
                            <flux:table.row class="transition-colors hover:bg-zinc-50/50 dark:hover:bg-zinc-800/20 group">
                                @if ($canDelete)
                                    <flux:table.cell>
                                        <input type="checkbox" wire:model.live="selected" value="{{ $log['id'] }}"
                                            class="rounded border-zinc-300 dark:border-zinc-600" />
                                    </flux:table.cell>
                                @endif

                                <flux:table.cell>
                                    @php
                                        $initials = collect(explode(' ', $log['student_name']))
                                            ->map(fn($w) => strtoupper(substr($w, 0, 1)))
                                            ->take(2)
                                            ->join('');
                                    @endphp
                                    <div class="flex items-center gap-3">
                                        <div class="size-8 rounded-full bg-violet-50 dark:bg-violet-900/30 text-violet-600 dark:text-violet-400 ring-1 ring-inset ring-violet-500/20 flex items-center justify-center font-bold text-xs transition-transform group-hover:scale-105 shrink-0">
                                            {{ $initials }}
                                        </div>
                                        <div class="flex flex-col">
                                            <span class="font-semibold text-sm text-zinc-900 dark:text-white leading-snug">{{ $log['student_name'] }}</span>
                                            <span class="font-mono text-[11px] text-zinc-500 dark:text-zinc-400 leading-normal">{{ $log['admission_number'] }}</span>
                                        </div>
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell>
                                    <div class="flex items-center gap-2 text-zinc-600 dark:text-zinc-300 text-xs">
                                        <flux:icon icon="cpu-chip" class="size-4 text-zinc-400 shrink-0" />
                                        <span class="truncate max-w-[220px]">{{ $log['device_name'] }}</span>
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell>
                                    <flux:badge :color="$log['status_color']" size="sm" class="font-semibold text-[11px]">
                                        {{ $log['status_label'] }}
                                    </flux:badge>
                                </flux:table.cell>

                                <flux:table.cell>
                                    <div class="inline-flex items-center gap-1.5 text-xs font-medium text-zinc-600 dark:text-zinc-400 bg-zinc-100 dark:bg-zinc-800 px-2.5 py-1 rounded-md border border-zinc-200/50 dark:border-zinc-700/50">
                                        @if (stripos($log['verify_mode_label'], 'finger') !== false)
                                            <flux:icon icon="finger-print" class="size-3.5 text-zinc-500 shrink-0" />
                                        @elseif(stripos($log['verify_mode_label'], 'card') !== false)
                                            <flux:icon icon="identification" class="size-3.5 text-zinc-500 shrink-0" />
                                        @elseif(stripos($log['verify_mode_label'], 'face') !== false)
                                            <flux:icon icon="user" class="size-3.5 text-zinc-500 shrink-0" />
                                        @elseif(stripos($log['verify_mode_label'], 'password') !== false)
                                            <flux:icon icon="key" class="size-3.5 text-zinc-500 shrink-0" />
                                        @else
                                            <flux:icon icon="check-badge" class="size-3.5 text-zinc-500 shrink-0" />
                                        @endif
                                        <span>{{ $log['verify_mode_label'] }}</span>
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell>
                                    <div class="flex flex-col text-xs">
                                        <span class="font-mono font-medium text-zinc-900 dark:text-zinc-100">{{ $log['occurred_at'] }}</span>
                                    </div>
                                </flux:table.cell>

                                @if ($canDelete)
                                    <flux:table.cell>
                                        <flux:modal.trigger name="confirm-delete-log">
                                            <flux:button type="button" size="sm" icon="trash" variant="ghost"
                                                class="text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10"
                                                wire:click="confirmDelete({{ $log['id'] }})">
                                            </flux:button>
                                        </flux:modal.trigger>
                                    </flux:table.cell>
                                @endif
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="{{ $canDelete ? 7 : 5 }}">
                                    <x-empty-state icon="clock" color="violet" title="No Attendance Records"
                                        description="Real-time check-in logs and biometric scans will appear here automatically.">
                                        @if ($canTakeAttendance)
                                            <flux:button href="{{ route('attendance.take') }}" wire:navigate.hover
                                                icon="clipboard-document-check" variant="primary">
                                                Take Attendance
                                            </flux:button>
                                        @endif
                                    </x-empty-state>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>

            @if ($logs->hasPages())
                <div class="p-4 border-t border-zinc-200 dark:border-zinc-800">
                    {{ $logs->links() }}
                </div>
            @endif
        </div>
    @endif

    @if ($canDelete)
        <flux:modal name="confirm-delete-log" class="max-w-md" wire:close="$set('confirmingId', null)">
            <div class="space-y-5">
                <div>
                    <flux:heading size="lg">Delete this attendance record?</flux:heading>
                    <flux:subheading>This permanently removes it from your records. It does not affect anything stored on the device.</flux:subheading>
                </div>
                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button type="button" variant="filled">Cancel</flux:button>
                    </flux:modal.close>
                    <flux:modal.close>
                        <flux:button type="button" variant="danger" wire:click="deleteLog">Delete Record</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        </flux:modal>

        <flux:modal name="confirm-delete-selected" class="max-w-md">
            <div class="space-y-5">
                <div>
                    <flux:heading size="lg">Delete {{ count($selected) }} selected {{ Str::plural('record', count($selected)) }}?</flux:heading>
                    <flux:subheading>This permanently removes them from your records. It does not affect anything stored on the device.</flux:subheading>
                </div>
                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button type="button" variant="filled">Cancel</flux:button>
                    </flux:modal.close>
                    <flux:modal.close>
                        <flux:button type="button" variant="danger" wire:click="deleteSelected">Delete Selected</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
