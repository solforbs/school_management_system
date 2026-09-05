<?php

namespace App\Services\Export;

use App\Services\Export\Concerns\StylesWorksheets;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * One place to turn "a list of rows this user is allowed to see" into a
 * downloadable file, so every module's export button produces the same
 * look and feel instead of each controller rolling its own writer.
 *
 * Deliberately generic: callers pass plain header labels + row arrays (the
 * same shape controllers already build for CSV export today), not models -
 * this keeps the service ignorant of any module's schema.
 */
class TableExportService
{
    use StylesWorksheets;

    /**
     * @param  array<int, string>  $headers
     * @param  iterable<int, array<int, mixed>>  $rows
     */
    public function excel(iterable $rows, array $headers, string $filename, string $title = 'Export'): StreamedResponse
    {
        @ini_set('memory_limit', '512M');

        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);

        $this->addSheet($spreadsheet, $this->sheetTitle($title), $headers, $rows);

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            @ini_set('memory_limit', '512M');
            $writer->save('php://output');
        }, $this->withExtension($filename, 'xlsx'), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  array<int, string>  $headers
     * @param  iterable<int, array<int, mixed>>  $rows
     */
    public function pdf(iterable $rows, array $headers, string $filename, string $title = 'Export'): StreamedResponse
    {
        $rows = $rows instanceof Collection ? $rows->values()->all() : [...$rows];

        $pdf = Pdf::loadView('exports.table', [
            'title' => $title,
            'headers' => $headers,
            'rows' => $rows,
            'generatedAt' => now()->format('D, d M Y H:i'),
        ])->setPaper('a4', count($headers) > 6 ? 'landscape' : 'portrait');

        $filename = $this->withExtension($filename, 'pdf');

        return response()->streamDownload(fn () => print ($pdf->output()), $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    private function sheetTitle(string $title): string
    {
        // Excel sheet titles are capped at 31 characters and cannot contain
        // : \ / ? * [ ].
        $clean = preg_replace('/[:\\\\\/\?\*\[\]]/', '', $title) ?? $title;

        return mb_substr($clean, 0, 31) ?: 'Export';
    }

    private function withExtension(string $filename, string $extension): string
    {
        return str_ends_with($filename, ".{$extension}") ? $filename : "{$filename}.{$extension}";
    }
}
