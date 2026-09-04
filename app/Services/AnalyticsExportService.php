<?php

namespace App\Services;

use App\Models\ContactMessage;
use App\Models\Devices;
use App\Models\Payment;
use App\Models\User;
use Athwari\LaravelZktecoAdms\Models\ZktecoAttendanceLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\FeeManagement\Models\Fee;
use Modules\Institution\Models\Institution;
use Modules\Student\Models\StudentDetails;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Builds the "export everything" workbook behind the Report page's export
 * button. Admins get a system-wide workbook, Directors get one scoped to
 * their own institution(s); everyone else keeps the simpler fees-only CSV
 * they already had (their own records are already "everything" within what
 * they're allowed to see).
 */
class AnalyticsExportService
{
    /**
     * @return array{filename: string, spreadsheet: Spreadsheet}
     */
    public function forUser(User $user): array
    {
        if ($user->hasRole('Admin')) {
            return [
                'filename' => 'system-report-'.now()->format('Y-m-d').'.xlsx',
                'spreadsheet' => $this->systemWorkbook(),
            ];
        }

        $institutionIds = $user->institution()->pluck('id');

        return [
            'filename' => 'institution-report-'.now()->format('Y-m-d').'.xlsx',
            'spreadsheet' => $this->institutionWorkbook($institutionIds),
        ];
    }

    private function systemWorkbook(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);

        $this->addInstitutionsSheet($spreadsheet, Institution::with('owner')->get());
        $this->addUsersSheet($spreadsheet, User::withoutRole('Admin')->with('roles')->get());
        $this->addStudentsSheet($spreadsheet, StudentDetails::with(['student', 'institution'])->get(), showInstitution: true);
        $this->addParentsSheet($spreadsheet, User::role('Parent')->with('parent')->get(), showInstitution: false);
        $this->addFeesSheet($spreadsheet, Fee::with(['student', 'institution'])->latest()->get(), showInstitution: true);
        $this->addPaymentsSheet($spreadsheet, Payment::with(['institution', 'plan'])->latest('created_at')->get());
        $this->addDevicesSheet($spreadsheet, Devices::with(['zktecoDevice', 'institution'])->get(), showInstitution: true);
        $this->addAttendanceSheet($spreadsheet, ZktecoAttendanceLog::query());
        $this->addMessagesSheet($spreadsheet, ContactMessage::latest()->get());

        return $spreadsheet;
    }

    private function institutionWorkbook(Collection $institutionIds): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);

        $students = StudentDetails::whereIn('institution_id', $institutionIds)->with('student')->get();
        $parents = User::role('Parent')
            ->whereHas('children', fn ($q) => $q->whereIn('institution_id', $institutionIds))
            ->with('parent')
            ->get();
        $fees = Fee::whereIn('institution_id', $institutionIds)->with('student')->latest()->get();
        $devices = Devices::whereIn('institution_id', $institutionIds)->with('zktecoDevice')->get();
        $deviceIds = $devices->pluck('zktecoDevice.id')->filter();

        $this->addStudentsSheet($spreadsheet, $students, showInstitution: false);
        $this->addParentsSheet($spreadsheet, $parents, showInstitution: false);
        $this->addFeesSheet($spreadsheet, $fees, showInstitution: false);
        $this->addDevicesSheet($spreadsheet, $devices, showInstitution: false);
        $this->addAttendanceSheet($spreadsheet, ZktecoAttendanceLog::whereIn('device_id', $deviceIds));

        return $spreadsheet;
    }

    private function addInstitutionsSheet(Spreadsheet $spreadsheet, Collection $institutions): void
    {
        $this->addSheet(
            $spreadsheet,
            'Institutions',
            ['Name', 'Code', 'Type', 'County', 'City', 'Status', 'Active', 'Approved', 'Owner', 'Owner Email', 'Created'],
            $institutions->map(fn (Institution $institution) => [
                $institution->name,
                $institution->code,
                $institution->type,
                $institution->county,
                $institution->city,
                ucfirst($institution->status ?? 'unknown'),
                $institution->is_active ? 'Yes' : 'No',
                $institution->is_approved ? 'Yes' : 'No',
                $institution->owner?->name,
                $institution->owner?->email,
                $institution->created_at?->format('Y-m-d'),
            ]),
        );
    }

    private function addUsersSheet(Spreadsheet $spreadsheet, Collection $users): void
    {
        $this->addSheet(
            $spreadsheet,
            'Users',
            ['Name', 'Email', 'Role(s)', 'Status', 'Created'],
            $users->map(fn (User $user) => [
                $user->name,
                $user->email,
                $user->roles->pluck('name')->implode(', '),
                ucfirst($user->status ?? 'unknown'),
                $user->created_at?->format('Y-m-d'),
            ]),
        );
    }

    private function addStudentsSheet(Spreadsheet $spreadsheet, Collection $students, bool $showInstitution): void
    {
        $headers = ['Name', 'Admission No.', 'Gender', 'Enrollment Status', 'Active', 'Date of Birth', 'City', 'Created'];
        if ($showInstitution) {
            array_splice($headers, 3, 0, ['Institution']);
        }

        $this->addSheet(
            $spreadsheet,
            'Students',
            $headers,
            $students->map(function (StudentDetails $studentDetails) use ($showInstitution) {
                $row = [
                    $studentDetails->student?->name,
                    $studentDetails->admission_number,
                    $studentDetails->gender ? ucfirst($studentDetails->gender) : null,
                ];

                if ($showInstitution) {
                    $row[] = $studentDetails->institution?->name;
                }

                return [
                    ...$row,
                    ucfirst($studentDetails->enrollment_status),
                    $studentDetails->is_active ? 'Yes' : 'No',
                    $studentDetails->date_of_birth,
                    $studentDetails->city,
                    $studentDetails->created_at?->format('Y-m-d'),
                ];
            }),
        );
    }

    private function addParentsSheet(Spreadsheet $spreadsheet, Collection $parents, bool $showInstitution): void
    {
        $this->addSheet(
            $spreadsheet,
            'Parents',
            ['Name', 'Email', 'Phone', 'Occupation', 'Children', 'Created'],
            $parents->map(fn (User $parent) => [
                $parent->name,
                $parent->email,
                $parent->parent?->parent_phone,
                $parent->parent?->parent_occupation,
                $parent->children->count(),
                $parent->created_at?->format('Y-m-d'),
            ]),
        );
    }

    private function addFeesSheet(Spreadsheet $spreadsheet, Collection $fees, bool $showInstitution): void
    {
        $headers = ['Student', 'Title', 'Type', 'Amount', 'Paid', 'Balance', 'Status', 'Due Date', 'Created'];
        if ($showInstitution) {
            array_splice($headers, 1, 0, ['Institution']);
        }

        $this->addSheet(
            $spreadsheet,
            'Fees',
            $headers,
            $fees->map(function (Fee $fee) use ($showInstitution) {
                $row = [$fee->student?->name];

                if ($showInstitution) {
                    $row[] = $fee->institution?->name;
                }

                return [
                    ...$row,
                    $fee->title,
                    $fee->fee_type,
                    (float) $fee->amount,
                    (float) $fee->amount_paid,
                    $fee->balance,
                    ucfirst($fee->status),
                    $fee->due_date?->format('Y-m-d'),
                    $fee->created_at?->format('Y-m-d'),
                ];
            }),
        );
    }

    private function addDevicesSheet(Spreadsheet $spreadsheet, Collection $devices, bool $showInstitution): void
    {
        $headers = ['Serial Number', 'Device Name', 'IP Address', 'Online', 'Created'];
        if ($showInstitution) {
            array_splice($headers, 1, 0, ['Institution']);
        }

        $this->addSheet(
            $spreadsheet,
            'Devices',
            $headers,
            $devices->map(function (Devices $device) use ($showInstitution) {
                $row = [$device->serial_number];

                if ($showInstitution) {
                    $row[] = $device->institution?->name;
                }

                return [
                    ...$row,
                    $device->zktecoDevice?->name,
                    $device->zktecoDevice?->ip_address,
                    $device->zktecoDevice?->isOnline() ? 'Yes' : 'No',
                    $device->created_at?->format('Y-m-d'),
                ];
            }),
        );
    }

    /**
     * Daily check-in counts for the last 30 days, rather than every raw log
     * line - biometric attendance logs can run into the tens of thousands of
     * rows, which isn't a "detail" a spreadsheet reader can use directly
     * anyway. Grouped in PHP for the same SQLite/MySQL portability reason as
     * AnalyticsService's trend helpers.
     */
    /**
     * @param  Collection<int, Payment>  $payments
     */
    private function addPaymentsSheet(Spreadsheet $spreadsheet, Collection $payments): void
    {
        $this->addSheet(
            $spreadsheet,
            'Payments',
            ['Institution', 'Purpose', 'Plan', 'Amount', 'Currency', 'Channel', 'Status', 'Reference', 'Paid At', 'Created'],
            $payments->map(fn (Payment $payment) => [
                $payment->institution === null ? 'Unclaimed' : $payment->institution->name,
                $payment->isSetupFee() ? 'Setup fee' : 'Subscription',
                $payment->plan?->name,
                (float) $payment->amount,
                $payment->currency,
                $payment->channel,
                ucfirst($payment->status),
                $payment->reference,
                $payment->paid_at?->format('Y-m-d H:i'),
                $payment->created_at?->format('Y-m-d H:i'),
            ]),
        );
    }

    private function addAttendanceSheet(Spreadsheet $spreadsheet, Builder $query): void
    {
        $start = now()->subDays(29)->startOfDay();

        $counts = (clone $query)
            ->where('occurred_at', '>=', $start)
            ->toBase()
            ->selectRaw('DATE(occurred_at) as log_date, count(*) as total')
            ->groupBy('log_date')
            ->pluck('total', 'log_date');

        $rows = collect();
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $key = $date->format('Y-m-d');
            $count = (int) ($counts->get($key) ?? $counts->get($date->format('Y-m-d 00:00:00')) ?? 0);
            $rows->push([$key, $count]);
        }

        $this->addSheet($spreadsheet, 'Attendance (30 days)', ['Date', 'Check-ins'], $rows);
    }

    private function addMessagesSheet(Spreadsheet $spreadsheet, Collection $messages): void
    {
        $topics = config('contact.topics', []);

        $this->addSheet(
            $spreadsheet,
            'Contact Messages',
            ['Name', 'Email', 'Phone', 'Topic', 'Message', 'Received'],
            $messages->map(fn (ContactMessage $message) => [
                $message->name,
                $message->email,
                $message->phone,
                $topics[$message->topic] ?? $message->topic,
                $message->message,
                $message->created_at->format('Y-m-d H:i'),
            ]),
        );
    }

    /**
     * @param  array<int, string>  $headers
     * @param  iterable<int, array<int, mixed>>  $rows
     */
    private function addSheet(Spreadsheet $spreadsheet, string $title, array $headers, iterable $rows): Worksheet
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($title);

        $sheet->fromArray($headers, null, 'A1');

        $rows = $rows instanceof Collection ? $rows->values()->all() : [...$rows];
        if ($rows !== []) {
            $sheet->fromArray($rows, null, 'A2');
        }

        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));

        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastColumn}1")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('E5E7EB');

        for ($i = 1; $i <= count($headers); $i++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }

        $sheet->freezePane('A2');

        return $sheet;
    }
}
