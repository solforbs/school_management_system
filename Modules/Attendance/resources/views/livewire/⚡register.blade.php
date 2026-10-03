<?php

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Attendance\Models\Attendance;
use Modules\Attendance\Services\ClassAttendanceService;
use Modules\Classes\Models\SchoolClass;

new #[Title('Class Attendance Register')] class extends Component
{
    #[Url]
    public ?int $classId = null;

    #[Url]
    public string $date = '';

    /** @var array<int, string|null> */
    public array $statuses = [];

    /** @var array<int, bool> */
    public array $parentNotified = [];

    public string $notes = '';

    public bool $showSuccessBanner = false;

    public string $successMessage = '';

    public function mount(?int $classId = null): void
    {
        abort_unless(auth()->user()->can('view attendance'), 403);

        $this->date = $this->date ?: today()->toDateString();
        $this->classId = $classId ?: ($this->classId ?: null);

        $service = app(ClassAttendanceService::class);
        $resolved = $service->resolveClassForUser(auth()->user(), $this->classId);

        if ($resolved) {
            $this->classId = $resolved->id;
            $this->loadRegisterData();
        }
    }

    public function updatedClassId(): void
    {
        $this->showSuccessBanner = false;
        $this->loadRegisterData();
    }

    public function updatedDate(): void
    {
        $this->showSuccessBanner = false;
        $this->loadRegisterData();
    }

    public function loadRegisterData(): void
    {
        $class = $this->currentClass;
        if (! $class) {
            $this->statuses = [];
            $this->parentNotified = [];

            return;
        }

        $service = app(ClassAttendanceService::class);
        $register = $service->getRegister($class, $this->date);

        $statuses = [];
        $notified = [];

        foreach ($register as $row) {
            $sId = (int) $row['student_id'];
            $statuses[$sId] = $row['status'];
            $notified[$sId] = (bool) $row['parent_notified'];
        }

        $this->statuses = $statuses;
        $this->parentNotified = $notified;

        // Load existing notes for this class and date if available
        $existingNote = Attendance::where('school_class_id', $class->id)
            ->where('date', Carbon::parse($this->date)->toDateString())
            ->whereNotNull('notes')
            ->value('notes');

        $this->notes = $existingNote ?? '';
    }

    #[Computed]
    public function selectableClasses(): Collection
    {
        return app(ClassAttendanceService::class)->getSelectableClasses(auth()->user());
    }

    #[Computed]
    public function currentClass(): ?SchoolClass
    {
        if (! $this->classId) {
            return null;
        }

        return $this->selectableClasses->firstWhere('id', $this->classId);
    }

    #[Computed]
    public function register(): Collection
    {
        $class = $this->currentClass;
        if (! $class) {
            return collect();
        }

        return app(ClassAttendanceService::class)->getRegister($class, $this->date);
    }

    #[Computed]
    public function totalLearnersCount(): int
    {
        return $this->register->count();
    }

    #[Computed]
    public function presentCount(): int
    {
        return collect($this->statuses)->filter(fn ($s) => $s === 'present')->count();
    }

    #[Computed]
    public function absentCount(): int
    {
        return collect($this->statuses)->filter(fn ($s) => $s === 'absent')->count();
    }

    #[Computed]
    public function attendanceRate(): int
    {
        $total = $this->totalLearnersCount;
        if ($total === 0) {
            return 0;
        }

        return (int) round(($this->presentCount / $total) * 100);
    }

    #[Computed]
    public function absentStudents(): Collection
    {
        return $this->register->filter(function ($row) {
            $sId = (int) $row['student_id'];

            return ($this->statuses[$sId] ?? null) === 'absent';
        })->values();
    }

    public function markStatus(int $studentId, string $status): void
    {
        $current = $this->statuses[$studentId] ?? null;

        if ($current === $status) {
            $this->statuses[$studentId] = null;
        } else {
            $this->statuses[$studentId] = $status;
        }

        $this->showSuccessBanner = false;
    }

    public function markAllPresent(): void
    {
        foreach ($this->register as $row) {
            $sId = (int) $row['student_id'];
            $this->statuses[$sId] = 'present';
        }

        $this->showSuccessBanner = false;
    }

    public function clearAll(): void
    {
        foreach ($this->register as $row) {
            $sId = (int) $row['student_id'];
            $this->statuses[$sId] = null;
        }

        $this->showSuccessBanner = false;
    }

    public function toggleParentNotified(int $studentId): void
    {
        $this->parentNotified[$studentId] = ! ($this->parentNotified[$studentId] ?? false);
        $this->showSuccessBanner = false;
    }

    /**
     * Save class attendance batch from Alpine state or Livewire properties.
     *
     * @param  array<int, string|null>|null  $clientStatuses
     * @param  array<int, bool>|null  $clientNotified
     */
    public function saveAttendance(?array $clientStatuses = null, ?array $clientNotified = null, ?string $clientNotes = null): void
    {
        $class = $this->currentClass;
        abort_unless($class, 404, 'No class selected');

        if ($clientStatuses !== null) {
            $this->statuses = $clientStatuses;
        }
        if ($clientNotified !== null) {
            $this->parentNotified = $clientNotified;
        }
        if ($clientNotes !== null) {
            $this->notes = $clientNotes;
        }

        $entries = [];
        foreach ($this->statuses as $studentId => $status) {
            $entries[(int) $studentId] = [
                'status' => $status,
                'parent_notified' => (bool) ($this->parentNotified[$studentId] ?? false),
            ];
        }

        app(ClassAttendanceService::class)->saveRegister(
            $class,
            $this->date,
            $entries,
            auth()->user(),
            $this->notes
        );

        $msg = 'Attendance for '.$class->name.' ('.Carbon::parse($this->date)->format('D, M j, Y').') saved successfully!';
        \Flux\Flux::toast(text: $msg, variant: 'success');
        $this->showSuccessBanner = true;
        $this->successMessage = $msg;
    }
}; ?>

<div class="space-y-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-6 pb-24 lg:pb-8">

    {{-- Breadcrumb & Navigation Tabs --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-zinc-200 dark:border-zinc-800 pb-4">
        <div>
            <nav class="flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400 font-medium mb-1">
                <span>Classes</span>
                <flux:icon icon="chevron-right" class="size-3 text-zinc-400" />
                <span class="text-zinc-700 dark:text-zinc-200 font-semibold">{{ $this->currentClass?->name ?? 'Select Class' }}</span>
                <flux:icon icon="chevron-right" class="size-3 text-zinc-400" />
                <span class="text-blue-600 dark:text-blue-400 font-bold">Attendance</span>
            </nav>
            <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-zinc-900 dark:text-white">
                Attendance
            </h1>
            <p class="text-xs sm:text-sm text-zinc-500 dark:text-zinc-400 mt-0.5">
                Mark who is present today. You can update this at any time.
            </p>
        </div>

        {{-- Date Selector & Mode Switch --}}
        <div class="flex flex-col sm:flex-row sm:items-center gap-2.5 sm:gap-3 w-full sm:w-auto">
            {{-- Tab Switcher: Register vs Hardware Logs --}}
            <div class="inline-flex rounded-xl bg-zinc-100 dark:bg-zinc-800 p-1 border border-zinc-200 dark:border-zinc-700 w-full sm:w-auto justify-center sm:justify-start">
                <span class="flex-1 sm:flex-none text-center px-3 py-1.5 rounded-lg text-xs font-bold bg-white dark:bg-zinc-900 text-blue-600 dark:text-blue-400 shadow-xs border border-zinc-200/80 dark:border-zinc-700/80">
                    Roll-Call Register
                </span>
                <a href="{{ route('attendance.index') }}" wire:navigate.hover class="flex-1 sm:flex-none text-center px-3 py-1.5 rounded-lg text-xs font-semibold text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white transition">
                    Biometric Logs
                </a>
            </div>

            {{-- Date display pill with date picker --}}
            <div x-data="{ open: false }" class="relative w-full sm:w-auto">
                <div class="flex items-center justify-between sm:justify-start gap-2 px-3 py-2 sm:py-1.5 rounded-xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 shadow-xs text-xs font-semibold text-zinc-800 dark:text-zinc-200">
                    <div class="flex items-center gap-2">
                        <flux:icon icon="calendar-days" class="size-4 text-zinc-400 shrink-0" />
                        <span class="sm:hidden">{{ Carbon::parse($this->date)->format('D, j M Y') }}</span>
                        <span class="hidden sm:inline">{{ Carbon::parse($this->date)->format('l, j F Y') }}</span>
                    </div>
                    <button type="button" @click="open = !open" class="text-blue-600 dark:text-blue-400 font-bold hover:underline ml-1 cursor-pointer">
                        Change date
                    </button>
                </div>
                <div x-show="open" @click.outside="open = false" x-cloak class="absolute right-0 mt-2 z-30 p-3 bg-white dark:bg-zinc-900 rounded-xl shadow-xl border border-zinc-200 dark:border-zinc-800 space-y-2 w-full sm:w-auto">
                    <label class="block text-xs font-bold text-zinc-600 dark:text-zinc-300">Choose Date</label>
                    <input type="date" wire:model.live="date" @change="open = false" max="{{ today()->toDateString() }}" class="w-full px-2.5 py-1.5 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-white focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
        </div>
    </div>

    {{-- Class Selection Switcher --}}
    @if ($this->selectableClasses->count() > 1)
        <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3 bg-zinc-50 dark:bg-zinc-900/60 p-3 sm:p-3.5 rounded-xl border border-zinc-200 dark:border-zinc-800 text-xs">
            <span class="font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider text-[11px] sm:text-xs shrink-0">Switch Class:</span>
            <div class="flex flex-wrap gap-2 w-full sm:w-auto">
                @foreach ($this->selectableClasses as $classOption)
                    <button type="button"
                        wire:click="$set('classId', {{ $classOption->id }})"
                        class="flex-1 sm:flex-none text-center px-3 py-2 sm:py-1.5 rounded-lg text-xs font-semibold transition border cursor-pointer {{ $this->classId === $classOption->id ? 'bg-blue-600 text-white border-blue-600 shadow-xs' : 'bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border-zinc-200 dark:border-zinc-700 hover:bg-zinc-100' }}">
                        {{ $classOption->name }} <span class="opacity-80">({{ $classOption->students_count }})</span>
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    @if (! $this->currentClass)
        <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-12 text-center space-y-3">
            <flux:icon icon="users" class="size-12 text-zinc-400 mx-auto" />
            <h3 class="text-base font-bold text-zinc-900 dark:text-white">No Accessible Classes Found</h3>
            <p class="text-xs text-zinc-500 max-w-md mx-auto">
                You currently do not have assigned classes or homeroom responsibilities. Please contact your administrator.
            </p>
        </div>
    @else
        @php
            $students = $this->register;
            $allStudentIds = $students->pluck('student_id')->map(fn ($id) => (int) $id)->all();
            $totalCount = $students->count();
        @endphp

        {{-- Instant 0ms SPA Alpine Container --}}
        <div x-data="{
            statuses: @js($statuses),
            notified: @js($parentNotified),
            notes: @js($notes),
            allStudentIds: @js($allStudentIds),
            totalCount: {{ $totalCount }},
            saving: false,
            savedBanner: false,
            savedMessage: '',

            setStatus(id, val) {
                this.statuses[id] = (this.statuses[id] === val ? null : val);
            },
            markAllPresent() {
                this.allStudentIds.forEach(id => {
                    this.statuses[id] = 'present';
                });
            },
            clearAll() {
                this.allStudentIds.forEach(id => {
                    this.statuses[id] = null;
                });
            },
            toggleNotified(id) {
                this.notified[id] = !Boolean(this.notified[id]);
            },
            get presentCount() {
                return Object.values(this.statuses).filter(s => s === 'present').length;
            },
            get absentCount() {
                return Object.values(this.statuses).filter(s => s === 'absent').length;
            },
            get attendanceRate() {
                return this.totalCount > 0 ? Math.round((this.presentCount / this.totalCount) * 100) : 0;
            },
            async save() {
                this.saving = true;
                try {
                    const cleanStatuses = {};
                    for (const [k, v] of Object.entries(this.statuses || {})) {
                        cleanStatuses[Number(k)] = v;
                    }
                    const cleanNotified = {};
                    for (const [k, v] of Object.entries(this.notified || {})) {
                        cleanNotified[Number(k)] = Boolean(v);
                    }
                    const cleanNotes = String(this.notes || '');

                    await $wire.saveAttendance(cleanStatuses, cleanNotified, cleanNotes);
                    this.savedBanner = true;
                    this.savedMessage = 'Attendance for {{ $this->currentClass?->name ?? 'Class' }} saved successfully!';
                    setTimeout(() => { this.savedBanner = false; }, 6000);
                } catch (err) {
                    console.error('Attendance save error:', err);
                    alert('Unable to save attendance: ' + (err?.message || 'Server error.'));
                } finally {
                    this.saving = false;
                }
            }
        }" class="space-y-6">

            {{-- Top Alert Banner (Alpine + Livewire Synchronized) --}}
            <div x-show="savedBanner || $wire.showSuccessBanner" x-cloak class="rounded-xl p-4 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 flex items-center justify-between text-emerald-800 dark:text-emerald-200 transition">
                <div class="flex items-center gap-2.5 text-xs sm:text-sm font-semibold">
                    <flux:icon icon="check-circle" class="size-5 text-emerald-600 shrink-0" />
                    <span x-text="savedMessage || $wire.successMessage">{{ $this->successMessage ?: 'Attendance saved successfully!' }}</span>
                </div>
                <button type="button" @click="savedBanner = false; $wire.set('showSuccessBanner', false)" class="text-emerald-700 hover:text-emerald-900 text-sm font-bold cursor-pointer">×</button>
            </div>

            {{-- Class Summary & Live KPI Bar --}}
            <div class="rounded-2xl bg-white dark:bg-zinc-900 p-4 sm:p-6 border border-zinc-200/90 dark:border-zinc-800 shadow-xs">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-5 sm:gap-6">
                    {{-- Class meta --}}
                    <div class="space-y-1">
                        <h2 class="text-2xl sm:text-3xl font-black text-zinc-900 dark:text-white tracking-tight">
                            {{ $this->currentClass->name }}
                        </h2>
                        <div class="flex items-center gap-2 text-xs sm:text-sm text-zinc-500 dark:text-zinc-400">
                            <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ currentInstitution()?->name ?? 'Solforbs Academy' }}</span>
                            <span>•</span>
                            <span class="font-semibold text-zinc-900 dark:text-white">{{ $totalCount }} learners</span>
                        </div>
                    </div>

                    {{-- 3 Live Instant 0ms KPI Counters (2-col on mobile with Rate full-width, 3-col on tablet/desktop) --}}
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 sm:gap-4 w-full lg:w-auto">
                        {{-- Present KPI --}}
                        <div class="flex items-center gap-2.5 sm:gap-3 p-3 sm:px-4 sm:py-3 rounded-xl bg-blue-50/70 dark:bg-blue-950/30 border border-blue-100 dark:border-blue-900/40 min-w-0">
                            <div class="size-9 sm:size-10 rounded-lg bg-blue-600/10 dark:bg-blue-500/20 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
                                <flux:icon icon="users" class="size-4 sm:size-5" />
                            </div>
                            <div class="min-w-0">
                                <span class="block text-xl sm:text-2xl font-black text-blue-900 dark:text-blue-100 leading-tight" x-text="presentCount">
                                    {{ $this->presentCount }}
                                </span>
                                <span class="block text-[11px] sm:text-xs font-semibold uppercase tracking-wider text-blue-700/80 dark:text-blue-300/80 truncate">
                                    Present
                                </span>
                            </div>
                        </div>

                        {{-- Absent KPI --}}
                        <div class="flex items-center gap-2.5 sm:gap-3 p-3 sm:px-4 sm:py-3 rounded-xl bg-rose-50/70 dark:bg-rose-950/30 border border-rose-100 dark:border-rose-900/40 min-w-0">
                            <div class="size-9 sm:size-10 rounded-lg bg-rose-600/10 dark:bg-rose-500/20 text-rose-600 dark:text-rose-400 flex items-center justify-center shrink-0">
                                <flux:icon icon="user-minus" class="size-4 sm:size-5" />
                            </div>
                            <div class="min-w-0">
                                <span class="block text-xl sm:text-2xl font-black text-rose-900 dark:text-rose-100 leading-tight" x-text="absentCount">
                                    {{ $this->absentCount }}
                                </span>
                                <span class="block text-[11px] sm:text-xs font-semibold uppercase tracking-wider text-rose-700/80 dark:text-rose-300/80 truncate">
                                    Absent
                                </span>
                            </div>
                        </div>

                        {{-- Rate KPI (Full width on mobile with progress bar, 3rd column on tablet/desktop) --}}
                        <div class="col-span-2 sm:col-span-1 flex items-center justify-between sm:justify-start gap-2.5 sm:gap-3 p-3 sm:px-4 sm:py-3 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700 min-w-0">
                            <div class="flex items-center gap-2.5 sm:gap-3 min-w-0">
                                <div class="size-9 sm:size-10 rounded-lg bg-zinc-200/60 dark:bg-zinc-700 text-zinc-700 dark:text-zinc-300 flex items-center justify-center shrink-0">
                                    <span class="font-bold text-xs sm:text-sm">%</span>
                                </div>
                                <div class="min-w-0">
                                    <span class="block text-xl sm:text-2xl font-black text-zinc-900 dark:text-white leading-tight" x-text="attendanceRate + '%'">
                                        {{ $this->attendanceRate }}%
                                    </span>
                                    <span class="block text-[11px] sm:text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 truncate">
                                        Attendance rate
                                    </span>
                                </div>
                            </div>
                            {{-- Mini rate progress bar on mobile --}}
                            <div class="sm:hidden w-28 bg-zinc-200 dark:bg-zinc-700 rounded-full h-2 overflow-hidden shrink-0">
                                <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" :style="'width: ' + attendanceRate + '%'"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Main Layout: Left Register (2/3) & Right Panel (1/3) --}}
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

                {{-- Left Section: Class Register Card (lg:col-span-8) --}}
                <div class="lg:col-span-8 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/90 dark:border-zinc-800 shadow-xs overflow-hidden">
                    {{-- Register Header & Instant Actions --}}
                    <div class="px-4 sm:px-5 py-3.5 sm:py-4 border-b border-zinc-200/80 dark:border-zinc-800 flex items-center justify-between flex-wrap gap-2.5 bg-zinc-50/50 dark:bg-zinc-800/30">
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm sm:text-base font-bold text-zinc-900 dark:text-white">
                                Class register
                            </h3>
                            <span class="text-xs text-zinc-500 font-medium">
                                ({{ $totalCount }} learners)
                            </span>
                        </div>

                        <div class="flex items-center gap-2 sm:gap-3">
                            <button type="button"
                                @click="markAllPresent()"
                                class="text-xs font-bold text-blue-600 dark:text-blue-400 hover:text-blue-800 transition cursor-pointer">
                                Mark all present
                            </button>
                            <span class="text-zinc-300 dark:text-zinc-700">•</span>
                            <button type="button"
                                @click="clearAll()"
                                class="px-2 py-1 text-xs font-semibold text-zinc-600 dark:text-zinc-300 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-700 transition cursor-pointer">
                                Clear all
                            </button>
                        </div>
                    </div>

                    @if ($students->isEmpty())
                        <div class="p-12 text-center text-xs text-zinc-500">
                            No students enrolled in this class yet.
                        </div>
                    @else
                        @php
                            $halfCount = (int) ceil($students->count() / 2);
                            $col1 = $students->slice(0, $halfCount);
                            $col2 = $students->slice($halfCount);
                        @endphp

                        {{-- Two-column split register on desktop, single column on small screens --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-zinc-200/80 dark:divide-zinc-800">

                            {{-- Left Column (Learners 1 to N/2) --}}
                            <div class="divide-y divide-zinc-100 dark:divide-zinc-800/60">
                                {{-- Header --}}
                                <div class="grid grid-cols-12 px-3 sm:px-4 py-2.5 bg-zinc-50/70 dark:bg-zinc-800/40 text-[11px] font-bold uppercase tracking-wider text-zinc-400 border-b border-zinc-200/60 dark:border-zinc-800">
                                    <span class="col-span-1 text-center font-mono">#</span>
                                    <span class="col-span-7">Name</span>
                                    <span class="col-span-2 text-center flex items-center justify-center">
                                        <span class="hidden sm:inline">Present</span>
                                        <flux:icon icon="check" class="size-3.5 sm:hidden text-blue-600 stroke-[3]" />
                                    </span>
                                    <span class="col-span-2 text-center flex items-center justify-center">
                                        <span class="hidden sm:inline">Absent</span>
                                        <flux:icon icon="x-mark" class="size-3.5 sm:hidden text-rose-500 stroke-[3]" />
                                    </span>
                                </div>

                                @foreach ($col1 as $student)
                                    @php $sId = (int) $student['student_id']; @endphp
                                    <div class="grid grid-cols-12 items-center px-3 sm:px-4 py-2.5 hover:bg-zinc-50/70 dark:hover:bg-zinc-800/40 transition">
                                        {{-- Number --}}
                                        <span class="col-span-1 text-xs text-zinc-400 font-mono text-center">
                                            {{ $student['index'] }}
                                        </span>

                                        {{-- Student Name & Indicators --}}
                                        <div class="col-span-7 pr-1 sm:pr-2 min-w-0">
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <span class="text-xs sm:text-sm font-semibold text-zinc-900 dark:text-white truncate">
                                                    {{ $student['name'] }}
                                                </span>
                                                @if ($student['has_gate_scan'])
                                                    <span title="Gate check-in recorded at {{ $student['scanned_at'] }}" class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-sky-50 text-sky-700 dark:bg-sky-950/40 dark:text-sky-300 border border-sky-200/80 dark:border-sky-800/60 shrink-0 whitespace-nowrap">
                                                        <flux:icon icon="bolt" class="size-2.5 text-sky-500" />
                                                        <span>{{ $student['scanned_at'] }}</span>
                                                    </span>
                                                @endif
                                            </div>
                                        </div>

                                        {{-- Instant Present Checkbox / Tick --}}
                                        <div class="col-span-2 flex justify-center">
                                            <button type="button"
                                                @click="setStatus({{ $sId }}, 'present')"
                                                :class="statuses[{{ $sId }}] === 'present' ? 'bg-blue-600 border-blue-600 text-white shadow-xs' : 'border-zinc-300 dark:border-zinc-600 hover:border-blue-400 bg-white dark:bg-zinc-800'"
                                                class="size-7 sm:size-6 rounded-lg sm:rounded-md flex items-center justify-center transition border cursor-pointer select-none active:scale-95"
                                                aria-label="Mark {{ $student['name'] }} present">
                                                <span x-show="statuses[{{ $sId }}] === 'present'" x-cloak>
                                                    <flux:icon icon="check" class="size-4 stroke-[3]" />
                                                </span>
                                            </button>
                                        </div>

                                        {{-- Instant Absent Checkbox / Tick --}}
                                        <div class="col-span-2 flex justify-center">
                                            <button type="button"
                                                @click="setStatus({{ $sId }}, 'absent')"
                                                :class="statuses[{{ $sId }}] === 'absent' ? 'bg-rose-500 border-rose-500 text-white shadow-xs' : 'border-zinc-300 dark:border-zinc-600 hover:border-rose-400 bg-white dark:bg-zinc-800'"
                                                class="size-7 sm:size-6 rounded-lg sm:rounded-md flex items-center justify-center transition border cursor-pointer select-none active:scale-95"
                                                aria-label="Mark {{ $student['name'] }} absent">
                                                <span x-show="statuses[{{ $sId }}] === 'absent'" x-cloak>
                                                    <flux:icon icon="x-mark" class="size-4 stroke-[3]" />
                                                </span>
                                            </button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            {{-- Right Column (Learners N/2 + 1 to N) --}}
                            <div class="divide-y divide-zinc-100 dark:divide-zinc-800/60">
                                {{-- Header --}}
                                <div class="grid grid-cols-12 px-3 sm:px-4 py-2.5 bg-zinc-50/70 dark:bg-zinc-800/40 text-[11px] font-bold uppercase tracking-wider text-zinc-400 border-b border-zinc-200/60 dark:border-zinc-800">
                                    <span class="col-span-1 text-center font-mono">#</span>
                                    <span class="col-span-7">Name</span>
                                    <span class="col-span-2 text-center flex items-center justify-center">
                                        <span class="hidden sm:inline">Present</span>
                                        <flux:icon icon="check" class="size-3.5 sm:hidden text-blue-600 stroke-[3]" />
                                    </span>
                                    <span class="col-span-2 text-center flex items-center justify-center">
                                        <span class="hidden sm:inline">Absent</span>
                                        <flux:icon icon="x-mark" class="size-3.5 sm:hidden text-rose-500 stroke-[3]" />
                                    </span>
                                </div>

                                @foreach ($col2 as $student)
                                    @php $sId = (int) $student['student_id']; @endphp
                                    <div class="grid grid-cols-12 items-center px-3 sm:px-4 py-2.5 hover:bg-zinc-50/70 dark:hover:bg-zinc-800/40 transition">
                                        {{-- Number --}}
                                        <span class="col-span-1 text-xs text-zinc-400 font-mono text-center">
                                            {{ $student['index'] }}
                                        </span>

                                        {{-- Student Name & Indicators --}}
                                        <div class="col-span-7 pr-1 sm:pr-2 min-w-0">
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <span class="text-xs sm:text-sm font-semibold text-zinc-900 dark:text-white truncate">
                                                    {{ $student['name'] }}
                                                </span>
                                                @if ($student['has_gate_scan'])
                                                    <span title="Gate check-in recorded at {{ $student['scanned_at'] }}" class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-sky-50 text-sky-700 dark:bg-sky-950/40 dark:text-sky-300 border border-sky-200/80 dark:border-sky-800/60 shrink-0 whitespace-nowrap">
                                                        <flux:icon icon="bolt" class="size-2.5 text-sky-500" />
                                                        <span>{{ $student['scanned_at'] }}</span>
                                                    </span>
                                                @endif
                                            </div>
                                        </div>

                                        {{-- Instant Present Checkbox / Tick --}}
                                        <div class="col-span-2 flex justify-center">
                                            <button type="button"
                                                @click="setStatus({{ $sId }}, 'present')"
                                                :class="statuses[{{ $sId }}] === 'present' ? 'bg-blue-600 border-blue-600 text-white shadow-xs' : 'border-zinc-300 dark:border-zinc-600 hover:border-blue-400 bg-white dark:bg-zinc-800'"
                                                class="size-7 sm:size-6 rounded-lg sm:rounded-md flex items-center justify-center transition border cursor-pointer select-none active:scale-95"
                                                aria-label="Mark {{ $student['name'] }} present">
                                                <span x-show="statuses[{{ $sId }}] === 'present'" x-cloak>
                                                    <flux:icon icon="check" class="size-4 stroke-[3]" />
                                                </span>
                                            </button>
                                        </div>

                                        {{-- Instant Absent Checkbox / Tick --}}
                                        <div class="col-span-2 flex justify-center">
                                            <button type="button"
                                                @click="setStatus({{ $sId }}, 'absent')"
                                                :class="statuses[{{ $sId }}] === 'absent' ? 'bg-rose-500 border-rose-500 text-white shadow-xs' : 'border-zinc-300 dark:border-zinc-600 hover:border-rose-400 bg-white dark:bg-zinc-800'"
                                                class="size-7 sm:size-6 rounded-lg sm:rounded-md flex items-center justify-center transition border cursor-pointer select-none active:scale-95"
                                                aria-label="Mark {{ $student['name'] }} absent">
                                                <span x-show="statuses[{{ $sId }}] === 'absent'" x-cloak>
                                                    <flux:icon icon="x-mark" class="size-4 stroke-[3]" />
                                                </span>
                                            </button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Right Section: Absent Today, Notes, Save Action (lg:col-span-4) --}}
                <div class="lg:col-span-4 space-y-4">

                    {{-- Absent Today Card --}}
                    <div class="rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/90 dark:border-zinc-800 p-5 shadow-xs space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-bold text-zinc-900 dark:text-white flex items-center gap-1.5">
                                <span>Absent today</span>
                                <span class="text-xs px-2 py-0.5 rounded-full bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300 font-bold border border-rose-200 dark:border-rose-800"
                                    x-text="absentCount">
                                    {{ $this->absentCount }}
                                </span>
                            </h3>
                        </div>

                        {{-- Empty State when no absentees --}}
                        <div x-show="absentCount === 0" class="py-6 text-center text-xs text-zinc-400 dark:text-zinc-500">
                            No learners marked absent today.
                        </div>

                        {{-- Dynamic Absentee Rows --}}
                        <div x-show="absentCount > 0" class="divide-y divide-zinc-100 dark:divide-zinc-800 space-y-2">
                            @foreach ($students as $absentee)
                                @php $aId = (int) $absentee['student_id']; @endphp
                                <div x-show="statuses[{{ $aId }}] === 'absent'" x-cloak class="pt-2 flex items-center justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="text-xs font-bold text-zinc-900 dark:text-white truncate">
                                            {{ $absentee['name'] }}
                                        </p>
                                        <p class="text-[11px] text-zinc-400 font-mono">
                                            #{{ $absentee['admission_number'] }}
                                        </p>
                                    </div>

                                    {{-- Instant Parent Notified Switch --}}
                                    <div class="flex items-center gap-2 shrink-0">
                                        <span class="text-[11px] text-zinc-500 font-medium">Parent notified</span>
                                        <button type="button"
                                            @click="toggleNotified({{ $aId }})"
                                            :class="notified[{{ $aId }}] ? 'bg-blue-600' : 'bg-zinc-200 dark:bg-zinc-700'"
                                            class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-hidden"
                                            role="switch"
                                            :aria-checked="notified[{{ $aId }}] ? 'true' : 'false'">
                                            <span class="sr-only">Toggle notification for parent</span>
                                            <span aria-hidden="true"
                                                :class="notified[{{ $aId }}] ? 'translate-x-4' : 'translate-x-0'"
                                                class="pointer-events-none inline-block size-4 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out">
                                            </span>
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Notes Card --}}
                    <div class="rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/90 dark:border-zinc-800 p-5 shadow-xs space-y-3">
                        <div class="flex items-center justify-between">
                            <label for="attendance-notes" class="text-sm font-bold text-zinc-900 dark:text-white">
                                Notes (optional)
                            </label>
                            <span class="text-[11px] text-zinc-400 font-mono" x-text="(notes?.length || 0) + '/200'">
                                {{ mb_strlen($this->notes) }}/200
                            </span>
                        </div>
                        <textarea id="attendance-notes"
                            x-model="notes"
                            maxlength="200"
                            rows="3"
                            placeholder="Add any notes about today's attendance..."
                            class="w-full text-xs rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/40 p-3 text-zinc-900 dark:text-white placeholder:text-zinc-400 focus:bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition resize-none"></textarea>
                    </div>

                    {{-- Save Attendance Button --}}
                    <button type="button"
                        @click="save()"
                        :disabled="saving"
                        class="w-full py-3.5 px-4 rounded-xl text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 active:bg-blue-800 shadow-md hover:shadow-lg transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50">
                        <span x-show="!saving" class="flex items-center gap-2">
                            <flux:icon icon="check-circle" class="size-5" />
                            <span>Save attendance</span>
                        </span>
                        <span x-show="saving" x-cloak class="flex items-center gap-2">
                            <svg class="animate-spin size-4 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>Saving...</span>
                        </span>
                    </button>

                    {{-- Mini Success Confirmation under button --}}
                    <div x-show="savedBanner || $wire.showSuccessBanner" x-cloak class="text-center pt-1 transition">
                        <span class="inline-flex items-center gap-1.5 text-xs font-bold text-emerald-600 dark:text-emerald-400">
                            <flux:icon icon="check-circle" class="size-4" />
                            Attendance saved successfully!
                        </span>
                    </div>
                </div>
            </div>

            {{-- Mobile Sticky Bottom Save Bar (Visible only on screens < lg) --}}
            <div class="lg:hidden fixed bottom-0 inset-x-0 z-30 bg-white/95 dark:bg-zinc-900/95 backdrop-blur-md border-t border-zinc-200 dark:border-zinc-800 p-3 px-4 shadow-lg flex items-center justify-between gap-3">
                <div class="flex items-center gap-2 text-xs font-semibold">
                    <span class="text-blue-600 dark:text-blue-400 font-bold" x-text="presentCount + ' Present'">{{ $this->presentCount }} Present</span>
                    <span class="text-zinc-300 dark:text-zinc-600">•</span>
                    <span class="text-rose-600 dark:text-rose-400 font-bold" x-text="absentCount + ' Absent'">{{ $this->absentCount }} Absent</span>
                </div>
                <button type="button"
                    @click="save()"
                    :disabled="saving"
                    class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 active:bg-blue-800 shadow-sm transition flex items-center gap-1.5 cursor-pointer disabled:opacity-50">
                    <flux:icon icon="check" class="size-3.5 stroke-[3]" />
                    <span x-text="saving ? 'Saving...' : 'Save attendance'">Save attendance</span>
                </button>
            </div>
        </div>
    @endif
</div>
