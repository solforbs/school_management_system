<?php

namespace App\Services\Export\Concerns;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Shared worksheet styling: bold+gray header row, autosized columns, frozen
 * header pane. Used by every spreadsheet export in the app so every workbook
 * (the analytics "export everything" one and every module's list export)
 * reads the same way.
 */
trait StylesWorksheets
{
    /**
     * @param  array<int, string>  $headers
     * @param  iterable<int, array<int, mixed>>  $rows
     */
    private function addSheet(Spreadsheet $spreadsheet, string $title, array $headers, iterable $rows): Worksheet
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($title);

        $sheet->fromArray($headers, null, 'A1');

        $rows = $rows instanceof Collection ? $rows->values()->all() : [...$rows];
        if ($rows !== []) {
            $sheet->fromArray($rows, null, 'A2');
        }

        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));

        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastColumn}1")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('E5E7EB');

        $useFixedColumnWidth = count($rows) > 500;
        for ($i = 1; $i <= count($headers); $i++) {
            $col = Coordinate::stringFromColumnIndex($i);
            if ($useFixedColumnWidth) {
                $sheet->getColumnDimension($col)->setWidth(18);
            } else {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
        }

        $sheet->freezePane('A2');

        // getStyle() above has a side effect: it marks whichever sheet it's
        // called on as the workbook's active tab. Left alone, a multi-sheet
        // workbook would always open on the LAST sheet this method styled -
        // often an empty one - making the whole export look blank even
        // though every other sheet is fully populated. Resetting to 0 after
        // every call means the final state, once all sheets are added, is
        // always "open on the first sheet" regardless of how many follow it.
        $spreadsheet->setActiveSheetIndex(0);

        return $sheet;
    }
}
