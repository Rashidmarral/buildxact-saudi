<?php

namespace App\Services\Reports;

use App\Models\Company;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Trial Balance → real .xlsx, not just a CSV dump. Same Hamly-parity
 * type-color system the web/PDF Trial Balance already use
 * (blue/amber/violet/emerald/rose), but the real reason this exists
 * rather than reusing the CSV path: every subtotal, type total, and
 * grand total is a genuine Excel SUM() formula over the row range
 * written just above it, not a pre-computed literal. Open it, tweak a
 * category's closing balance to test a what-if, and every total above
 * it recalculates — the CSV export can never do that, it's just text.
 */
class TrialBalanceExcelExporter
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
     * @param  array{from: \Carbon\Carbon, to: \Carbon\Carbon}  $period
     * @param  Collection<string, array>  $types  keyed by account type
     */
    public function summary(Company $company, array $period, Collection $types): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(__('Trial Balance'));

        $columns = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];
        $row = $this->writeDocumentHeader($sheet, $company, __('Trial Balance'), $period, $columns);

        $headerRow = $row;
        $sheet->fromArray([
            __('Category'), __('Opening Dr'), __('Opening Cr'), __('Dr'), __('Cr'), __('Closing Dr'), __('Closing Cr'),
        ], null, 'A'.$row);
        $this->styleColumnHeaderRow($sheet, $row, 'A', 'G');
        $row++;

        $typeTotalRows = [];

        foreach ($types as $typeKey => $type) {
            $color = self::TYPE_COLORS[$typeKey] ?? self::FALLBACK_COLOR;

            $sheet->setCellValue('A'.$row, $type['label']);
            $this->styleTypeHeaderRow($sheet, $row, 'A', 'G', $color);
            $row++;

            $categoryStartRow = $row;

            foreach ($type['categories'] as $categoryLabel => $category) {
                $sheet->setCellValue('A'.$row, $categoryLabel.' ('.$category['rows']->count().')');
                $sheet->setCellValue('B'.$row, (float) $category['openingDebit']);
                $sheet->setCellValue('C'.$row, (float) $category['openingCredit']);
                $sheet->setCellValue('D'.$row, (float) $category['periodDebit']);
                $sheet->setCellValue('E'.$row, (float) $category['periodCredit']);
                $sheet->setCellValue('F'.$row, (float) $category['closingDebit']);
                $sheet->setCellValue('G'.$row, (float) $category['closingCredit']);
                $this->styleDataRow($sheet, $row, 'A', 'G', $color['fill']);
                $row++;
            }

            $categoryEndRow = $row - 1;

            $sheet->setCellValue('A'.$row, __('Total of :type', ['type' => $type['label']]));
            foreach (['B', 'C', 'D', 'E', 'F', 'G'] as $col) {
                $sheet->setCellValue($col.$row, $categoryEndRow >= $categoryStartRow
                    ? "=SUM({$col}{$categoryStartRow}:{$col}{$categoryEndRow})"
                    : 0);
            }
            $this->styleTotalRow($sheet, $row, 'A', 'G', $color);
            $typeTotalRows[] = $row;
            $row++;
        }

        $sheet->setCellValue('A'.$row, __('GRAND TOTAL'));
        foreach (['B', 'C', 'D', 'E', 'F', 'G'] as $col) {
            $sheet->setCellValue($col.$row, $typeTotalRows === []
                ? 0
                : '='.implode('+', array_map(fn ($r) => $col.$r, $typeTotalRows)));
        }
        $this->styleGrandTotalRow($sheet, $row, 'A', 'G');

        $this->finishSheet($sheet, $headerRow, ['A' => 34, 'B' => 15, 'C' => 15, 'D' => 15, 'E' => 15, 'F' => 15, 'G' => 15]);

        return $this->output($spreadsheet);
    }

    /**
     * @param  array{from: \Carbon\Carbon, to: \Carbon\Carbon}  $period
     * @param  Collection<string, array>  $types  keyed by account type
     */
    public function detailed(Company $company, array $period, Collection $types): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(__('Trial Balance'));

        $columns = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
        $row = $this->writeDocumentHeader($sheet, $company, __('Trial Balance (Detailed)'), $period, $columns);

        $headerRow = $row;
        $sheet->fromArray([
            __('Code'), __('Account'), __('Opening Dr'), __('Opening Cr'), __('Dr'), __('Cr'), __('Closing Dr'), __('Closing Cr'),
        ], null, 'A'.$row);
        $this->styleColumnHeaderRow($sheet, $row, 'A', 'H');
        $row++;

        $typeTotalRows = [];
        // Closing-only figures per category, collected while walking the
        // main table, so the "Closing Balance Summary" sheet below can be
        // written as its own self-contained set of SUM() formulas instead
        // of a second pass over the report service's data.
        $closingSummary = [];

        foreach ($types as $typeKey => $type) {
            $color = self::TYPE_COLORS[$typeKey] ?? self::FALLBACK_COLOR;
            $closingSummary[$typeKey] = ['label' => $type['label'], 'color' => $color, 'categories' => []];

            $sheet->setCellValue('A'.$row, $type['label']);
            $this->styleTypeHeaderRow($sheet, $row, 'A', 'H', $color);
            $row++;

            $categorySubtotalRows = [];

            foreach ($type['categories'] as $categoryLabel => $category) {
                $sheet->setCellValue('A'.$row, $categoryLabel);
                $this->styleCategoryLabelRow($sheet, $row, 'A', 'H', $color);
                $row++;

                $accountStartRow = $row;

                foreach ($category['rows'] as $accountRow) {
                    $sheet->setCellValue('A'.$row, $accountRow['account']->code);
                    $sheet->setCellValue('B'.$row, $accountRow['account']->name);
                    $sheet->setCellValue('C'.$row, (float) $accountRow['openingDebit']);
                    $sheet->setCellValue('D'.$row, (float) $accountRow['openingCredit']);
                    $sheet->setCellValue('E'.$row, (float) $accountRow['periodDebit']);
                    $sheet->setCellValue('F'.$row, (float) $accountRow['periodCredit']);
                    $sheet->setCellValue('G'.$row, (float) $accountRow['closingDebit']);
                    $sheet->setCellValue('H'.$row, (float) $accountRow['closingCredit']);
                    $this->styleDataRow($sheet, $row, 'A', 'H', $color['fill']);
                    $row++;
                }

                $accountEndRow = $row - 1;

                $sheet->setCellValue('A'.$row, __('Subtotal — :category', ['category' => $categoryLabel]));
                foreach (['C', 'D', 'E', 'F', 'G', 'H'] as $col) {
                    $sheet->setCellValue($col.$row, $accountEndRow >= $accountStartRow
                        ? "=SUM({$col}{$accountStartRow}:{$col}{$accountEndRow})"
                        : 0);
                }
                $this->styleTotalRow($sheet, $row, 'A', 'H', $color);
                $categorySubtotalRows[] = $row;
                $closingSummary[$typeKey]['categories'][$categoryLabel] = ['closingDebitRow' => $row, 'closingDebitCol' => 'G', 'closingCreditCol' => 'H'];
                $row++;
            }

            $sheet->setCellValue('A'.$row, __('Total of :type', ['type' => $type['label']]));
            foreach (['C', 'D', 'E', 'F', 'G', 'H'] as $col) {
                $sheet->setCellValue($col.$row, $categorySubtotalRows === []
                    ? 0
                    : '='.implode('+', array_map(fn ($r) => $col.$r, $categorySubtotalRows)));
            }
            $this->styleTotalRow($sheet, $row, 'A', 'H', $color);
            $typeTotalRows[] = $row;
            $row++;
        }

        $sheet->setCellValue('A'.$row, __('GRAND TOTAL'));
        foreach (['C', 'D', 'E', 'F', 'G', 'H'] as $col) {
            $sheet->setCellValue($col.$row, $typeTotalRows === []
                ? 0
                : '='.implode('+', array_map(fn ($r) => $col.$r, $typeTotalRows)));
        }
        $this->styleGrandTotalRow($sheet, $row, 'A', 'H');

        $this->finishSheet($sheet, $headerRow, ['A' => 10, 'B' => 30, 'C' => 15, 'D' => 15, 'E' => 15, 'F' => 15, 'G' => 15, 'H' => 15]);

        $this->writeClosingSummarySheet($spreadsheet, $company, $period, $closingSummary);

        return $this->output($spreadsheet);
    }

    /**
     * A condensed Debit/Credit-only recap of every category's closing
     * balance, matching the same section the web/PDF detailed Trial
     * Balance already end with — as its own sheet (not a second region
     * on the main sheet) so it reads as a clean standalone page when
     * printed. Pulls its figures with real cross-sheet formulas
     * (='Trial Balance'!G12) from the main sheet's own subtotal rows
     * rather than re-deriving them, so the two sheets can never drift
     * out of sync if a cell on the main sheet is edited.
     */
    private function writeClosingSummarySheet(Spreadsheet $spreadsheet, Company $company, array $period, array $closingSummary): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle(__('Closing Summary'));
        $sourceSheetName = __('Trial Balance');

        $row = $this->writeDocumentHeader($sheet, $company, __('Closing Balance Summary'), $period, ['A', 'B', 'C']);

        $headerRow = $row;
        $sheet->fromArray([__('Heads of Account'), __('Debit :currency', ['currency' => $company->currency]), __('Credit :currency', ['currency' => $company->currency])], null, 'A'.$row);
        $this->styleColumnHeaderRow($sheet, $row, 'A', 'C');
        $row++;

        $typeTotalRows = [];

        foreach ($closingSummary as $type) {
            $color = $type['color'];

            $sheet->setCellValue('A'.$row, $type['label']);
            $this->styleTypeHeaderRow($sheet, $row, 'A', 'C', $color);
            $row++;

            $categoryRows = [];

            foreach ($type['categories'] as $categoryLabel => $ref) {
                $sheet->setCellValue('A'.$row, $categoryLabel);
                $sheet->setCellValue('B'.$row, "='{$sourceSheetName}'!{$ref['closingDebitCol']}{$ref['closingDebitRow']}");
                $sheet->setCellValue('C'.$row, "='{$sourceSheetName}'!{$ref['closingCreditCol']}{$ref['closingDebitRow']}");
                $this->styleDataRow($sheet, $row, 'A', 'C', $color['fill']);
                $categoryRows[] = $row;
                $row++;
            }

            $sheet->setCellValue('A'.$row, __('Total of :type', ['type' => $type['label']]));
            foreach (['B', 'C'] as $col) {
                $sheet->setCellValue($col.$row, $categoryRows === []
                    ? 0
                    : '='.implode('+', array_map(fn ($r) => $col.$r, $categoryRows)));
            }
            $this->styleTotalRow($sheet, $row, 'A', 'C', $color);
            $typeTotalRows[] = $row;
            $row++;
        }

        $sheet->setCellValue('A'.$row, __('GRAND TOTAL'));
        foreach (['B', 'C'] as $col) {
            $sheet->setCellValue($col.$row, $typeTotalRows === []
                ? 0
                : '='.implode('+', array_map(fn ($r) => $col.$r, $typeTotalRows)));
        }
        $this->styleGrandTotalRow($sheet, $row, 'A', 'C');

        $this->finishSheet($sheet, $headerRow, ['A' => 34, 'B' => 18, 'C' => 18]);
    }

    /**
     * Company name/logo, report title, and the period banner — the same
     * three elements the PDF's header.blade.php/period-banner.blade.php
     * render, re-expressed as merged, styled cells since a worksheet has
     * no HTML/CSS to borrow those partials' markup from. Returns the row
     * number the caller's column header row should start on.
     */
    private function writeDocumentHeader(Worksheet $sheet, Company $company, string $title, array $period, array $columns): int
    {
        $lastColumn = end($columns);
        $row = 1;

        if ($company->logo_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($company->logo_path)) {
            try {
                $drawing = new Drawing();
                $drawing->setPath(\Illuminate\Support\Facades\Storage::disk('public')->path($company->logo_path));
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
        $sheet->setCellValue('A'.$row, __('Period: :from to :to', ['from' => $period['from']->format('d M Y'), 'to' => $period['to']->format('d M Y')]));
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
     * @param  array<string, int>  $columnWidths
     */
    private function finishSheet(Worksheet $sheet, int $headerRow, array $columnWidths): void
    {
        foreach ($columnWidths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        // Freezes everything above and including the column header row,
        // so the Category/Account/Dr/Cr labels stay visible while
        // scrolling a trial balance that runs to hundreds of accounts.
        $sheet->freezePane('A'.($headerRow + 1));

        $lastColumn = array_key_last($columnWidths);
        $sheet->setSelectedCell('A1');
        $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
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
