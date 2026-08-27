<?php

namespace Modules\Classes\Http\Controllers;

use App\Http\Controllers\Concerns\Sortable;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Classes\Actions\SaveSchoolClass;
use Modules\Classes\Models\SchoolClass;
use Modules\Institution\Models\Institution;
use Modules\Student\Models\StudentDetails;

class ClassesController extends Controller
{
    use Sortable;

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        abort_unless(Auth::user()->can('create classes'), 403);

        $validated = $request->validate(SaveSchoolClass::rules());

        SaveSchoolClass::handle($validated, $this->institutionId());

        return redirect()->route('classes.index')->with('success', 'Class created successfully!');
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        abort_unless(Auth::user()->can('view classes'), 403);

        $schoolClass = $this->scopedClass($id, ['institution', 'classTeacher', 'students.student']);

        $schoolClass->setRelation('students', $this->sortCollection(
            $schoolClass->students,
            sortable: [
                'name' => fn ($studentDetails) => $studentDetails->student?->name,
                'admission_number' => 'admission_number',
            ],
            defaultColumn: 'admission_number',
            defaultDirection: 'asc',
        ));

        return view('classes::show', compact('schoolClass'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        abort_unless(Auth::user()->can('update classes'), 403);

        $schoolClass = $this->scopedClass($id);
        $validated = $request->validate(SaveSchoolClass::rules());

        SaveSchoolClass::handle($validated, $this->institutionId($schoolClass), $schoolClass);

        return redirect()->route('classes.show', $schoolClass->id)->with('success', 'Class updated!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        abort_unless(Auth::user()->can('delete classes'), 403);

        $schoolClass = $this->scopedClass($id);
        $schoolClass->delete();

        return redirect()->route('classes.index')->with('success', 'Class removed!');
    }

    /**
     * The school a class belongs to. Never a client-submitted one for a
     * non-admin - it's always whichever institution is currently active for
     * them.
     */
    private function institutionId(?SchoolClass $schoolClass = null): int
    {
        $institutionId = $schoolClass?->institution_id ?? currentInstitution()?->id;

        abort_unless($institutionId, 422, 'No institution selected.');

        return $institutionId;
    }

    private function scopeToViewer($query): void
    {
        $user = Auth::user();

        if (isAdmin()) {
            return;
        }

        if ($user->hasRole('Teacher')) {
            $institutionId = $user->teacherUserDetails?->institution_id;
            $query->where('institution_id', $institutionId ?? 0);

            return;
        }

        if ($user->hasAnyRole(['Parent', 'Student'])) {
            $institutionIds = $user->hasRole('Parent')
                ? StudentDetails::where('parent_id', $user->id)->pluck('institution_id')
                : StudentDetails::where('student_id', $user->id)->pluck('institution_id');

            $query->whereIn('institution_id', $institutionIds);

            return;
        }

        $query->where('institution_id', currentInstitution()?->id ?? 0);
    }

    private function scopedClass(int $id, array $with = []): SchoolClass
    {
        $query = SchoolClass::with($with);
        $this->scopeToViewer($query);

        return $query->findOrFail($id);
    }
}
