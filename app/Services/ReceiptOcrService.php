<?php

namespace App\Services;

use Carbon\Carbon;
use Smalot\PdfParser\Parser as PdfParser;
use thiagoalessio\TesseractOCR\TesseractOCR;

class ReceiptOcrService
{
    /**
     * @return array{amount: ?float, currency: ?string, index_number: ?string, admission_number: ?string, student_name: ?string, paid_at: ?string, reference: ?string, payment_method: ?string, raw: array}
     */
    public function extractReceiptDetails(string $absoluteFilePath, string $mimeType): array
    {
        $text = $mimeType === 'application/pdf'
            ? $this->extractPdfText($absoluteFilePath)
            : $this->extractImageText($absoluteFilePath);

        return $this->parse($text);
    }

    private function extractImageText(string $path): string
    {
        $ocr = new TesseractOCR($path);

        if ($executable = config('services.tesseract.executable')) {
            $ocr->executable($executable);
        }

        return $ocr->run();
    }

    private function extractPdfText(string $path): string
    {
        return (new PdfParser)->parseFile($path)->getText();
    }

    private function parse(string $text): array
    {
        $normalized = preg_replace('/\s+/', ' ', trim($text));

        return [
            'amount' => $this->extractAmount($normalized),
            'currency' => $this->extractCurrency($normalized),
            'index_number' => $this->extractIndexNumber($normalized),
            'admission_number' => $this->extractAdmissionNumber($normalized),
            'student_name' => $this->extractStudentName($normalized),
            'paid_at' => $this->extractDate($normalized),
            'reference' => $this->extractReference($normalized),
            'payment_method' => $this->extractPaymentMethod($normalized),
            'raw' => ['text' => $text],
        ];
    }

    /**
     * Handles multiple amount formats:
     * - "6000.00 KSH"
     * - "AMOUNT: KES 50,000.00"
     * - "AMOUNT: KES950.00"
     * - "Grand Total 50,000"
     */
    private function extractAmount(string $text): ?float
    {
        $patterns = [
            // "AMOUNT: 6000.00 KSH"
            '/\b(?:Amount|Total|Grand Total)\s*[:]?\s*([\d,]+(?:\.\d{1,2})?)\s*(?:KSH|KES|Kshs)?/i',
            // "AMOUNT: KES 50,000.00" or "AMOUNT: KES950.00"
            '/\b(?:Amount|Total|Grand Total)\s*[:]?\s*(?:KSH|KES|Kshs)?\s*([\d,]+(?:\.\d{1,2})?)/i',
            // "Kshs. 1,500"
            '/\b(?:Ksh|KES|Kshs)\s*[:.]?\s*([\d,]+(?:\.\d{1,2})?)/i',
            // "1,500.00 KSH"
            '/\b([\d,]+(?:\.\d{1,2})?)\s*(?:KSH|KES|Kshs)\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                return (float) str_replace(',', '', $m[1]);
            }
        }

        return null;
    }

    private function extractCurrency(string $text): ?string
    {
        return preg_match('/\b(Ksh|KES|Kshs)\b/i', $text) ? 'KES' : null;
    }

    /**
     * Extracts Index Number (common in Kenya for secondary schools).
     * Examples: "INDEX NO: 1573"
     */
    private function extractIndexNumber(string $text): ?string
    {
        if (preg_match('/\b(?:INDEX\s*NO\.?|REG\s*NO\.?|STUDENT\s*NO\.?)\s*[:\-]?\s*([A-Z0-9\-\/]{2,20})/i', $text, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    /**
     * Extracts Admission Number (common for primary schools).
     * Examples: "ADM NO: 3423", "ADMISSION NO: 3423", "Account: 1116762668" (fallback)
     */
    private function extractAdmissionNumber(string $text): ?string
    {
        // Specific Admission Number patterns
        if (preg_match('/\b(?:ADM\s*NO\.?|ADMISSION\s*NO\.?|Acc\.?\s*No\.?)\s*[:\-]?\s*([A-Z0-9\-\/]{3,20})/i', $text, $m)) {
            return trim($m[1]);
        }

        // Fallback to "Account" or "Bill A/C" if no specific ADM is present
        if (preg_match('/\b(?:Account|Bill\s*A\/C|DESTINATION\s*ACC)\s*[:\-]?\s*([A-Z0-9\-\/]{3,20})/i', $text, $m)) {
            return trim($m[1]);
        }

        // Fallback to any alphanumeric code
        if (preg_match('/\b([A-Z]{2,5}[\-\/]?\d{3,8})\b/', $text, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Extracts Student/Payer Name from various layouts.
     * Examples: "NAME: JOSEPH KAMAU", "NARRATION: Agnes Okol", "Tells of Student: JOSEPH"
     */
    private function extractStudentName(string $text): ?string
    {
        // Priority 1: "NAME: JOSEPH KAMAU" or "Student Name: JOSEPH"
        if (preg_match('/\b(?:Student\s*Name|Tells\s*of\s*Student|NAME)\s*[:]?\s*([A-Z][A-Z\s]{2,40}?)(?=\s+(?:INDEX|ADM|Account|Class|Date|Receipt|Amount|\.|,|$))/i', $text, $m)) {
            return trim($m[1]);
        }

        // Priority 2: "NARRATION: Agnes Okol"
        if (preg_match('/\b(?:NARRATION|Narration|Paid\s*by)\s*[:]?\s*([A-Z][A-Z\s]{2,40}?)(?=\s+(?:AMOUNT|Amount|BANK_REF|Ref|\.|,|$))/i', $text, $m)) {
            return trim($m[1]);
        }

        // Priority 3: M-PESA patterns "Sent to", "Paid to"
        if (preg_match('/\b(?:sent to|paid to|received from)\s+([A-Z][A-Z\s]{2,40}?)(?=\s+\d|\s+for\b|\.|,)/', $text, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    /**
     * Handles DD/MM/YYYY and DD MON YYYY formats.
     */
    private function extractDate(string $text): ?string
    {
        // Standard numeric date format: 22/04/19 or 27-05-2020
        if (preg_match('/\b(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})\b/', $text, $m)) {
            try {
                $year = strlen($m[3]) === 2 ? '20'.$m[3] : $m[3];

                return Carbon::create((int) $year, (int) $m[2], (int) $m[1])->toDateString();
            } catch (\Throwable) {
                return null;
            }
        }

        // Text month format: 27 MAY 2020
        if (preg_match('/\b(\d{1,2})\s+([A-Za-z]{3,9})\s+(\d{2,4})\b/', $text, $m)) {
            try {
                $month = Carbon::parse($m[2])->month;
                $year = strlen($m[3]) === 2 ? '20'.$m[3] : $m[3];

                return Carbon::create((int) $year, (int) $month, (int) $m[1])->toDateString();
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    /**
     * Extracts reference numbers from various systems.
     * M-Pesa code, Receipt No, RRM, Bank Ref, Authorization Code.
     */
    private function extractReference(string $text): ?string
    {
        // Priority 1: M-Pesa confirmation code at the very beginning (e.g. "QGH7X2K9PL ...")
        if (preg_match('/^([A-Z0-9]{10})\b/', $text, $m)) {
            return $m[1];
        }

        // Priority 2: Specific Bank Reference Patterns
        $patterns = [
            '/\bBANK_REF\s*[:]?\s*([A-Z0-9]{4,20})/i',
            '/\bREF\s*NO\.?\s*[:]?\s*([A-Z0-9]{4,20})/i',
            '/\bRRM\s*[:]?\s*([A-Z0-9]{4,20})/i',
            '/\bReceipt\s*(?:No\.?)?\s*[:]?\s*([A-Z0-9]{4,20})/i',
            '/\bAuthorization\s*code\s*[:]?\s*([A-Z0-9]{4,20})/i',
            '/\bTransaction\s*(?:Code|ID)\s*[:]?\s*([A-Z0-9]{4,20})/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                return $m[1];
            }
        }

        return null;
    }

    private function extractPaymentMethod(string $text): ?string
    {
        return match (true) {
            (bool) preg_match('/\b(m-?pesa|mpesa)\b/i', $text) => 'mobile_money',
            (bool) preg_match('/\b(bank|deposit|swift|rtgs)\b/i', $text) => 'bank',
            (bool) preg_match('/\bcheque\b/i', $text) => 'cheque',
            (bool) preg_match('/\bcash\b/i', $text) => 'cash',
            default => null,
        };
    }
}
