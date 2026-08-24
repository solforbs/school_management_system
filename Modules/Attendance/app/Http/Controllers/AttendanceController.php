<?php

namespace Modules\Attendance\Http\Controllers;

use App\Http\Controllers\Concerns\Sortable;
use App\Http\Controllers\Controller;
use App\Models\Devices;
use App\Models\User;
use Athwari\LaravelZktecoAdms\Enums\AttendanceStatus;
use Athwari\LaravelZktecoAdms\Models\ZktecoAttendanceLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Modules\Institution\Models\Institution;

class AttendanceController extends Controller
{
    use Sortable;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        abort_unless(Auth::user()->can('view attendance'), 403);

        $user = Auth::user();
        $institutionId = $this->resolveInstitutionId($user);
        $institution = null;
        $logs = null;

        if ($institutionId) {
            $institution = Institution::find($institutionId);
        }

        if ($institution) {
            $logs = $this->applySort(
                $this->scopedAttendanceLogs($institution, $user, $request),
                sortable: ['occurred_at', 'status'],
                defaultColumn: 'occurred_at',
                defaultDirection: 'desc',
            )->paginate(25)->withQueryString();

            $logs->through(fn (ZktecoAttendanceLog $log) => $this->toDisplayRow($log));
        }

        return view('attendance::index', compact('institution', 'logs'));
    }

    /**
     * Export attendance logs to CSV stream with active filters applied.
     */
    public function export(Request $request)
    {
        abort_unless(Auth::user()->can('view attendance'), 403);

        $user = Auth::user();
        $institutionId = $this->resolveInstitutionId($user);
        $institution = $institutionId ? Institution::find($institutionId) : null;

        abort_unless($institution, 404, 'No institution found.');

        $query = $this->scopedAttendanceLogs($institution, $user, $request)
            ->orderByDesc('occurred_at');

        $filename = 'attendance-logs-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Student Name', 'Admission No', 'Device / Terminal', 'Status', 'Verify Mode', 'Date & Time']);

            $query->chunk(500, function ($logs) use ($handle) {
                foreach ($logs as $log) {
                    $row = $this->toDisplayRow($log);
                    fputcsv($handle, [
                        $row['student_name'],
                        $row['admission_number'],
                        $row['device_name'],
                        $row['status_label'],
                        $row['verify_mode_label'],
                        $row['occurred_at'],
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

        $logs = $this->scopedAttendanceLogs($institution, Auth::user(), $request)
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
     * Build the attendance log query for this user/institution: scoped to
     * devices in the institution, restricted to the caller's own children
     * (Parent) or self (Student), and filtered by the request's search/date
     * inputs. Logs that don't map back to an actual student are excluded.
     */
    private function scopedAttendanceLogs(Institution $institution, User $user, Request $request): Builder
    {
        if (! isAdmin()) {
            abort_unless($this->allowedInstitutionIds($user)->contains($institution->id), 403);
        }

        $deviceIds = Devices::whereInstitutionId($institution->id)
            ->when($request->filled('device_id'), fn ($q) => $q->where('id', $request->integer('device_id')))
            ->whereNotNull('zkteco_device_id')
            ->pluck('zkteco_device_id');

        $pins = $this->visiblePins($user);
        $search = $request->string('search')->trim()->toString();

        return ZktecoAttendanceLog::whereIn('device_id', $deviceIds)
            // A log only counts if it maps back to an actual student record.
            ->whereHas('zktecoUser.appUser.studentUserDetails')
            ->with(['device', 'zktecoUser.appUser.studentUserDetails'])
            ->when($pins !== null, fn ($q) => $q->whereIn('pin', $pins))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('pin', 'like', "%{$search}%")
                        ->orWhereHas('zktecoUser.appUser', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('zktecoUser.appUser.studentUserDetails', function ($q) use ($search) {
                            $q->where('admission_number', 'like', "%{$search}%")
                                ->orWhere('student_number', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->filled('from'), fn ($q) => $q->whereDate('occurred_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('occurred_at', '<=', $request->date('to')));
    }

    /**
     * The single institution to load onto the index page for this user:
     * their own (Director/Admin), their child's (Parent), or their own
     * enrollment/employment (Student/Teacher).
     */
    private function resolveInstitutionId(User $user): ?int
    {
        return currentInstitution()?->id;
    }

    /**
     * Institution IDs a non-admin user is allowed to view attendance for.
     */
    private function allowedInstitutionIds(User $user): Collection
    {
        if ($user->hasRole('Parent')) {
            return $user->children()->pluck('institution_id')->filter()->unique()->values();
        }

        if ($user->hasRole('Student')) {
            return collect([$user->studentUserDetails?->institution_id])->filter()->values();
        }

        if ($user->hasRole('Teacher')) {
            return collect([$user->teacherUserDetails?->institution_id])->filter()->values();
        }

        // Director/Accountant/other institution owners - just the one
        // currently active, not every institution they own.
        return collect([currentInstitution()?->id])->filter()->values();
    }

    /**
     * Device PINs (== app User IDs) this user may see attendance for, or
     * null when the role isn't restricted to specific students (e.g.
     * Director sees every student in the institution).
     */
    private function visiblePins(User $user): ?array
    {
        if ($user->hasRole('Parent')) {
            return $user->children()->pluck('student_id')->map(fn ($id) => (string) $id)->all();
        }

        if ($user->hasRole('Student')) {
            return [(string) $user->id];
        }

        return null;
    }

    /**
     * Flatten a log into the display-ready row consumed by the index view.
     */
    private function toDisplayRow(ZktecoAttendanceLog $log): array
    {
        $student = $log->zktecoUser->appUser;
        $studentDetails = $student->studentUserDetails;
        [$statusLabel, $statusColor] = $this->statusMeta($log->status);

        return [
            'id' => $log->id,
            'student_name' => $student->name,
            'admission_number' => $studentDetails->admission_number ?? '—',
            'device_name' => $log->device?->name ?? $log->device?->serial_number ?? '—',
            'status_label' => $statusLabel,
            'status_color' => $statusColor,
            'verify_mode_label' => $log->verify_mode?->getLabel() ?? 'Unknown',
            'occurred_at' => $log->occurred_at?->format('M j, Y g:i A') ?? '—',
        ];
    }

    /**
     * Flatten a log into the JSON shape returned by the API endpoint.
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

    /**
     * [Label, Flux badge color] for a status. Not using AttendanceStatus::getLabel()
     * here because it resolves via a translation key that this app has no
     * translations for and would just print the raw key.
     */
    private function statusMeta(?AttendanceStatus $status): array
    {
        return match ($status) {
            AttendanceStatus::CheckIn => ['Check In', 'emerald'],
            AttendanceStatus::CheckOut => ['Check Out', 'red'],
            AttendanceStatus::BreakOut => ['Break Out', 'amber'],
            AttendanceStatus::BreakIn => ['Break In', 'sky'],
            AttendanceStatus::OvertimeIn => ['Overtime In', 'indigo'],
            AttendanceStatus::OvertimeOut => ['Overtime Out', 'zinc'],
            default => ['Unknown', 'zinc'],
        };
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        abort_unless(Auth::user()->can('create attendance'), 403);

        return view('attendance::create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        abort_unless(Auth::user()->can('create attendance'), 403);
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        abort_unless(Auth::user()->can('view attendance'), 403);

        return view('attendance::show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        abort_unless(Auth::user()->can('edit attendance'), 403);

        return view('attendance::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        abort_unless(Auth::user()->can('update attendance'), 403);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        abort_unless(Auth::user()->can('delete attendance'), 403);
    }
}
