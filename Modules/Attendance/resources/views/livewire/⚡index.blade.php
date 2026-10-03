<?php

use Athwari\LaravelZktecoAdms\Enums\AttendanceStatus;
use Athwari\LaravelZktecoAdms\Models\ZktecoAttendanceLog;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Attendance\Services\AttendanceLogQuery;
use Modules\Attendance\Services\ClassAttendanceService;
use Modules\Classes\Models\SchoolClass;
use Modules\Institution\Models\Institution;

new #[Title('Attendance Logs')] class extends Component
{
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

    /** Set once the operator opts into "every matching record", not just this page. */
    public bool $selectAllMatching = false;

    public ?int $confirmingId = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->can('view attendance'), 403);

        if (auth()->user()->hasRole('Teacher') && empty($this->class_id)) {
            $primaryClass = app(ClassAttendanceService::class)->resolveClassForUser(auth()->user());
            if ($primaryClass) {
                $this->class_id = (string) $primaryClass->id;
            }
        }
    }

    public function updated($property): void
    {
        if (in_array($property, ['search', 'status', 'class_id', 'from', 'to'], true)) {
            $this->resetPage();
            $this->selected = [];
            $this->selectAllOnPage = false;
            $this->selectAllMatching = false;
        }
    }

    public function setDateRange(string $from = '', string $to = ''): void
    {
        $this->from = $from;
        $this->to = $to;
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->status = '';
        $this->class_id = '';
        $this->from = '';
        $this->to = '';
        $this->resetPage();
        $this->selected = [];
        $this->selectAllOnPage = false;
        $this->selectAllMatching = false;
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

        return app(ClassAttendanceService::class)
            ->getSelectableClasses(auth()->user())
            ->all();
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
     * How many records match the filters currently applied - what
     * "Delete all" will actually remove.
     */
    #[Computed]
    public function filteredCount(): int
    {
        $query = $this->filteredQuery();

        return $query === null ? 0 : (clone $query)->count();
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

        if (! $value) {
            $this->selectAllMatching = false;
        }
    }

    public function confirmDelete(int $id): void
    {
        $this->confirmingId = $id;
    }

    public function deleteLog(): void
    {
        abort_unless(auth()->user()->can('delete attendance'), 403);

        $id = $this->confirmingId;
        $this->confirmingId = null;

        if ($id !== null) {
            $this->purge($this->baseQuery, [$id]);
        }

        Flux::modal('confirm-delete-log')->close();
    }

    public function deleteSelected(): void
    {
        abort_unless(auth()->user()->can('delete attendance'), 403);

        $this->purge($this->baseQuery, $this->selected);

        $this->selected = [];
        $this->selectAllOnPage = false;
        $this->selectAllMatching = false;

        Flux::modal('confirm-delete-selected')->close();
    }

    /**
     * Remove every record that matches the filters on screen - not just
     * the current page - within this user's own permission scope.
     */
    public function deleteAll(): void
    {
        abort_unless(auth()->user()->can('delete attendance'), 403);

        $query = $this->filteredQuery();

        if ($query !== null) {
            (clone $query)
                ->pluck('id')
                ->chunk(500)
                ->each(fn ($chunk) => ZktecoAttendanceLog::whereKey($chunk->all())->delete());
        }

        $this->selected = [];
        $this->selectAllOnPage = false;
        $this->selectAllMatching = false;
        $this->resetPage();
        $this->forgetCounts();

        Flux::modal('confirm-delete-all')->close();
    }

    /**
     * Hard-delete the given ids, but only those that fall inside this
     * user's permission-scoped query - a crafted id for another
     * institution's log never matches and is silently skipped.
     *
     * @param  array<int, int>  $ids
     */
    private function purge(?Builder $scope, array $ids): void
    {
        if ($scope === null || $ids === []) {
            return;
        }

        $scopedIds = (clone $scope)->whereIn('id', $ids)->pluck('id');

        if ($scopedIds->isNotEmpty()) {
            ZktecoAttendanceLog::whereKey($scopedIds->all())->delete();
        }

        $this->forgetCounts();
    }

    private function forgetCounts(): void
    {
        unset($this->logs, $this->totalLogs, $this->todayLogs, $this->presentToday, $this->filteredCount);
    }
}; ?>

@php
    $logs = $this->logs;
    $classes = $this->classes;
    $totalLogs = $this->totalLogs;
    $todayLogs = $this->todayLogs;
    $presentToday = $this->presentToday;

    $attendanceRate = $todayLogs > 0 ? (int) round(($presentToday / $todayLogs) * 100) : 0;

    $canTakeAttendance = Route::has('attendance.register') && auth()->user()->can('view attendance');
    $canDelete = auth()->user()->can('delete attendance');
    $filteredCount = $canDelete ? $this->filteredCount : 0;
    $hasActiveFilters = $search !== '' || $status !== '' || $class_id !== '' || $from !== '' || $to !== '';

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
                <flux:heading size="xl" class="font-black tracking-tight text-zinc-900 dark:text-white">Attendance Stream</flux:heading>
                <flux:badge size="sm" color="emerald" class="font-semibold">
                    <span class="size-1.5 rounded-full bg-emerald-500 mr-1.5 animate-pulse"></span>
                    Live Feed
                </flux:badge>
            </div>
            <flux:text class="text-zinc-500 text-sm mt-1">
                Real-time biometric scans, gate check-in logs, and classroom presence tracking.
            </flux:text>
        </div>

        <div class="flex flex-wrap items-center gap-2 sm:gap-3 w-full sm:w-auto">
            {{-- Tab Switcher: Register vs Hardware Logs --}}
            <div class="inline-flex rounded-xl bg-zinc-100 dark:bg-zinc-800 p-1 border border-zinc-200 dark:border-zinc-700">
                <a href="{{ route('attendance.register') }}" wire:navigate.hover class="px-3 py-1.5 rounded-lg text-xs font-semibold text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white transition">
                    Roll-Call Register
                </a>
                <span class="px-3 py-1.5 rounded-lg text-xs font-bold bg-white dark:bg-zinc-900 text-blue-600 dark:text-blue-400 shadow-xs border border-zinc-200/80 dark:border-zinc-700/80">
                    Biometric Logs
                </span>
            </div>

            <x-export-buttons base-route="attendance.export" module="attendance" label="Export Logs" :params="[
                'search' => $search,
                'status' => $status,
                'class_id' => $class_id,
                'from' => $from,
                'to' => $to,
            ]" />
            @if ($canDelete && $filteredCount > 0)
                <flux:modal.trigger name="confirm-delete-all">
                    <flux:button type="button" icon="trash" variant="ghost"
                        class="text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10">
                        {{ $hasActiveFilters ? 'Delete Filtered' : 'Delete All' }}
                    </flux:button>
                </flux:modal.trigger>
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
        <div class="grid grid-cols-2 gap-2.5 sm:gap-4 lg:grid-cols-4">
            <x-kpi-card title="Today's Check-ins" icon="clock" color="violet" :count="$todayLogs" subtext="total daily logs" />
            <x-kpi-card title="Present On Campus" icon="check-circle" color="emerald" :count="$presentToday"
                subtext="marked in attendance" />
            <x-kpi-card title="Attendance Rate" icon="chart-bar" color="indigo" :count="$attendanceRate" countSuffix="%"
                subtext="daily turn-out percentage" />
            <x-kpi-card title="Total Record Archive" icon="document-text" color="sky" :count="$totalLogs"
                subtext="lifetime attendance events" />
        </div>

        {{-- Filter Toolbar --}}
        <div x-data="{ mobileFiltersOpen: {{ $hasActiveFilters ? 'true' : 'false' }} }"
            class="bg-white dark:bg-zinc-900 shadow-[0_1px_3px_0_rgba(0,0,0,0.05)] border border-zinc-200 dark:border-zinc-800 rounded-xl p-3 sm:p-4 space-y-3">
            {{-- Quick Date Preset Pills --}}
            <div class="flex items-center gap-1.5 text-xs overflow-x-auto pb-1 sm:pb-0 scrollbar-none">
                <span class="text-zinc-400 font-medium mr-1 flex items-center gap-1 shrink-0">
                    <flux:icon icon="calendar" class="size-3.5" />
                    Preset:
                </span>
                <button type="button" wire:click="setDateRange"
                    class="px-2.5 py-1 rounded-lg font-medium transition-all shrink-0 {{ $isAllTime ? 'bg-indigo-600 text-white shadow-xs' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700' }}">
                    All Time
                </button>
                <button type="button" wire:click="setDateRange('{{ $todayStr }}', '{{ $todayStr }}')"
                    class="px-2.5 py-1 rounded-lg font-medium transition-all shrink-0 {{ $isToday ? 'bg-indigo-600 text-white shadow-xs' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700' }}">
                    ⚡ Today
                </button>
                <button type="button" wire:click="setDateRange('{{ $yesterdayStr }}', '{{ $yesterdayStr }}')"
                    class="px-2.5 py-1 rounded-lg font-medium transition-all shrink-0 {{ $isYesterday ? 'bg-indigo-600 text-white shadow-xs' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700' }}">
                    Yesterday
                </button>
                <button type="button" wire:click="setDateRange('{{ $startOfWeekStr }}', '{{ $endOfWeekStr }}')"
                    class="px-2.5 py-1 rounded-lg font-medium transition-all shrink-0 {{ $isThisWeek ? 'bg-indigo-600 text-white shadow-xs' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700' }}">
                    This Week
                </button>
                <button type="button" wire:click="setDateRange('{{ $startOfMonthStr }}', '{{ $endOfMonthStr }}')"
                    class="px-2.5 py-1 rounded-lg font-medium transition-all shrink-0 {{ $isThisMonth ? 'bg-indigo-600 text-white shadow-xs' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700' }}">
                    This Month
                </button>
            </div>

            <div class="flex flex-col lg:flex-row items-stretch lg:items-center gap-2.5 pt-2 border-t border-zinc-100 dark:border-zinc-800">
                {{-- Search & Mobile Toggle Row --}}
                <div class="flex items-center gap-2 w-full lg:w-auto lg:flex-1">
                    <div class="flex-1 relative min-w-0">
                        <flux:input x-ref="searchInput" wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                            placeholder="Search student name, ADM... (Press '/' to focus)" class="w-full" />
                    </div>

                    {{-- Mobile Filter Trigger Button --}}
                    <button type="button" @click="mobileFiltersOpen = !mobileFiltersOpen"
                        class="lg:hidden shrink-0 inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold transition border shadow-xs"
                        :class="mobileFiltersOpen || {{ $hasActiveFilters ? 'true' : 'false' }} 
                            ? 'bg-zinc-900 text-white border-zinc-900 dark:bg-white dark:text-zinc-900' 
                            : 'bg-zinc-100 text-zinc-700 border-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:border-zinc-700'">
                        <flux:icon icon="adjustments-horizontal" class="size-4" />
                        <span>Filters</span>
                        @if($hasActiveFilters)
                            <span class="size-2 rounded-full bg-emerald-400 shrink-0"></span>
                        @endif
                    </button>
                </div>

                {{-- Filter Controls (Collapsible on Mobile < lg, Inline on Desktop >= lg) --}}
                <div class="w-full lg:w-auto lg:flex lg:items-center lg:gap-2.5"
                    :class="mobileFiltersOpen ? 'flex flex-col sm:grid sm:grid-cols-2 gap-2.5 pt-2 border-t border-zinc-100 dark:border-zinc-800 lg:border-0 lg:pt-0' : 'hidden lg:flex'">
                    
                    <div class="w-full lg:w-40">
                        <label class="block text-[10px] font-bold text-zinc-400 uppercase tracking-wider mb-1 lg:hidden">Status</label>
                        <flux:select wire:model.live="status" placeholder="All Statuses" class="w-full">
                            <flux:select.option value="">All Statuses</flux:select.option>
                            <flux:select.option value="CheckIn">Check In</flux:select.option>
                            <flux:select.option value="CheckOut">Check Out</flux:select.option>
                            <flux:select.option value="OvertimeIn">Overtime In</flux:select.option>
                            <flux:select.option value="OvertimeOut">Overtime Out</flux:select.option>
                            <flux:select.option value="BreakIn">Break In</flux:select.option>
                            <flux:select.option value="BreakOut">Break Out</flux:select.option>
                        </flux:select>
                    </div>

                    <div class="w-full lg:w-44">
                        <label class="block text-[10px] font-bold text-zinc-400 uppercase tracking-wider mb-1 lg:hidden">Class</label>
                        @php
                            $isTeacher = auth()->user()->hasRole('Teacher');
                            $defaultClassLabel = $isTeacher ? (count($classes) === 1 ? 'My Class' : 'All My Classes') : 'All Classes';
                        @endphp
                        <flux:select wire:model.live="class_id" :placeholder="$defaultClassLabel" class="w-full">
                            <flux:select.option value="">{{ $defaultClassLabel }}</flux:select.option>
                            @foreach ($classes as $class)
                                <flux:select.option value="{{ $class->id }}">{{ $class->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    {{-- Responsive Date Range (Stacked on mobile phone, 2-col on tablet/sm, inline on lg) --}}
                    <div class="col-span-full sm:col-span-2 lg:col-span-1 flex flex-col sm:flex-row sm:items-center gap-2 w-full lg:w-auto">
                        <div class="w-full sm:flex-1 lg:w-36">
                            <label class="block text-[10px] font-bold text-zinc-400 uppercase tracking-wider mb-1 lg:hidden">From Date</label>
                            <flux:input type="date" wire:model.live="from" class="w-full" />
                        </div>
                        <span class="hidden lg:inline text-zinc-400 text-xs font-medium shrink-0">to</span>
                        <div class="w-full sm:flex-1 lg:w-36">
                            <label class="block text-[10px] font-bold text-zinc-400 uppercase tracking-wider mb-1 lg:hidden">To Date</label>
                            <flux:input type="date" wire:model.live="to" class="w-full" />
                        </div>
                    </div>

                    @if($hasActiveFilters)
                        <div class="col-span-full sm:col-span-2 lg:col-span-1 flex items-center justify-end pt-1 lg:pt-0">
                            <button type="button" wire:click="resetFilters"
                                class="inline-flex items-center justify-center gap-1 px-3 py-2 rounded-xl bg-zinc-100 dark:bg-zinc-800 text-xs font-semibold text-zinc-600 dark:text-zinc-400 hover:bg-zinc-200 dark:hover:bg-zinc-700 transition w-full lg:w-auto">
                                <flux:icon icon="x-mark" class="size-3.5" />
                                <span>Clear Filters</span>
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Bulk action bar --}}
        @if ($canDelete && count($selected) > 0)
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 rounded-xl px-4 py-2.5">
                <flux:text class="text-red-700 dark:text-red-400 text-sm font-medium">
                    @if ($selectAllMatching)
                        All {{ number_format($filteredCount) }} matching {{ Str::plural('record', $filteredCount) }} selected.
                        <button type="button" wire:click="$set('selectAllMatching', false)"
                            class="underline hover:no-underline">Select page only</button>
                    @else
                        {{ count($selected) }} {{ Str::plural('record', count($selected)) }} selected.
                        @if ($selectAllOnPage && $filteredCount > count($selected))
                            <button type="button" wire:click="$set('selectAllMatching', true)"
                                class="underline hover:no-underline font-semibold">Select all {{ number_format($filteredCount) }} matching</button>
                        @endif
                    @endif
                </flux:text>
                @if ($selectAllMatching)
                    <flux:modal.trigger name="confirm-delete-all">
                        <flux:button type="button" size="sm" variant="danger" icon="trash">
                            Delete all {{ number_format($filteredCount) }}
                        </flux:button>
                    </flux:modal.trigger>
                @else
                    <flux:modal.trigger name="confirm-delete-selected">
                        <flux:button type="button" size="sm" variant="danger" icon="trash">Delete Selected</flux:button>
                    </flux:modal.trigger>
                @endif
            </div>
        @endif

        {{-- Attendance Table Card --}}
        <div class="bg-white dark:bg-zinc-900 shadow-[0_1px_3px_0_rgba(0,0,0,0.05)] border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden flex flex-col">
            {{-- Mobile Stacked Cards (Visible on screen < 640px) --}}
            <div class="block sm:hidden divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($logs as $log)
                    @php
                        $initials = collect(explode(' ', $log['student_name']))
                            ->map(fn($w) => strtoupper(substr($w, 0, 1)))
                            ->take(2)
                            ->join('');
                    @endphp
                    <div class="p-3.5 space-y-2 hover:bg-zinc-50/60 dark:hover:bg-zinc-800/40 transition-colors">
                        {{-- Row 1: Checkbox, Avatar, Name, Admission No & Status Badge --}}
                        <div class="flex items-start justify-between gap-2.5">
                            <div class="flex items-center gap-2.5 min-w-0 flex-1">
                                @if ($canDelete)
                                    <input type="checkbox" wire:model.live="selected" value="{{ $log['id'] }}"
                                        class="rounded border-zinc-300 dark:border-zinc-600 shrink-0" />
                                @endif
                                <div class="size-10 rounded-full bg-violet-50 dark:bg-violet-900/30 text-violet-600 dark:text-violet-400 ring-1 ring-inset ring-violet-500/20 flex items-center justify-center font-bold text-xs shrink-0">
                                    {{ $initials }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="font-bold text-sm text-zinc-900 dark:text-white leading-snug">
                                            {{ $log['student_name'] }}
                                        </span>
                                        <span class="inline-flex px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 font-mono text-[10px] font-semibold border border-zinc-200 dark:border-zinc-700 whitespace-nowrap shrink-0">
                                            {{ $log['admission_number'] }}
                                        </span>
                                    </div>
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5 font-medium truncate flex items-center gap-1.5">
                                        <flux:icon icon="cpu-chip" class="size-3 text-zinc-400 shrink-0" />
                                        <span class="truncate">{{ $log['device_name'] }}</span>
                                        <span class="text-zinc-300 dark:text-zinc-600">&bull;</span>
                                        <span class="text-[11px]">{{ $log['verify_mode_label'] }}</span>
                                    </div>
                                </div>
                            </div>
                            <flux:badge :color="$log['status_color']" size="sm" class="font-semibold text-[11px] whitespace-nowrap shrink-0">
                                {{ $log['status_label'] }}
                            </flux:badge>
                        </div>

                        {{-- Row 2: Timestamp & Actions --}}
                        <div class="flex items-center justify-between gap-2 pt-1 border-t border-zinc-100 dark:border-zinc-800/80 text-xs">
                            <div class="flex items-center gap-1.5 font-mono text-[11px] text-zinc-600 dark:text-zinc-400">
                                <flux:icon icon="clock" class="size-3 text-zinc-400 shrink-0" />
                                <span>{{ $log['occurred_at'] }}</span>
                            </div>

                            @if ($canDelete)
                                <flux:modal.trigger name="confirm-delete-log">
                                    <flux:button type="button" size="xs" icon="trash" variant="ghost"
                                        class="text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 text-xs"
                                        wire:click="confirmDelete({{ $log['id'] }})">
                                        Delete
                                    </flux:button>
                                </flux:modal.trigger>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="p-6">
                        <x-empty-state icon="clock" color="violet" title="No Attendance Records"
                            description="Real-time check-in logs and biometric scans will appear here automatically.">
                            @if ($canTakeAttendance)
                                <flux:button href="{{ route('attendance.register') }}" wire:navigate.hover
                                    icon="clipboard-document-check" variant="primary">
                                    Take Attendance
                                </flux:button>
                            @endif
                        </x-empty-state>
                    </div>
                @endforelse
            </div>

            {{-- Desktop Table View --}}
            <div class="hidden sm:block overflow-x-auto">
                <flux:table class="p-2 min-w-[720px]">
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
                                            <flux:button href="{{ route('attendance.register') }}" wire:navigate.hover
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
                    <flux:subheading>This permanently removes it from the database. It does not affect anything stored on the device.</flux:subheading>
                </div>
                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button type="button" variant="filled">Cancel</flux:button>
                    </flux:modal.close>
                    <flux:button type="button" variant="danger" icon="trash" wire:click="deleteLog"
                        wire:loading.attr="disabled">Delete Record</flux:button>
                </div>
            </div>
        </flux:modal>

        <flux:modal name="confirm-delete-selected" class="max-w-md">
            <div class="space-y-5">
                <div>
                    <flux:heading size="lg">Delete {{ count($selected) }} selected {{ Str::plural('record', count($selected)) }}?</flux:heading>
                    <flux:subheading>This permanently removes them from the database. It does not affect anything stored on the device.</flux:subheading>
                </div>
                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button type="button" variant="filled">Cancel</flux:button>
                    </flux:modal.close>
                    <flux:button type="button" variant="danger" icon="trash" wire:click="deleteSelected"
                        wire:loading.attr="disabled">Delete Selected</flux:button>
                </div>
            </div>
        </flux:modal>

        <flux:modal name="confirm-delete-all" class="max-w-md">
            <div class="space-y-5">
                <div>
                    <flux:heading size="lg">
                        Delete {{ number_format($filteredCount) }} attendance {{ Str::plural('record', $filteredCount) }}?
                    </flux:heading>
                    <flux:subheading>
                        @if ($hasActiveFilters)
                            This permanently removes <strong>every record matching the current filters</strong> — not
                            just this page — from the database. It cannot be undone.
                        @else
                            This permanently removes <strong>every attendance record for this institution</strong> from
                            the database. It cannot be undone.
                        @endif
                        It does not affect anything stored on the device.
                    </flux:subheading>
                </div>
                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button type="button" variant="filled">Cancel</flux:button>
                    </flux:modal.close>
                    <flux:button type="button" variant="danger" icon="trash" wire:click="deleteAll"
                        wire:loading.attr="disabled">
                        Delete {{ $hasActiveFilters ? 'filtered' : 'all' }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
