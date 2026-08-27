<?php

namespace Modules\Curriculum\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Curriculum\Actions\SaveCurriculum;
use Modules\Curriculum\Models\Curriculum;
use Modules\Student\Models\StudentDetails;

class CurriculumController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        abort_unless(Auth::user()->can('create curriculum'), 403);

        $data = $request->validate(SaveCurriculum::rules());

        SaveCurriculum::handle($data, $this->institutionId());

        return redirect()->route('curriculum.index')->with('success', 'Curriculum created successfully!');
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        abort_unless(Auth::user()->can('view curriculum'), 403);

        $curriculum = $this->scopedCurriculum($id);
        $curriculum->load('institution');

        return view('curriculum::show', compact('curriculum'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        abort_unless(Auth::user()->can('update curriculum'), 403);

        $curriculum = $this->scopedCurriculum($id);
        $data = $request->validate(SaveCurriculum::rules());

        SaveCurriculum::handle($data, $this->institutionId($curriculum), $curriculum);

        return redirect()->route('curriculum.index')->with('success', 'Curriculum updated!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        abort_unless(Auth::user()->can('delete curriculum'), 403);

        $curriculum = $this->scopedCurriculum($id);
        $curriculum->delete();

        return back()->with('success', 'Curriculum deleted successfully!');
    }

    /**
     * The school a curriculum belongs to. Never a client-submitted one for
     * a non-admin.
     */
    private function institutionId(?Curriculum $curriculum = null): int
    {
        $institutionId = $curriculum?->institution_id ?? currentInstitution()?->id;

        abort_unless($institutionId, 422, 'No institution selected.');

        return $institutionId;
    }

    private function scopeToViewer($query): void
    {
        $user = Auth::user();

        if (isAdmin()) {
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

    private function scopedCurriculum(int $id): Curriculum
    {
        $query = Curriculum::with('institution');
        $this->scopeToViewer($query);

        return $query->findOrFail($id);
    }
}
