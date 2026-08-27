<?php

namespace Modules\Examinations\Http\Controllers;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Classes\Models\SchoolClass;
use Modules\Examinations\Models\Examination;
use Modules\Examinations\Services\ExamTimetableBuilder;
use Modules\Institution\Models\Institution;

/**
 * The printed examination timetable: one sitting of one term, a page per
 * class, handed out as a PDF.
 */
class ExamTimetableController extends Controller
{
    /**
     * Build the timetable fresh on every click and hand it back.
     *
     * The file is stored rather than streamed so a school can keep the copy
     * it issued, but the previous copy of *this* timetable is deleted first:
     * papers get rescheduled, and a stale PDF sitting next to the current one
     * is how the wrong times end up on a noticeboard.
     */
    public function download(Request $request, ExamTimetableBuilder $builder)
    {
        abort_unless(Auth::user()->can('view examination'), 403);

        $filters = $request->validate([
            'term' => 'nullable|string|max:100',
            'academic_year' => 'nullable|integer',
            'exam_type' => 'nullable|in:'.implode(',', array_keys(Examination::EXAM_TYPES)),
            'class_id' => 'nullable|exists:classes,id',
        ]);

        $groups = $builder->build(Auth::user(), $filters);

        $institution = $this->institutionFor($groups);

        $pdf = Pdf::loadView('examinations::pdf.timetable', [
            'groups' => $groups,
            'institution' => $institution,
            'heading' => $this->heading($filters),
            'subheading' => $this->subheading($filters, $groups),
            'logoDataUri' => $this->logoDataUri($institution),
        ]);

        $filename = $this->filename($filters);
        $path = $this->storagePath($institution, $filename);

        // Same filters, same path - so the newest generation replaces the one
        // before it instead of piling up beside it.
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }

        Storage::disk('public')->put($path, $pdf->output());

        return Storage::disk('public')->download($path, $filename);
    }

    /**
     * Where this school's copy of this timetable lives. Deterministic on
     * purpose: it's what makes "replace the previous one" a single delete.
     */
    private function storagePath(?Institution $institution, string $filename): string
    {
        return 'exam-timetables/'.($institution?->id ?? 'unassigned').'/'.$filename;
    }

    /**
     * The school the timetable belongs to. Taken from the papers themselves
     * so an Admin printing another school's timetable gets that school's
     * letterhead rather than none.
     *
     * @param  Collection<int, array<string, mixed>>  $groups
     */
    private function institutionFor($groups): ?Institution
    {
        $institutionId = $groups
            ->flatMap(fn (array $group) => $group['examinations']->pluck('institution_id'))
            ->filter()
            ->first();

        return $institutionId ? Institution::find($institutionId) : currentInstitution();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function heading(array $filters): string
    {
        $sitting = filled($filters['exam_type'] ?? null)
            ? Examination::EXAM_TYPES[$filters['exam_type']]
            : 'Examination';

        return $sitting.' Timetable';
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  Collection<int, array<string, mixed>>  $groups
     */
    private function subheading(array $filters, $groups): string
    {
        $parts = array_filter([
            $filters['term'] ?? null,
            $filters['academic_year'] ?? null,
        ]);

        $scope = filled($filters['class_id'] ?? null)
            ? (SchoolClass::find($filters['class_id'])?->name ?? 'Selected class')
            : $groups->count().' class(es)';

        return implode(' · ', array_filter([
            $parts ? implode(' ', $parts) : 'All terms',
            $scope,
        ]));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filename(array $filters): string
    {
        $parts = array_filter([
            $filters['exam_type'] ?? null,
            $filters['term'] ?? null,
            $filters['academic_year'] ?? null,
            filled($filters['class_id'] ?? null) ? SchoolClass::find($filters['class_id'])?->name : null,
        ]);

        $slug = Str::slug(implode(' ', $parts) ?: 'all');

        return 'exam-timetable-'.$slug.'.pdf';
    }

    private function logoDataUri(?Institution $institution): ?string
    {
        if (! $institution?->logo || ! Storage::disk('public')->exists($institution->logo)) {
            return null;
        }

        $mime = Storage::disk('public')->mimeType($institution->logo);
        $contents = Storage::disk('public')->get($institution->logo);

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }
}
