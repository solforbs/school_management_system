<?php

namespace App\Services;

use App\Http\Controllers\Concerns\Sortable;
use App\Models\ContactMessage;
use App\Models\Devices;
use App\Models\Payment;
use App\Models\User;
use Athwari\LaravelZktecoAdms\Models\ZktecoAttendanceLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Classes\Models\SchoolClass;
use Modules\FeeManagement\Models\Fee;
use Modules\Institution\Models\Institution;
use Modules\Staff\Models\StaffDetails;
use Modules\Staff\Models\StaffPayment;
use Modules\Student\Models\StudentDetails;
use Modules\Subject\Models\SubjectTeacher;

/**
 * Produces role-scoped analytics for the dashboard and the Report module,
 * so both surfaces read from a single source of truth.
 */
class AnalyticsService
{
    use Sortable;

    /**
     * Build the full stats payload for the given user, scoped to what their
     * role is allowed to see.
     */
    public function forUser(User $user): array
    {
        return match (true) {
            $user->hasRole('Admin') => $this->systemStats(),
            $user->hasRole('Director') => $this->institutionStats($user),
            $user->hasRole('Accountant') => $this->institutionStats($user, financeOnly: true),
            $user->hasRole('Parent') => $this->parentStats($user),
            $user->hasRole('Student') => $this->studentStats($user),
            $user->hasRole('Teacher') => $this->teacherStats($user),
            default => [],
        };
    }

    /**
     * Admin: platform-wide overview across every school.
     */
    public function systemStats(): array
    {
        $feeTotals = Fee::selectRaw('SUM(amount) as billed, SUM(amount_paid) as collected')->first();
        $billed = (float) ($feeTotals->billed ?? 0);
        $collected = (float) ($feeTotals->collected ?? 0);

        $devices = Devices::with('zktecoDevice')->get();

        return [
            'scope' => 'system',

            // Institutions
            'institutions_count' => Institution::count(),
            'active_institutions_count' => Institution::where('is_active', true)->count(),
            'pending_approvals_count' => Institution::where('is_approved', false)->count(),
            'institutions_by_type' => Institution::query()
                ->selectRaw('type, COUNT(*) as total')
                ->groupBy('type')
                ->pluck('total', 'type'),
            'institution_growth' => $this->monthlyCounts(Institution::query()),

            // Users
            'all_users_except_admin' => User::withoutRole('Admin')->count(),
            'users_by_role' => $this->usersByRole(),
            'active_count' => User::where('status', 'active')->count(),

            // Students
            'students_count' => StudentDetails::count(),
            'male_students_count' => StudentDetails::where('gender', 'male')->count(),
            'female_students_count' => StudentDetails::where('gender', 'female')->count(),
            'active_students_count' => StudentDetails::where('enrollment_status', 'active')->count(),
            'student_growth' => $this->monthlyCounts(StudentDetails::query()),

            // Fees
            'fees_billed' => $billed,
            'fees_collected' => $collected,
            'fees_outstanding' => $billed - $collected,
            'fee_collection_rate' => $billed > 0 ? round($collected / $billed * 100) : 0,
            'fees_by_status' => $this->feesByStatus(Fee::query()),
            'fee_collection_trend' => $this->monthlyFeeTrend(Fee::query()),

            // Devices & attendance
            'devices_count' => $devices->count(),
            'devices_online_count' => $devices->filter(fn (Devices $device) => $device->zktecoDevice?->isOnline())->count(),
            'attendance_today_count' => ZktecoAttendanceLog::whereDate('occurred_at', today())->count(),

            // Actionable / recent activity
            'pending_institutions' => Institution::with('owner')
                ->where('is_approved', false)
                ->oldest()
                ->get(),
            'recent_institutions' => $this->applySort(
                Institution::with('owner'),
                sortable: ['name', 'created_at'],
                defaultColumn: 'created_at',
                defaultDirection: 'desc',
            )->take(5)->get(),
            'recent_contact_messages' => ContactMessage::latest()->take(5)->get(),
        ] + $this->revenueStats();
    }

    /**
     * What institutions have actually paid the platform: onboarding setup
     * fees and subscription payments, settled and outstanding alike. Feeds
     * both the Admin dashboard's revenue tiles and the Payments page.
     *
     * @return array<string, mixed>
     */
    private function revenueStats(): array
    {
        $successful = Payment::where('status', 'successful');

        $totals = (clone $successful)->selectRaw(
            'SUM(amount) as total,
             SUM(CASE WHEN purpose = ? THEN amount ELSE 0 END) as setup,
             SUM(CASE WHEN purpose = ? THEN amount ELSE 0 END) as subscription',
            [Payment::PURPOSE_SETUP, Payment::PURPOSE_SUBSCRIPTION],
        )->first();

        $lastMonth = now()->subMonthNoOverflow();

        return [
            'revenue_total' => (float) ($totals->total ?? 0),
            'revenue_setup' => (float) ($totals->setup ?? 0),
            'revenue_subscription' => (float) ($totals->subscription ?? 0),
            'revenue_this_month' => (float) (clone $successful)
                ->whereYear('paid_at', now()->year)
                ->whereMonth('paid_at', now()->month)
                ->sum('amount'),
            'revenue_last_month' => (float) (clone $successful)
                ->whereYear('paid_at', $lastMonth->year)
                ->whereMonth('paid_at', $lastMonth->month)
                ->sum('amount'),
            'revenue_trend' => $this->monthlyAmountTrend(Payment::where('status', 'successful')),
            'payments_pending_count' => Payment::where('status', 'pending')->count(),
            'payments_pending_amount' => (float) Payment::where('status', 'pending')->sum('amount'),
            'payments_failed_count' => Payment::whereIn('status', ['failed', 'abandoned'])
                ->where('created_at', '>=', now()->subDays(30))
                ->count(),
            'recent_payments' => Payment::with(['institution', 'plan'])
                ->latest('created_at')
                ->take(6)
                ->get(),
        ];
    }

    /**
     * Month-by-month settled-payment totals for the last N months (oldest
     * first), including months with no activity - grouped by paid_at, the
     * only date a settled payment is guaranteed to carry. Grouped in PHP
     * for the same reason monthlyCounts() is: identical behaviour across
     * this app's MySQL and test-suite SQLite connections.
     *
     * @param  Builder<Payment>  $query
     * @return array<string, float>
     */
    private function monthlyAmountTrend(Builder $query, int $months = 6): array
    {
        $start = now()->subMonths($months - 1)->startOfMonth();

        $grouped = $query->where('paid_at', '>=', $start)
            ->get(['paid_at', 'amount'])
            ->groupBy(fn (Payment $payment) => $payment->paid_at?->format('Y-m') ?? '');

        $trend = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $trend[$month->format('M Y')] = (float) $grouped->get($month->format('Y-m'), collect())->sum('amount');
        }

        return $trend;
    }

    /**
     * Month-by-month row counts for the last N months (oldest first),
     * including months with zero rows, keyed by a display label.
     */
    private function monthlyCounts(Builder $query, int $months = 6): array
    {
        $start = now()->subMonths($months - 1)->startOfMonth();

        $driver = DB::connection()->getDriverName();
        $dateFormat = $driver === 'sqlite' ? "strftime('%Y-%m', created_at)" : 'DATE_FORMAT(created_at, "%Y-%m")';

        $counts = (clone $query)
            ->where('created_at', '>=', $start)
            ->toBase()
            ->selectRaw("{$dateFormat} as month, COUNT(*) as total")
            ->groupBy('month')
            ->pluck('total', 'month');

        $trend = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $trend[$month->format('M Y')] = (int) ($counts->get($month->format('Y-m'), 0));
        }

        return $trend;
    }

    /**
     * Month-by-month billed vs. collected fee totals for the last N months
     * (oldest first), including months with no activity.
     */
    private function monthlyFeeTrend(Builder $query, int $months = 6): array
    {
        $start = now()->subMonths($months - 1)->startOfMonth();

        $driver = DB::connection()->getDriverName();
        $dateFormat = $driver === 'sqlite' ? "strftime('%Y-%m', created_at)" : 'DATE_FORMAT(created_at, "%Y-%m")';

        $grouped = (clone $query)
            ->where('created_at', '>=', $start)
            ->toBase()
            ->selectRaw("{$dateFormat} as month, SUM(amount) as billed, SUM(amount_paid) as collected")
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        $trend = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $row = $grouped->get($month->format('Y-m'));

            $trend[$month->format('M Y')] = [
                'billed' => (float) ($row->billed ?? 0),
                'collected' => (float) ($row->collected ?? 0),
            ];
        }

        return $trend;
    }

    /**
     * Fee counts by their computed status (paid/partial/overdue/pending).
     */
    private function feesByStatus(Builder $query): array
    {
        $today = today()->toDateString();
        $driver = DB::connection()->getDriverName();

        $dueCondition = $driver === 'sqlite'
            ? "due_date IS NOT NULL AND date(due_date) < date('{$today}')"
            : "due_date IS NOT NULL AND due_date < '{$today}'";

        $counts = (clone $query)
            ->toBase()
            ->selectRaw("
                CASE 
                    WHEN amount_paid >= amount THEN 'paid'
                    WHEN amount_paid > 0 THEN 'partial'
                    WHEN {$dueCondition} THEN 'overdue'
                    ELSE 'pending'
                END as fee_status,
                COUNT(*) as total
            ")
            ->groupBy('fee_status')
            ->pluck('total', 'fee_status')
            ->toArray();

        return [
            'paid' => (int) ($counts['paid'] ?? 0),
            'partial' => (int) ($counts['partial'] ?? 0),
            'overdue' => (int) ($counts['overdue'] ?? 0),
            'pending' => (int) ($counts['pending'] ?? 0),
        ];
    }

    /**
     * Staff payroll for the current month - the other half of an
     * Accountant's remit alongside fees.
     *
     * @param  Collection<int, int>  $institutionIds
     * @return array{staff_count: int, payroll_month_total: float, payroll_month_paid: float, payroll_pending_count: int}
     */
    private function payrollStats(Collection $institutionIds): array
    {
        $payments = StaffPayment::whereIn('institution_id', $institutionIds)
            ->whereYear('period', today()->year)
            ->whereMonth('period', today()->month)
            ->where('status', '!=', 'cancelled');

        $totals = (clone $payments)
            ->selectRaw('SUM(net_amount) as total, SUM(CASE WHEN status = ? THEN net_amount ELSE 0 END) as paid', ['paid'])
            ->first();

        return [
            'staff_count' => StaffDetails::whereIn('institution_id', $institutionIds)->count(),
            'payroll_month_total' => (float) ($totals->total ?? 0),
            'payroll_month_paid' => (float) ($totals->paid ?? 0),
            'payroll_pending_count' => (clone $payments)->where('status', 'pending')->count(),
        ];
    }

    /**
     * Director/Accountant: everything scoped to whichever single
     * institution the Director currently has active - not every school
     * they own, since the rest of the system now runs institution-based
     * rather than user-based (see HasInstitution).
     */
    public function institutionStats(User $user, bool $financeOnly = false): array
    {
        // A Director works from whichever school they've made active; an
        // Accountant doesn't own one at all, and is reached through the
        // staff record they were created from.
        $institution = $user->hasRole('Accountant')
            ? $user->staffUserDetails?->institution
            : $user->activeInstitution;
        $institutionIds = $institution ? collect([$institution->id]) : collect();

        $feeQuery = Fee::whereIn('institution_id', $institutionIds);
        $feeTotals = (clone $feeQuery)->selectRaw('SUM(amount) as billed, SUM(amount_paid) as collected')->first();

        $billed = (float) ($feeTotals->billed ?? 0);
        $collected = (float) ($feeTotals->collected ?? 0);

        $stats = [
            'scope' => 'institution',
            'institution' => $institution,
            'fees_billed' => $billed,
            'fees_collected' => $collected,
            'fees_outstanding' => $billed - $collected,
            'fee_collection_rate' => $billed > 0 ? round($collected / $billed * 100) : 0,
            'overdue_fees_count' => (clone $feeQuery)->whereColumn('amount_paid', '<', 'amount')
                ->whereDate('due_date', '<', today())
                ->count(),
            'fees_by_status' => $this->feesByStatus(clone $feeQuery),
            'fee_collection_trend' => $this->monthlyFeeTrend(clone $feeQuery),
            'recent_fees' => $this->applySort(
                (clone $feeQuery)->with('student'),
                sortable: ['title', 'amount', 'created_at'],
                defaultColumn: 'created_at',
                defaultDirection: 'desc',
            )->take(5)->get(),
        ] + $this->payrollStats($institutionIds);

        if ($financeOnly) {
            return $stats;
        }

        $deviceIds = Devices::whereIn('institution_id', $institutionIds)
            ->whereNotNull('zkteco_device_id')
            ->pluck('zkteco_device_id');

        $devices = Devices::whereIn('institution_id', $institutionIds)->with('zktecoDevice')->get();
        $studentQuery = StudentDetails::whereIn('institution_id', $institutionIds);
        $genderCounts = (clone $studentQuery)
            ->selectRaw("SUM(CASE WHEN gender = 'male' THEN 1 ELSE 0 END) as male, SUM(CASE WHEN gender = 'female' THEN 1 ELSE 0 END) as female")
            ->first();

        return $stats + [
            'students_count' => (clone $studentQuery)->count(),
            'male_students_count' => (int) ($genderCounts->male ?? 0),
            'female_students_count' => (int) ($genderCounts->female ?? 0),
            'active_students_count' => (clone $studentQuery)->where('enrollment_status', 'active')->count(),
            'parents_count' => $institution?->parents()->count() ?? 0,
            'devices_count' => $devices->count(),
            'devices_online_count' => $devices->filter(fn (Devices $device) => $device->zktecoDevice?->isOnline())->count(),
            'attendance_today_count' => ZktecoAttendanceLog::whereIn('device_id', $deviceIds)
                ->whereDate('occurred_at', today())
                ->count(),
            'student_growth' => $this->monthlyCounts(clone $studentQuery),
            'enrollment_by_status' => (clone $studentQuery)
                ->selectRaw('enrollment_status, COUNT(*) as total')
                ->groupBy('enrollment_status')
                ->pluck('total', 'enrollment_status'),
            'recent_students' => $this->applySort(
                (clone $studentQuery)->with('student'),
                sortable: ['admission_number', 'enrollment_status', 'created_at'],
                defaultColumn: 'created_at',
                defaultDirection: 'desc',
            )->take(5)->get(),
        ];
    }

    /**
     * Parent: summary across their children only.
     */
    public function parentStats(User $user): array
    {
        $children = $this->applySort(
            StudentDetails::where('parent_id', $user->id)->with('student', 'institution'),
            sortable: ['admission_number', 'enrollment_status'],
            defaultColumn: 'admission_number',
            defaultDirection: 'asc',
        )->get();
        $childIds = $children->pluck('student_id');

        $feeQuery = Fee::where('parent_id', $user->id);
        $feeTotals = (clone $feeQuery)->selectRaw('SUM(amount) as billed, SUM(amount_paid) as collected')->first();

        $deviceIds = Devices::whereIn('institution_id', $children->pluck('institution_id')->unique())
            ->whereNotNull('zkteco_device_id')
            ->pluck('zkteco_device_id');

        return [
            'scope' => 'parent',
            'children' => $children,
            'children_count' => $children->count(),
            'fees_billed' => (float) ($feeTotals->billed ?? 0),
            'fees_collected' => (float) ($feeTotals->collected ?? 0),
            'fees_outstanding' => (float) ($feeTotals->billed ?? 0) - (float) ($feeTotals->collected ?? 0),
            'attendance_today_count' => ZktecoAttendanceLog::whereIn('device_id', $deviceIds)
                ->whereIn('pin', $childIds->map(fn ($id) => (string) $id))
                ->whereDate('occurred_at', today())
                ->count(),
            'recent_fees' => $this->applySort(
                (clone $feeQuery)->with('student'),
                sortable: ['title', 'amount', 'created_at'],
                defaultColumn: 'created_at',
                defaultDirection: 'desc',
            )->take(5)->get(),
        ];
    }

    /**
     * Student: their own attendance and fee summary.
     */
    public function studentStats(User $user): array
    {
        $deviceIds = Devices::whereIn(
            'institution_id',
            StudentDetails::where('student_id', $user->id)->pluck('institution_id')
        )->whereNotNull('zkteco_device_id')->pluck('zkteco_device_id');

        $attendanceThisMonth = ZktecoAttendanceLog::whereIn('device_id', $deviceIds)
            ->where('pin', (string) $user->id)
            ->whereMonth('occurred_at', now()->month)
            ->whereYear('occurred_at', now()->year)
            ->count();

        $feeQuery = Fee::where('student_id', $user->id);
        $feeTotals = (clone $feeQuery)->selectRaw('SUM(amount) as billed, SUM(amount_paid) as collected')->first();

        return [
            'scope' => 'student',
            'attendance_this_month_count' => $attendanceThisMonth,
            'attendance_today_count' => ZktecoAttendanceLog::whereIn('device_id', $deviceIds)
                ->where('pin', (string) $user->id)
                ->whereDate('occurred_at', today())
                ->count(),
            'fees_billed' => (float) ($feeTotals->billed ?? 0),
            'fees_collected' => (float) ($feeTotals->collected ?? 0),
            'fees_outstanding' => (float) ($feeTotals->billed ?? 0) - (float) ($feeTotals->collected ?? 0),
            'recent_fees' => $this->applySort(
                clone $feeQuery,
                sortable: ['title', 'amount', 'created_at'],
                defaultColumn: 'created_at',
                defaultDirection: 'desc',
            )->take(5)->get(),
        ];
    }

    /**
     * Teacher: overview of the school they teach at.
     */
    public function teacherStats(User $user): array
    {
        $institution = $user->teacherUserDetails?->institution;

        if (! $institution) {
            return [
                'scope' => 'teacher',
                'institution' => null,
                'students_count' => 0,
                'attendance_today_count' => 0,
                'homeroom_class' => null,
                'teaching_allocations' => collect(),
            ];
        }

        // 1. Homeroom Class (where this user is the designated Class Teacher)
        $homeroomClass = SchoolClass::where('institution_id', $institution->id)
            ->where('class_teacher_id', $user->id)
            ->with(['curriculum'])
            ->withCount('students')
            ->first();

        // 2. Teaching Allocations (Class + Subject pairs assigned to this teacher)
        $teachingAllocations = SubjectTeacher::where('institution_id', $institution->id)
            ->where('teacher_id', $user->id)
            ->with(['schoolClass.curriculum', 'subject'])
            ->get();

        // Taught class IDs (homeroom + subject classes)
        $taughtClassIds = $teachingAllocations->pluck('class_id')
            ->when($homeroomClass, fn ($c) => $c->push($homeroomClass->id))
            ->unique()
            ->filter();

        // Scope students count to the classes the teacher actually teaches
        $teacherStudentsQuery = StudentDetails::where('institution_id', $institution->id)
            ->when($taughtClassIds->isNotEmpty(), fn ($q) => $q->whereIn('class_id', $taughtClassIds));

        $teacherGenderCounts = (clone $teacherStudentsQuery)
            ->selectRaw("SUM(CASE WHEN gender = 'male' THEN 1 ELSE 0 END) as male, SUM(CASE WHEN gender = 'female' THEN 1 ELSE 0 END) as female")
            ->first();

        $deviceIds = Devices::where('institution_id', $institution->id)
            ->whereNotNull('zkteco_device_id')
            ->pluck('zkteco_device_id');

        return [
            'scope' => 'teacher',
            'institution' => $institution,
            'homeroom_class' => $homeroomClass,
            'teaching_allocations' => $teachingAllocations,
            'students_count' => (clone $teacherStudentsQuery)->count(),
            'male_students_count' => (int) ($teacherGenderCounts->male ?? 0),
            'female_students_count' => (int) ($teacherGenderCounts->female ?? 0),
            'attendance_today_count' => ZktecoAttendanceLog::whereIn('device_id', $deviceIds)
                ->whereDate('occurred_at', today())
                ->count(),
        ];
    }

    /**
     * Count of users per role, platform-wide.
     */
    private function usersByRole(): Collection
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', User::class)
            ->selectRaw('roles.name, COUNT(*) as total')
            ->groupBy('roles.name')
            ->orderByDesc('total')
            ->pluck('total', 'roles.name');
    }
}
