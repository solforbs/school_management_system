<?php

namespace Modules\Attendance\Http\Controllers;

use App\Http\Controllers\Concerns\Sortable;
use App\Http\Controllers\Controller;
use App\Services\Export\TableExportService;
use Athwari\LaravelZktecoAdms\Models\ZktecoAttendanceLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Attendance\Services\AttendanceLogQuery;
use Modules\Institution\Models\Institution;

class AttendanceController extends Controller
{
    use Sortable;

    private const HEADERS = ['Student Name', 'Admission No', 'Device / Terminal', 'Status', 'Verify Mode', 'Date & Time'];

    /**
     * Export attendance logs (CSV by default, or Excel/PDF via ?format=)
     * with active filters applied.
     */
    public function export(Request $request, TableExportService $exporter)
    {
        abort_unless(Auth::user()->can('view attendance'), 403);

        $user = Auth::user();
        $institutionId = currentInstitution()?->id;
        $institution = $institutionId ? Institution::find($institutionId) : null;

        abort_unless($institution, 404, 'No institution found.');

        $query = AttendanceLogQuery::build($institution->id, $user, $request->only(['device_id', 'search', 'from', 'to']))
            ->orderByDesc('occurred_at');

        $format = $request->string('format')->lower()->value();
        $filename = 'attendance-logs-'.now()->format('Y-m-d');

        if (in_array($format, ['xlsx', 'pdf'], true)) {
            abort_unless(Auth::user()->can('export attendance'), 403);

            $rows = $query->get()->map(fn (ZktecoAttendanceLog $log) => $this->toExportRow($log));

            return $format === 'xlsx'
                ? $exporter->excel($rows, self::HEADERS, $filename, 'Attendance Logs')
                : $exporter->pdf($rows, self::HEADERS, $filename, 'Attendance Logs');
        }

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, self::HEADERS);

            $query->chunk(500, function ($logs) use ($handle) {
                foreach ($logs as $log) {
                    fputcsv($handle, $this->toExportRow($log));
                }
            });

            fclose($handle);
        }, $filename.'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Flatten a log into the row shape every export format (CSV, Excel, PDF)
     * shares, always using the exact device verification method label
     * (Fingerprint/Card/Password/etc) rather than its raw numeric code.
     *
     * @return array<int, string>
     */
    private function toExportRow(ZktecoAttendanceLog $log): array
    {
        $student = $log->zktecoUser?->appUser;
        $studentDetails = $student?->studentUserDetails;
        [$statusLabel] = AttendanceLogQuery::statusMeta($log->status);

        return [
            $student?->name ?? 'Unknown',
            $studentDetails?->admission_number ?? 'N/A',
            $log->device?->name ?? 'N/A',
            $statusLabel,
            $log->verify_mode?->getLabel() ?? 'Unknown',
            $log->occurred_at?->format('Y-m-d H:i:s') ?? '',
        ];
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
