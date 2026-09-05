<?php

namespace Modules\Result\Http\Controllers;

use App\Http\Controllers\Concerns\Sortable;
use App\Http\Controllers\Controller;
use App\Services\Export\TableExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Classes\Models\SchoolClass;
use Modules\Examinations\Models\Examination;
use Modules\Institution\Models\Institution;
use Modules\Result\Actions\SaveResult;
use Modules\Result\Models\Result;
use Modules\Result\Services\ResultAccessService;
use Modules\Student\Models\StudentDetails;

class ResultController extends Controller
{
    use Sortable;

    private const HEADERS = ['Class', 'Level', 'Students', 'Marks Recorded', 'Mean Score'];

    /**
     * Export the per-class results overview shown on the index landing page
     * (Excel or PDF), scoped to exactly the classes this viewer may open -
     * a Teacher's own classes, everyone else's institution.
     */
    public function export(Request $request, TableExportService $exporter)
    {
        abort_unless(Auth::user()->can('export result'), 403);

        $classes = ResultAccessService::selectableClasses(Auth::user());
        $classIds = $classes->pluck('id');

        $resultStats = $classIds->isEmpty() ? collect() : Result::query()
            ->selectRaw('class_id, count(*) as results_count, avg(marks_obtained) as mean_mark')
            ->whereIn('class_id', $classIds)
            ->groupBy('class_id')
            ->get()
            ->keyBy('class_id');

        $studentStats = $classIds->isEmpty() ? collect() : StudentDetails::query()
            ->selectRaw('class_id, count(*) as students_count')
            ->whereIn('class_id', $classIds)
            ->groupBy('class_id')
            ->get()
            ->keyBy('class_id');

        $rows = $classes->map(fn (SchoolClass $class) => $this->toExportRow($class, $resultStats, $studentStats));

        $format = $request->string('format')->lower()->value();
        $filename = 'exam-results-overview-'.now()->format('Y-m-d');

        return $format === 'pdf'
            ? $exporter->pdf($rows, self::HEADERS, $filename, 'Exam Results Overview')
            : $exporter->excel($rows, self::HEADERS, $filename, 'Exam Results Overview');
    }

    /**
     * Flatten a class and its headline figures into the row shape shared by
     * the Excel and PDF exports.
     *
     * @return array<int, string>
     */
    private function toExportRow(SchoolClass $class, $resultStats, $studentStats): array
    {
        $resultRow = $resultStats[$class->id] ?? null;
        $studentRow = $studentStats[$class->id] ?? null;

        return [
            $class->name,
            $class->level ?? 'Class',
            (string) (int) ($studentRow->students_count ?? 0),
            (string) (int) ($resultRow->results_count ?? 0),
            number_format(round((float) ($resultRow->mean_mark ?? 0), 1), 1),
        ];
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        abort_unless(Auth::user()->can('create result'), 403);

        $validated = $request->validate(SaveResult::rules());
        $subjectId = $this->resolvedSubjectId($validated);
        $this->assertTeacherCanGrade((int) $validated['class_id'], $subjectId);

        if (SaveResult::duplicateExists($validated['examination_id'], $validated['student_id'], null, $subjectId)) {
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
        $subjectId = $this->resolvedSubjectId($validated);
        $this->assertTeacherCanGrade((int) $validated['class_id'], $subjectId);

        if (SaveResult::duplicateExists($validated['examination_id'], $validated['student_id'], $result->id, $subjectId)) {
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
    private function assertTeacherCanGrade(int $classId, ?int $subjectId): void
    {
        if (! Auth::user()->hasRole('Teacher')) {
            return;
        }

        abort_unless(ResultAccessService::canGrade(Auth::user(), $classId, $subjectId), 403);
    }

    /**
     * The subject a single-form result is for: taken from the form, or
     * from a paper the Director pinned to one subject.
     *
     * @param  array<string, mixed>  $validated
     */
    private function resolvedSubjectId(array $validated): ?int
    {
        if (! empty($validated['subject_id'])) {
            return (int) $validated['subject_id'];
        }

        $pinned = Examination::find($validated['examination_id'])?->subject_id;

        return $pinned !== null ? (int) $pinned : null;
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
