<?php

use App\Models\Devices;
use App\Models\User;
use Athwari\LaravelZktecoAdms\Enums\AttendanceStatus;
use Athwari\LaravelZktecoAdms\Models\ZktecoAttendanceLog;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Classes\Models\SchoolClass;

new #[Title('Live Gate Attendance')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $class_id = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('view attendance'), 403);
    }

    #[Computed]
    public function logs(): LengthAwarePaginator
    {
        $institutionId = currentInstitutionId();

        $deviceIds = $institutionId ? Devices::where('institution_id', $institutionId)->whereNotNull('zkteco_device_id')->pluck('zkteco_device_id') : collect();

        $query = ZktecoAttendanceLog::with(['device', 'zktecoUser.appUser.studentUserDetails.schoolClass'])
            ->when(!isAdmin() && $deviceIds->isNotEmpty(), fn($q) => $q->whereIn('device_id', $deviceIds))
            ->when($this->search !== '', function ($q) {
                $term = '%' . $this->search . '%';

                $q->where(function ($sub) use ($term) {
                    $sub->where('pin', 'like', $term)->orWhereHas('zktecoUser.appUser', fn($uq) => $uq->where('name', 'like', $term))->orWhereHas('zktecoUser.appUser.studentUserDetails', fn($dq) => $dq->where('admission_number', 'like', $term));
                });
            })
            ->when($this->status !== '', function ($q) {
                $q->where(function ($sq) {
                    $sq->where('status', $this->status)->orWhere('status', AttendanceStatus::tryFrom($this->status));
                });
            })
            ->when($this->class_id !== '', fn($q) => $q->whereHas('zktecoUser.appUser.studentUserDetails', fn($sq) => $sq->where('class_id', $this->class_id)))
            ->orderBy('occurred_at', 'desc');

        $rawLogs = $query->paginate(15);

        $mappedItems = collect($rawLogs->items())->map(function (ZktecoAttendanceLog $log) {
            $student = $log->zktecoUser?->appUser;
            $details = $student?->studentUserDetails;

            $statusStr = $log->status instanceof AttendanceStatus ? $log->status->value : (string) ($log->status ?? 'present');

            return [
                'id' => $log->id,
                'student_name' => $student?->name ?? 'Learner',
                'admission_number' => $details?->admission_number ?? 'PIN-' . $log->pin,
                'class_name' => $details?->schoolClass?->name ?? 'Unassigned',
                'device_name' => $log->device?->name ?? ($log->device?->serial_number ?? 'Terminal 1'),
                'status' => $statusStr,
                'timestamp' => $log->occurred_at ? $log->occurred_at->toIso8601String() : null,
            ];
        });

        return new LengthAwarePaginator($mappedItems, $rawLogs->total(), $rawLogs->perPage(), $rawLogs->currentPage(), ['path' => request()->url(), 'query' => request()->query()]);
    }

    #[Computed]
    public function classes(): array
    {
        return SchoolClass::when(!isAdmin(), fn($q) => $q->where('institution_id', currentInstitutionId()))
            ->orderBy('name')
            ->get()
            ->all();
    }

    #[Computed]
    public function totalLogs(): int
    {
        $institutionId = currentInstitutionId();
        $deviceIds = $institutionId ? Devices::where('institution_id', $institutionId)->whereNotNull('zkteco_device_id')->pluck('zkteco_device_id') : collect();

        return ZktecoAttendanceLog::when(!isAdmin() && $deviceIds->isNotEmpty(), fn($q) => $q->whereIn('device_id', $deviceIds))->count();
    }

    #[Computed]
    public function todayLogs(): int
    {
        $institutionId = currentInstitutionId();
        $deviceIds = $institutionId ? Devices::where('institution_id', $institutionId)->whereNotNull('zkteco_device_id')->pluck('zkteco_device_id') : collect();

        return ZktecoAttendanceLog::when(!isAdmin() && $deviceIds->isNotEmpty(), fn($q) => $q->whereIn('device_id', $deviceIds))
            ->whereDate('occurred_at', today())
            ->count();
    }

    #[Computed]
    public function presentToday(): int
    {
        $institutionId = currentInstitutionId();
        $deviceIds = $institutionId ? Devices::where('institution_id', $institutionId)->whereNotNull('zkteco_device_id')->pluck('zkteco_device_id') : collect();

        return ZktecoAttendanceLog::when(!isAdmin() && $deviceIds->isNotEmpty(), fn($q) => $q->whereIn('device_id', $deviceIds))
            ->whereDate('occurred_at', today())
            ->where('status', AttendanceStatus::CheckIn)
            ->count();
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
    $canManageStudents = Route::has('student.index') && auth()->user()->can('view student');
@endphp

<div class="flex flex-col gap-6 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">

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
            @can('view attendance')
                <flux:button
                    href="{{ route('attendance.export', ['search' => $this->search, 'class_id' => $this->class_id]) }}"
                    icon="arrow-down-tray" variant="subtle" class="font-semibold shadow-sm">
                    Export CSV
                </flux:button>
            @endcan
            @if ($canTakeAttendance)
                <flux:button href="{{ route('attendance.take') }}" wire:navigate.hover icon="clipboard-document-check"
                    variant="primary" class="shadow-sm hover:shadow-md transition-all">
                    Take Roll Call
                </flux:button>
            @endif
        </div>
    </div>

    {{-- Animated KPI Metrics Grid --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-kpi-card title="Today's Check-ins" icon="clock" color="violet" :count="$todayLogs" subtext="total daily logs"
            :href="route('attendance.index')" />
        <x-kpi-card title="Present On Campus" icon="check-circle" color="emerald" :count="$presentToday"
            subtext="marked in attendance" :href="route('attendance.index', ['status' => 'present'])" />
        <x-kpi-card title="Attendance Rate" icon="chart-bar" color="indigo" :count="$attendanceRate" countSuffix="%"
            subtext="daily turn-out percentage" />
        <x-kpi-card title="Total Record Archive" icon="document-text" color="sky" :count="$totalLogs"
            subtext="lifetime attendance events" :href="route('attendance.index')" />
    </div>

    {{-- Filter and Search Toolbar --}}
    <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search student name, ADM..."
                icon="magnifying-glass" class="w-full sm:w-64" />

            <flux:select wire:model.live="status" placeholder="All Statuses" class="w-full sm:w-40">
                <flux:select.option value="">All Statuses</flux:select.option>
                <flux:select.option value="CheckIn">Check In</flux:select.option>
                <flux:select.option value="CheckOut">Check Out</flux:select.option>
            </flux:select>

            <flux:select wire:model.live="class_id" placeholder="All Classes" class="w-full sm:w-44">
                <flux:select.option value="">All Classes</flux:select.option>
                @foreach ($this->classes as $class)
                    <flux:select.option value="{{ $class->id }}">{{ $class->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    {{-- Attendance Table Card --}}
    <div
        class="bg-white dark:bg-zinc-900 shadow-[0_1px_3px_0_rgba(0,0,0,0.05)] border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden flex flex-col">
        <div class="overflow-x-auto">
            <flux:table class="p-2">
                <flux:table.columns>
                    <flux:table.column>Learner Profile</flux:table.column>
                    <flux:table.column>Access Point / Device</flux:table.column>
                    <flux:table.column>Verification Status</flux:table.column>
                    <flux:table.column>Timestamp</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($logs as $log)
                        @php
                            $studentName = $log['student_name'] ?? 'Learner';
                            $initials = collect(explode(' ', $studentName))
                                ->map(fn($w) => strtoupper(substr($w, 0, 1)))
                                ->take(2)
                                ->join('');
                            $rawDate = $log['timestamp'] ?? null;
                            $dateStr = '—';
                            $timeStr = '';
                            if ($rawDate && $rawDate !== '—') {
                                try {
                                    $parsed = Carbon::parse($rawDate);
                                    $dateStr = $parsed->format('M d, Y');
                                    $timeStr = $parsed->format('h:i A');
                                } catch (\Throwable $e) {
                                    $dateStr = $rawDate;
                                }
                            }
                        @endphp
                        <flux:table.row class="transition-colors hover:bg-zinc-50/50 dark:hover:bg-zinc-800/20 group">
                            <flux:table.cell>
                                <div class="flex items-center gap-3">
                                    <div
                                        class="size-8 rounded-full bg-violet-50 dark:bg-violet-900/30 text-violet-600 dark:text-violet-400 ring-1 ring-inset ring-violet-500/20 flex items-center justify-center font-bold text-xs transition-transform group-hover:scale-105 shrink-0">
                                        {{ $initials }}
                                    </div>
                                    <div class="flex flex-col">
                                        <span
                                            class="font-semibold text-sm text-zinc-900 dark:text-white leading-snug">{{ $studentName }}</span>
                                        <span
                                            class="font-mono text-[11px] text-zinc-500 dark:text-zinc-400 leading-normal">{{ $log['admission_number'] }}</span>
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
                                @php
                                    $st = strtolower($log['status']);
                                    $color = match (true) {
                                        str_contains($st, 'in') => 'emerald',
                                        str_contains($st, 'out') => 'red',
                                        default => 'zinc',
                                    };
                                @endphp
                                <flux:badge :color="$color" size="sm"
                                    class="capitalize font-semibold text-[11px]">
                                    {{ $log['status'] }}
                                </flux:badge>
                            </flux:table.cell>

                            <flux:table.cell>
                                <div class="flex flex-col text-xs">
                                    <span
                                        class="font-mono font-medium text-zinc-900 dark:text-zinc-100">{{ $timeStr ?: '—' }}</span>
                                    <span class="text-[11px] text-zinc-400">{{ $dateStr }}</span>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="4">
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
</div>
