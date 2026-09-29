<?php

namespace Modules\Report\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AnalyticsExportService;
use App\Services\AnalyticsService;
use Carbon\CarbonImmutable;
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
     *
     * The workbook can be narrowed to a period: an explicit "from"/"to"
     * (either one alone is an open-ended range) takes priority, otherwise a
     * "period" preset (this_month/this_year) is honoured; no period at all
     * exports everything.
     */
    public function export(Request $request)
    {
        $user = Auth::user();

        abort_unless($user->can('export report'), 403);

        raiseMemoryLimit('512M');

        // Only ever raise the ceiling, never introduce one. Under a SAPI with
        // no limit - CLI, a queue worker, the test suite - set_time_limit(300)
        // would *impose* 300 seconds on everything that follows in that
        // process, not just this export.
        if ((int) ini_get('max_execution_time') !== 0) {
            @set_time_limit(300);
        }

        [$from, $to, $label] = $this->resolvePeriod($request);

        if ($user->hasRole('Admin') || $user->hasRole('Director')) {
            return $this->exportWorkbook($user, $from, $to, $label);
        }

        return $this->exportFeesCsv($user);
    }

    /**
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable, 2: string}
     */
    private function resolvePeriod(Request $request): array
    {
        $from = $request->date('from');
        $to = $request->date('to');

        if ($from || $to) {
            $from = $from?->toImmutable()->startOfDay();
            $to = $to?->toImmutable()->endOfDay();

            $label = match (true) {
                $from && $to => $from->format('d M Y').' – '.$to->format('d M Y'),
                (bool) $from => 'From '.$from->format('d M Y'),
                default => 'Up to '.$to->format('d M Y'),
            };

            return [$from, $to, $label];
        }

        return match ($request->string('period')->toString()) {
            'this_month' => [now()->startOfMonth(), now()->endOfMonth(), now()->format('F Y')],
            'this_year' => [now()->startOfYear(), now()->endOfYear(), now()->format('Y')],
            default => [null, null, 'All time'],
        };
    }

    private function exportWorkbook(User $user, ?CarbonImmutable $from, ?CarbonImmutable $to, string $label): StreamedResponse
    {
        ['filename' => $filename, 'spreadsheet' => $spreadsheet] = $this->export->forUser($user, $from, $to, $label);

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
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
