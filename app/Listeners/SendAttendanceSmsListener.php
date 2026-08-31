<?php

namespace App\Listeners;

use App\Services\SmsService;
use Athwari\LaravelZktecoAdms\DTOs\AttendanceRecord;
use Athwari\LaravelZktecoAdms\Enums\AttendanceStatus;
use Athwari\LaravelZktecoAdms\Events\AttendanceReceived;
use Athwari\LaravelZktecoAdms\Models\ZktecoUser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Texts a student's parent every time the student punches a ZKTeco
 * biometric device (check-in/out, break, overtime), mirroring
 * SyncStudentsToDeviceListener's pattern of reacting to device events.
 */
class SendAttendanceSmsListener
{
    public function __construct(private readonly SmsService $smsService) {}

    public function handle(AttendanceReceived $event): void
    {
        if (! featureEnabled('sms')) {
            return;
        }

        foreach ($event->records as $record) {
            $this->notifyParent($record);
        }
    }

    private function notifyParent(AttendanceRecord $record): void
    {
        $student = ZktecoUser::where('pin', $record->pin)->first()?->appUser;

        if (! $student) {
            return;
        }

        /*
     * Prevent the same biometric attendance event from sending
     * the parent an SMS more than once.
     *
     * The key is based on the actual attendance event, not merely
     * the student, so legitimate later punches can still send.
     */
        $timestamp = $record->timestamp->format('Y-m-d H:i:s');
        $status = $record->statusEnum()->value;

        $lockKey = "attendance-sms:{$student->id}:{$timestamp}:{$status}";

        if (! Cache::add($lockKey, true, now()->addMinutes(10))) {
            Log::debug('Duplicate attendance SMS prevented', [
                'student_id' => $student->id,
                'timestamp' => $timestamp,
                'status' => $status,
            ]);

            return;
        }

        $phone = $student->studentParent?->parent?->parent_phone;

        if (! $phone) {
            return;
        }

        $message = sprintf(
            'Dear Parent, %s %s on %s.',
            $student->name,
            $this->punchLabel($record),
            $record->timestamp->format('d M Y, h:i A'),
        );

        $result = $this->smsService->send(
            (int) preg_replace('/\D/', '', $phone),
            $message
        );

        if (! ($result['success'] ?? false)) {
            Log::warning('Attendance SMS failed', [
                'student_id' => $student->id,
                'error' => $result['error'] ?? null,
            ]);
        }
    }

    private function punchLabel(AttendanceRecord $record): string
    {
        return match ($record->statusEnum()) {
            AttendanceStatus::CheckIn => 'checked in',
            AttendanceStatus::CheckOut => 'checked out',
            AttendanceStatus::BreakOut => 'went on break',
            AttendanceStatus::BreakIn => 'returned from break',
            AttendanceStatus::OvertimeIn => 'started overtime',
            AttendanceStatus::OvertimeOut => 'ended overtime',
            default => 'recorded attendance',
        };
    }
}
