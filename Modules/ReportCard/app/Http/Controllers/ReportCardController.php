<?php

namespace Modules\ReportCard\Http\Controllers;

use App\Http\Controllers\Concerns\Sortable;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Classes\Models\SchoolClass;
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

        $institution = currentInstitution();
        $institutionId = $institution?->id ?? 0;

        $query = ReportCard::with(['institution', 'schoolClass', 'student']);
        $this->scopeToViewer($query);

        // Filter by search query (student name or admission number)
        $search = request('search');
        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('mean_grade', $search)
                    ->orWhereHas('student', function ($sq) use ($search) {
                        $sq->where('name', 'LIKE', "%{$search}%");
                    })
                    ->orWhereHas('student.studentUserDetails', function ($suq) use ($search) {
                        $suq->where('admission_number', 'LIKE', "%{$search}%")
                            ->orWhere('student_number', 'LIKE', "%{$search}%");
                    });
            });
        }

        // Filter by class
        $classId = request('class_id');
        if (! empty($classId)) {
            $query->where('class_id', $classId);
        }

        // Filter by term
        $term = request('term');
        if (! empty($term)) {
            $query->where('term', $term);
        }

        // Filter by education level
        $level = request('level');
        if ($level === 'primary') {
            $query->whereHas('schoolClass', fn ($cq) => $cq->where('name', 'LIKE', 'Grade 1%')
                ->orWhere('name', 'LIKE', 'Grade 2%')
                ->orWhere('name', 'LIKE', 'Grade 3%')
                ->orWhere('name', 'LIKE', 'Grade 4%')
                ->orWhere('name', 'LIKE', 'Grade 5%')
                ->orWhere('name', 'LIKE', 'Grade 6%'));
        } elseif ($level === 'jss') {
            $query->whereHas('schoolClass', fn ($cq) => $cq->where('name', 'LIKE', 'Grade 7%')
                ->orWhere('name', 'LIKE', 'Grade 8%')
                ->orWhere('name', 'LIKE', 'Grade 9%'));
        } elseif ($level === 'secondary') {
            $query->whereHas('schoolClass', fn ($cq) => $cq->where('name', 'LIKE', 'Form%'));
        }

        // Summary metrics
        $totalReportsCount = (clone $query)->count();
        $avgScore = round((float) ReportCard::where('institution_id', $institutionId)->avg('mean_percentage'), 1);
        $classes = SchoolClass::where('institution_id', $institutionId)->orderBy('name')->get();

        $reportCards = $this->applySort(
            $query,
            sortable: ['term', 'mean_grade'],
            defaultColumn: 'completed_at',
            defaultDirection: 'desc',
        )->paginate(15)->withQueryString();

        return view('reportcard::index', compact('reportCards', 'classes', 'totalReportsCount', 'avgScore', 'institution'));
    }

    /**
     * Show the specified resource.
     */

    /**
     * View or print the report card PDF.
     */
    public function pdf($id, ReportCardPdfService $pdfService)
    {
        abort_unless(Auth::user()->can('view reportcard'), 403);

        $query = ReportCard::with(['institution', 'schoolClass', 'student']);
        $this->scopeToViewer($query);

        $reportCard = $query->findOrFail($id);

        $pdfPath = $pdfService->generate($reportCard);

        $studentName = Str::slug($reportCard->student?->name ?? 'student');

        return Storage::disk('public')->response(
            $pdfPath,
            "{$studentName}-report-card.pdf",
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$studentName.'-report-card.pdf"',
            ]
        );
    }

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
}
