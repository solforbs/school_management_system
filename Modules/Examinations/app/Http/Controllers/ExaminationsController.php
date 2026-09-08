<?php

namespace Modules\Examinations\Http\Controllers;

use App\Http\Controllers\Concerns\Sortable;
use App\Http\Controllers\Controller;
use App\Services\Export\TableExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Examinations\Actions\SaveExamination;
use Modules\Examinations\Actions\SaveExamSitting;
use Modules\Examinations\Models\Examination;
use Modules\Student\Models\StudentDetails;

class ExaminationsController extends Controller
{
    use Sortable;

    private const HEADERS = ['Title', 'Subject', 'Class', 'Sitting', 'Term', 'Exam Date', 'Start Time', 'Room', 'Total Marks'];

    /**
     * Export the examinations list (Excel or PDF) with the same filters and
     * institution/role scoping the index listing applies.
     */
    public function export(Request $request, TableExportService $exporter)
    {
        abort_unless(Auth::user()->can('export examination'), 403);

        $today = now()->toDateString();

        $query = Examination::with(['schoolClass', 'subject']);
        $this->scopeToViewer($query);

        $search = $request->string('search')->value();
        $classId = $request->string('class_id')->value();
        $subjectId = $request->string('subject_id')->value();
        $examType = $request->string('exam_type')->value();
        $status = $request->string('status')->value();

        $query->when($search !== '', fn ($q) => $q->where(function ($sq) use ($search) {
            $sq->where('title', 'like', '%'.$search.'%')
                ->orWhere('subject_name', 'like', '%'.$search.'%')
                ->orWhereHas('subject', fn ($subQ) => $subQ->where('name', 'like', '%'.$search.'%'));
        }))
            ->when($classId !== '', fn ($q) => $q->where('class_id', $classId))
            ->when($subjectId !== '', fn ($q) => $q->where('subject_id', $subjectId))
            ->when($examType !== '', fn ($q) => $q->where('exam_type', $examType))
            ->when($status === 'upcoming', fn ($q) => $q->where('exam_date', '>', $today))
            ->when($status === 'completed', fn ($q) => $q->where('exam_date', '<', $today))
            ->when($status === 'today', fn ($q) => $q->where('exam_date', $today))
            ->orderBy('exam_date', 'desc');

        $rows = $query->get()->map(fn (Examination $examination) => $this->toExportRow($examination));

        $format = $request->string('format')->lower()->value();
        $filename = 'examinations-'.now()->format('Y-m-d');

        return $format === 'pdf'
            ? $exporter->pdf($rows, self::HEADERS, $filename, 'Examinations')
            : $exporter->excel($rows, self::HEADERS, $filename, 'Examinations');
    }

    /**
     * Flatten an examination into the row shape shared by the Excel and PDF
     * exports.
     *
     * @return array<int, string>
     */
    private function toExportRow(Examination $examination): array
    {
        return [
            $examination->title,
            $examination->subject?->name ?? $examination->subject_name ?? 'All subjects',
            $examination->schoolClass?->name ?? $examination->class_name ?? 'All classes',
            $examination->exam_type ? $examination->examTypeLabel() : 'Not specified',
            $examination->term ?? 'Term 1',
            $examination->exam_date ? $examination->exam_date->format('Y-m-d') : '',
            $examination->start_time ? $examination->start_time->format('H:i') : '',
            $examination->room ?? '',
            (string) ($examination->total_marks ?? 100),
        ];
    }

    /**
     * Store a newly created resource in storage.
     *
     * Creating an examination creates a sitting, which fans out into one
     * paper per subject per class - a school never enters a subject at a
     * time.
     */
    public function store(Request $request)
    {
        abort_unless(Auth::user()->can('create examination'), 403);

        $validated = $request->validate(SaveExamSitting::rules());

        SaveExamSitting::handle($validated, $this->institutionId($request));

        return redirect()->route('examinations.index')->with('success', 'Examination created — a paper was scheduled for every subject and class.');
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
