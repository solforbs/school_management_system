<?php

namespace App\Services;

use App\Http\Controllers\Concerns\Sortable;
use App\Models\ContactMessage;
use App\Models\Devices;
use App\Models\Payment;
use App\Models\TeacherClasses;
use App\Models\User;
use Athwari\LaravelZktecoAdms\Models\ZktecoAttendanceLog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Attendance\Models\Attendance;
use Modules\Classes\Models\SchoolClass;
use Modules\Examinations\Models\ExamSitting;
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

        $todayDate = today()->toDateString();
        $attendanceRecords = Attendance::whereIn('institution_id', $institutionIds)
            ->whereDate('date', $todayDate)
            ->get();

        $attendancePresentCount = $attendanceRecords->whereIn('status', ['present', 'late'])->count();
        $attendanceAbsentCount = $attendanceRecords->where('status', 'absent')->count();

        $biometricScansTodayCount = ZktecoAttendanceLog::whereIn('device_id', $deviceIds)
            ->whereDate('occurred_at', today())
            ->pluck('pin')
            ->unique()
            ->count();

        $presentToday = $attendanceRecords->isNotEmpty() ? $attendancePresentCount : $biometricScansTodayCount;
        $totalClasses = SchoolClass::whereIn('institution_id', $institutionIds)->count();
        $classesCompleted = $totalClasses > 0
            ? Attendance::whereIn('institution_id', $institutionIds)
                ->whereDate('date', $todayDate)
                ->distinct('school_class_id')
                ->count('school_class_id')
            : 0;

        $activeStudentsCount = (clone $studentQuery)->where('enrollment_status', 'active')->count();
        $attendanceRate = $activeStudentsCount > 0 ? (int) round(($presentToday / $activeStudentsCount) * 100) : 0;

        return $stats + [
            'students_count' => (clone $studentQuery)->count(),
            'male_students_count' => (int) ($genderCounts->male ?? 0),
            'female_students_count' => (int) ($genderCounts->female ?? 0),
            'active_students_count' => $activeStudentsCount,
            'parents_count' => $institution?->parents()->count() ?? 0,
            'devices_count' => $devices->count(),
            'devices_online_count' => $devices->filter(fn (Devices $device) => $device->zktecoDevice?->isOnline())->count(),
            'attendance_today_count' => $presentToday,
            'attendance_absent_count' => $attendanceAbsentCount,
            'attendance_rate_today' => $attendanceRate,
            'classes_rollcall_total' => $totalClasses,
            'classes_rollcall_completed' => $classesCompleted,
            'biometric_scans_today_count' => $biometricScansTodayCount,
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
                'male_students_count' => 0,
                'female_students_count' => 0,
                'attendance_today_count' => 0,
                'biometric_scans_today_count' => 0,
                'biometric_status_text' => 'Biometric check-in not done today',
                'rollcall_completed' => false,
                'rollcall_present_count' => 0,
                'rollcall_absent_count' => 0,
                'rollcall_late_count' => 0,
                'rollcall_total_marked' => 0,
                'rollcall_attendance_rate' => null,
                'rollcall_saved_at' => null,
                'classes_completed_count' => 0,
                'classes_total_count' => 0,
                'homeroom_class' => null,
                'homeroom_stats' => null,
                'teaching_allocations' => collect(),
                'absent_students_today' => collect(),
                'weekly_attendance_trend' => [],
                'recent_assessments' => collect(),
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

        // Direct class assignments via teacher_classes pivot
        $pivotClassIds = TeacherClasses::where('teacher_id', $user->id)->pluck('class_id');

        // Taught class IDs (homeroom + subject classes + pivot classes)
        $taughtClassIds = $teachingAllocations->pluck('class_id')
            ->when($homeroomClass, fn ($c) => $c->push($homeroomClass->id))
            ->concat($pivotClassIds)
            ->unique()
            ->filter()
            ->values();

        // Scope students count to the classes the teacher actually teaches
        $teacherStudentsQuery = StudentDetails::where('institution_id', $institution->id)
            ->when($taughtClassIds->isNotEmpty(), fn ($q) => $q->whereIn('class_id', $taughtClassIds));

        $teacherGenderCounts = (clone $teacherStudentsQuery)
            ->selectRaw("SUM(CASE WHEN gender = 'male' THEN 1 ELSE 0 END) as male, SUM(CASE WHEN gender = 'female' THEN 1 ELSE 0 END) as female")
            ->first();

        $teacherStudentUserIds = (clone $teacherStudentsQuery)
            ->pluck('student_id')
            ->filter()
            ->unique()
            ->values();

        $teacherStudentPins = $teacherStudentUserIds->map(fn ($id) => (string) $id)->all();

        // 3. Hardware biometric device scans today (strictly scoped to teacher's learners)
        $deviceIds = Devices::where('institution_id', $institution->id)
            ->whereNotNull('zkteco_device_id')
            ->pluck('zkteco_device_id');

        $biometricScansTodayCount = 0;
        if (! empty($teacherStudentPins) && $deviceIds->isNotEmpty()) {
            $biometricScansTodayCount = ZktecoAttendanceLog::whereIn('device_id', $deviceIds)
                ->whereIn('pin', $teacherStudentPins)
                ->whereDate('occurred_at', today())
                ->distinct('pin')
                ->count('pin');
        }

        // 4. Classroom Roll-Call Attendances Today
        $todayDate = today()->toDateString();
        $todayAttendances = Attendance::where('institution_id', $institution->id)
            ->whereIn('school_class_id', $taughtClassIds)
            ->whereDate('date', $todayDate)
            ->with(['student', 'studentDetails', 'schoolClass'])
            ->get();

        $rollcallMarkedCount = $todayAttendances->count();
        $rollcallPresentCount = $todayAttendances->whereIn('status', ['present', 'late'])->count();
        $rollcallAbsentCount = $todayAttendances->where('status', 'absent')->count();
        $rollcallLateCount = $todayAttendances->where('status', 'late')->count();

        $rollcallAttendanceRate = $rollcallMarkedCount > 0
            ? (int) round(($rollcallPresentCount / $rollcallMarkedCount) * 100)
            : null;

        $classesWithAttendance = $todayAttendances->pluck('school_class_id')->unique();
        $classesCompletedCount = $classesWithAttendance->count();
        $totalAccessibleClassesCount = $taughtClassIds->count();
        $rollcallCompleted = $rollcallMarkedCount > 0;
        $rollcallSavedAt = $todayAttendances->max('updated_at');

        // 5. Homeroom Class Pastoral & Attendance Health (if designated Class Teacher)
        $homeroomStats = null;
        if ($homeroomClass) {
            $homeroomRecords = $todayAttendances->where('school_class_id', $homeroomClass->id);
            $hrMarked = $homeroomRecords->count();
            $hrPresent = $homeroomRecords->whereIn('status', ['present', 'late'])->count();
            $hrAbsent = $homeroomRecords->where('status', 'absent')->count();
            $hrLate = $homeroomRecords->where('status', 'late')->count();
            $hrRate = $hrMarked > 0 ? (int) round(($hrPresent / $hrMarked) * 100) : null;
            $hrSavedAt = $homeroomRecords->max('updated_at');

            $homeroomStats = [
                'class' => $homeroomClass,
                'total_students' => (int) ($homeroomClass->students_count ?? 0),
                'marked_count' => $hrMarked,
                'present_count' => $hrPresent,
                'absent_count' => $hrAbsent,
                'late_count' => $hrLate,
                'attendance_rate' => $hrRate,
                'is_completed' => $hrMarked > 0,
                'saved_at' => $hrSavedAt ? Carbon::parse($hrSavedAt)->format('g:i A') : null,
            ];
        }

        // 6. Today's Absentee Roster (with rapid parent contact shortcuts)
        $absentStudentsToday = $todayAttendances
            ->filter(fn (Attendance $a): bool => $a->status === 'absent')
            ->values()
            ->map(function (Attendance $attendance): array {
                $details = $attendance->studentDetails;
                $student = $attendance->student;
                $schoolClass = $attendance->schoolClass;

                $parentPhone = $details ? $details->guardian_phone : null;
                $parentEmail = $details ? $details->guardian_email : null;
                $parentName = ($details && $details->guardian_name) ? $details->guardian_name : 'Parent / Guardian';

                return [
                    'student_id' => $attendance->student_id,
                    'student_name' => $student ? $student->name : 'Unknown Student',
                    'admission_number' => ($details && $details->admission_number) ? $details->admission_number : '—',
                    'class_id' => $attendance->school_class_id,
                    'class_name' => $schoolClass ? $schoolClass->name : 'Class',
                    'parent_name' => $parentName,
                    'parent_phone' => $parentPhone,
                    'parent_email' => $parentEmail,
                    'parent_notified' => (bool) $attendance->parent_notified,
                    'notes' => $attendance->notes,
                    'scanned_at_gate' => (bool) ($attendance->source === 'biometric' || $attendance->scanned_at !== null),
                ];
            });

        // 7. Weekly 5-Day Attendance Turnout Trend (Weekdays)
        $cursor = today();
        $days = [];
        while (count($days) < 5) {
            if (! $cursor->isWeekend()) {
                $days[] = $cursor->copy();
            }
            $cursor = $cursor->copy()->subDay();
        }
        $days = array_reverse($days);

        $trendAttendances = Attendance::where('institution_id', $institution->id)
            ->whereIn('school_class_id', $taughtClassIds)
            ->whereIn('date', array_map(fn ($d) => $d->toDateString(), $days))
            ->get()
            ->groupBy(fn (Attendance $att): string => Carbon::parse($att->date)->toDateString());

        $weeklyAttendanceTrend = [];
        foreach ($days as $day) {
            $dateStr = $day->toDateString();
            $records = $trendAttendances->get($dateStr, collect());
            $marked = $records->count();
            $present = $records->whereIn('status', ['present', 'late'])->count();
            $absent = $records->where('status', 'absent')->count();

            $weeklyAttendanceTrend[] = [
                'date' => $dateStr,
                'label' => $day->isToday() ? 'Today' : $day->format('D, M j'),
                'day_name' => $day->format('D'),
                'is_today' => $day->isToday(),
                'total_marked' => $marked,
                'present' => $present,
                'absent' => $absent,
                'rate' => $marked > 0 ? (int) round(($present / $marked) * 100) : null,
            ];
        }

        // 8. Recent Classroom Assessments & Marksheet Progress
        $recentAssessments = ExamSitting::where('institution_id', $institution->id)
            ->when($taughtClassIds->isNotEmpty(), fn ($q) => $q->where(function ($sub) use ($taughtClassIds) {
                $sub->whereIn('class_id', $taughtClassIds)
                    ->orWhereNull('class_id');
            }))
            ->with(['schoolClass'])
            ->latest('created_at')
            ->take(5)
            ->get()
            ->map(function (ExamSitting $sitting): array {
                $schoolClass = $sitting->schoolClass;

                return [
                    'id' => $sitting->id,
                    'title' => $sitting->title,
                    'term' => $sitting->term,
                    'academic_year' => $sitting->academic_year,
                    'category_label' => $sitting->assessmentCategoryLabel(),
                    'class_name' => $schoolClass ? $schoolClass->name : 'All Classes',
                    'class_id' => $sitting->class_id,
                    'is_classroom_assessment' => $sitting->isClassroomAssessment(),
                    'is_sba' => $sitting->isSba(),
                    'created_at' => $sitting->created_at?->format('M j, Y'),
                ];
            });

        return [
            'scope' => 'teacher',
            'institution' => $institution,
            'homeroom_class' => $homeroomClass,
            'homeroom_stats' => $homeroomStats,
            'teaching_allocations' => $teachingAllocations,
            'students_count' => (clone $teacherStudentsQuery)->count(),
            'male_students_count' => (int) ($teacherGenderCounts->male ?? 0),
            'female_students_count' => (int) ($teacherGenderCounts->female ?? 0),

            // Attendance & Biometrics
            'attendance_today_count' => $rollcallPresentCount > 0 ? $rollcallPresentCount : $biometricScansTodayCount,
            'biometric_scans_today_count' => $biometricScansTodayCount,
            'biometric_status_text' => $biometricScansTodayCount > 0
                ? "{$biometricScansTodayCount} biometric check-ins"
                : 'Biometric check-in not done today',

            'rollcall_completed' => $rollcallCompleted,
            'rollcall_present_count' => $rollcallPresentCount,
            'rollcall_absent_count' => $rollcallAbsentCount,
            'rollcall_late_count' => $rollcallLateCount,
            'rollcall_total_marked' => $rollcallMarkedCount,
            'rollcall_attendance_rate' => $rollcallAttendanceRate,
            'rollcall_saved_at' => $rollcallSavedAt ? Carbon::parse($rollcallSavedAt)->format('g:i A') : null,
            'classes_completed_count' => $classesCompletedCount,
            'classes_total_count' => $totalAccessibleClassesCount,

            // Lists & Trends
            'absent_students_today' => $absentStudentsToday,
            'weekly_attendance_trend' => $weeklyAttendanceTrend,
            'recent_assessments' => $recentAssessments,
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
