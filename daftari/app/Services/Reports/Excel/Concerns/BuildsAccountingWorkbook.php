<?php

namespace App\Services\Reports\Excel\Concerns;

use App\Models\Company;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Shared "modern branded workbook" chrome for every accounting report's
 * Excel export — lifted out of the first one built (Trial Balance) so
 * each report exporter after it only has to supply its own row/column
 * layout and formulas, not re-derive the company header, the
 * Hamly-parity type-color palette, or the SUM()-formula styling
 * conventions from scratch. Mirrors the role
 * documents/print/partials/report/styles.blade.php plays for PDF
 * exports, for the one other export format that can carry real
 * formulas and page/freeze-pane behavior a PDF or CSV never can.
 */
trait BuildsAccountingWorkbook
{
    private const TYPE_COLORS = [
        'asset' => ['fill' => 'DBEAFE', 'accent' => '2563EB', 'text' => '1D4ED8'],
        'liability' => ['fill' => 'FEF3C7', 'accent' => 'F59E0B', 'text' => 'B45309'],
        'equity' => ['fill' => 'EDE9FE', 'accent' => '7C3AED', 'text' => '6D28D9'],
        'revenue' => ['fill' => 'D1FAE5', 'accent' => '059669', 'text' => '047857'],
        'expense' => ['fill' => 'FFE4E6', 'accent' => 'E11D48', 'text' => 'BE123C'],
    ];

    private const FALLBACK_COLOR = ['fill' => 'F1F5F9', 'accent' => '64748B', 'text' => '475569'];

    private const NUMBER_FORMAT = '#,##0.00;[Red]-#,##0.00;""';

    /**
     * @return array{fill: string, accent: string, text: string}
     */
    private function colorFor(?string $typeKey): array
    {
        return self::TYPE_COLORS[$typeKey] ?? self::FALLBACK_COLOR;
    }

    /**
     * Company name/logo, report title, and a period/as-of banner — the
     * same three elements the PDF's header.blade.php/period-banner.
     * blade.php render, re-expressed as merged, styled cells since a
     * worksheet has no HTML/CSS to borrow those partials' markup from.
     * Returns the row number the caller's column header row should
     * start on. $subtitle is already-formatted text (a period range, an
     * "as of" date, ...) — callers vary too much in what that line says
     * to standardize it here.
     */
    private function writeDocumentHeader(Worksheet $sheet, Company $company, string $title, string $subtitle, array $columns): int
    {
        $lastColumn = end($columns);
        $row = 1;

        if ($company->logo_path && Storage::disk('public')->exists($company->logo_path)) {
            try {
                $drawing = new Drawing();
                $drawing->setPath(Storage::disk('public')->path($company->logo_path));
                $drawing->setHeight(42);
                $drawing->setCoordinates('A'.$row);
                $drawing->setOffsetX(2);
                $drawing->setOffsetY(4);
                $drawing->setWorksheet($sheet);
                $sheet->getRowDimension($row)->setRowHeight(34);
            } catch (\Throwable) {
                // A corrupt/unreadable logo file should never break the
                // export — the text header below still carries the
                // company name regardless.
            }
        }

        $sheet->mergeCells('A'.$row.':'.$lastColumn.$row);
        $sheet->setCellValue('A'.$row, $company->name);
        $sheet->getStyle('A'.$row)->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('0F172A');
        $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
        $row++;

        $sheet->mergeCells('A'.$row.':'.$lastColumn.$row);
        $sheet->setCellValue('A'.$row, mb_strtoupper($title));
        $sheet->getStyle('A'.$row)->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('334155');
        $row++;

        $sheet->mergeCells('A'.$row.':'.$lastColumn.$row);
        $sheet->setCellValue('A'.$row, $subtitle);
        $sheet->getStyle('A'.$row)->getFont()->setItalic(true)->setSize(9)->getColor()->setRGB('64748B');
        $row++;

        $row++; // blank spacer row before the data table

        return $row;
    }

    private function styleColumnHeaderRow(Worksheet $sheet, int $row, string $from, string $to): void
    {
        $range = $from.$row.':'.$to.$row;
        $sheet->getStyle($range)->getFont()->setBold(true)->setSize(9)->getColor()->setRGB('475569');
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F1F5F9');
        $sheet->getStyle($range)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB('CBD5E1');
        $sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle($from.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        foreach (range(ord($from) + 1, ord($to)) as $colOrd) {
            $sheet->getStyle(chr($colOrd).$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
    }

    private function styleTypeHeaderRow(Worksheet $sheet, int $row, string $from, string $to, array $color): void
    {
        $sheet->mergeCells($from.$row.':'.$to.$row);
        $range = $from.$row.':'.$to.$row;
        $sheet->getStyle($range)->getFont()->setBold(true)->setSize(10)->getColor()->setRGB($color['text']);
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($color['fill']);
        $sheet->getStyle($range)->getBorders()->getLeft()->setBorderStyle(Border::BORDER_THICK)->getColor()->setRGB($color['accent']);
        $sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    }

    private function styleCategoryLabelRow(Worksheet $sheet, int $row, string $from, string $to, array $color): void
    {
        $sheet->mergeCells($from.$row.':'.$to.$row);
        $sheet->getStyle($from.$row)->getFont()->setItalic(true)->setBold(true)->setSize(9)->getColor()->setRGB($color['text']);
    }

    private function styleDataRow(Worksheet $sheet, int $row, string $from, string $to, string $fillHex): void
    {
        $range = $from.$row.':'.$to.$row;
        $sheet->getStyle($range)->getFont()->setSize(9.5);
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->tint($fillHex));
        $sheet->getStyle($range)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('E2E8F0');
        foreach (range(ord($from) + 1, ord($to)) as $colOrd) {
            $cell = chr($colOrd).$row;
            $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle($cell)->getNumberFormat()->setFormatCode(self::NUMBER_FORMAT);
        }
    }

    private function styleTotalRow(Worksheet $sheet, int $row, string $from, string $to, array $color): void
    {
        $range = $from.$row.':'.$to.$row;
        $sheet->getStyle($range)->getFont()->setBold(true)->setSize(9.5)->getColor()->setRGB($color['text']);
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($color['fill']);
        $sheet->getStyle($range)->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB($color['accent']);
        $sheet->getStyle($range)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB($color['accent']);
        foreach (range(ord($from) + 1, ord($to)) as $colOrd) {
            $cell = chr($colOrd).$row;
            $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle($cell)->getNumberFormat()->setFormatCode(self::NUMBER_FORMAT);
        }
    }

    private function styleGrandTotalRow(Worksheet $sheet, int $row, string $from, string $to): void
    {
        $range = $from.$row.':'.$to.$row;
        $sheet->getStyle($range)->getFont()->setBold(true)->setSize(10.5)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0F172A');
        foreach (range(ord($from) + 1, ord($to)) as $colOrd) {
            $cell = chr($colOrd).$row;
            $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle($cell)->getNumberFormat()->setFormatCode(self::NUMBER_FORMAT);
        }
    }

    /**
     * Excel cell fills have no alpha channel a real spreadsheet app
     * actually renders, so "the type's tint but lighter" (the web/PDF
     * -soft row treatment) has to be a literal lighter color, not a
     * transparent one — blends the type's own fill color 70% toward
     * white so data rows read as gently grouped under their type's
     * section header, not as strongly colored as that header itself.
     */
    private function tint(string $hex, float $towardWhite = 0.7): string
    {
        [$r, $g, $b] = array_map(fn ($c) => hexdec(substr($hex, $c * 2, 2)), [0, 1, 2]);
        $blend = fn (int $channel) => (int) round($channel + ($towardWhite * (255 - $channel)));

        return sprintf('%02X%02X%02X', $blend($r), $blend($g), $blend($b));
    }

    /**
     * @param  array<string, int>  $columnWidths
     */
    private function finishSheet(Worksheet $sheet, int $headerRow, array $columnWidths): void
    {
        foreach ($columnWidths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        // Freezes everything above and including the column header row,
        // so the label columns stay visible while scrolling a report
        // that runs to hundreds of rows.
        $sheet->freezePane('A'.($headerRow + 1));

        $lastColumn = array_key_last($columnWidths);
        $sheet->setSelectedCell('A1');
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setFitToWidth(1)->setFitToHeight(0);
        $sheet->getPageSetup()->setPrintArea('A1:'.$lastColumn.$sheet->getHighestRow());
    }

    private function output(Spreadsheet $spreadsheet): string
    {
        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');

        return ob_get_clean();
    }
}
