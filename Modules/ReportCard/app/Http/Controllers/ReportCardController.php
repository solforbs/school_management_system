<?php

namespace Modules\ReportCard\Http\Controllers;

use App\Http\Controllers\Concerns\Sortable;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\ReportCard\Models\ReportCard;
use Modules\ReportCard\Services\ReportCardPdfService;
use Modules\Student\Models\StudentDetails;

class ReportCardController extends Controller
{
    use Sortable;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        abort_unless(Auth::user()->can('view reportcard'), 403);

        $query = ReportCard::with(['institution', 'schoolClass', 'student']);
        $this->scopeToViewer($query);

        $reportCards = $query->paginate();

        return view('reportcard::index', compact('reportCards'));
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
    public function pdf(ReportCard $reportCard): Response
    {
        // Authorization check
        abort_unless(Auth::user()->can('view reportcard'), 403);

        $query = ReportCard::with(['institution', 'schoolClass', 'student']);
        $this->scopeToViewer($query);

        // Verify the user has access to this report card
        $query->findOrFail($reportCard->id);

        $pdf = Pdf::loadView('reportcard::pdf', [
            'reportCard' => $reportCard->load(['student', 'schoolClass', 'institution']),
        ])->setPaper('a4', 'portrait');

        $filename = 'report-card-' . $reportCard->student->id . '-' . $reportCard->schoolClass?->id . '.pdf';

        return $pdf->download($filename);
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

        $reportCard = ReportCard::findOrFail($id);

        // First unlink its PDF then delete the report card
        if ($reportCard->pdf_path && Storage::disk('public')->exists($reportCard->pdf_path)) {
            Storage::disk('public')->delete($reportCard->pdf_path);
        }

        $reportCard->delete();

        return redirect()->route('reportcard.index')->with('success', 'Report card deleted successfully.');
    }
}