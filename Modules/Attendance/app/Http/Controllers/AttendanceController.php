<?php

namespace Modules\Attendance\Http\Controllers;

use App\Http\Controllers\Concerns\Sortable;
use App\Http\Controllers\Controller;
use App\Models\Devices;
use Athwari\LaravelZktecoAdms\Models\ZktecoAttendanceLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Attendance\Services\AttendanceLogQuery;
use Modules\Institution\Models\Institution;

class AttendanceController extends Controller
{
    use Sortable;

    /**
     * Export attendance logs to CSV stream with active filters applied.
     */
    public function export(Request $request)
    {
        abort_unless(Auth::user()->can('view attendance'), 403);

        $user = Auth::user();
        $institutionId = currentInstitution()?->id;
        $institution = $institutionId ? Institution::find($institutionId) : null;

        abort_unless($institution, 404, 'No institution found.');

        $query = AttendanceLogQuery::build($institution->id, $user, $request->only(['device_id', 'search', 'from', 'to']))
            ->orderByDesc('occurred_at');

        $filename = 'attendance-logs-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Student Name', 'Admission No', 'Device / Terminal', 'Status', 'Verify Mode', 'Date & Time']);

            $query->chunk(500, function ($logs) use ($handle) {
                foreach ($logs as $log) {
                    $student = $log->zktecoUser?->appUser;
                    $studentDetails = $student?->studentUserDetails;
                    fputcsv($handle, [
                        $student?->name ?? 'Unknown',
                        $studentDetails?->admission_number ?? 'N/A',
                        $log->device?->name ?? 'N/A',
                        ucfirst($log->status?->value ?? (string) $log->status),
                        ucfirst($log->verify_mode?->value ?? (string) $log->verify_mode),
                        $log->occurred_at?->format('Y-m-d H:i:s') ?? '',
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * List student attendance recorded on devices belonging to an institution.
     */
    public function institutionAttendance(Request $request, int $institution)
    {
        abort_unless(Auth::check() && Auth::user()->can('view attendance'), 403);

        $institution = Institution::findOrFail($institution);

        $logs = AttendanceLogQuery::build($institution->id, Auth::user(), $request->only(['device_id', 'search', 'from', 'to']))
            ->orderByDesc('occurred_at')
            ->paginate($request->integer('per_page', 25));

        $logs->getCollection()->transform(fn (ZktecoAttendanceLog $log) => $this->toApiRow($log));

        return response()->json([
            'success' => true,
            'institution' => [
                'id' => $institution->id,
                'name' => $institution->name,
            ],
            'data' => $logs,
        ]);
    }

    /**
     * Flatten a log into the JSON shape returned by the API endpoint.
     *
     * @return array<string, mixed>
     */
    private function toApiRow(ZktecoAttendanceLog $log): array
    {
        $student = $log->zktecoUser->appUser;
        $studentDetails = $student->studentUserDetails;

        return [
            'id' => $log->id,
            'device' => [
                'id' => $log->device?->id,
                'serial_number' => $log->device?->serial_number,
                'name' => $log->device?->name,
            ],
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'admission_number' => $studentDetails->admission_number,
                'student_number' => $studentDetails->student_number,
            ],
            'pin' => $log->pin,
            'status' => $log->status,
            'verify_mode' => $log->verify_mode,
            'recorded_at' => $log->recorded_at,
            'occurred_at' => $log->occurred_at,
        ];
    }
}
