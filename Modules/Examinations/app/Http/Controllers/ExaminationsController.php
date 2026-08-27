<?php

namespace Modules\Examinations\Http\Controllers;

use App\Http\Controllers\Concerns\Sortable;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Examinations\Actions\SaveExamination;
use Modules\Examinations\Models\Examination;
use Modules\Student\Models\StudentDetails;

class ExaminationsController extends Controller
{
    use Sortable;

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        abort_unless(Auth::user()->can('create examination'), 403);

        $validated = $request->validate(SaveExamination::rules());

        SaveExamination::handle($validated, $this->institutionId($request));

        return redirect()->route('examinations.index')->with('success', 'Examination created successfully!');
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        abort_unless(Auth::user()->can('view examination'), 403);

        $examination = $this->scopedExamination($id);

        return view('examinations::show', compact('examination'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        abort_unless(Auth::user()->can('update examination'), 403);

        $examination = $this->scopedExamination($id);
        $validated = $request->validate(SaveExamination::rules());

        SaveExamination::handle($validated, $this->institutionId($request, $examination), $examination);

        return redirect()->route('examinations.show', $examination->id)->with('success', 'Examination updated!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        abort_unless(Auth::user()->can('delete examination'), 403);

        $examination = $this->scopedExamination($id);
        $examination->delete();

        return redirect()->route('examinations.index')->with('success', 'Examination removed!');
    }

    /**
     * The school an examination belongs to. Never a client-submitted one
     * for a non-admin.
     */
    private function institutionId(Request $request, ?Examination $examination = null): int
    {
        $institutionId = isAdmin()
            ? ($request->integer('institution_id') ?: $examination?->institution_id)
            : currentInstitution()?->id;

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

    private function scopedExamination(int $id): Examination
    {
        $query = Examination::with(['institution', 'schoolClass', 'subject']);
        $this->scopeToViewer($query);

        return $query->findOrFail($id);
    }
}
