<?php

namespace Modules\Curriculum\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Export\TableExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Curriculum\Actions\SaveCurriculum;
use Modules\Curriculum\Models\Curriculum;
use Modules\Student\Models\StudentDetails;

class CurriculumController extends Controller
{
    private const HEADERS = ['Curriculum', 'System', 'Grading', 'Institution'];

    /**
     * Export curricula (Excel or PDF) with the same filters and institution
     * scoping the index screen applies.
     */
    public function export(Request $request, TableExportService $exporter)
    {
        abort_unless(Auth::user()->can('export curriculum'), 403);

        $search = (string) $request->query('search', '');

        $curricula = Curriculum::with('institution')
            ->when(! isAdmin(), fn ($q) => $q->where('institution_id', currentInstitutionId()))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', '%'.$search.'%')->orWhere('system', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('name')
            ->get();

        $rows = $curricula->map(fn (Curriculum $curriculum) => $this->toExportRow($curriculum));
        $filename = 'curriculum-'.now()->format('Y-m-d');

        return $request->string('format')->lower()->value() === 'pdf'
            ? $exporter->pdf($rows, self::HEADERS, $filename, 'Curricula')
            : $exporter->excel($rows, self::HEADERS, $filename, 'Curricula');
    }

    /**
     * @return array<int, string>
     */
    private function toExportRow(Curriculum $curriculum): array
    {
        return [
            $curriculum->name,
            $curriculum->systemLabel(),
            $curriculum->gradingLabel(),
            $curriculum->institution?->name ?? '—',
        ];
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        abort_unless(Auth::user()->can('create curriculum'), 403);

        $data = $request->validate(SaveCurriculum::rules());

        SaveCurriculum::handle($data, $this->institutionId());

        return redirect()->route('curriculum.index')->with('success', 'Curriculum created successfully!');
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        abort_unless(Auth::user()->can('view curriculum'), 403);

        $curriculum = $this->scopedCurriculum($id);
        $curriculum->load('institution');

        return view('curriculum::show', compact('curriculum'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        abort_unless(Auth::user()->can('update curriculum'), 403);

        $curriculum = $this->scopedCurriculum($id);
        $data = $request->validate(SaveCurriculum::rules());

        SaveCurriculum::handle($data, $this->institutionId($curriculum), $curriculum);

        return redirect()->route('curriculum.index')->with('success', 'Curriculum updated!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        abort_unless(Auth::user()->can('delete curriculum'), 403);

        $curriculum = $this->scopedCurriculum($id);
        $curriculum->delete();

        return back()->with('success', 'Curriculum deleted successfully!');
    }

    /**
     * The school a curriculum belongs to. Never a client-submitted one for
     * a non-admin.
     */
    private function institutionId(?Curriculum $curriculum = null): int
    {
        $institutionId = $curriculum?->institution_id ?? currentInstitution()?->id;

        abort_unless($institutionId, 422, 'No institution selected.');

        return $institutionId;
    }

    private function scopeToViewer($query): void
    {
        $user = Auth::user();

        if (isAdmin()) {
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

    private function scopedCurriculum(int $id): Curriculum
    {
        $query = Curriculum::with('institution');
        $this->scopeToViewer($query);

        return $query->findOrFail($id);
    }
}
