<?php

namespace Modules\ReportCard\Http\Controllers;

use App\Http\Controllers\Concerns\Sortable;
use App\Http\Controllers\Controller;
use App\Services\Export\TableExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\ReportCard\Models\ReportCard;
use Modules\ReportCard\Services\ReportCardPdfService;
use Modules\ReportCard\Services\ReportCardQueuer;
use Modules\Student\Models\StudentDetails;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportCardController extends Controller
{
    use Sortable;

    private const HEADERS = ['Student Name', 'Admission No', 'Class', 'Term', 'Academic Year', 'Mean %', 'Mean Grade', 'Status'];

    /**
     * Export the report card list (Excel/PDF) with the same institution
     * scoping and filters the Livewire index applies, so the file matches
     * what's currently on screen. This exports the LIST of report cards -
     * not an individual student's report card PDF (see the pdf()/download()
     * actions above for that, unrelated feature).
     */
    public function export(Request $request, TableExportService $exporter)
    {
        abort_unless(Auth::user()->can('export reportcard'), 403);

        $search = $request->string('search')->value();
        $classId = $request->string('class_id')->value();
        $termId = $request->string('term_id')->value();
        $academicYearId = $request->string('academic_year_id')->value();

        $query = ReportCard::with(['student.studentUserDetails.schoolClass', 'schoolClass', 'institution'])
            ->when(! isAdmin(), fn ($q) => $q->where('institution_id', currentInstitutionId()))
            ->when($search !== '', function ($q) use ($search) {
                $term = '%'.$search.'%';

                $q->where(function ($sub) use ($term) {
                    $sub->whereHas('student', fn ($sq) => $sq->where('name', 'like', $term)->orWhere('email', 'like', $term))
                        ->orWhereHas('student.studentUserDetails', fn ($duq) => $duq->where('admission_number', 'like', $term));
                });
            })
            ->when($classId !== '', fn ($q) => $q->where('class_id', $classId))
            ->when($termId !== '', fn ($q) => $q->where('term', $termId))
            ->when($academicYearId !== '', fn ($q) => $q->where('academic_year', $academicYearId))
            ->orderBy('created_at', 'desc');

        $rows = $query->get()->map(fn (ReportCard $reportCard) => $this->toExportRow($reportCard));

        $filename = 'report-cards-'.now()->format('Y-m-d');
        $format = $request->string('format')->lower()->value();

        return $format === 'pdf'
            ? $exporter->pdf($rows, self::HEADERS, $filename, 'Report Cards')
            : $exporter->excel($rows, self::HEADERS, $filename, 'Report Cards');
    }

    /**
     * Flatten a report card into the row shape shared by every export
     * format (Excel, PDF).
     *
     * @return array<int, string>
     */
    private function toExportRow(ReportCard $reportCard): array
    {
        $student = $reportCard->student;
        $studentDetails = $student?->studentUserDetails;

        return [
            $student?->name ?? 'Unknown Student',
            $studentDetails?->admission_number ?? 'N/A',
            $reportCard->schoolClass?->name ?? $studentDetails?->schoolClass?->name ?? 'Unassigned',
            $reportCard->term ?: 'Term 1',
            (string) ($reportCard->academic_year ?: date('Y')),
            $reportCard->mean_percentage !== null ? number_format((float) $reportCard->mean_percentage, 1).'%' : 'N/A',
            $reportCard->mean_grade ?? 'N/A',
            ucfirst(str_replace('_', ' ', (string) ($reportCard->status ?? 'pending'))),
        ];
    }

    /**
     * Queue every report card that's been ready for a day, scoped the same
     * way the listing itself is. A manual backstop for the nightly command -
     * a director who can't wait for it, or wants to confirm it isn't stuck,
     * doesn't have to touch the server to run it again.
     */
    public function sendReady(ReportCardQueuer $queuer)
    {
        abort_unless(Auth::user()->can('edit reportcard'), 403);

        $query = ReportCard::query();
        if (! isAdmin()) {
            $query->where('institution_id', currentInstitution()?->id ?? 0);
        }

        $count = $queuer->queueReady($query);

        if ($count === 0) {
            return redirect()->route('reportcard.index')
                ->with('success', 'No report cards are ready to send yet.');
        }

        return redirect()->route('reportcard.index')
            ->with('success', "Queued {$count} report card(s) for sending. They go out gradually in the background to protect your messaging budget.");
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        abort_unless(Auth::user()->can('view reportcard'), 403);

        $query = ReportCard::with(['institution', 'schoolClass', 'student']);
        $this->scopeToViewer($query);

        $reportCard = $query->findOrFail($id);

        return view('reportcard::show', compact('reportCard'));
    }

    /**
     * Serve a report card PDF from the one-time link a parent was emailed
     * and texted. The token is the only credential - parents aren't
     * expected to have an account - so it's spent on first use.
     */
    public function download(string $token, ReportCardPdfService $pdfService)
    {
        $reportCard = ReportCard::where('download_token', $token)->firstOrFail();

        if ($reportCard->isDownloaded()) {
            return response()->view('reportcard::download-expired', [
                'reportCard' => $reportCard,
            ], 410);
        }

        // The stored PDF can go missing (storage cleared, never generated
        // because delivery failed part way) - rebuild it rather than 404
        // on a link the parent was legitimately given.
        if (! $reportCard->pdf_path || ! Storage::disk('public')->exists($reportCard->pdf_path)) {
            $pdfService->generate($reportCard->fresh());
            $reportCard->refresh();
        }

        $reportCard->update(['downloaded_at' => now()]);

        $studentName = Str::slug($reportCard->student?->name ?? 'student');

        return Storage::disk('public')->download(
            $reportCard->pdf_path,
            "{$studentName}-report-card.pdf",
        );
    }

    /**
     * Generate and return a PDF for an authenticated user viewing a report card.
     */
    public function pdf(int $reportCard, ReportCardPdfService $pdfService): StreamedResponse
    {
        abort_unless(Auth::user()->can('view reportcard'), 403);

        $query = ReportCard::with([
            'institution',
            'schoolClass',
            'student',
        ]);

        $this->scopeToViewer($query);

        $reportCardModel = $query->findOrFail($reportCard);

        if (! $reportCardModel->pdf_path || ! Storage::disk('public')->exists($reportCardModel->pdf_path)) {
            $pdfService->generate($reportCardModel);
            $reportCardModel->refresh();
        }

        $studentName = Str::slug($reportCardModel->student?->name ?? 'student');

        return Storage::disk('public')->response(
            $reportCardModel->pdf_path,
            "{$studentName}-report-card.pdf",
        );
    }

    /**
     * Scope query to current viewer's permissions.
     */
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

    /**
     * Delete the specified resource.
     */
    public function destroy(string $id)
    {
        abort_unless(Auth::user()->can('delete reportcard'), 403);

        // Scoped like show()/pdf(): an unscoped lookup here would let a
        // crafted id delete another school's report card.
        $query = ReportCard::query();
        $this->scopeToViewer($query);

        $reportCard = $query->findOrFail($id);

        // First unlink its PDF then delete the report card
        if ($reportCard->pdf_path && Storage::disk('public')->exists($reportCard->pdf_path)) {
            Storage::disk('public')->delete($reportCard->pdf_path);
        }

        $reportCard->delete();

        return redirect()->route('reportcard.index')->with('success', 'Report card deleted successfully.');
    }
}
