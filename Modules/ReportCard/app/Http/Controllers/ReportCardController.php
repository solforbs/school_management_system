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
    public function pdf(int $reportCard): Response
    {
        abort_unless(Auth::user()->can('view reportcard'), 403);

        $query = ReportCard::with([
            'institution',
            'schoolClass',
            'student',
        ]);

        $this->scopeToViewer($query);

        $reportCardModel = $query->findOrFail($reportCard);

        $student = $reportCardModel->student;
        $institution = $reportCardModel->institution;
        $schoolClass = $reportCardModel->schoolClass;

        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Report Card</title>

            <style>
                @page {
                    size: A4 portrait;
                    margin: 15mm;
                }

                body {
                    font-family: Arial, sans-serif;
                    color: #111;
                    margin: 0;
                }

                .header {
                    text-align: center;
                    margin-bottom: 30px;
                }

                .student-info {
                    margin-bottom: 25px;
                }

                table {
                    width: 100%;
                    border-collapse: collapse;
                }

                th,
                td {
                    border: 1px solid #222;
                    padding: 8px;
                    text-align: left;
                }

                th {
                    background: #f3f4f6;
                }

                .print-button {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    padding: 10px 18px;
                    background: #111827;
                    color: white;
                    border: 0;
                    border-radius: 6px;
                    cursor: pointer;
                }

                @media print {
                    .print-button {
                        display: none;
                    }
                }
            </style>
        </head>

        <body>

            <button class="print-button" onclick="window.print()">
                Print / Save PDF
            </button>

            <div class="header">
                <h1>' . e($institution->name ?? 'Institution') . '</h1>
                <h2>STUDENT REPORT CARD</h2>
            </div>

            <div class="student-info">
                <p><strong>Student:</strong> ' . e($student->name ?? '') . '</p>
                <p><strong>Class:</strong> ' . e($schoolClass->name ?? '') . '</p>
            </div>

            <!-- Add your report-card subjects/marks here -->

        </body>
        </html>
    ';

        return response($html)
            ->header('Content-Type', 'text/html; charset=UTF-8');
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
