<?php

namespace Modules\Subject\Http\Controllers;

use App\Http\Controllers\Concerns\Sortable;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Examinations\Models\Examination;
use Modules\Institution\Models\Institution;
use Modules\Student\Models\StudentDetails;
use Modules\Subject\Models\Subject;

class SubjectController extends Controller
{
    use Sortable;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        abort_unless(Auth::user()->can('view subject'), 403);

        $institution = currentInstitution();
        $institutionId = $institution?->id ?? 0;

        $baseQuery = Subject::query();
        $this->scopeToViewer($baseQuery);

        // Aggregated DB-level KPIs with zero hydration overhead
        $totalSubjects = (clone $baseQuery)->count();
        $totalCompulsory = (clone $baseQuery)->where('is_compulsory', true)->count();
        $totalElective = (clone $baseQuery)->where('is_compulsory', false)->count();

        $subjectIds = (clone $baseQuery)->pluck('id');
        $totalExams = Examination::whereIn('subject_id', $subjectIds)->count();

        $query = (clone $baseQuery)->with('institution')->withCount('examinations');

        // Search Filter
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('code', 'LIKE', "%{$search}%");
            });
        }

        // Curriculum Classification Filter (Compulsory vs Elective)
        if ($request->filled('is_compulsory')) {
            $query->where('is_compulsory', $request->boolean('is_compulsory'));
        }

        // Status Filter
        if ($request->filled('status')) {
            $isActive = $request->input('status') === 'active';
            $query->where('is_active', $isActive);
        }

        $query = $this->applySort(
            $query,
            sortable: ['name', 'code', 'is_compulsory', 'is_active', 'created_at'],
            defaultColumn: 'name',
            defaultDirection: 'asc',
        );

        $subjects = $query->paginate(12)->withQueryString();

        return view('subject::index', [
            'subjects' => $subjects,
            'totalSubjects' => $totalSubjects,
            'totalCompulsory' => $totalCompulsory,
            'totalElective' => $totalElective,
            'totalExams' => $totalExams,
            'institution' => $institution,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        abort_unless(Auth::user()->can('create subject'), 403);

        $institutions = isAdmin() ? Institution::all() : collect([currentInstitution()])->filter();
        $institution = currentInstitution();

        return view('subject::create', compact('institutions', 'institution'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        abort_unless(Auth::user()->can('create subject'), 403);

        $validated = $this->validated($request);

        Subject::create($validated);

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
            sortable: ['title' => 'title', 'exam_date' => 'exam_date', 'term' => 'term', 'total_marks' => 'total_marks'],
            defaultColumn: 'exam_date',
            defaultDirection: 'desc',
        ));

        $institution = currentInstitution();

        return view('subject::show', compact('subject', 'institution'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        abort_unless(Auth::user()->can('edit subject'), 403);

        $subject = $this->scopedSubject($id);
        $institutions = isAdmin() ? Institution::all() : collect([currentInstitution()])->filter();
        $institution = currentInstitution();

        return view('subject::edit', compact('subject', 'institutions', 'institution'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        abort_unless(Auth::user()->can('edit subject') || Auth::user()->can('update subject'), 403);

        $subject = $this->scopedSubject($id);
        $validated = $this->validated($request);

        $subject->update($validated);

        return redirect()->route('subject.show', $subject->id)->with('success', 'Subject updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        abort_unless(Auth::user()->can('delete subject'), 403);

        $subject = $this->scopedSubject($id);
        $subject->delete();

        return redirect()->route('subject.index')->with('success', 'Subject removed successfully!');
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'institution_id' => ['nullable', 'exists:institutions,id'],
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
        ]);

        $validated['is_compulsory'] = $request->boolean('is_compulsory');
        $validated['is_active'] = $request->boolean('is_active', true);

        $validated['institution_id'] = isAdmin() ? $validated['institution_id'] : currentInstitution()?->id;

        abort_unless($validated['institution_id'], 422, 'No institution selected.');

        return $validated;
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
