<?php

namespace Modules\Timetable\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Export\TableExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Modules\Classes\Models\SchoolClass;
use Modules\Institution\Models\Institution;
use Modules\ReportCard\Support\TermResolver;
use Modules\Student\Models\StudentDetails;
use Modules\Timetable\Actions\SaveTimetableEntry;
use Modules\Timetable\Models\TimetableEntry;
use Modules\Timetable\Models\TimetableStructure;
use Modules\Timetable\Services\TimetableImportService;

class TimetableController extends Controller
{
    private const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    private const HEADERS = ['Day', 'Start Time', 'End Time', 'Class', 'Subject', 'Teacher', 'Room', 'Notes'];

    /**
     * Export a timetable (Excel or PDF): a teacher's own schedule, or the
     * selected class's schedule, with the same institution/role scoping the
     * index listing applies.
     */
    public function export(Request $request, TableExportService $exporter)
    {
        abort_unless(Auth::user()->can('export timetable'), 403);

        $format = $request->string('format')->lower()->value();
        $filename = 'timetable-'.now()->format('Y-m-d');

        if ($format === 'pdf') {
            $data = $this->scheduleViewData($request, Auth::user());
            $pdf = Pdf::loadView('timetable::pdf.timetable', $data)->setPaper('a4', 'landscape');

            return response()->streamDownload(fn () => print ($pdf->output()), $filename.'.pdf', [
                'Content-Type' => 'application/pdf',
            ]);
        }

        $rows = $this->scheduleRows($request, Auth::user());

        return $exporter->excel($rows, self::HEADERS, $filename, 'Timetable');
    }

    /**
     * The printable timetable: displays the exact same visual weekly grid UI
     * formatted for printing on school notice boards, classroom walls, or saving to PDF.
     */
    public function printable(Request $request)
    {
        abort_unless(Auth::user()->can('view timetable'), 403);

        $data = $this->scheduleViewData($request, Auth::user());

        if ($request->query('format') === 'pdf' && ! $request->has('preview')) {
            $filename = 'timetable-'.now()->format('Y-m-d');
            $pdf = Pdf::loadView('timetable::pdf.timetable', $data)->setPaper('a4', 'landscape');

            return response()->streamDownload(fn () => print ($pdf->output()), $filename.'.pdf', [
                'Content-Type' => 'application/pdf',
            ]);
        }

        return view('timetable::print', $data);
    }

    /**
     * Compile schedule data matching the weekly visual grid for print and PDF generation.
     *
     * @return array<string, mixed>
     */
    private function scheduleViewData(Request $request, $user): array
    {
        $institutionId = currentInstitution()?->id ?? $user->institution_id ?? 0;
        $institution = Institution::find($institutionId);

        $classes = $this->exportScopedClasses($user);

        $isTeacher = $user->hasRole('Teacher');
        $teacher = null;
        $selectedClass = null;

        if ($isTeacher) {
            $teacher = $user;
            $institution = $user->institution ?? $institution;
            $institutionId = $institution?->id ?? $institutionId;
            $entries = TimetableEntry::with(['schoolClass', 'subjectRecord', 'teacher'])
                ->where('teacher_id', $user->id)
                ->get();
            $title = "Teacher Schedule — {$teacher->name}";
        } else {
            $classId = $request->integer('class_id');
            $selectedClass = $classId ? $classes->firstWhere('id', $classId) : $classes->first();
            abort_unless($selectedClass, 404, 'No class selected.');

            $institution = $selectedClass->institution ?? Institution::find($selectedClass->institution_id) ?? $institution;
            $institutionId = $institution?->id ?? $institutionId;

            $entries = TimetableEntry::with(['teacher', 'subjectRecord', 'schoolClass'])
                ->where('class_id', $selectedClass->id)
                ->get();
            $title = "Class Timetable — {$selectedClass->name}";
        }

        $structure = TimetableStructure::where('institution_id', $institutionId)
            ->where('is_default', true)
            ->first()
            ?? TimetableStructure::where('institution_id', $institutionId)->first();

        if ($structure && ! empty($structure->getAllScheduleIntervals())) {
            $periods = collect($structure->getAllScheduleIntervals())->map(function ($it) {
                return [
                    'time' => $it['start_time'].'-'.$it['end_time'],
                    'name' => $it['name'] ?? ('Period '.$it['period_number']),
                    'period_number' => $it['period_number'] ?? null,
                    'is_break' => ! empty($it['is_break']),
                    'color' => $it['color'] ?? 'zinc',
                    'type' => $it['type'] ?? 'lesson',
                ];
            })->values();
        } else {
            $periods = $entries->map(function ($entry) {
                $time = $entry->start_time?->format('H:i').'-'.$entry->end_time?->format('H:i');

                return [
                    'time' => $time,
                    'name' => $time,
                    'period_number' => null,
                    'is_break' => false,
                    'color' => 'zinc',
                    'type' => 'lesson',
                ];
            })->unique('time')->sort()->values();
        }

        $days = ! empty($structure?->days_of_week) ? $structure->days_of_week : SaveTimetableEntry::DAYS;

        $grid = [];
        foreach ($entries as $entry) {
            $key = $entry->start_time?->format('H:i').'-'.$entry->end_time?->format('H:i');
            $grid[$entry->day_of_week][$key] = $entry;
            if ($entry->period_number) {
                $grid[$entry->day_of_week]['p_'.$entry->period_number] = $entry;
            }
        }

        $term = $this->resolveCurrentTerm($selectedClass);

        return [
            'institution' => $institution,
            'selectedClass' => $selectedClass,
            'teacher' => $teacher,
            'classes' => $classes,
            'isTeacher' => $isTeacher,
            'title' => $title,
            'structure' => $structure,
            'periods' => $periods,
            'days' => $days,
            'grid' => $grid,
            'academicYear' => now()->year,
            'term' => $term,
            'logoDataUri' => $this->logoDataUri($institution),
        ];
    }

    private function resolveCurrentTerm(?SchoolClass $schoolClass): string
    {
        if ($schoolClass) {
            $resolved = TermResolver::currentFor($schoolClass);
            if ($resolved && ! empty($resolved['term'])) {
                return $resolved['term'];
            }
        }

        $month = now()->month;

        return match (true) {
            $month <= 4 => 'Term 1',
            $month <= 8 => 'Term 2',
            default => 'Term 3',
        };
    }

    private function logoDataUri(?Institution $institution): ?string
    {
        // 1. If institution has a custom logo attribute, resolve its data URI
        if ($institution && ! empty($institution->logo)) {
            $uri = $this->resolveImageDataUri($institution->logo);
            if ($uri) {
                return $uri;
            }
        }

        // 2. If institution has a sibling with the same name that has a logo (e.g. seeded demo duplicate)
        if ($institution) {
            $sibling = Institution::where('name', $institution->name)
                ->whereNotNull('logo')
                ->where('logo', '!=', '')
                ->first();
            if ($sibling && ! empty($sibling->logo)) {
                $uri = $this->resolveImageDataUri($sibling->logo);
                if ($uri) {
                    return $uri;
                }
            }
        }

        // 3. Fallback to Solforbs official logo in public/logos/solforbs-logo.png
        $defaultLogo = public_path('logos/solforbs-logo.png');
        if (file_exists($defaultLogo)) {
            return $this->pathToDataUri($defaultLogo);
        }

        return null;
    }

    private function resolveImageDataUri(string $pathOrUrl): ?string
    {
        if (str_starts_with($pathOrUrl, 'data:image/')) {
            return $pathOrUrl;
        }

        if (filter_var($pathOrUrl, FILTER_VALIDATE_URL)) {
            try {
                $ctx = stream_context_create([
                    'http' => ['timeout' => 2],
                    'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
                ]);
                $contents = @file_get_contents($pathOrUrl, false, $ctx);
                if ($contents !== false && strlen($contents) > 0) {
                    $finfo = new \finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->buffer($contents) ?: 'image/png';

                    return 'data:'.$mime.';base64,'.base64_encode($contents);
                }
            } catch (\Throwable) {
                // proceed to local checks
            }
        }

        $cleaned = ltrim($pathOrUrl, '/\\');
        $withoutStorage = preg_replace('#^storage/#', '', $cleaned);

        if (Storage::disk('public')->exists($withoutStorage)) {
            $mime = Storage::disk('public')->mimeType($withoutStorage) ?: 'image/png';
            $contents = Storage::disk('public')->get($withoutStorage);

            return 'data:'.$mime.';base64,'.base64_encode($contents);
        }

        if (Storage::disk('public')->exists($cleaned)) {
            $mime = Storage::disk('public')->mimeType($cleaned) ?: 'image/png';
            $contents = Storage::disk('public')->get($cleaned);

            return 'data:'.$mime.';base64,'.base64_encode($contents);
        }

        $candidates = [
            storage_path('app/public/'.$withoutStorage),
            storage_path('app/public/'.$cleaned),
            public_path($cleaned),
            public_path('storage/'.$withoutStorage),
            base_path($cleaned),
        ];

        foreach ($candidates as $filePath) {
            if (file_exists($filePath) && is_file($filePath)) {
                return $this->pathToDataUri($filePath);
            }
        }

        return null;
    }

    private function pathToDataUri(string $filePath): ?string
    {
        try {
            $mime = mime_content_type($filePath) ?: 'image/png';
            $contents = file_get_contents($filePath);
            if ($contents !== false) {
                return 'data:'.$mime.';base64,'.base64_encode($contents);
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    /**
     * The schedule rows shared by the Excel export and the printable PDF: a
     * teacher's own lessons, or the selected class's, with the same
     * institution/role scoping the index listing applies.
     *
     * @return Collection<int, array<int, string>>
     */
    private function scheduleRows(Request $request, $user): Collection
    {
        if ($user->hasRole('Teacher')) {
            $entries = TimetableEntry::with(['schoolClass', 'subjectRecord', 'teacher'])
                ->where('teacher_id', $user->id)
                ->get();
        } else {
            $classId = $request->integer('class_id');
            $class = $classId ? $this->exportScopedClasses($user)->firstWhere('id', $classId) : null;
            abort_unless($class, 404, 'No class selected.');

            $entries = TimetableEntry::with(['teacher', 'subjectRecord', 'schoolClass'])
                ->where('class_id', $class->id)
                ->get();
        }

        return $entries->map(fn (TimetableEntry $entry) => $this->toExportRow($entry));
    }

    /**
     * The classes selectable for export, scoped exactly like the index
     * listing: a Student/Parent only their own child(ren)'s class(es),
     * everyone else their institution's (or every institution's, for an
     * Admin).
     *
     * @return Collection<int, SchoolClass>
     */
    private function exportScopedClasses($user): Collection
    {
        if ($user->hasRole('Student') || $user->hasRole('Parent')) {
            $classIds = $user->hasRole('Parent')
                ? StudentDetails::where('parent_id', $user->id)->pluck('class_id')
                : collect([StudentDetails::where('student_id', $user->id)->value('class_id')]);

            $classIds = $classIds->filter()->map(fn ($id) => (int) $id)->unique()->values();

            return SchoolClass::whereIn('id', $classIds)->orderBy('name')->get();
        }

        $query = SchoolClass::query();

        if (! isAdmin()) {
            $query->where('institution_id', currentInstitution()?->id ?? 0);
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Flatten a timetable entry into the row shape shared by the Excel and
     * PDF exports.
     *
     * @return array<int, string>
     */
    private function toExportRow(TimetableEntry $entry): array
    {
        return [
            $entry->day_of_week,
            $entry->start_time?->format('H:i') ?? '',
            $entry->end_time?->format('H:i') ?? '',
            $entry->schoolClass?->name ?? $entry->class_name ?? '',
            $entry->subjectRecord?->name ?? $entry->subject ?? '',
            $entry->teacher?->name ?? '',
            $entry->room ?? '',
            $entry->notes ?? '',
        ];
    }

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
            ['Day', 'Start Time', 'End Time', 'Subject', 'Teacher', 'Room', 'Notes'],
            ['Monday', '08:00', '08:40', 'Mathematics', 'Mr. Kamau', 'Room 101', '[Double Lesson Part 1/2]'],
            ['Monday', '08:40', '09:20', 'Mathematics', 'Mr. Kamau', 'Room 101', '[Double Lesson Part 2/2]'],
            ['Monday', '09:50', '10:30', 'English', 'Mrs. Mwangi', 'Room 102', ''],
            ['Tuesday', '08:00', '08:40', 'Chemistry', 'kamau@school.com', 'Lab 1', ''],
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

        if (SaveTimetableEntry::classIsDoubleBooked($validated)) {
            return redirect()->back()->withInput()
                ->with('error', 'This class already has another lesson scheduled in this slot.');
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

        if (SaveTimetableEntry::classIsDoubleBooked($validated, $entry->id)) {
            return redirect()->back()->withInput()
                ->with('error', 'This class already has another lesson scheduled in this slot.');
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
