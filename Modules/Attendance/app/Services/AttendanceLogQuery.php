<?php

namespace Modules\Attendance\Services;

use App\Models\Devices;
use App\Models\User;
use Athwari\LaravelZktecoAdms\Enums\AttendanceStatus;
use Athwari\LaravelZktecoAdms\Models\ZktecoAttendanceLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Student\Models\StudentDetails;

/**
 * Who may see which attendance logs, and how a log reads on screen.
 *
 * Shared by the Livewire log viewer and the JSON endpoint, so both answer
 * "may this person see this scan?" the same way.
 */
class AttendanceLogQuery
{
    /**
     * @param  array{device_id?: int|string|null, search?: string|null, from?: string|null, to?: string|null}  $filters
     */
    public static function build(int $institutionId, User $user, array $filters = []): Builder
    {
        // No isAdmin() bypass here on purpose: attendance is a Director-only
        // concern, and Admin no longer holds any 'view attendance'-family
        // permission (see PermissionSeeder), so every caller already aborts
        // before reaching this method. Institution scoping therefore applies
        // unconditionally to whoever does get here.
        abort_unless(self::allowedInstitutionIds($user)->contains($institutionId), 403);

        $deviceId = $filters['device_id'] ?? null;
        $search = trim((string) ($filters['search'] ?? ''));
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;

        $deviceIds = Devices::whereInstitutionId($institutionId)
            ->when($deviceId, fn ($q) => $q->where('id', $deviceId))
            ->whereNotNull('zkteco_device_id')
            ->pluck('zkteco_device_id');

        $pins = self::visiblePins($user);

        return ZktecoAttendanceLog::whereIn('device_id', $deviceIds)
            // A log only counts if it maps back to an actual student record.
            ->whereHas('zktecoUser.appUser.studentUserDetails')
            ->with(['device', 'zktecoUser.appUser.studentUserDetails'])
            ->when($pins !== null, fn ($q) => $q->whereIn('pin', $pins))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('pin', 'like', "%{$search}%")
                        ->orWhereHas('zktecoUser.appUser', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('zktecoUser.appUser.studentUserDetails', function ($q) use ($search) {
                            $q->where('admission_number', 'like', "%{$search}%")
                                ->orWhere('student_number', 'like', "%{$search}%");
                        });
                });
            })
            ->when(filled($from), fn ($q) => $q->whereDate('occurred_at', '>=', $from))
            ->when(filled($to), fn ($q) => $q->whereDate('occurred_at', '<=', $to));
    }

    /**
     * School staff - the roles that legitimately see every student's scan
     * for their institution. Everyone else (a guardian, a student, or an
     * account whose 'Parent' role was never set) is narrowed to specific
     * students by visiblePins(), never handed the whole institution.
     */
    private static function isSchoolStaff(User $user): bool
    {
        return $user->hasAnyRole(['Director', 'Accountant', 'Teacher']);
    }

    /**
     * The student user IDs a non-staff user is attached to: their own
     * children (as a guardian) and themselves (if they are a student).
     *
     * @return Collection<int, int>
     */
    private static function attachedStudentIds(User $user): Collection
    {
        $ids = $user->children()->pluck('student_id');

        if ($user->studentUserDetails()->exists()) {
            $ids->push($user->id);
        }

        return $ids->filter()->unique()->values();
    }

    /**
     * Institution IDs a non-admin user is allowed to view attendance for.
     *
     * @return Collection<int, int>
     */
    public static function allowedInstitutionIds(User $user): Collection
    {
        if ($user->hasRole('Teacher')) {
            return collect([$user->teacherUserDetails?->institution_id])->filter()->values();
        }

        if (self::isSchoolStaff($user)) {
            // Director/Accountant/other institution owners - just the one
            // currently active, not every institution they own.
            return collect([currentInstitution()?->id])->filter()->values();
        }

        // A guardian or student (or an account with the underlying links
        // but neither role): the institutions their own students sit in.
        // Fall back to their resolved institution so a valid in-context
        // request isn't 403'd; visiblePins() still narrows the rows to
        // nothing when there is no student attachment.
        $fromStudents = StudentDetails::query()
            ->where(function ($q) use ($user) {
                $q->where('parent_id', $user->id)->orWhere('student_id', $user->id);
            })
            ->pluck('institution_id')
            ->filter()
            ->unique()
            ->values();

        return $fromStudents->isNotEmpty()
            ? $fromStudents
            : collect([currentInstitution()?->id])->filter()->values();
    }

    /**
     * Device PINs (== app User IDs) this user may see attendance for, or
     * null when the user is school staff and so sees every student in the
     * institution. A non-staff user is always scoped to the students they
     * are attached to; if that set is empty they see nothing (an empty
     * array), never everything.
     *
     * @return string[]|null
     */
    public static function visiblePins(User $user): ?array
    {
        if (self::isSchoolStaff($user)) {
            return null;
        }

        return self::attachedStudentIds($user)
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    /**
     * Flatten a log into the display-ready row the log viewer shows.
     *
     * @return array<string, mixed>
     */
    public static function toDisplayRow(ZktecoAttendanceLog $log): array
    {
        $student = $log->zktecoUser->appUser;
        $studentDetails = $student->studentUserDetails;
        [$statusLabel, $statusColor] = self::statusMeta($log->status);

        return [
            'id' => $log->id,
            'student_name' => $student->name,
            'admission_number' => $studentDetails->admission_number ?? '—',
            'device_name' => $log->device?->name ?? $log->device?->serial_number ?? '—',
            'status_label' => $statusLabel,
            'status_color' => $statusColor,
            'verify_mode_label' => $log->verify_mode?->getLabel() ?? 'Unknown',
            'occurred_at' => $log->occurred_at?->format('M j, Y g:i A') ?? '—',
        ];
    }

    /**
     * [Label, Flux badge color] for a status. Not using
     * AttendanceStatus::getLabel() here because it resolves via a
     * translation key this app has no translations for, and would print the
     * raw key.
     *
     * @return array{0: string, 1: string}
     */
    public static function statusMeta(?AttendanceStatus $status): array
    {
        return match ($status) {
            AttendanceStatus::CheckIn => ['Check In', 'emerald'],
            AttendanceStatus::CheckOut => ['Check Out', 'red'],
            AttendanceStatus::BreakOut => ['Break Out', 'amber'],
            AttendanceStatus::BreakIn => ['Break In', 'sky'],
            AttendanceStatus::OvertimeIn => ['Overtime In', 'indigo'],
            AttendanceStatus::OvertimeOut => ['Overtime Out', 'zinc'],
            default => ['Unknown', 'zinc'],
        };
    }
}
