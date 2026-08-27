<?php

namespace Modules\Attendance\Services;

use App\Models\Devices;
use App\Models\User;
use Athwari\LaravelZktecoAdms\Enums\AttendanceStatus;
use Athwari\LaravelZktecoAdms\Models\ZktecoAttendanceLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

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
        if (! isAdmin()) {
            abort_unless(self::allowedInstitutionIds($user)->contains($institutionId), 403);
        }

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
     * Institution IDs a non-admin user is allowed to view attendance for.
     *
     * @return Collection<int, int>
     */
    public static function allowedInstitutionIds(User $user): Collection
    {
        if ($user->hasRole('Parent')) {
            return $user->children()->pluck('institution_id')->filter()->unique()->values();
        }

        if ($user->hasRole('Student')) {
            return collect([$user->studentUserDetails?->institution_id])->filter()->values();
        }

        if ($user->hasRole('Teacher')) {
            return collect([$user->teacherUserDetails?->institution_id])->filter()->values();
        }

        // Director/Accountant/other institution owners - just the one
        // currently active, not every institution they own.
        return collect([currentInstitution()?->id])->filter()->values();
    }

    /**
     * Device PINs (== app User IDs) this user may see attendance for, or
     * null when the role isn't restricted to specific students (e.g. a
     * Director sees every student in the institution).
     *
     * @return string[]|null
     */
    public static function visiblePins(User $user): ?array
    {
        if ($user->hasRole('Parent')) {
            return $user->children()->pluck('student_id')->map(fn ($id) => (string) $id)->all();
        }

        if ($user->hasRole('Student')) {
            return [(string) $user->id];
        }

        return null;
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
