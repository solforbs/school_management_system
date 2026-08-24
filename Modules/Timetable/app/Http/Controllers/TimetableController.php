<?php

namespace Modules\Timetable\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Modules\Classes\Models\SchoolClass;
use Modules\Institution\Models\Institution;
use Modules\Student\Models\StudentDetails;
use Modules\Subject\Models\Subject;
use Modules\Timetable\Models\TimetableEntry;
use Modules\Timetable\Services\TimetableImportService;

class TimetableController extends Controller
{
    private const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    /**
     * Display a listing of the resource: pick a class, see its timetable as
     * a day-by-period grid.
     */
    public function index(Request $request)
    {
        abort_unless(Auth::user()->can('view timetable'), 403);

        $user = Auth::user();
        $isStudent = $user->hasRole('Student');
        $isParent = $user->hasRole('Parent');
        $isTeacher = $user->hasRole('Teacher');

        $classes = collect();
        $selectedClass = null;
        $showPicker = false;
        $days = self::DAYS;

        if ($isTeacher) {
            // A teacher doesn't pick a class either - they see every lesson
            // they're assigned to teach, across all of their classes, for
            // the whole week.
            $entries = TimetableEntry::with(['schoolClass'])
                ->where('teacher_id', $user->id)
                ->get();
        } else {
            if ($isStudent) {
                // A student doesn't pick a class - they only ever see their own.
                $classIds = collect([StudentDetails::where('student_id', $user->id)->value('class_id')]);
            } elseif ($isParent) {
                // A parent doesn't pick from every class either - only the
                // class(es) their own children are in.
                $classIds = StudentDetails::where('parent_id', $user->id)->pluck('class_id');
            }

            if ($isStudent || $isParent) {
                $classIds = $classIds->filter()->map(fn ($id) => (int) $id)->unique()->values();
                $classes = SchoolClass::whereIn('id', $classIds)->orderBy('name')->get();
            } else {
                $classes = $this->scopedClasses();
            }

            // Hide the picker whenever there's nothing to actually choose
            // between: always for a student, and for a parent whose children
            // all share the same class.
            $showPicker = ! $isStudent && ! ($isParent && $classes->count() <= 1);

            $classId = $request->integer('class_id') ?: $classes->first()?->id;
            $selectedClass = $classId ? $classes->firstWhere('id', $classId) : null;

            $entries = $selectedClass
                ? TimetableEntry::with(['teacher'])->where('class_id', $selectedClass->id)->get()
                : collect();
        }

        $grid = null;
        $periods = collect();
        $totalWeeklyPeriods = $entries->count();
        $uniqueStreamsCount = $entries->pluck('class_id')->filter()->unique()->count();
        $uniqueSubjectsCount = $entries->pluck('subject')->filter()->unique()->count();
        $currentDayName = now()->format('l');
        $todayPeriodsCount = $entries->where('day_of_week', $currentDayName)->count();

        if ($isTeacher || $selectedClass) {
            $periods = $entries
                ->map(fn (TimetableEntry $e) => $e->start_time?->format('H:i').'-'.$e->end_time?->format('H:i'))
                ->unique()
                ->sort()
                ->values();

            $grid = [];
            foreach ($entries as $entry) {
                $periodKey = $entry->start_time?->format('H:i').'-'.$entry->end_time?->format('H:i');
                $grid[$entry->day_of_week][$periodKey] = $entry;
            }
        }

        return view('timetable::index', compact(
            'classes',
            'selectedClass',
            'entries',
            'grid',
            'periods',
            'days',
            'isStudent',
            'isParent',
            'isTeacher',
            'showPicker',
            'totalWeeklyPeriods',
            'uniqueStreamsCount',
            'uniqueSubjectsCount',
            'todayPeriodsCount',
            'currentDayName'
        ));
    }

    /**
     * Show the form for importing a class's timetable from a CSV/XLS file.
     */
    public function import()
    {
        abort_unless(Auth::user()->can('create timetable'), 403);

        $classes = $this->scopedClasses();

        return view('timetable::import', compact('classes'));
    }

    /**
     * Handle the uploaded file and merge it into the selected class's
     * timetable.
     */
    public function importStore(Request $request, TimetableImportService $importer)
    {
        abort_unless(Auth::user()->can('create timetable'), 403);

        $validated = $request->validate([
            'class_id' => 'required|exists:classes,id',
            'file' => 'required|file|mimes:csv,txt,xls,xlsx|max:5120',
        ]);

        $class = $this->scopedClasses()->firstWhere('id', $validated['class_id']);
        abort_unless($class, 403);

        $result = $importer->import($request->file('file'), $class);

        if ($result['created'] === 0) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Import failed: '.implode(' ', $result['errors']));
        }

        $message = "Imported {$result['created']} timetable entries for {$class->name}.";
        if ($result['skipped'] > 0) {
            $message .= " Skipped {$result['skipped']} break/lunch row(s).";
        }
        if (! empty($result['errors'])) {
            $message .= ' '.count($result['errors']).' row(s) had errors: '.implode(' ', $result['errors']);
        }
        if (! empty($result['warnings'])) {
            $message .= ' '.implode(' ', $result['warnings']);
        }

        return redirect()->route('timetable.index', ['class_id' => $class->id])->with('success', $message);
    }

    /**
     * Download a blank CSV template for the import.
     */
    public function importTemplate()
    {
        abort_unless(Auth::user()->can('create timetable'), 403);

        $rows = [
            ['Day', 'Start Time', 'End Time', 'Subject', 'Teacher Email', 'Room'],
            ['Monday', '08:20', '08:55', 'MATHS', '', ''],
            ['Monday', '08:55', '09:30', 'PHE', '', ''],
            ['Monday', '09:50', '10:25', 'ENG', '', ''],
        ];

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, 'timetable-import-template.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        abort_unless(Auth::user()->can('create timetable'), 403);

        $institutions = isAdmin() ? Institution::all() : collect([currentInstitution()])->filter();
        $teachers = $this->scopedTeachers();
        $classes = $this->scopedClasses();
        $days = self::DAYS;

        return view('timetable::create', compact('institutions', 'teachers', 'classes', 'days'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        abort_unless(Auth::user()->can('create timetable'), 403);

        $validated = $this->validated($request);
        $this->assertNoTeacherConflict($validated);

        TimetableEntry::create($validated);

        return redirect()->route('timetable.index')->with('success', 'Timetable entry created successfully!');
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        abort_unless(Auth::user()->can('view timetable'), 403);

        $entry = $this->scopedEntry($id, ['institution', 'teacher', 'schoolClass']);

        return view('timetable::show', compact('entry'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        abort_unless(Auth::user()->can('edit timetable'), 403);

        $entry = $this->scopedEntry($id);
        $institutions = isAdmin() ? Institution::all() : collect([currentInstitution()])->filter();
        $teachers = $this->scopedTeachers();
        $classes = $this->scopedClasses();
        $days = self::DAYS;

        return view('timetable::edit', compact('entry', 'institutions', 'teachers', 'classes', 'days'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        abort_unless(Auth::user()->can('update timetable'), 403);

        $entry = $this->scopedEntry($id);
        $validated = $this->validated($request);
        $this->assertNoTeacherConflict($validated, $entry->id);

        $entry->update($validated);

        return redirect()->route('timetable.show', $entry->id)->with('success', 'Timetable entry updated!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        abort_unless(Auth::user()->can('delete timetable'), 403);

        $entry = $this->scopedEntry($id);
        $entry->delete();

        return redirect()->route('timetable.index')->with('success', 'Timetable entry removed!');
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'institution_id' => ['nullable', 'exists:institutions,id'],
            'class_id' => 'required|exists:classes,id',
            'teacher_id' => 'nullable|exists:users,id',
            'subject' => 'required|string|max:255',
            'day_of_week' => 'required|in:'.implode(',', self::DAYS),
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'room' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        // Never trust a client-submitted institution_id for a non-admin -
        // it's always whichever institution is currently active for them.
        $validated['institution_id'] = isAdmin() ? $validated['institution_id'] : currentInstitution()?->id;

        abort_unless($validated['institution_id'], 422, 'No institution selected.');

        // Keep the legacy class_name column in sync for anything still
        // reading it directly, though class_id is now the source of truth.
        $validated['class_name'] = SchoolClass::find($validated['class_id'])?->name;

        // Best-effort link to the Subject catalog for this institution -
        // subject stays free text since not every typed name has a
        // matching catalog entry.
        $validated['subject_id'] = Subject::where('institution_id', $validated['institution_id'])
            ->where('name', $validated['subject'])
            ->value('id');

        return $validated;
    }

    /**
     * Rejects assigning a teacher to two overlapping day+time slots across
     * different classes. A slot with no teacher assigned is always fine.
     */
    private function assertNoTeacherConflict(array $validated, ?int $excludeId = null): void
    {
        if (empty($validated['teacher_id'])) {
            return;
        }

        $conflict = TimetableEntry::where('teacher_id', $validated['teacher_id'])
            ->where('day_of_week', $validated['day_of_week'])
            ->where('start_time', '<', $validated['end_time'])
            ->where('end_time', '>', $validated['start_time'])
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'teacher_id' => 'This teacher is already scheduled for another class at this day and time.',
            ]);
        }
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

    private function scopedEntry(int $id, array $with = []): TimetableEntry
    {
        $query = TimetableEntry::with($with);
        $this->scopeToViewer($query);

        return $query->findOrFail($id);
    }

    /**
     * Teachers selectable for a timetable entry, scoped to the viewer's
     * institution(s) unless they're an Admin.
     */
    private function scopedTeachers()
    {
        $query = User::role('Teacher')->with('teacherUserDetails');

        if (! isAdmin()) {
            $activeInstitutionId = currentInstitution()?->id ?? 0;
            $query->whereHas('teacherUserDetails', fn ($q) => $q->where('institution_id', $activeInstitutionId));
        }

        return $query->get();
    }

    /**
     * Classes selectable for a timetable entry, scoped to the viewer's
     * institution(s) unless they're an Admin.
     */
    private function scopedClasses()
    {
        $query = SchoolClass::query();

        if (! isAdmin()) {
            $query->where('institution_id', currentInstitution()?->id ?? 0);
        }

        return $query->orderBy('name')->get();
    }
}
