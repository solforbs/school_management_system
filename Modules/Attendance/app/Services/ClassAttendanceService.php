<?php

namespace Modules\Attendance\Services;

use App\Models\Devices;
use App\Models\TeacherClasses;
use App\Models\User;
use App\Services\SmsService;
use Athwari\LaravelZktecoAdms\Models\ZktecoAttendanceLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Attendance\Models\Attendance;
use Modules\Classes\Models\SchoolClass;
use Modules\Student\Models\StudentDetails;
use Modules\Subject\Models\SubjectTeacher;

class ClassAttendanceService
{
    /** @var array<string, Collection<int, array<string, mixed>>> */
    private array $registerCache = [];

    /** @var array<string, array<string, mixed>> */
    private array $metaCache = [];

    /** @var array<int, Collection<int, SchoolClass>> */
    private array $selectableClassesCache = [];

    /**
     * Resolve the class a user is authorized to manage or view attendance for.
     */
    public function resolveClassForUser(User $user, ?int $classId = null): ?SchoolClass
    {
        $selectable = $this->getSelectableClasses($user);

        if ($classId !== null) {
            return $selectable->firstWhere('id', $classId);
        }

        // For teachers: prioritize designated Homeroom class first, then first taught class
        if ($user->hasRole('Teacher')) {
            $homeroom = SchoolClass::where('class_teacher_id', $user->id)->first();
            if ($homeroom && $selectable->contains('id', $homeroom->id)) {
                return $homeroom;
            }
        }

        return $selectable->first();
    }

    /**
     * Whether the user may record roll-call (save, notify parents) for the class.
     * Teachers are limited to classes they are the designated class teacher of;
     * subject teachers keep read-only access. Other staff roles are unrestricted.
     */
    public function canRecord(User $user, SchoolClass $class): bool
    {
        if (! $user->hasRole('Teacher')) {
            return true;
        }

        return (int) $class->class_teacher_id === (int) $user->id;
    }

    /**
     * Get the classes selectable by this user.
     * Teachers see only their homeroom class and classes they teach.
     * Directors/Admins see all classes in the active institution.
     *
     * @return Collection<int, SchoolClass>
     */
    public function getSelectableClasses(User $user): Collection
    {
        if (isset($this->selectableClassesCache[$user->id])) {
            return $this->selectableClassesCache[$user->id];
        }

        $institutionId = currentInstitution()?->id
            ?? $user->teacherUserDetails?->institution_id
            ?? $user->active_institution_id;

        if (! $institutionId) {
            return collect();
        }

        if (! $user->hasRole('Teacher')) {
            return $this->selectableClassesCache[$user->id] = SchoolClass::where('institution_id', $institutionId)
                ->withCount('students')
                ->orderBy('name')
                ->get();
        }

        // Homeroom class
        $homeroomClassIds = SchoolClass::where('institution_id', $institutionId)
            ->where('class_teacher_id', $user->id)
            ->pluck('id');

        // Classes taught via subject teacher assignments
        $taughtClassIds = SubjectTeacher::where('teacher_id', $user->id)
            ->pluck('class_id');

        // Direct class assignments via teacher_classes pivot
        $pivotClassIds = TeacherClasses::where('teacher_id', $user->id)
            ->pluck('class_id');

        $accessibleClassIds = $homeroomClassIds
            ->concat($taughtClassIds)
            ->concat($pivotClassIds)
            ->filter()
            ->unique()
            ->values();

        return $this->selectableClassesCache[$user->id] = SchoolClass::whereIn('id', $accessibleClassIds)
            ->withCount('students')
            ->orderBy('name')
            ->get();
    }

    /**
     * Build the student roster and evaluate initial biometric / saved states for a given class and date.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getRegister(SchoolClass $class, string $date): Collection
    {
        $parsedDate = Carbon::parse($date)->toDateString();
        $cacheKey = "{$class->id}_{$parsedDate}";
        if (isset($this->registerCache[$cacheKey])) {
            return $this->registerCache[$cacheKey];
        }

        // 1. Get all active students enrolled in this class
        $studentDetails = StudentDetails::query()
            ->where('class_id', $class->id)
            ->where('is_active', true)
            ->with('student')
            ->get()
            ->sortBy(fn ($detail) => $detail->student?->name ?? '')
            ->values();

        if ($studentDetails->isEmpty()) {
            return $this->registerCache[$cacheKey] = collect();
        }

        $studentUserIds = $studentDetails->pluck('student_id')->filter()->unique()->values();

        // 2. Fetch existing saved Attendance records for this class and date
        $existingRecords = Attendance::query()
            ->where('school_class_id', $class->id)
            ->whereDate('date', $parsedDate)
            ->whereIn('student_id', $studentUserIds)
            ->with('device')
            ->get()
            ->keyBy('student_id');

        // 3. Fetch device check-ins for these students on this date
        $deviceIds = Devices::where('institution_id', $class->institution_id)
            ->whereNotNull('zkteco_device_id')
            ->pluck('zkteco_device_id');

        $gateLogs = collect();
        if ($deviceIds->isNotEmpty() && $studentUserIds->isNotEmpty()) {
            $gateLogs = ZktecoAttendanceLog::query()
                ->whereIn('device_id', $deviceIds)
                ->whereIn('pin', $studentUserIds->map(fn ($id) => (string) $id))
                ->whereDate('occurred_at', $parsedDate)
                ->with('device')
                ->orderBy('occurred_at')
                ->get()
                ->keyBy('pin');
        }

        // 4. Combine into display-ready student rows
        return $this->registerCache[$cacheKey] = $studentDetails->map(function (StudentDetails $detail, int $index) use ($existingRecords, $gateLogs) {
            $userId = $detail->student_id;
            $user = $detail->student;
            /** @var Attendance|null $saved */
            $saved = $existingRecords->get($userId);
            /** @var ZktecoAttendanceLog|null $gateLog */
            $gateLog = $gateLogs->get((string) $userId);

            $status = null;
            $source = null;
            $parentNotified = false;
            $scannedAtFormatted = null;
            $scannedAtRaw = null;
            $deviceName = null;
            $isSaved = false;

            if ($saved !== null) {
                $status = $saved->status;
                $source = $saved->source;
                $parentNotified = (bool) $saved->parent_notified;
                $scannedAtRaw = $saved->scanned_at;
                $scannedAtFormatted = $saved->scanned_at?->format('g:i A');
                $deviceName = $saved->device?->name ?? $saved->device?->serial_number;
                $isSaved = true;
            } elseif ($gateLog !== null) {
                // Device auto-ticking: gate scan exists, pre-tick as Present
                $status = 'present';
                $source = 'biometric';
                $parentNotified = false;
                $scannedAtRaw = $gateLog->occurred_at;
                $scannedAtFormatted = $gateLog->occurred_at?->format('g:i A');
                $deviceName = $gateLog->device?->name ?? $gateLog->device?->serial_number ?? 'Gate Terminal';
                $isSaved = false;
            }

            return [
                'index' => $index + 1,
                'student_id' => $userId,
                'name' => $user?->name ?? 'Learner #'.$detail->id,
                'admission_number' => $detail->admission_number ?? $detail->student_number ?? '—',
                'parent_name' => $detail->parent_name ?? $detail->guardian_name ?? 'Parent/Guardian',
                'parent_phone' => $detail->parent_phone ?? $detail->guardian_phone ?? null,
                'parent_email' => $detail->parent_email ?? $detail->guardian_email ?? null,
                'status' => $status, // 'present' | 'absent' | null
                'source' => $source, // 'biometric' | 'manual' | 'verified' | 'override' | null
                'parent_notified' => $parentNotified,
                'scanned_at' => $scannedAtFormatted,
                'scanned_at_raw' => $scannedAtRaw,
                'device_name' => $deviceName,
                'has_gate_scan' => $gateLog !== null,
                'is_saved' => $isSaved,
            ];
        });
    }

    /**
     * Persist attendance register with transactional consistency and source tracking.
     *
     * @param  array<int, array{status: string|null, parent_notified?: bool}>  $entries
     */
    public function saveRegister(
        SchoolClass $class,
        string $date,
        array $entries,
        User $recorder,
        ?string $notes = null
    ): void {
        $parsedDate = Carbon::parse($date)->toDateString();
        $studentUserIds = array_keys($entries);

        // Fetch gate scans to detect overrides / verifications
        $deviceIds = Devices::where('institution_id', $class->institution_id)
            ->whereNotNull('zkteco_device_id')
            ->pluck('zkteco_device_id');

        $gateLogs = collect();
        if ($deviceIds->isNotEmpty() && ! empty($studentUserIds)) {
            $gateLogs = ZktecoAttendanceLog::query()
                ->whereIn('device_id', $deviceIds)
                ->whereIn('pin', array_map('strval', $studentUserIds))
                ->whereDate('occurred_at', $parsedDate)
                ->orderBy('occurred_at')
                ->get()
                ->keyBy('pin');
        }

        DB::transaction(function () use ($class, $parsedDate, $entries, $recorder, $gateLogs, $notes) {
            foreach ($entries as $studentId => $payload) {
                $status = $payload['status'] ?? null;

                // Skip unselected / pending entries
                if (! in_array($status, ['present', 'absent', 'late', 'excused'], true)) {
                    continue;
                }

                $parentNotified = (bool) ($payload['parent_notified'] ?? false);
                /** @var ZktecoAttendanceLog|null $gateLog */
                $gateLog = $gateLogs->get((string) $studentId);

                // Determine single-source-of-truth status origin
                if ($status === 'present') {
                    $source = $gateLog !== null ? 'verified' : 'manual';
                } elseif ($status === 'absent') {
                    $source = $gateLog !== null ? 'override' : 'manual';
                } else {
                    $source = 'manual';
                }

                $deviceId = null;
                $scannedAt = null;

                if ($gateLog !== null) {
                    $deviceModel = Devices::where('zkteco_device_id', $gateLog->device_id)->first();
                    $deviceId = $deviceModel?->id;
                    $scannedAt = $gateLog->occurred_at;
                }

                Attendance::updateOrCreate(
                    [
                        'institution_id' => $class->institution_id,
                        'school_class_id' => $class->id,
                        'student_id' => (int) $studentId,
                        'date' => $parsedDate,
                    ],
                    [
                        'status' => $status,
                        'source' => $source,
                        'device_id' => $deviceId,
                        'scanned_at' => $scannedAt,
                        'recorded_by' => $recorder->id,
                        'parent_notified' => $status === 'absent' ? $parentNotified : false,
                        'notes' => $notes ? mb_substr($notes, 0, 255) : null,
                    ]
                );
            }
        });

        $this->registerCache = [];
        $this->metaCache = [];
    }

    /**
     * Get real-time biometric and roll-call session metadata for the register view.
     *
     * @return array<string, mixed>
     */
    public function getRegisterMeta(SchoolClass $class, string $date, ?User $viewer = null): array
    {
        $parsedDate = Carbon::parse($date)->toDateString();
        $viewer ??= auth()->user();
        $viewerId = $viewer?->id ?? 0;
        $cacheKey = "{$class->id}_{$parsedDate}_{$viewerId}";
        if (isset($this->metaCache[$cacheKey])) {
            return $this->metaCache[$cacheKey];
        }

        // Class teacher check
        $classTeacher = $class->classTeacher;
        $isClassTeacher = $viewer && ((int) $class->class_teacher_id === (int) $viewer->id);

        // Biometric device check for this institution
        $deviceIds = Devices::where('institution_id', $class->institution_id)
            ->whereNotNull('zkteco_device_id')
            ->pluck('zkteco_device_id');

        // Check active enrolled students in this class
        $studentUserIds = StudentDetails::where('class_id', $class->id)
            ->where('is_active', true)
            ->pluck('student_id')
            ->filter();

        $gateLogs = collect();
        if ($deviceIds->isNotEmpty() && $studentUserIds->isNotEmpty()) {
            $gateLogs = ZktecoAttendanceLog::query()
                ->whereIn('device_id', $deviceIds)
                ->whereIn('pin', $studentUserIds->map(fn ($id) => (string) $id))
                ->whereDate('occurred_at', $parsedDate)
                ->with('device')
                ->orderBy('occurred_at')
                ->get();
        }

        $earliestTime = null;
        $latestTime = null;
        $deviceName = null;

        if ($gateLogs->isNotEmpty()) {
            $firstLog = $gateLogs->first();
            $lastLog = $gateLogs->last();
            $earliestTime = $firstLog?->occurred_at?->format('g:i A');
            $latestTime = $lastLog?->occurred_at?->format('g:i A');
            $deviceName = $firstLog?->device?->name ?? $firstLog?->device?->serial_number ?? 'Main Gate Terminal';
        }

        // Saved roll-call status check
        $savedRecords = Attendance::where('school_class_id', $class->id)
            ->whereDate('date', $parsedDate)
            ->with('recorder')
            ->get();

        $isSaved = $savedRecords->isNotEmpty();
        $savedAt = null;
        $savedByName = null;
        $savedByIsClassTeacher = false;
        $savedPresentCount = 0;
        $savedAbsentCount = 0;

        if ($isSaved) {
            $firstSaved = $savedRecords->first();
            $savedAt = $firstSaved?->updated_at?->format('g:i A') ?? $firstSaved?->created_at?->format('g:i A');
            $savedByName = $firstSaved?->recorder?->name ?? 'Staff';
            $savedByIsClassTeacher = ((int) $firstSaved?->recorded_by === (int) $class->class_teacher_id);
            $savedPresentCount = $savedRecords->whereIn('status', ['present', 'late'])->count();
            $savedAbsentCount = $savedRecords->where('status', 'absent')->count();
        }

        return $this->metaCache[$cacheKey] = [
            'class_name' => $class->name,
            'class_teacher_name' => $classTeacher?->name,
            'is_current_user_class_teacher' => $isClassTeacher,
            'has_gate_scans' => $gateLogs->isNotEmpty(),
            'gate_scanned_count' => $gateLogs->pluck('pin')->unique()->count(),
            'gate_earliest_time' => $earliestTime,
            'gate_latest_time' => $latestTime,
            'gate_device_name' => $deviceName,
            'is_saved' => $isSaved,
            'saved_at' => $savedAt,
            'saved_by_name' => $savedByName,
            'saved_by_is_class_teacher' => $savedByIsClassTeacher,
            'saved_present_count' => $savedPresentCount,
            'saved_absent_count' => $savedAbsentCount,
        ];
    }

    /**
     * Dispatch an SMS absence notice to a parent after explicit confirmation.
     *
     * @return array{success: bool, message: string}
     */
    public function dispatchParentAbsenceSms(int $studentId, int $classId, string $date, string $customMessage, User $sender): array
    {
        $studentDetail = StudentDetails::where('student_id', $studentId)->first();
        if (! $studentDetail) {
            return ['success' => false, 'message' => 'Student record not found.'];
        }

        $phone = $studentDetail->parent_phone ?? $studentDetail->guardian_phone;
        if (! $phone) {
            return ['success' => false, 'message' => 'No parent or guardian phone number on file for this learner.'];
        }

        $smsService = app(SmsService::class);
        $result = $smsService->send((int) preg_replace('/\D/', '', $phone), $customMessage);

        if (! empty($result['success'])) {
            Attendance::updateOrCreate(
                [
                    'institution_id' => $sender->institution_id ?? $studentDetail->institution_id,
                    'school_class_id' => $classId,
                    'student_id' => $studentId,
                    'date' => Carbon::parse($date)->toDateString(),
                ],
                [
                    'status' => 'absent',
                    'source' => 'manual',
                    'parent_notified' => true,
                    'recorded_by' => $sender->id,
                ]
            );

            return ['success' => true, 'message' => 'Absence notice sent to '.$phone];
        }

        return ['success' => false, 'message' => $result['error'] ?? 'SMS dispatch failed.'];
    }
}
