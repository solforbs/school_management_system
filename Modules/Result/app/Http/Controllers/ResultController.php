<?php

namespace Modules\Result\Http\Controllers;

use App\Http\Controllers\Concerns\Sortable;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Examinations\Models\Examination;
use Modules\Institution\Models\Institution;
use Modules\Result\Actions\SaveResult;
use Modules\Result\Models\Result;
use Modules\Result\Services\ResultAccessService;

class ResultController extends Controller
{
    use Sortable;

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        abort_unless(Auth::user()->can('create result'), 403);

        $validated = $request->validate(SaveResult::rules());
        $this->assertTeacherCanGrade($validated['class_id'], $validated['examination_id']);

        if (SaveResult::duplicateExists($validated['examination_id'], $validated['student_id'])) {
            return redirect()->back()->withInput()
                ->with('error', 'A result for this student in this examination already exists. Edit it instead.');
        }

        SaveResult::handle($validated, $this->institutionId($request), recordedBy: Auth::id());

        return redirect()->route('result.index')->with('success', 'Result recorded successfully!');
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        abort_unless(Auth::user()->can('view result'), 403);

        $result = $this->scopedResult($id);

        return view('result::show', compact('result'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        abort_unless(Auth::user()->can('edit result') || Auth::user()->can('update result'), 403);

        $result = $this->scopedResult($id);
        $validated = $request->validate(SaveResult::rules());
        $this->assertTeacherCanGrade($validated['class_id'], $validated['examination_id']);

        if (SaveResult::duplicateExists($validated['examination_id'], $validated['student_id'], $result->id)) {
            return redirect()->back()->withInput()
                ->with('error', 'A result for this student in this examination already exists.');
        }

        SaveResult::handle($validated, $this->institutionId($request, $result), $result);

        return redirect()->route('result.show', $result->id)->with('success', 'Result updated!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        abort_unless(Auth::user()->can('delete result'), 403);

        $result = $this->scopedResult($id);
        $result->delete();

        return redirect()->route('result.index')->with('success', 'Result removed!');
    }

    /**
     * The school a result belongs to. Never a client-submitted one for a
     * non-admin - it's always whichever institution is currently
     * active/assigned for them.
     */
    private function institutionId(Request $request, ?Result $result = null): int
    {
        $institutionId = isAdmin()
            ? ($request->integer('institution_id') ?: $result?->institution_id)
            : currentInstitution()?->id;

        abort_unless($institutionId, 422, 'No institution selected.');

        return $institutionId;
    }

    /**
     * Re-verifies server-side (not just via the limited dropdown options)
     * that a Teacher actually owns these marks - either they're the
     * subject's teacher for that class, or they're the class teacher, for
     * whom every subject in their class counts. Closes the gap a crafted
     * request could otherwise exploit to grade a subject they don't teach.
     */
    private function assertTeacherCanGrade(int $classId, int $examinationId): void
    {
        if (! Auth::user()->hasRole('Teacher')) {
            return;
        }

        $subjectId = Examination::find($examinationId)?->subject_id;

        abort_unless(ResultAccessService::canGrade(Auth::user(), $classId, $subjectId), 403);
    }

    private function scopeToViewer($query): void
    {
        ResultAccessService::scopeVisibleResults($query, Auth::user());
    }

    private function scopedResult(int $id): Result
    {
        $query = Result::with(['institution', 'schoolClass', 'student', 'examination.subject', 'recordedBy']);
        $this->scopeToViewer($query);

        return $query->findOrFail($id);
    }
}
