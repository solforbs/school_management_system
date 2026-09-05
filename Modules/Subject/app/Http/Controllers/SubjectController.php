<?php

namespace Modules\Subject\Http\Controllers;

use App\Http\Controllers\Concerns\Sortable;
use App\Http\Controllers\Controller;
use App\Services\Export\TableExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Institution\Models\Institution;
use Modules\Student\Models\StudentDetails;
use Modules\Subject\Actions\SaveSubject;
use Modules\Subject\Models\Subject;

class SubjectController extends Controller
{
    use Sortable;

    private const HEADERS = ['Subject', 'Code', 'Institution', 'Type', 'Linked Exams'];

    /**
     * Export subjects (Excel or PDF) with the same filters and institution
     * scoping the index screen applies.
     */
    public function export(Request $request, TableExportService $exporter)
    {
        abort_unless(Auth::user()->can('export subject'), 403);

        $search = (string) $request->query('search', '');
        $compulsory = (string) $request->query('compulsory', '');

        $subjects = Subject::with(['institution'])
            ->withCount('examinations')
            ->when(! isAdmin(), fn ($q) => $q->where('institution_id', currentInstitutionId()))
            ->when(isTeacher(), function ($q) {
                $q->whereIn('id', function ($sub) {
                    $sub->select('subject_id')
                        ->from('teacher_subjects')
                        ->where('teacher_id', auth()->id());
                });
            })
            ->when($search !== '', function ($q) use ($search) {
                $term = '%'.$search.'%';
                $q->where('name', 'like', $term)->orWhere('code', 'like', $term);
            })
            ->when($compulsory !== '', fn ($q) => $q->where('is_compulsory', $compulsory === 'yes'))
            ->orderBy('name')
            ->get();

        $rows = $subjects->map(fn (Subject $subject) => $this->toExportRow($subject));
        $filename = 'subjects-'.now()->format('Y-m-d');

        return $request->string('format')->lower()->value() === 'pdf'
            ? $exporter->pdf($rows, self::HEADERS, $filename, 'Subjects')
            : $exporter->excel($rows, self::HEADERS, $filename, 'Subjects');
    }

    /**
     * @return array<int, string>
     */
    private function toExportRow(Subject $subject): array
    {
        $examCount = $subject->examinations_count ?? $subject->examinations()->count();

        return [
            $subject->name,
            $subject->code ?? '—',
            $subject->institution?->name ?? '—',
            $subject->is_compulsory ? 'Compulsory' : 'Optional',
            (string) $examCount,
        ];
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        abort_unless(Auth::user()->can('create subject'), 403);

        $validated = $request->validate(SaveSubject::rules());

        SaveSubject::handle(
            $validated + ['is_compulsory' => $request->boolean('is_compulsory'), 'is_active' => $request->boolean('is_active', true)],
            $this->institutionId(),
        );

        return redirect()->route('subject.index')->with('success', 'Subject created successfully!');
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        abort_unless(Auth::user()->can('view subject'), 403);

        $subject = $this->scopedSubject($id, ['examinations']);

        $subject->setRelation('examinations', $this->sortCollection(
            $subject->examinations,
            sortable: ['title' => 'title', 'exam_date' => 'exam_date'],
            defaultColumn: 'exam_date',
            defaultDirection: 'asc',
        ));

        return view('subject::show', compact('subject'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        abort_unless(Auth::user()->can('edit subject') || Auth::user()->can('update subject'), 403);

        $subject = $this->scopedSubject($id);
        $validated = $request->validate(SaveSubject::rules());

        SaveSubject::handle(
            $validated + ['is_compulsory' => $request->boolean('is_compulsory'), 'is_active' => $request->boolean('is_active', true)],
            $this->institutionId($subject),
            $subject,
        );

        return redirect()->route('subject.show', $subject->id)->with('success', 'Subject updated!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        abort_unless(Auth::user()->can('delete subject'), 403);

        $subject = $this->scopedSubject($id);
        $subject->delete();

        return redirect()->route('subject.index')->with('success', 'Subject removed!');
    }

    /**
     * The school a subject belongs to. Never a client-submitted one for a
     * non-admin - it's always whichever institution is currently active for
     * them.
     */
    private function institutionId(?Subject $subject = null): int
    {
        $institutionId = $subject?->institution_id ?? currentInstitution()?->id;

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

    private function scopedSubject(int $id, array $with = []): Subject
    {
        $query = Subject::with(array_merge(['institution'], $with));
        $this->scopeToViewer($query);

        return $query->findOrFail($id);
    }
}
