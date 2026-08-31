<?php

namespace Modules\FeeManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\FeePaymentService;
use App\Services\ImageCompressionService;
use App\Services\ReceiptOcrService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\FeeManagement\Models\Fee;
use Modules\Student\Models\StudentDetails;

/**
 * The AI receipt-scanning flow: upload a photo or PDF of a payment
 * receipt, OCR extracts amount/admission number/etc, the
 * Director/Accountant reviews (and corrects, if needed) before it's
 * saved as a real payment. A pro-tier feature - gated by
 * institutionHasFeature('ai_receipt_scanning').
 */
class FeeReceiptController extends Controller
{
    private const CACHE_PREFIX = 'receipt_extraction:';

    public function __construct(
        private readonly ReceiptOcrService $ocr,
        private readonly ImageCompressionService $imageCompressor,
        private readonly FeePaymentService $feePaymentService,
    ) {}

    public function create()
    {
        $this->authorizeFeature();

        return view('feemanagement::receipts.create');
    }

    /**
     * Runs the uploaded receipt through OCR, tries to match a student by
     * admission number within the current institution, and shows the
     * result for review before anything is saved.
     */
    public function extract(Request $request)
    {

        $this->authorizeFeature();

        $validated = $request->validate([
            'receipt' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:10240',
        ]);

        $file = $validated['receipt'];
        $isPdf = $file->getMimeType() === 'application/pdf';

        try {
            $extracted = $this->ocr->extractReceiptDetails($file->getRealPath(), $file->getMimeType());
        } catch (\Throwable $e) {
            $errorMessage = $e->getMessage();

            if (str_contains(strtolower($errorMessage), 'tesseract')) {
                $userFriendlyMessage = 'The AI scanning engine is currently unavailable on this server. Please contact your system administrator.';
            } else {
                $userFriendlyMessage = "We couldn't process this receipt. Please ensure the image is clear and in a supported format, then try again.";
            }

            return redirect()->route('feemanagement.receipts.create')
                ->with('error', $userFriendlyMessage);
        }

        // PDFs pass through as-is (Intervention can't process them);
        // images get compressed the same way every other upload does.
        $receiptPath = $isPdf
            ? $file->store('fee-receipts', 'public')
            : $this->imageCompressor->store($file, 'fee-receipts');

        $token = (string) Str::uuid();
        Cache::put(self::CACHE_PREFIX.$token, [
            'receipt_path' => $receiptPath,
            'raw' => $extracted['raw'],
        ], now()->addMinutes(30));

        $institution = currentInstitution();
        $matchedStudent = null;
        $searchNumber = $extracted['admission_number'] ?? $extracted['index_number'] ?? null;

        if ($searchNumber && $institution) {
            $trimmed = trim($searchNumber);
            $matchedStudent = StudentDetails::with('student')
                ->where('institution_id', $institution->id)
                ->where(function ($q) use ($trimmed) {
                    $q->where('admission_number', $trimmed)
                        ->orWhere('student_number', $trimmed);
                })
                ->first();
        }

        $students = StudentDetails::with('student')
            ->where('institution_id', $institution?->id ?? 0)
            ->get();

        $fees = $matchedStudent
            ? Fee::where('institution_id', $institution->id)
                ->where('student_id', $matchedStudent->student_id)
                ->orderByRaw('(amount - amount_paid) <= 0, due_date')
                ->get()
            : collect();

        return view('feemanagement::receipts.review', [
            'token' => $token,
            'extracted' => $extracted,
            'matchedStudent' => $matchedStudent,
            'students' => $students,
            'fees' => $fees,
            'receiptUrl' => Storage::disk('public')->url($receiptPath),
            'isPdf' => $isPdf,
        ]);
    }

    /**
     * Finalizes the review: applies the (possibly corrected) amount to
     * whichever fee was chosen.
     */
    public function store(Request $request)
    {
        $this->authorizeFeature();

        $validated = $request->validate([
            'token' => 'required|string',
            'student_id' => 'required|exists:student_details,student_id',
            'fee_id' => 'required|exists:fees,id',
            'amount' => 'required|numeric|min:0.01',
            'reference' => 'nullable|string|max:255',
            'payment_method' => 'nullable|string|max:100',
            'paid_at' => 'nullable|date',
        ]);

        $institution = currentInstitution();

        $studentDetails = StudentDetails::where('student_id', $validated['student_id'])
            ->where('institution_id', $institution?->id ?? 0)
            ->firstOrFail();

        $fee = Fee::where('id', $validated['fee_id'])
            ->where('institution_id', $institution?->id ?? 0)
            ->where('student_id', $studentDetails->student_id)
            ->firstOrFail();

        $cached = Cache::pull(self::CACHE_PREFIX.$validated['token']);

        $this->feePaymentService->record($fee, [
            'amount' => $validated['amount'],
            'reference' => $validated['reference'] ?? null,
            'payment_method' => $validated['payment_method'] ?? null,
            'paid_at' => $validated['paid_at'] ?? Carbon::today(),
            'receipt_path' => $cached['receipt_path'] ?? null,
            'source' => 'ai_receipt',
            'extraction_raw_response' => $cached['raw'] ?? null,
            'recorded_by' => Auth::id(),
        ]);

        return redirect()->route('feemanagement.show', $fee->id)
            ->with('success', 'Payment recorded from receipt.');
    }

    /**
     * JSON list of a student's fees within the current institution - used
     * by the review page to refresh the fee picker when the reviewer
     * manually changes the matched student.
     */
    public function studentFees(Request $request)
    {
        $this->authorizeFeature();

        $validated = $request->validate([
            'student_id' => 'required|exists:student_details,student_id',
        ]);

        $institution = currentInstitution();

        $studentDetails = StudentDetails::where('student_id', $validated['student_id'])
            ->where('institution_id', $institution?->id ?? 0)
            ->firstOrFail();

        $fees = Fee::where('institution_id', $institution->id)
            ->where('student_id', $studentDetails->student_id)
            ->orderByRaw('(amount - amount_paid) <= 0, due_date')
            ->get(['id', 'title', 'amount', 'amount_paid']);

        return response()->json($fees->map(fn (Fee $fee) => [
            'id' => $fee->id,
            'label' => "{$fee->title} — balance ".number_format($fee->balance, 2),
        ]));
    }

    private function authorizeFeature(): void
    {
        abort_unless(Auth::user()->can('create feemanagement'), 403);
        abort_unless(institutionHasFeature('ai_receipt_scanning'), 403, 'AI receipt scanning is not included in your current plan.');
    }
}
