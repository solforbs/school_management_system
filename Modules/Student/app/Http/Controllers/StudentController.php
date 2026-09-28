<?php

namespace Modules\Student\Http\Controllers;

use App\Http\Controllers\Concerns\Sortable;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Export\TableExportService;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Institution\Models\Institution;
use Modules\Result\Services\ResultAccessService;
use Modules\Student\Actions\SaveStudent;
use Modules\Student\Exceptions\StudentLimitException;
use Modules\Student\Models\StudentDetails;
use Modules\Teacher\Models\TeacherDetails;

class StudentController extends Controller
{
    use Sortable;

    private const HEADERS = ['Student Name', 'Admission No', 'Student No', 'Class', 'Gender', 'Guardian', 'Guardian Phone', 'Status'];

    public function __construct(
        private readonly SaveStudent $saveStudent,
    ) {}

    /**
     * Export the student directory (Excel/PDF) with the same filters and
     * institution/role scoping the Livewire index component applies.
     */
    public function export(Request $request, TableExportService $exporter)
    {
        abort_unless(Auth::user()->can('export student'), 403);

        $query = $this->scopedForExport()
            ->with(['student', 'schoolClass'])
            ->when($request->filled('status'), fn ($q) => $q->where('enrollment_status', $request->string('status')->value()))
            ->when($request->filled('class_id'), fn ($q) => $q->where('class_id', $request->integer('class_id')))
            ->when($request->filled('gender'), fn ($q) => $q->where('gender', $request->string('gender')->value()))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->string('search')->value().'%';

                $q->where(function ($inner) use ($term) {
                    $inner->where('admission_number', 'like', $term)
                        ->orWhere('student_number', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhereHas('student', fn ($q2) => $q2->where('name', 'like', $term)->orWhere('email', 'like', $term));
                });
            })
            ->orderByDesc('created_at');

        $rows = $query->get()->map(fn (StudentDetails $details) => $this->toExportRow($details));

        $filename = 'students-'.now()->format('Y-m-d');
        $format = $request->string('format')->lower()->value();

        return $format === 'pdf'
            ? $exporter->pdf($rows, self::HEADERS, $filename, 'Student Directory')
            : $exporter->excel($rows, self::HEADERS, $filename, 'Student Directory');
    }

    /**
     * Same institution/role scoping as the Livewire index component's
     * scoped() method: a Parent only sees their own children, everyone
     * else (non-admin) is limited to their current institution.
     */
    private function scopedForExport()
    {
        $query = StudentDetails::query();

        if (Auth::user()->hasRole('Parent')) {
            return $query->where('parent_id', Auth::id());
        }

        if (Auth::user()->hasRole('Teacher')) {
            $classIds = ResultAccessService::classIds(Auth::user());

            return $query->where('institution_id', currentInstitutionId() ?? 0)
                ->whereIn('class_id', $classIds);
        }

        if (! isAdmin()) {
            return $query->where('institution_id', currentInstitutionId() ?? 0);
        }

        return $query;
    }

    /**
     * @return array<int, string>
     */
    private function toExportRow(StudentDetails $details): array
    {
        return [
            $details->student?->name ?? 'Student',
            $details->admission_number ?? 'N/A',
            $details->student_number ?? 'N/A',
            $details->schoolClass?->name ?? 'Unassigned',
            $details->gender ? ucfirst($details->gender) : 'Unspecified',
            $details->guardian_name ?: ($details->parent_name ?: 'Not Listed'),
            $details->guardian_phone ?: ($details->parent_phone ?: ($details->phone ?: 'N/A')),
            $details->enrollment_status ? ucfirst($details->enrollment_status) : 'Unknown',
        ];
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $canCreate = $user->can('create student');

        // Check if teacher has explicit enrollment permission
        if (! $canCreate && $user->hasRole('Teacher')) {
            $teacherDetails = TeacherDetails::where('teacher_id', $user->id)
                ->where('institution_id', currentInstitutionId())
                ->first();
            $canCreate = $teacherDetails?->can_enroll_students ?? false;
        }

        abort_unless($canCreate, 403, 'You do not have permission to enroll students.');

        // Never trust a client-submitted institution_id for a non-admin -
        // the student is always created under whichever institution is
        // currently active for the acting Director.
        $institutionId = isAdmin()
            ? ($request->integer('institution_id') ?: currentInstitution()?->id)
            : currentInstitution()?->id;

        abort_unless($institutionId, 422, 'No institution selected.');

        $validated = $request->validate(SaveStudent::createRules($institutionId));

        try {
            $this->saveStudent->create($validated, $institutionId, $request->file('profile_image'));

            return redirect()->route('student.index')->with('success', 'Student created successfully!');
        } catch (StudentLimitException $e) {
            // The plan's student ceiling - a business rule, not a failure.
            // Surface it as a field error so it lands in the form's error
            // list rather than as a generic "something went wrong".
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return redirect()->back()->withInput()->withErrors(['name' => $e->getMessage()]);
        } catch (QueryException $e) {
            Log::error('Student create database exception: '.$e->getMessage(), ['exception' => $e]);

            $field = 'name';
            $friendlyMessage = 'Could not enroll student due to a data constraint conflict.';

            if (str_contains($e->getMessage(), 'unique_phone') || str_contains($e->getMessage(), 'parent_phone')) {
                $field = 'parent_phone';
                $friendlyMessage = 'This parent phone number is already registered to another parent account.';
            } elseif (str_contains($e->getMessage(), 'admission_number')) {
                $field = 'admission_number';
                $friendlyMessage = 'This admission number is already assigned to another student.';
            } elseif (str_contains($e->getMessage(), 'student_number')) {
                $field = 'student_number';
                $friendlyMessage = 'This student ID number is already in use.';
            } elseif (str_contains($e->getMessage(), 'email')) {
                $field = 'email';
                $friendlyMessage = 'This email address is already in use by another account.';
            }

            return redirect()->back()
                ->withInput()
                ->withErrors([$field => $friendlyMessage])
                ->with('error', $friendlyMessage);
        } catch (Exception $e) {
            Log::error('Student creation failed: '.$e->getMessage(), ['exception' => $e]);

            return redirect()->back()
                ->withInput()
                ->withErrors(['name' => 'Unable to enroll student. Please check the entered details and try again.'])
                ->with('error', 'Unable to enroll student. Please check the entered details and try again.');
        }
    }

    /**
     * Show the specified resource.
     */
    public function show(User $student)
    {
        abort_unless(Auth::user()->can('view student'), 403);
        $this->authorizeStudentAccess($student);

        return view('student::show', compact('student'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $canUpdate = Auth::user()->can('update student') || Auth::user()->can('edit student');
        abort_unless($canUpdate, 403);

        $student = User::findOrFail($id);
        $this->authorizeStudentAccess($student);

        $studentDetailsId = $student->studentUserDetails?->id;
        $validated = $request->validate(SaveStudent::updateRules($student->id, $studentDetailsId));

        try {
            $this->saveStudent->update($student, $validated, $request->file('profile_image'));

            return redirect()->route('student.show', $student->id)->with('success', 'Student profile updated successfully!');
        } catch (QueryException $e) {
            Log::error('Student update database exception: '.$e->getMessage(), ['exception' => $e]);

            $field = 'name';
            $friendlyMessage = 'Could not update student due to a data constraint conflict.';

            if (str_contains($e->getMessage(), 'unique_phone') || str_contains($e->getMessage(), 'parent_phone')) {
                $field = 'parent_phone';
                $friendlyMessage = 'This parent phone number is already registered to another parent account.';
            } elseif (str_contains($e->getMessage(), 'admission_number')) {
                $field = 'admission_number';
                $friendlyMessage = 'This admission number is already assigned to another student.';
            } elseif (str_contains($e->getMessage(), 'student_number')) {
                $field = 'student_number';
                $friendlyMessage = 'This student ID number is already in use.';
            } elseif (str_contains($e->getMessage(), 'email')) {
                $field = 'email';
                $friendlyMessage = 'This email address is already in use by another account.';
            }

            return redirect()->back()
                ->withInput()
                ->withErrors([$field => $friendlyMessage])
                ->with('error', $friendlyMessage);
        } catch (Exception $e) {
            Log::error('Student update failed: '.$e->getMessage(), ['exception' => $e]);

            return redirect()->back()
                ->withInput()
                ->withErrors(['name' => 'Unable to save student profile changes. Please verify the entered details and try again.'])
                ->with('error', 'Unable to save student profile changes. Please verify the entered details and try again.');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id)
    {
        abort_unless(Auth::user()->can('delete student'), 403);

        $user = User::findOrFail($id);
        $this->authorizeStudentAccess($user);

        if ($user->studentUserDetails) {
            $user->studentUserDetails->delete();
        }
        $user->delete();

        return redirect()->route('student.index')->with('success', 'Sutudent and Parent successfully removed from institution!');
    }

    /**
     * Guards against acting on a student outside the caller's own scope:
     * a Parent may only reach their own children, a Student only
     * themselves, and everyone else (Director/Teacher/Accountant) only
     * students in whichever institution is currently active for them.
     * Admin is unrestricted.
     */
    private function authorizeStudentAccess(User $student): void
    {
        if (isAdmin()) {
            return;
        }

        $user = Auth::user();

        if ($user->hasRole('Parent')) {
            abort_unless(
                StudentDetails::where('student_id', $student->id)->where('parent_id', $user->id)->exists(),
                403
            );

            return;
        }

        if ($user->hasRole('Student')) {
            abort_unless($student->id === $user->id, 403);

            return;
        }

        $studentDetails = StudentDetails::where('student_id', $student->id)->first();
        $studentInstitutionId = $studentDetails?->institution_id;

        abort_unless($studentInstitutionId && $studentInstitutionId === currentInstitution()?->id, 403);

        if ($user->hasRole('Teacher')) {
            $classIds = ResultAccessService::classIds($user);
            abort_unless($studentDetails?->class_id && $classIds->contains((int) $studentDetails->class_id), 403);
        }
    }
}
