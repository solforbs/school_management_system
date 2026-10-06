<?php

use App\Services\SmsService;
use Carbon\Carbon;
use Flux\Flux;
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
            ->whereDate('date', Carbon::parse($this->date)->toDateString())
            ->whereNotNull('notes')
            ->value('notes');

        $this->notes = $existingNote ?? '';
    }

    #[Computed]
    public function selectableClasses(): Collection
    {
        return app(ClassAttendanceService::class)->getSelectableClasses(auth()->user());
    }

    /**
     * Teachers may only record roll-call for a class they are the designated
     * class teacher of. Subject teachers get a read-only register. Directors,
     * Admins and other staff keep full access.
     */
    #[Computed]
    public function canRecord(): bool
    {
        $class = $this->currentClass;

        return $class !== null
            && app(ClassAttendanceService::class)->canRecord(auth()->user(), $class);
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
        abort_unless($this->canRecord, 403, 'Only the class teacher can update attendance.');

        $this->parentNotified[$studentId] = ! ($this->parentNotified[$studentId] ?? false);
        $this->showSuccessBanner = false;

        $class = $this->currentClass;
        if ($class) {
            Attendance::where('school_class_id', $class->id)
                ->where('student_id', $studentId)
                ->whereDate('date', Carbon::parse($this->date)->toDateString())
                ->update(['parent_notified' => (bool) $this->parentNotified[$studentId]]);
        }
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
        abort_unless($this->canRecord, 403, 'Only the class teacher can record attendance.');

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
        Flux::toast(text: $msg, variant: 'success');
        $this->showSuccessBanner = true;
        $this->successMessage = $msg;
    }

    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function registerMeta(): array
    {
        $class = $this->currentClass;
        if (! $class) {
            return [];
        }

        return app(ClassAttendanceService::class)->getRegisterMeta($class, $this->date, auth()->user());
    }

    /**
     * Dispatch parent absence SMS and return detailed status.
     *
     * @return array{success: bool, message: string}
     */
    public function sendParentSms(int $studentId, string $message): array
    {
        $class = $this->currentClass;
        abort_unless($class, 404, 'No class selected');
        abort_unless($this->canRecord, 403, 'Only the class teacher can notify parents.');

        $result = app(ClassAttendanceService::class)->dispatchParentAbsenceSms(
            $studentId,
            $class->id,
            $this->date,
            $message,
            auth()->user()
        );

        if ($result['success']) {
            $this->parentNotified[$studentId] = true;
            Flux::toast(text: $result['message'], variant: 'success');
        } else {
            Flux::toast(text: $result['message'], variant: 'danger');
        }

        return $result;
    }

    public function markParentNotifiedManually(int $studentId): void
    {
        $class = $this->currentClass;
        abort_unless($class, 404, 'No class selected');
        abort_unless($this->canRecord, 403, 'Only the class teacher can notify parents.');

        $this->parentNotified[$studentId] = true;

        $institutionId = currentInstitution()?->id
            ?? auth()->user()->teacherUserDetails?->institution_id
            ?? auth()->user()->active_institution_id
            ?? 0;

        Attendance::updateOrCreate(
            [
                'institution_id' => $institutionId,
                'school_class_id' => $class->id,
                'student_id' => $studentId,
                'date' => Carbon::parse($this->date)->toDateString(),
            ],
            [
                'status' => 'absent',
                'source' => 'manual',
                'parent_notified' => true,
                'recorded_by' => auth()->id(),
            ]
        );

        Flux::toast(text: 'Marked as notified via direct contact (call/email).', variant: 'success');
    }

    #[Computed]
    public function isSmsConfigured(): bool
    {
        return app(SmsService::class)->isConfigured();
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

    {{-- Class Selection Switcher & Class Teacher Identification --}}
    @php $meta = $this->registerMeta; @endphp
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-zinc-50 dark:bg-zinc-900/60 p-3 sm:p-3.5 rounded-xl border border-zinc-200 dark:border-zinc-800 text-xs">
        @if ($this->selectableClasses->count() > 1)
            <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3 w-full sm:w-auto">
                <span class="font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider text-[11px] sm:text-xs shrink-0">Switch Class:</span>
                <div class="flex flex-wrap gap-2 w-full sm:w-auto">
                    @foreach ($this->selectableClasses as $classOption)
                        <button type="button"
                            wire:click="$set('classId', {{ $classOption->id }})"
                            wire:loading.attr="disabled"
                            class="inline-flex items-center justify-center gap-1.5 flex-1 sm:flex-none px-3 py-2 sm:py-1.5 rounded-lg text-xs font-semibold transition border cursor-pointer {{ $this->classId === $classOption->id ? 'bg-blue-600 text-white border-blue-600 shadow-xs' : 'bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border-zinc-200 dark:border-zinc-700 hover:bg-zinc-100 dark:hover:bg-zinc-700' }}">
                            <flux:icon wire:loading wire:target="$set('classId', {{ $classOption->id }})" icon="arrow-path" class="size-3 animate-spin shrink-0" />
                            <span>{{ $classOption->name }}</span>
                            <span class="opacity-80 font-normal">({{ $classOption->students_count }})</span>
                        </button>
                    @endforeach
                </div>
            </div>
        @else
            <div></div>
        @endif

        {{-- Class Teacher Identification Badge --}}
        <div class="flex items-center gap-2 shrink-0 pt-2 sm:pt-0 border-t sm:border-t-0 border-zinc-200/80 dark:border-zinc-800">
            @if ($meta['is_current_user_class_teacher'] ?? false)
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-bold bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300 border border-blue-200 dark:border-blue-800 shadow-2xs">
                    <flux:icon icon="star" class="size-3.5 text-blue-600 dark:text-blue-400" />
                    <span>Homeroom Class Teacher (You)</span>
                </span>
            @elseif (!empty($meta['class_teacher_name']))
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs text-zinc-600 dark:text-zinc-400 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700">
                    <flux:icon icon="user" class="size-3.5 text-zinc-400" />
                    <span>Class Teacher: <strong class="text-zinc-900 dark:text-white font-semibold">{{ $meta['class_teacher_name'] }}</strong></span>
                </span>
            @endif
        </div>
    </div>

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
            readOnly: @js(! $this->canRecord),
            savedBanner: false,
            savedMessage: '',

            setStatus(id, val) {
                if (this.readOnly) return;
                this.statuses[id] = (this.statuses[id] === val ? null : val);
            },
            markAllPresent() {
                if (this.readOnly) return;
                this.allStudentIds.forEach(id => {
                    this.statuses[id] = 'present';
                });
            },
            clearAll() {
                if (this.readOnly) return;
                this.allStudentIds.forEach(id => {
                    this.statuses[id] = null;
                });
            },
            async toggleNotified(id) {
                const nextVal = !Boolean(this.notified[id]);
                this.notified[id] = nextVal;
                await $wire.toggleParentNotified(id);
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
            notifyModal: false,
            notifyStudent: { student_id: null, name: '', admission_number: '', parent_name: '', parent_phone: '', parent_email: '' },
            notifyMessage: '',
            notifySending: false,
            notifyError: null,
            isSmsConfigured: @js($this->isSmsConfigured),

            openNotifyModal(student) {
                this.notifyStudent = student;
                this.notifyError = null;
                const parent = student.parent_name || 'Parent/Guardian';
                const school = '{{ addslashes(currentInstitution()?->name ?? 'Our School') }}';
                const cls = '{{ addslashes($this->currentClass?->name ?? 'Class') }}';
                const dt = '{{ Carbon::parse($this->date)->format('D, j M Y') }}';
                this.notifyMessage = `Dear ${parent}, please be informed that ${student.name} (#${student.admission_number}) was marked absent from ${cls} today (${dt}). Please contact ${school} if you are unaware of this absence.`;
                this.notifyModal = true;
            },
            async confirmSendSms() {
                if (!this.notifyStudent.student_id || !this.notifyStudent.parent_phone) return;
                this.notifySending = true;
                this.notifyError = null;
                try {
                    const result = await $wire.sendParentSms(this.notifyStudent.student_id, this.notifyMessage);
                    if (result && result.success) {
                        this.notified[this.notifyStudent.student_id] = true;
                        this.notifyModal = false;
                    } else {
                        this.notifyError = result?.message || 'SMS delivery failed. SMS gateway credentials are not configured.';
                    }
                } catch (e) {
                    console.error('SMS send error:', e);
                    this.notifyError = e?.message || 'Unable to dispatch SMS due to server error.';
                } finally {
                    this.notifySending = false;
                }
            },
            markNotifiedDirectly() {
                if (!this.notifyStudent.student_id) return;
                this.notified[this.notifyStudent.student_id] = true;
                $wire.markParentNotifiedManually(this.notifyStudent.student_id);
                this.notifyModal = false;
            },
            async save() {
                if (this.readOnly) return;
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

            @unless ($this->canRecord)
                <div class="rounded-xl p-3.5 bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/60 flex items-start gap-3 text-amber-900 dark:text-amber-200">
                    <flux:icon icon="eye" class="size-5 text-amber-600 shrink-0 mt-0.5" />
                    <p class="text-xs sm:text-sm">
                        <strong>View only.</strong>
                        Daily roll-call for {{ $this->currentClass?->name }} is recorded by the class teacher. You can review attendance for the classes you teach but cannot change it.
                    </p>
                </div>
            @endunless

            {{-- Top Alert Banner (Alpine + Livewire Synchronized) --}}
            <div x-show="savedBanner || $wire.showSuccessBanner" x-cloak class="rounded-xl p-4 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 flex items-center justify-between text-emerald-800 dark:text-emerald-200 transition">
                <div class="flex items-center gap-2.5 text-xs sm:text-sm font-semibold">
                    <flux:icon icon="check-circle" class="size-5 text-emerald-600 shrink-0" />
                    <span x-text="savedMessage || $wire.successMessage">{{ $this->successMessage ?: 'Attendance saved successfully!' }}</span>
                </div>
                <button type="button" @click="savedBanner = false; $wire.set('showSuccessBanner', false)" class="text-emerald-700 hover:text-emerald-900 text-sm font-bold cursor-pointer">×</button>
            </div>

            {{-- Biometric vs Manual Intelligent Mode & Session Context Banner --}}
            @if ($meta['is_saved'] ?? false)
                <div class="rounded-2xl p-4 sm:p-5 bg-emerald-50/80 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/60 text-emerald-900 dark:text-emerald-100 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 shadow-xs">
                    <div class="flex items-start gap-3">
                        <div class="size-10 rounded-xl bg-emerald-600/10 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0 mt-0.5">
                            <flux:icon icon="check-circle" class="size-5" />
                        </div>
                        <div class="space-y-0.5">
                            <h4 class="text-sm font-bold text-emerald-950 dark:text-white">
                                Class Roll-Call Already Recorded
                            </h4>
                            <p class="text-xs text-emerald-700 dark:text-emerald-300">
                                Roll-call completed at <strong>{{ $meta['saved_at'] }}</strong> by <strong>{{ $meta['saved_by_name'] }}</strong> ({{ $meta['saved_by_is_class_teacher'] ? 'Class Teacher' : 'Teacher' }}). {{ $meta['saved_present_count'] }} Present, {{ $meta['saved_absent_count'] }} Absent.
                                @if ($meta['has_gate_scans'] ?? false)
                                    Synced with {{ $meta['gate_scanned_count'] }} biometric gate check-in{{ $meta['gate_scanned_count'] === 1 ? '' : 's' }} ({{ $meta['gate_earliest_time'] }} – {{ $meta['gate_latest_time'] }}).
                                @endif
                                You can adjust any entries and re-save if needed.
                            </p>
                        </div>
                    </div>
                </div>
            @elseif ($meta['has_gate_scans'] ?? false)
                <div class="rounded-2xl p-4 sm:p-5 bg-sky-50/80 dark:bg-sky-950/30 border border-sky-200 dark:border-sky-800/60 text-sky-900 dark:text-sky-100 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 shadow-xs">
                    <div class="flex items-start gap-3">
                        <div class="size-10 rounded-xl bg-sky-600/10 dark:bg-sky-500/20 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0 mt-0.5">
                            <flux:icon icon="bolt" class="size-5" />
                        </div>
                        <div class="space-y-0.5">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h4 class="text-sm font-bold text-sky-950 dark:text-white">
                                    Biometric Gate Check-Ins Detected
                                </h4>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-sky-100 text-sky-800 dark:bg-sky-900 dark:text-sky-200">
                                    {{ $meta['gate_scanned_count'] }} of {{ $totalCount }} Checked In
                                </span>
                            </div>
                            <p class="text-xs text-sky-700 dark:text-sky-300">
                                Student attendance recorded using biometrics between <strong>{{ $meta['gate_earliest_time'] }}</strong> and <strong>{{ $meta['gate_latest_time'] }}</strong> through <strong>{{ $meta['gate_device_name'] }}</strong>. Verified learners are pre-ticked below. Confirm this attendance and adjust any absentees.
                            </p>
                        </div>
                    </div>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white dark:bg-zinc-800 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-700 text-xs font-bold shadow-2xs whitespace-nowrap shrink-0">
                        <flux:icon icon="check" class="size-3.5 text-sky-600 stroke-[3]" />
                        Pre-ticked · Confirm & Save
                    </span>
                </div>
            @else
                <div class="rounded-2xl p-4 sm:p-5 bg-amber-50/80 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/60 text-amber-900 dark:text-amber-100 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 shadow-xs">
                    <div class="flex items-start gap-3">
                        <div class="size-10 rounded-xl bg-amber-600/10 dark:bg-amber-500/20 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0 mt-0.5">
                            <flux:icon icon="exclamation-triangle" class="size-5" />
                        </div>
                        <div class="space-y-0.5">
                            <h4 class="text-sm font-bold text-amber-950 dark:text-white">
                                No Biometric Scans Recorded Today
                            </h4>
                            <p class="text-xs text-amber-700 dark:text-amber-300">
                                No gate check-ins recorded for this class on {{ Carbon::parse($this->date)->format('D, j M Y') }} (device offline, disconnected, or manual roll-call day). Please call out names and tick learners present manually below.
                            </p>
                        </div>
                    </div>
                    <button type="button" @click="markAllPresent()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white dark:bg-zinc-800 text-amber-800 dark:text-amber-200 border border-amber-200 dark:border-amber-700 text-xs font-bold shadow-2xs hover:bg-amber-50 dark:hover:bg-zinc-700 transition cursor-pointer shrink-0">
                        <flux:icon icon="check" class="size-3.5 text-amber-600 stroke-[3]" />
                        Mark All Present
                    </button>
                </div>
            @endif

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

                        @if ($this->canRecord)
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
                        @endif
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
                                @php
                                    $aId = (int) $absentee['student_id'];
                                    $hasPhone = !empty($absentee['parent_phone']);
                                @endphp
                                <div x-show="statuses[{{ $aId }}] === 'absent'" x-cloak class="pt-2.5 pb-1 flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-xs font-bold text-zinc-900 dark:text-white truncate">
                                            {{ $absentee['name'] }}
                                        </p>
                                        <div class="flex items-center gap-1.5 text-[11px] text-zinc-400">
                                            <span class="font-mono">#{{ $absentee['admission_number'] }}</span>
                                            @if($hasPhone)
                                                <span>•</span>
                                                <span class="text-zinc-500 font-mono text-[10px]">{{ $absentee['parent_phone'] }}</span>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Notify Action Button with Direct Fallbacks and Confirmation Modal --}}
                                    <div class="flex items-center gap-1.5 shrink-0">
                                        <template x-if="notified[{{ $aId }}]">
                                            <div class="flex items-center gap-1">
                                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                                    <flux:icon icon="check" class="size-3 stroke-[3]" />
                                                    <span>Notified</span>
                                                </span>
                                                <button type="button"
                                                    @click="toggleNotified({{ $aId }})"
                                                    title="Click to reset / mark unnotified"
                                                    class="size-6 rounded-full text-zinc-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 flex items-center justify-center transition cursor-pointer">
                                                    <flux:icon icon="x-mark" class="size-3" />
                                                </button>
                                            </div>
                                        </template>
                                        <template x-if="!notified[{{ $aId }}]">
                                            <div class="flex items-center gap-1">
                                                @if($hasPhone)
                                                    <a href="tel:{{ $absentee['parent_phone'] }}" title="Call parent: {{ $absentee['parent_phone'] }}"
                                                        class="size-7 rounded-lg text-zinc-500 hover:text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 border border-zinc-200 dark:border-zinc-700 flex items-center justify-center transition">
                                                        <flux:icon icon="phone" class="size-3.5" />
                                                    </a>
                                                @endif
                                                @if(!empty($absentee['parent_email']))
                                                    <a href="mailto:{{ $absentee['parent_email'] }}?subject={{ rawurlencode('Absence Notice: '.$absentee['name']) }}" title="Email parent: {{ $absentee['parent_email'] }}"
                                                        class="size-7 rounded-lg text-zinc-500 hover:text-sky-600 hover:bg-sky-50 dark:hover:bg-sky-950/40 border border-zinc-200 dark:border-zinc-700 flex items-center justify-center transition">
                                                        <flux:icon icon="envelope" class="size-3.5" />
                                                    </a>
                                                @endif
                                                @if ($this->canRecord)
                                                <button type="button"
                                                    @click="openNotifyModal({{ json_encode($absentee) }})"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-bold text-blue-700 dark:text-blue-300 bg-blue-50 dark:bg-blue-950/40 hover:bg-blue-100 dark:hover:bg-blue-900/60 border border-blue-200/80 dark:border-blue-800 transition cursor-pointer">
                                                    <flux:icon icon="chat-bubble-left-ellipsis" class="size-3.5" />
                                                    <span>Notify</span>
                                                </button>
                                                @endif
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    @if ($this->canRecord)
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
                    @endif
                </div>
            </div>

            @if ($this->canRecord)
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
            @endif

            {{-- Parent Absence Notification Preview & Confirmation Modal --}}
            <div x-show="notifyModal" x-cloak
                class="fixed inset-0 z-50 overflow-y-auto"
                aria-labelledby="notify-modal-title" role="dialog" aria-modal="true">
                {{-- Backdrop --}}
                <div class="fixed inset-0 bg-zinc-900/60 backdrop-blur-xs transition-opacity"
                    @click="if(!notifySending) notifyModal = false"></div>

                <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
                    <div class="relative transform overflow-hidden rounded-2xl bg-white dark:bg-zinc-900 text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-zinc-200 dark:border-zinc-800 p-6 space-y-5"
                        @click.outside="if(!notifySending) notifyModal = false">

                        {{-- Header --}}
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <div class="size-10 rounded-xl bg-blue-50 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
                                    <flux:icon icon="chat-bubble-left-ellipsis" class="size-5" />
                                </div>
                                <div>
                                    <h3 class="text-base font-bold text-zinc-900 dark:text-white" id="notify-modal-title">
                                        Parent Absence Notification
                                    </h3>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                        Review and confirm SMS before dispatching
                                    </p>
                                </div>
                            </div>
                            <button type="button" @click="if(!notifySending) notifyModal = false"
                                class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 p-1 rounded-lg">
                                <flux:icon icon="x-mark" class="size-5" />
                            </button>
                        </div>

                        {{-- Recipient Card --}}
                        <div class="rounded-xl p-3.5 bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200/80 dark:border-zinc-700/60 space-y-2">
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-zinc-500 font-medium">Student:</span>
                                <span class="font-bold text-zinc-900 dark:text-white" x-text="notifyStudent.name + ' (#' + notifyStudent.admission_number + ')'"></span>
                            </div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-zinc-500 font-medium">Parent / Guardian:</span>
                                <span class="font-bold text-zinc-900 dark:text-white" x-text="notifyStudent.parent_name || 'Primary Guardian'"></span>
                            </div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-zinc-500 font-medium">Recipient Phone:</span>
                                <template x-if="notifyStudent.parent_phone">
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono font-bold text-blue-600 dark:text-blue-400" x-text="notifyStudent.parent_phone"></span>
                                        <a :href="'tel:' + notifyStudent.parent_phone"
                                            title="Call parent directly"
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/50 hover:bg-emerald-100 border border-emerald-200 dark:border-emerald-800 transition">
                                            <flux:icon icon="phone" class="size-3" />
                                            <span>Call</span>
                                        </a>
                                    </div>
                                </template>
                                <template x-if="!notifyStudent.parent_phone">
                                    <span class="font-bold text-rose-600 dark:text-rose-400 flex items-center gap-1">
                                        <flux:icon icon="exclamation-triangle" class="size-3.5" />
                                        No phone number on file
                                    </span>
                                </template>
                            </div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-zinc-500 font-medium">Recipient Email:</span>
                                <template x-if="notifyStudent.parent_email">
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono text-zinc-700 dark:text-zinc-300" x-text="notifyStudent.parent_email"></span>
                                        <a :href="'mailto:' + notifyStudent.parent_email + '?subject=' + encodeURIComponent('Absence Notice: ' + notifyStudent.name) + '&body=' + encodeURIComponent(notifyMessage)"
                                            title="Email parent directly"
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold text-sky-700 dark:text-sky-300 bg-sky-50 dark:bg-sky-950/50 hover:bg-sky-100 border border-sky-200 dark:border-sky-800 transition">
                                            <flux:icon icon="envelope" class="size-3" />
                                            <span>Email</span>
                                        </a>
                                    </div>
                                </template>
                                <template x-if="!notifyStudent.parent_email">
                                    <span class="text-zinc-400 text-[11px]">No email on file</span>
                                </template>
                            </div>
                        </div>

                        {{-- Warning if no phone --}}
                        <div x-show="!notifyStudent.parent_phone" class="rounded-xl p-3 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/60 text-xs text-rose-700 dark:text-rose-300">
                            This student has no parent or guardian phone number registered in their profile. Please update the student record before sending SMS.
                        </div>

                        {{-- SMS Gateway Failure / Disconnection Alert with Direct Fallbacks --}}
                        <div x-show="notifyError" x-cloak class="rounded-xl p-3.5 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 space-y-2.5">
                            <div class="flex items-start gap-2.5">
                                <flux:icon icon="exclamation-circle" class="size-4 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" />
                                <div class="text-xs space-y-1">
                                    <p class="font-bold text-rose-800 dark:text-rose-200">SMS Dispatch Failed / Gateway Not Configured</p>
                                    <p class="text-rose-700 dark:text-rose-300" x-text="notifyError"></p>
                                    <p class="text-zinc-600 dark:text-zinc-300 font-medium pt-0.5">
                                        First priority is SMS, but you can contact the parent directly right now:
                                    </p>
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center gap-2 pt-1 border-t border-rose-200/80 dark:border-rose-800/80">
                                <template x-if="notifyStudent.parent_phone">
                                    <a :href="'tel:' + notifyStudent.parent_phone"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 shadow-xs transition">
                                        <flux:icon icon="phone" class="size-3.5" />
                                        <span>Call Parent Now (<span x-text="notifyStudent.parent_phone"></span>)</span>
                                    </a>
                                </template>
                                <template x-if="notifyStudent.parent_email">
                                    <a :href="'mailto:' + notifyStudent.parent_email + '?subject=' + encodeURIComponent('Absence Notice: ' + notifyStudent.name) + '&body=' + encodeURIComponent(notifyMessage)"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold text-white bg-sky-600 hover:bg-sky-700 shadow-xs transition">
                                        <flux:icon icon="envelope" class="size-3.5" />
                                        <span>Email Parent (<span x-text="notifyStudent.parent_email"></span>)</span>
                                    </a>
                                </template>
                                <button type="button" @click="markNotifiedDirectly()"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold text-zinc-700 dark:text-zinc-200 bg-white dark:bg-zinc-800 hover:bg-zinc-100 dark:hover:bg-zinc-700 border border-zinc-200 dark:border-zinc-700 transition cursor-pointer">
                                    <flux:icon icon="check" class="size-3.5" />
                                    <span>Mark as Notified (Direct Contact)</span>
                                </button>
                            </div>
                        </div>

                        {{-- Notice if SMS gateway is unconfigured before dispatch --}}
                        <div x-show="!isSmsConfigured && !notifyError" class="rounded-xl p-3 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/70 text-xs text-amber-800 dark:text-amber-200 flex items-start gap-2.5">
                            <flux:icon icon="information-circle" class="size-4 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
                            <div>
                                <span class="font-bold">SMS credentials pending:</span> SMS gateway credentials (HostPinnacle) are not yet configured in system settings. Priority 1 is SMS, but you can also call or email the parent directly using the shortcuts below.
                            </div>
                        </div>

                        {{-- Message Preview & Edit --}}
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <label for="sms-preview-message" class="text-xs font-bold text-zinc-900 dark:text-white">
                                    SMS Message Content
                                </label>
                                <span class="text-[11px] text-zinc-400 font-mono" x-text="(notifyMessage?.length || 0) + ' chars · ' + Math.max(1, Math.ceil((notifyMessage?.length || 0) / 160)) + ' SMS'"></span>
                            </div>
                            <textarea id="sms-preview-message"
                                x-model="notifyMessage"
                                rows="4"
                                :disabled="notifySending || !notifyStudent.parent_phone"
                                class="w-full text-xs rounded-xl border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-zinc-900 dark:text-white placeholder:text-zinc-400 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition resize-none"></textarea>
                            <p class="text-[11px] text-zinc-500 dark:text-zinc-400">
                                You can customize the wording above. Standard SMS rates apply.
                            </p>
                        </div>

                        {{-- Action Buttons --}}
                        <div class="flex flex-col-reverse sm:flex-row sm:items-center justify-between gap-3 pt-2">
                            <div class="flex items-center gap-2">
                                <template x-if="notifyStudent.parent_phone">
                                    <a :href="'tel:' + notifyStudent.parent_phone"
                                        title="Call parent directly"
                                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/40 hover:bg-emerald-100 dark:hover:bg-emerald-900/60 border border-emerald-200 dark:border-emerald-800 transition">
                                        <flux:icon icon="phone" class="size-3.5" />
                                        <span>Call</span>
                                    </a>
                                </template>
                                <template x-if="notifyStudent.parent_email">
                                    <a :href="'mailto:' + notifyStudent.parent_email + '?subject=' + encodeURIComponent('Absence Notice: ' + notifyStudent.name) + '&body=' + encodeURIComponent(notifyMessage)"
                                        title="Email parent directly"
                                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold text-sky-700 dark:text-sky-300 bg-sky-50 dark:bg-sky-950/40 hover:bg-sky-100 dark:hover:bg-sky-900/60 border border-sky-200 dark:border-sky-800 transition">
                                        <flux:icon icon="envelope" class="size-3.5" />
                                        <span>Email</span>
                                    </a>
                                </template>
                            </div>

                            <div class="flex items-center justify-end gap-2">
                                <button type="button"
                                    @click="if(!notifySending) notifyModal = false"
                                    :disabled="notifySending"
                                    class="px-4 py-2 rounded-xl text-xs font-bold text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 transition cursor-pointer disabled:opacity-50">
                                    Cancel
                                </button>

                                <button type="button"
                                    @click="confirmSendSms()"
                                    :disabled="notifySending || !notifyStudent.parent_phone"
                                    class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 active:bg-blue-800 shadow-sm transition flex items-center gap-2 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                                    <span x-show="!notifySending" class="flex items-center gap-1.5">
                                        <flux:icon icon="paper-airplane" class="size-3.5" />
                                        <span>Confirm & Send SMS</span>
                                    </span>
                                    <span x-show="notifySending" x-cloak class="flex items-center gap-1.5">
                                        <svg class="animate-spin size-3.5 text-white" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span>Sending SMS...</span>
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
