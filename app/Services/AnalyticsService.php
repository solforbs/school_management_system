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
use Modules\FeeManagement\Models\Fee;
use Modules\Institution\Models\Institution;
use Modules\Staff\Models\StaffDetails;
use Modules\Staff\Models\StaffPayment;
use Modules\Student\Models\StudentDetails;

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
     * including months with zero rows, keyed by a display label. Grouped in
     * PHP rather than a SQL date-format function so it behaves identically
     * across the app's MySQL and test-suite SQLite connections.
     */
    private function monthlyCounts(Builder $query, int $months = 6): array
    {
        $start = now()->subMonths($months - 1)->startOfMonth();

        $counts = (clone $query)
            ->where('created_at', '>=', $start)
            ->get(['created_at'])
            ->groupBy(fn ($row) => $row->created_at->format('Y-m'))
            ->map->count();

        $trend = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $trend[$month->format('M Y')] = $counts->get($month->format('Y-m'), 0);
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

        $grouped = $query->where('created_at', '>=', $start)
            ->get(['created_at', 'amount', 'amount_paid'])
            ->groupBy(fn ($row) => $row->created_at->format('Y-m'));

        $trend = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $bucket = $grouped->get($month->format('Y-m'), collect());

            $trend[$month->format('M Y')] = [
                'billed' => (float) $bucket->sum('amount'),
                'collected' => (float) $bucket->sum('amount_paid'),
            ];
        }

        return $trend;
    }

    /**
     * Fee counts by their computed status (paid/partial/overdue/pending).
     * `status` is a PHP accessor, not a column, so this groups in memory
     * rather than via SQL - acceptable at this app's scale, and consistent
     * with how the rest of this service already aggregates fees.
     */
    private function feesByStatus(Builder $query): array
    {
        return $query->get(['amount', 'amount_paid', 'due_date'])
            ->groupBy('status')
            ->map->count()
            ->toArray();
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

        return $stats + [
            'students_count' => (clone $studentQuery)->count(),
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
            ];
        }

        $deviceIds = Devices::where('institution_id', $institution->id)
            ->whereNotNull('zkteco_device_id')
            ->pluck('zkteco_device_id');

        return [
            'scope' => 'teacher',
            'institution' => $institution,
            'students_count' => StudentDetails::where('institution_id', $institution->id)->count(),
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
        return User::with('roles')
            ->get()
            ->flatMap(fn (User $user) => $user->getRoleNames())
            ->countBy()
            ->sortDesc();
    }
}
