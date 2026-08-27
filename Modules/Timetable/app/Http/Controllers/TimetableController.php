<?php

namespace Modules\Timetable\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Classes\Models\SchoolClass;
use Modules\Institution\Models\Institution;
use Modules\Student\Models\StudentDetails;
use Modules\Timetable\Actions\SaveTimetableEntry;
use Modules\Timetable\Models\TimetableEntry;
use Modules\Timetable\Services\TimetableImportService;

class TimetableController extends Controller
{
    private const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    /**
     * Display a listing of the resource: pick a class, see its timetable as
     * a day-by-period grid.
     */
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
    public function store(Request $request)
    {
        abort_unless(Auth::user()->can('create timetable'), 403);

        $validated = $request->validate(SaveTimetableEntry::rules());

        $institutionId = isAdmin()
            ? ($request->integer('institution_id') ?: currentInstitution()?->id)
            : currentInstitution()?->id;

        abort_unless($institutionId, 422, 'No institution selected.');

        if (SaveTimetableEntry::teacherIsDoubleBooked($validated)) {
            return redirect()->back()->withInput()
                ->with('error', 'That teacher is already scheduled elsewhere in this slot.');
        }

        SaveTimetableEntry::handle($validated, $institutionId);

        return redirect()->route('timetable.index')->with('success', 'Timetable entry created successfully!');
    }

    public function show($id)
    {
        abort_unless(Auth::user()->can('view timetable'), 403);

        $entry = $this->scopedEntry($id, ['institution', 'teacher', 'schoolClass']);

        return view('timetable::show', compact('entry'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function update(Request $request, $id)
    {
        abort_unless(Auth::user()->can('update timetable'), 403);

        $entry = $this->scopedEntry($id);
        $validated = $request->validate(SaveTimetableEntry::rules());

        $institutionId = isAdmin()
            ? ($request->integer('institution_id') ?: $entry->institution_id)
            : currentInstitution()?->id;

        abort_unless($institutionId, 422, 'No institution selected.');

        if (SaveTimetableEntry::teacherIsDoubleBooked($validated, $entry->id)) {
            return redirect()->back()->withInput()
                ->with('error', 'That teacher is already scheduled elsewhere in this slot.');
        }

        SaveTimetableEntry::handle($validated, $institutionId, $entry);

        return redirect()->route('timetable.show', $entry->id)->with('success', 'Timetable entry updated!');
    }

    public function destroy($id)
    {
        abort_unless(Auth::user()->can('delete timetable'), 403);

        $entry = $this->scopedEntry($id);
        $entry->delete();

        return redirect()->route('timetable.index')->with('success', 'Timetable entry removed!');
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
