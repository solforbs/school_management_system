<?php

namespace Modules\Report\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AnalyticsExportService;
use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\FeeManagement\Models\Fee;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private AnalyticsService $analytics,
        private AnalyticsExportService $export,
    ) {}

    /**
     * Admins and Directors get the full "export everything" workbook
     * (institutions/users/students/parents/fees/devices/attendance/messages,
     * scoped to what they're allowed to see); everyone else who can export
     * keeps the simpler fees-only CSV, since their own records are already
     * the full extent of what they can see.
     */
    public function export(Request $request)
    {
        $user = Auth::user();

        abort_unless($user->can('export report'), 403);

        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        if ($user->hasRole('Admin') || $user->hasRole('Director')) {
            return $this->exportWorkbook($user);
        }

        return $this->exportFeesCsv($user);
    }

    private function exportWorkbook(User $user): StreamedResponse
    {
        @ini_set('memory_limit', '512M');

        ['filename' => $filename, 'spreadsheet' => $spreadsheet] = $this->export->forUser($user);

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            @ini_set('memory_limit', '512M');
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function exportFeesCsv(User $user): StreamedResponse
    {
        $query = Fee::with(['student', 'institution']);

        if ($user->hasRole('Parent')) {
            $query->where('parent_id', $user->id);
        } elseif ($user->hasRole('Student')) {
            $query->where('student_id', $user->id);
        } else {
            $query->where('institution_id', currentInstitution()?->id ?? 0);
        }

        $fees = $query->latest()->get();

        $filename = 'fees-report-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($fees) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Student', 'Institution', 'Title', 'Type', 'Amount', 'Paid', 'Balance', 'Status', 'Due Date']);

            foreach ($fees as $fee) {
                fputcsv($handle, [
                    $fee->student?->name,
                    $fee->institution?->name,
                    $fee->title,
                    $fee->fee_type,
                    $fee->amount,
                    $fee->amount_paid,
                    $fee->balance,
                    $fee->status,
                    $fee->due_date?->format('Y-m-d'),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
