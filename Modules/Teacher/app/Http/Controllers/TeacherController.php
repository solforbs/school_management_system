<?php

namespace Modules\Teacher\Http\Controllers;

use App\Http\Controllers\Concerns\Sortable;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Modules\Institution\Models\Institution;
use Modules\Teacher\Actions\SaveTeacher;
use Modules\Teacher\Models\TeacherDetails;

class TeacherController extends Controller
{
    use Sortable;

    /**
     * Display a listing of the resource.
     */
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        abort_unless(Auth::user()->can('create teacher'), 403);

        $validated = $request->validate(SaveTeacher::createRules());

        // Never trust a client-submitted institution_id for a non-admin.
        $institutionId = isAdmin()
            ? ($request->integer('institution_id') ?: currentInstitution()?->id)
            : currentInstitution()?->id;

        abort_unless($institutionId, 422, 'No institution selected.');

        try {
            SaveTeacher::create($validated, $institutionId);

            return redirect()->route('teacher.index')->with('success', 'Teacher created successfully!');
        } catch (\Exception $e) {
            Log::error('Teacher creation failed: '.$e->getMessage());

            return redirect()->back()->withInput()
                ->with('error', 'Failed to create teacher: '.$e->getMessage());
        }
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        abort_unless(Auth::user()->can('view teacher'), 403);

        $teacherDetails = TeacherDetails::with(['teacher', 'institution'])->where('teacher_id', $id)->firstOrFail();

        $this->authorizeAccessTo($teacherDetails);

        return view('teacher::show', compact('teacherDetails'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        abort_unless(Auth::user()->can('update teacher'), 403);

        $teacherDetails = TeacherDetails::with('teacher')->where('teacher_id', $id)->firstOrFail();
        $this->authorizeAccessTo($teacherDetails);

        $validated = $request->validate(SaveTeacher::updateRules($teacherDetails->teacher, $teacherDetails));

        $institutionId = isAdmin()
            ? ($request->integer('institution_id') ?: $teacherDetails->institution_id)
            : currentInstitution()?->id;

        abort_unless($institutionId, 422, 'No institution selected.');

        try {
            SaveTeacher::update($teacherDetails, $validated, $institutionId);

            return redirect()->route('teacher.show', $id)->with('success', 'Teacher updated successfully!');
        } catch (\Exception $e) {
            Log::error('Teacher update failed: '.$e->getMessage());

            return redirect()->back()->withInput()
                ->with('error', 'Failed to update teacher: '.$e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        abort_unless(Auth::user()->can('delete teacher'), 403);

        $teacherDetails = TeacherDetails::where('teacher_id', $id)->firstOrFail();

        $this->authorizeAccessTo($teacherDetails);

        $teacher = User::findOrFail($id);
        $teacherDetails->delete();
        $teacher->delete();

        return redirect()->route('teacher.index')->with('success', 'Teacher removed successfully!');
    }

    /**
     * Ensure a non-admin viewer only manages teachers from their currently
     * active institution.
     */
    private function authorizeAccessTo(TeacherDetails $teacherDetails): void
    {
        if (isAdmin()) {
            return;
        }

        abort_unless($teacherDetails->institution_id === currentInstitution()?->id, 403);
    }
}
