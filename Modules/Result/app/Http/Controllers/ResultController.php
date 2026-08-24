<?php

namespace Modules\Result\Http\Controllers;

use App\Http\Controllers\Concerns\Sortable;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Modules\Classes\Models\SchoolClass;
use Modules\Examinations\Models\Examination;
use Modules\Institution\Models\Institution;
use Modules\ReportCard\Services\GradingBandService;
use Modules\ReportCard\Services\ReportCardCompletionService;
use Modules\Result\Models\Result;
use Modules\Student\Models\StudentDetails;
use Modules\Timetable\Models\TimetableEntry;

class ResultController extends Controller
{
    use Sortable;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        abort_unless(Auth::user()->can('view result'), 403);

        $query = Result::with(['institution', 'schoolClass', 'student', 'examination']);
        $this->scopeToViewer($query);

        $classId = $request->integer('class_id') ?: null;

        $query
            ->when($classId, fn ($q) => $q->where('class_id', $classId))
            ->when($request->filled('examination_id'), fn ($q) => $q->where('examination_id', $request->integer('examination_id')));

        $search = $request->string('search');
        if ($search->isNotEmpty()) {
            $query->where(function ($q) use ($search) {
                $q->where('grade', $search)
                    ->orWhereHas('student', function ($sq) use ($search) {
                        $sq->where('name', 'LIKE', "%{$search}%");
                    })
                    ->orWhereHas('student.studentUserDetails', function ($suq) use ($search) {
                        $suq->where('admission_number', 'LIKE', "%{$search}%")
                            ->orWhere('student_number', 'LIKE', "%{$search}%");
                    })
                    ->orWhereHas('examination', function ($eq) use ($search) {
                        $eq->where('title', 'LIKE', "%{$search}%")
                            ->orWhere('term', 'LIKE', "%{$search}%");
                    });
            });
        }

        $kpiQuery = clone $query;
        $totalResultsCount = $kpiQuery->count();
        $avgScore = $totalResultsCount > 0 ? round((float) $kpiQuery->avg('marks_obtained'), 1) : 0;
        $highScore = $totalResultsCount > 0 ? (float) $kpiQuery->max('marks_obtained') : 0;
        $passingCount = $totalResultsCount > 0 ? (clone $kpiQuery)->where('marks_obtained', '>=', 50)->count() : 0;
        $passRate = $totalResultsCount > 0 ? round(($passingCount / $totalResultsCount) * 100, 1) : 100;

        $results = $this->applySort(
            $query,
            sortable: ['marks_obtained', 'grade'],
            defaultColumn: 'grade',
            defaultDirection: 'asc',
        )->paginate(15)->withQueryString();

        $classes = $this->scopedClasses();
        $examinations = $this->scopedExaminations($classId);
        $institution = currentInstitution();

        return view('result::index', compact('results', 'classes', 'examinations', 'totalResultsCount', 'avgScore', 'highScore', 'passRate', 'institution'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        abort_unless(Auth::user()->can('create result'), 403);

        $institutions = $this->viewerInstitutions();
        $classes = $this->scopedClasses();
        $examinations = $this->recentExaminationsPerClass();
        $students = $this->scopedStudents();

        return view('result::create', compact('institutions', 'classes', 'examinations', 'students'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        abort_unless(Auth::user()->can('create result'), 403);

        $validated = $this->validated($request);
        $this->assertTeacherCanGrade($validated['class_id'], $validated['examination_id']);

        if (Result::where('examination_id', $validated['examination_id'])
            ->where('student_id', $validated['student_id'])
            ->exists()) {
            return redirect()->back()->withInput()
                ->with('error', 'A result for this student in this examination already exists. Edit it instead.');
        }

        $validated['recorded_by'] = Auth::id();

        $result = Result::create($validated);

        $this->checkReportCardCompletion($result);

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
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        abort_unless(Auth::user()->can('edit result'), 403);

        $result = $this->scopedResult($id);
        $institutions = $this->viewerInstitutions();
        $classes = $this->scopedClasses();
        $examinations = $this->scopedExaminations();
        $students = $this->scopedStudents();

        return view('result::edit', compact('result', 'institutions', 'classes', 'examinations', 'students'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        abort_unless(Auth::user()->can('edit result') || Auth::user()->can('update result'), 403);

        $result = $this->scopedResult($id);
        $validated = $this->validated($request);
        $this->assertTeacherCanGrade($validated['class_id'], $validated['examination_id']);

        if (Result::where('examination_id', $validated['examination_id'])
            ->where('student_id', $validated['student_id'])
            ->where('id', '!=', $result->id)
            ->exists()) {
            return redirect()->back()->withInput()
                ->with('error', 'A result for this student in this examination already exists.');
        }

        $result->update($validated);

        $this->checkReportCardCompletion($result);

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

    private function checkReportCardCompletion(Result $result): void
    {
        $result->loadMissing(['student', 'examination']);

        if (! $result->examination?->term || ! $result->examination?->academic_year || ! $result->student) {
            return;
        }

        app(ReportCardCompletionService::class)->handle($result->student, $result->examination->term, $result->examination->academic_year);
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'institution_id' => ['nullable', 'exists:institutions,id'],
            'class_id' => 'required|exists:classes,id',
            'student_id' => 'required|exists:users,id',
            'examination_id' => 'required|exists:examinations,id',
            'marks_obtained' => 'required|numeric|min:0',
            'remarks' => 'nullable|string',
        ]);

        // Never trust a client-submitted institution_id for a non-admin -
        // it's always whichever institution is currently active/assigned
        // for them.
        $validated['institution_id'] = isAdmin() ? $validated['institution_id'] : currentInstitution()?->id;

        abort_unless($validated['institution_id'], 422, 'No institution selected.');

        $examination = Examination::find($validated['examination_id']);
        if ($examination && $validated['marks_obtained'] > $examination->total_marks) {
            abort(422, "Marks obtained can't exceed the examination's total marks ({$examination->total_marks}).");
        }

        // Grade is auto-computed from the institution's grading scale, not
        // typed by the director - left null if no scale is configured yet.
        $validated['grade'] = null;
        if ($examination && $examination->total_marks > 0) {
            $institution = Institution::find($validated['institution_id']);
            $percentage = $validated['marks_obtained'] / $examination->total_marks * 100;
            $validated['grade'] = $institution ? GradingBandService::resolve($institution, $percentage) : null;
        }

        return $validated;
    }

    /**
     * The (class, subject) pairs a teacher is scheduled to teach, derived
     * from their timetable - the sole source of truth for what a teacher
     * may grade, since there's no dedicated subject-teacher assignment.
     */
    private function teacherAssignedPairs(): Collection
    {
        return TimetableEntry::where('teacher_id', Auth::id())
            ->whereNotNull('subject_id')
            ->whereNotNull('class_id')
            ->get(['class_id', 'subject_id'])
            ->unique(fn ($entry) => $entry->class_id.'-'.$entry->subject_id)
            ->values();
    }

    /**
     * Re-verifies server-side (not just via the limited dropdown options)
     * that a Teacher is actually timetabled to teach the subject being
     * graded for that class - closes the gap a crafted request could
     * otherwise exploit to grade a subject they don't teach.
     */
    private function assertTeacherCanGrade(int $classId, int $examinationId): void
    {
        if (! Auth::user()->hasRole('Teacher')) {
            return;
        }

        $subjectId = Examination::find($examinationId)?->subject_id;

        $allowed = TimetableEntry::where('teacher_id', Auth::id())
            ->where('class_id', $classId)
            ->where('subject_id', $subjectId)
            ->exists();

        abort_unless($allowed, 403);
    }

    /**
     * Institutions selectable for a result. `Auth::user()->institution` is
     * an ownership relation (a Director owns institutions) - a Teacher owns
     * none, so that alone would leave them with an empty dropdown and no
     * way to ever submit the form. Teachers get their assigned institution
     * instead.
     */
    private function viewerInstitutions()
    {
        if (isAdmin()) {
            return Institution::all();
        }

        $user = Auth::user();

        if ($user->hasRole('Teacher')) {
            $institution = $user->teacherUserDetails?->institution;

            return $institution ? collect([$institution]) : collect();
        }

        return collect([currentInstitution()])->filter();
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

            $pairs = $this->teacherAssignedPairs();

            $query->where(function ($q) use ($pairs) {
                foreach ($pairs as $pair) {
                    $q->orWhere(function ($q2) use ($pair) {
                        $q2->where('class_id', $pair->class_id)
                            ->whereHas('examination', fn ($q3) => $q3->where('subject_id', $pair->subject_id));
                    });
                }

                // No assigned subject/class combo - the teacher sees nothing
                // rather than everything at the institution.
                if ($pairs->isEmpty()) {
                    $q->whereRaw('1 = 0');
                }
            });

            return;
        }

        if ($user->hasRole('Parent')) {
            $studentIds = StudentDetails::where('parent_id', $user->id)->pluck('student_id');
            $query->whereIn('student_id', $studentIds);

            return;
        }

        if ($user->hasRole('Student')) {
            $query->where('student_id', $user->id);

            return;
        }

        $query->where('institution_id', currentInstitution()?->id ?? 0);
    }

    private function scopedResult(int $id): Result
    {
        $query = Result::with(['institution', 'schoolClass', 'student', 'examination.subject', 'recordedBy']);
        $this->scopeToViewer($query);

        return $query->findOrFail($id);
    }

    /**
     * Classes selectable for a result, scoped to the viewer's
     * institution(s) unless they're an Admin - or, for a Teacher, to only
     * the classes they're timetabled to teach.
     */
    private function scopedClasses()
    {
        $user = Auth::user();

        if ($user->hasRole('Teacher')) {
            $classIds = $this->teacherAssignedPairs()->pluck('class_id')->unique();

            return SchoolClass::whereIn('id', $classIds)->orderBy('name')->get();
        }

        $query = SchoolClass::query();

        if (! isAdmin()) {
            $query->where('institution_id', currentInstitution()?->id ?? 0);
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Examinations selectable for a result, scoped to the viewer's
     * institution(s) unless they're an Admin, optionally narrowed to a
     * single class - or, for a Teacher, to only the (class, subject) pairs
     * they're timetabled to teach.
     */
    private function scopedExaminations(?int $classId = null)
    {
        $user = Auth::user();

        if ($user->hasRole('Teacher')) {
            $pairs = $this->teacherAssignedPairs();

            $query = Examination::with('subject')->where(function ($q) use ($pairs) {
                foreach ($pairs as $pair) {
                    $q->orWhere(fn ($q2) => $q2->where('class_id', $pair->class_id)->where('subject_id', $pair->subject_id));
                }

                if ($pairs->isEmpty()) {
                    $q->whereRaw('1 = 0');
                }
            });

            $query->when($classId, fn ($q) => $q->where('class_id', $classId));

            return $query->orderByDesc('exam_date')->get();
        }

        $query = Examination::with('subject');

        if (! isAdmin()) {
            $query->where('institution_id', currentInstitution()?->id ?? 0);
        }

        $query->when($classId, fn ($q) => $q->where('class_id', $classId));

        return $query->orderByDesc('exam_date')->get();
    }

    /**
     * The most recent examinations per class, scoped to the viewer's
     * institution(s) unless they're an Admin. Keeps the "add result"
     * examination picker focused on current exams instead of the full
     * history. For a Teacher, further narrowed to the (class, subject)
     * pairs they're timetabled to teach.
     */
    private function recentExaminationsPerClass(int $perClassLimit = 5)
    {
        $user = Auth::user();

        if ($user->hasRole('Teacher')) {
            $pairs = $this->teacherAssignedPairs();

            $query = Examination::with('subject')->where(function ($q) use ($pairs) {
                foreach ($pairs as $pair) {
                    $q->orWhere(fn ($q2) => $q2->where('class_id', $pair->class_id)->where('subject_id', $pair->subject_id));
                }

                if ($pairs->isEmpty()) {
                    $q->whereRaw('1 = 0');
                }
            });

            return $query->orderByDesc('exam_date')
                ->get()
                ->groupBy('class_id')
                ->flatMap(fn ($examinations) => $examinations->take($perClassLimit))
                ->values();
        }

        $query = Examination::with('subject');

        if (! isAdmin()) {
            $query->where('institution_id', currentInstitution()?->id ?? 0);
        }

        return $query->orderByDesc('exam_date')
            ->get()
            ->groupBy('class_id')
            ->flatMap(fn ($examinations) => $examinations->take($perClassLimit))
            ->values();
    }

    /**
     * Students selectable for a result, scoped to the viewer's
     * institution(s) unless they're an Admin - or, for a Teacher, to only
     * students in the classes they're timetabled to teach.
     */
    private function scopedStudents()
    {
        $user = Auth::user();

        if ($user->hasRole('Teacher')) {
            $classIds = $this->teacherAssignedPairs()->pluck('class_id')->unique();

            return StudentDetails::with('student')->whereIn('class_id', $classIds)->get();
        }

        $query = StudentDetails::with('student');

        if (! isAdmin()) {
            $query->where('institution_id', currentInstitution()?->id ?? 0);
        }

        return $query->get();
    }
}
