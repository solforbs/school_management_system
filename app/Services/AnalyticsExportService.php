<?php

namespace App\Services;

use App\Models\ContactMessage;
use App\Models\Devices;
use App\Models\Payment;
use App\Models\User;
use App\Services\Export\Concerns\StylesWorksheets;
use Athwari\LaravelZktecoAdms\Models\ZktecoAttendanceLog;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\FeeManagement\Models\Fee;
use Modules\Institution\Models\Institution;
use Modules\Student\Models\StudentDetails;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Builds the "export everything" workbook behind the Report page's export
 * button. Admins get a system-wide workbook, Directors get one scoped to
 * their own institution(s); everyone else keeps the simpler fees-only CSV
 * they already had (their own records are already "everything" within what
 * they're allowed to see).
 *
 * Every workbook opens on a Summary cover sheet - generated-at, the period
 * it covers, and headline totals - so the file reads as a report rather
 * than a bare data dump. When $from/$to are given, every time-stamped sheet
 * (users, students, fees, payments, attendance, messages) is narrowed to
 * that window; the roster-style sheets (institutions, devices) stay
 * unfiltered since "who exists" isn't a period-bound fact.
 */
class AnalyticsExportService
{
    use StylesWorksheets;

    /**
     * @return array{filename: string, spreadsheet: Spreadsheet}
     */
    public function forUser(User $user, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null, string $periodLabel = 'All time'): array
    {
        $suffix = $from || $to ? '-'.now()->format('His') : '';

        if ($user->hasRole('Admin')) {
            return [
                'filename' => 'system-report-'.now()->format('Y-m-d').$suffix.'.xlsx',
                'spreadsheet' => $this->systemWorkbook($from, $to, $periodLabel),
            ];
        }

        $institutionIds = $user->institution()->pluck('id');

        return [
            'filename' => 'institution-report-'.now()->format('Y-m-d').$suffix.'.xlsx',
            'spreadsheet' => $this->institutionWorkbook($institutionIds, $from, $to, $periodLabel),
        ];
    }

    private function systemWorkbook(?CarbonImmutable $from, ?CarbonImmutable $to, string $periodLabel): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);

        $institutionsQuery = Institution::with('owner')->orderBy('id');
        $usersQuery = $this->betweenDates(User::withoutRole('Admin')->with('roles'), $from, $to);
        $studentsQuery = $this->betweenDates(StudentDetails::with(['student', 'institution']), $from, $to);
        $parentsQuery = $this->betweenDates(User::role('Parent')->with('parent')->withCount('children'), $from, $to);
        $feesQuery = $this->betweenDates(Fee::with(['student', 'institution']), $from, $to)->latest();
        $paymentsQuery = $this->betweenDates(Payment::with(['institution', 'plan']), $from, $to)->latest('created_at');
        $devicesQuery = Devices::with(['zktecoDevice', 'institution'])->orderBy('id');
        $messagesQuery = $this->betweenDates(ContactMessage::query(), $from, $to)->latest();

        $this->addSummarySheet($spreadsheet, $periodLabel, [
            'Institutions' => (clone $institutionsQuery)->count(),
            'Users' => (clone $usersQuery)->count(),
            'Students (Total)' => (clone $studentsQuery)->count(),
            'Students (Male)' => (clone $studentsQuery)->where('gender', 'male')->count(),
            'Students (Female)' => (clone $studentsQuery)->where('gender', 'female')->count(),
            'Parents' => (clone $parentsQuery)->count(),
            'Fees raised' => 'KES '.number_format((float) (clone $feesQuery)->sum('amount'), 2),
            'Fees collected' => 'KES '.number_format((float) (clone $feesQuery)->sum('amount_paid'), 2),
            'Payments received' => 'KES '.number_format((float) (clone $paymentsQuery)->where('status', 'successful')->sum('amount'), 2),
            'Devices' => (clone $devicesQuery)->count(),
            'Contact messages' => (clone $messagesQuery)->count(),
        ]);
        $this->addInstitutionsSheet($spreadsheet, $institutionsQuery);
        $this->addUsersSheet($spreadsheet, $usersQuery);
        $this->addStudentsSheet($spreadsheet, $studentsQuery, showInstitution: true);
        $this->addParentsSheet($spreadsheet, $parentsQuery, showInstitution: false);
        $this->addFeesSheet($spreadsheet, $feesQuery, showInstitution: true);
        $this->addPaymentsSheet($spreadsheet, $paymentsQuery);
        $this->addDevicesSheet($spreadsheet, $devicesQuery, showInstitution: true);
        // Attendance is a Director-only concern - the system-wide workbook
        // deliberately excludes it so Admin never sees attendance data, even
        // in bulk-export form. Institution-scoped exports still get their
        // own attendance sheet below via institutionWorkbook().
        $this->addMessagesSheet($spreadsheet, $messagesQuery);

        return $spreadsheet;
    }

    private function institutionWorkbook(Collection $institutionIds, ?CarbonImmutable $from, ?CarbonImmutable $to, string $periodLabel): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);

        $studentsQuery = $this->betweenDates(StudentDetails::whereIn('institution_id', $institutionIds)->with('student'), $from, $to);
        $parentsQuery = $this->betweenDates(
            User::role('Parent')->whereHas('children', fn ($q) => $q->whereIn('institution_id', $institutionIds))->with('parent')->withCount('children'),
            $from,
            $to,
        );
        $feesQuery = $this->betweenDates(Fee::whereIn('institution_id', $institutionIds)->with('student'), $from, $to)->latest();
        $devicesQuery = Devices::whereIn('institution_id', $institutionIds)->with('zktecoDevice')->orderBy('id');
        $deviceIds = Devices::whereIn('institution_id', $institutionIds)
            ->whereNotNull('zkteco_device_id')
            ->pluck('zkteco_device_id');

        $this->addSummarySheet($spreadsheet, $periodLabel, [
            'Students (Total)' => (clone $studentsQuery)->count(),
            'Students (Male)' => (clone $studentsQuery)->where('gender', 'male')->count(),
            'Students (Female)' => (clone $studentsQuery)->where('gender', 'female')->count(),
            'Parents' => (clone $parentsQuery)->count(),
            'Fees raised' => 'KES '.number_format((float) (clone $feesQuery)->sum('amount'), 2),
            'Fees collected' => 'KES '.number_format((float) (clone $feesQuery)->sum('amount_paid'), 2),
            'Devices' => (clone $devicesQuery)->count(),
        ]);
        $this->addStudentsSheet($spreadsheet, $studentsQuery, showInstitution: false);
        $this->addParentsSheet($spreadsheet, $parentsQuery, showInstitution: false);
        $this->addFeesSheet($spreadsheet, $feesQuery, showInstitution: false);
        $this->addDevicesSheet($spreadsheet, $devicesQuery, showInstitution: false);
        $this->addAttendanceSheet($spreadsheet, ZktecoAttendanceLog::whereIn('device_id', $deviceIds), $from, $to);

        return $spreadsheet;
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function betweenDates(Builder $query, ?CarbonImmutable $from, ?CarbonImmutable $to): Builder
    {
        return $query
            ->when($from, fn (Builder $q) => $q->where('created_at', '>=', $from))
            ->when($to, fn (Builder $q) => $q->where('created_at', '<=', $to));
    }

    /**
     * Turns a query into the flat row arrays PhpSpreadsheet expects, one
     * chunk at a time rather than hydrating the whole result set (plus every
     * eager-loaded relation) into memory in one go. The biggest sheets here
     * (users, students, fees) can run into the tens of thousands of rows on
     * a real install, and this "export everything" workbook has no size
     * limit by design - chunking keeps peak memory bounded to one batch of
     * models instead of the entire table.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  callable(TModel): array<int, mixed>  $map
     * @return array<int, array<int, mixed>>
     */
    private function mapInChunks(Builder $query, callable $map, int $chunkSize = 500, ?int $limit = null): array
    {
        $rows = [];
        $count = 0;

        foreach ($query->lazy($chunkSize) as $model) {
            $rows[] = $map($model);
            $count++;
            if ($limit !== null && $count >= $limit) {
                break;
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, int|string>  $totals
     */
    private function addSummarySheet(Spreadsheet $spreadsheet, string $periodLabel, array $totals): void
    {
        $rows = collect($totals)->map(fn ($value, $label) => [$label, $value])->values()->all();

        $sheet = $this->addSheet($spreadsheet, 'Summary', ['Metric', 'Value'], $rows);
        $sheet->setCellValue('A'.(count($rows) + 3), 'Period covered');
        $sheet->setCellValue('B'.(count($rows) + 3), $periodLabel);
        $sheet->setCellValue('A'.(count($rows) + 4), 'Generated');
        $sheet->setCellValue('B'.(count($rows) + 4), now()->format('D, d M Y H:i'));
        $sheet->getStyle('A'.(count($rows) + 3).':A'.(count($rows) + 4))->getFont()->setBold(true);
    }

    /**
     * @param  Builder<Institution>  $query
     */
    private function addInstitutionsSheet(Spreadsheet $spreadsheet, Builder $query): void
    {
        $this->addSheet(
            $spreadsheet,
            'Institutions',
            ['Name', 'Code', 'Type', 'County', 'City', 'Status', 'Active', 'Approved', 'Owner', 'Owner Email', 'Created'],
            $this->mapInChunks($query, fn (Institution $institution) => [
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

    /**
     * @param  Builder<User>  $query
     */
    private function addUsersSheet(Spreadsheet $spreadsheet, Builder $query): void
    {
        $this->addSheet(
            $spreadsheet,
            'Users',
            ['Name', 'Email', 'Role(s)', 'Status', 'Created'],
            $this->mapInChunks($query, fn (User $user) => [
                $user->name,
                $user->email,
                $user->roles->pluck('name')->implode(', '),
                ucfirst($user->status ?? 'unknown'),
                $user->created_at?->format('Y-m-d'),
            ]),
        );
    }

    /**
     * @param  Builder<StudentDetails>  $query
     */
    private function addStudentsSheet(Spreadsheet $spreadsheet, Builder $query, bool $showInstitution): void
    {
        $headers = ['Name', 'Admission No.', 'Gender', 'Enrollment Status', 'Active', 'Date of Birth', 'City', 'Created'];
        if ($showInstitution) {
            array_splice($headers, 3, 0, ['Institution']);
        }

        $this->addSheet(
            $spreadsheet,
            'Students',
            $headers,
            $this->mapInChunks($query, function (StudentDetails $studentDetails) use ($showInstitution) {
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

    /**
     * @param  Builder<User>  $query
     */
    private function addParentsSheet(Spreadsheet $spreadsheet, Builder $query, bool $showInstitution): void
    {
        $this->addSheet(
            $spreadsheet,
            'Parents',
            ['Name', 'Email', 'Phone', 'Occupation', 'Children', 'Created'],
            $this->mapInChunks($query, fn (User $parent) => [
                $parent->name,
                $parent->email,
                $parent->parent?->parent_phone,
                $parent->parent?->parent_occupation,
                $parent->children_count ?? $parent->children->count(),
                $parent->created_at?->format('Y-m-d'),
            ]),
        );
    }

    /**
     * @param  Builder<Fee>  $query
     */
    private function addFeesSheet(Spreadsheet $spreadsheet, Builder $query, bool $showInstitution): void
    {
        $headers = ['Student', 'Title', 'Type', 'Amount', 'Paid', 'Balance', 'Status', 'Due Date', 'Created'];
        if ($showInstitution) {
            array_splice($headers, 1, 0, ['Institution']);
        }

        $this->addSheet(
            $spreadsheet,
            'Fees',
            $headers,
            $this->mapInChunks($query, function (Fee $fee) use ($showInstitution) {
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
            }, limit: 5000),
        );
    }

    /**
     * @param  Builder<Devices>  $query
     */
    private function addDevicesSheet(Spreadsheet $spreadsheet, Builder $query, bool $showInstitution): void
    {
        $headers = ['Serial Number', 'Device Name', 'IP Address', 'Online', 'Created'];
        if ($showInstitution) {
            array_splice($headers, 1, 0, ['Institution']);
        }

        $this->addSheet(
            $spreadsheet,
            'Devices',
            $headers,
            $this->mapInChunks($query, function (Devices $device) use ($showInstitution) {
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
     * @param  Builder<Payment>  $query
     */
    private function addPaymentsSheet(Spreadsheet $spreadsheet, Builder $query): void
    {
        $this->addSheet(
            $spreadsheet,
            'Payments',
            ['Institution', 'Purpose', 'Plan', 'Amount', 'Currency', 'Channel', 'Status', 'Reference', 'Paid At', 'Created'],
            $this->mapInChunks($query, fn (Payment $payment) => [
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

    /**
     * Daily check-in counts. Defaults to the trailing 30 days when no period
     * is selected; a custom period uses that range instead, capped at a
     * year so a huge "All time" range never tries to render one row per day
     * since the dawn of the log.
     */
    private function addAttendanceSheet(Spreadsheet $spreadsheet, Builder $query, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null): void
    {
        $end = ($to ?? now())->startOfDay();
        $start = $from ? $from->startOfDay() : $end->subDays(29);

        if ($start->diffInDays($end) > 365) {
            $start = $end->subDays(365);
        }

        $days = $start->diffInDays($end);

        $counts = (clone $query)
            ->where('occurred_at', '>=', $start)
            ->where('occurred_at', '<=', $end->endOfDay())
            ->toBase()
            ->selectRaw('DATE(occurred_at) as log_date, count(*) as total')
            ->groupBy('log_date')
            ->pluck('total', 'log_date');

        $rows = collect();
        for ($i = $days; $i >= 0; $i--) {
            $date = $end->subDays($i);
            $key = $date->format('Y-m-d');
            $count = (int) ($counts->get($key) ?? $counts->get($date->format('Y-m-d 00:00:00')) ?? 0);
            $rows->push([$key, $count]);
        }

        $title = $from || $to ? 'Attendance' : 'Attendance (30 days)';
        $this->addSheet($spreadsheet, $title, ['Date', 'Check-ins'], $rows);
    }

    /**
     * @param  Builder<ContactMessage>  $query
     */
    private function addMessagesSheet(Spreadsheet $spreadsheet, Builder $query): void
    {
        $topics = config('contact.topics', []);

        $this->addSheet(
            $spreadsheet,
            'Contact Messages',
            ['Name', 'Email', 'Phone', 'Topic', 'Message', 'Received'],
            $this->mapInChunks($query, fn (ContactMessage $message) => [
                $message->name,
                $message->email,
                $message->phone,
                $topics[$message->topic] ?? $message->topic,
                $message->message,
                $message->created_at->format('Y-m-d H:i'),
            ]),
        );
    }
}
