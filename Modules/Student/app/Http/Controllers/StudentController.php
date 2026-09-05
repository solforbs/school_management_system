<?php

namespace Modules\Student\Http\Controllers;

use App\Http\Controllers\Concerns\Sortable;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Export\TableExportService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Institution\Models\Institution;
use Modules\Student\Actions\SaveStudent;
use Modules\Student\Models\StudentDetails;

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
        abort_unless(Auth::user()->can('create student'), 403);

        $validated = $request->validate(SaveStudent::createRules());

        // Never trust a client-submitted institution_id for a non-admin -
        // the student is always created under whichever institution is
        // currently active for the acting Director.
        $institutionId = isAdmin()
            ? ($request->integer('institution_id') ?: currentInstitution()?->id)
            : currentInstitution()?->id;

        abort_unless($institutionId, 422, 'No institution selected.');

        try {
            $this->saveStudent->create($validated, $institutionId, $request->file('profile_image'));

            return redirect()->route('student.index')->with('success', 'Student created successfully!');
        } catch (Exception $e) {
            Log::error('Student creation failed: '.$e->getMessage());

            return redirect()->back()->withInput()
                ->with('error', 'Failed to create student: '.$e->getMessage());
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
        abort_unless(Auth::user()->can('update student'), 403);

        $student = User::findOrFail($id);
        $this->authorizeStudentAccess($student);

        $validated = $request->validate(SaveStudent::updateRules());

        try {
            $this->saveStudent->update($student, $validated, $request->file('profile_image'));

            return redirect()->back()->with('success', 'Student updated successfully!');
        } catch (Exception $e) {
            Log::error('Student update failed: '.$e->getMessage());

            return redirect()->back()->withInput()
                ->with('error', 'Failed to update student: '.$e->getMessage());
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

        $studentInstitutionId = StudentDetails::where('student_id', $student->id)->value('institution_id');

        abort_unless($studentInstitutionId && $studentInstitutionId === currentInstitution()?->id, 403);
    }
}
