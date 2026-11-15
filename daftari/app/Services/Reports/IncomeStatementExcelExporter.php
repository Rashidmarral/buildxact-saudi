<?php

namespace App\Services\Reports;

use App\Models\Company;
use App\Services\Reports\Excel\Concerns\BuildsAccountingWorkbook;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Income Statement → real .xlsx, Hamly-parity Trading and Profit & Loss
 * Account staging: Direct Income against Direct Expenses gives Gross
 * profit; Other Income and Indirect Expenses then give Net profit. Two
 * presentations of the same FinancialReportService::
 * incomeStatementGrouped() figures — detailed() lists every ledger
 * line under each category (one column), twoSided() shows group totals
 * only across two T-account-style columns (Expenses | Revenue) — same
 * relationship the Balance Sheet's plain/twoSided exporters have to
 * each other.
 */
class IncomeStatementExcelExporter
{
    use BuildsAccountingWorkbook;

    /**
     * @param  array{from: Carbon, to: Carbon}  $period
     * @param  array{directIncome: Collection, otherIncome: Collection, directExpenses: Collection, indirectExpenses: Collection, grossProfit: float, netProfit: float}  $data
     */
    public function detailed(Company $company, array $period, array $data): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(__('Income Statement (Detailed)'));

        $subtitle = __('Period: :from to :to', ['from' => $period['from']->format('d M Y'), 'to' => $period['to']->format('d M Y')]);
        $columns = ['A', 'B', 'C'];
        $row = $this->writeDocumentHeader($sheet, $company, __('Income Statement (Detailed)'), $subtitle, $columns);
        $headerRow = $row;

        $sheet->fromArray([__('Ledger no.'), __('Account'), __('Amount')], null, 'A'.$row);
        $this->styleColumnHeaderRow($sheet, $row, 'A', 'C');
        $row++;

        $sections = [
            ['label' => __('Direct income'), 'categories' => $data['directIncome'], 'color' => $this->colorFor('revenue')],
            ['label' => __('Direct expenses'), 'categories' => $data['directExpenses'], 'color' => $this->colorFor('expense')],
        ];
        $row = $this->writeDetailedSections($sheet, $row, $sections);

        $row = $this->writeHighlightRow($sheet, $row, $data['grossProfit'] >= 0 ? __('Gross profit') : __('Gross loss'), abs($data['grossProfit']));
        $row++; // blank spacer

        $sections = [
            ['label' => __('Other income'), 'categories' => $data['otherIncome'], 'color' => $this->colorFor('revenue')],
            ['label' => __('Indirect expenses'), 'categories' => $data['indirectExpenses'], 'color' => $this->colorFor('expense')],
        ];
        $row = $this->writeDetailedSections($sheet, $row, $sections);

        $row = $this->writeHighlightRow($sheet, $row, $data['netProfit'] >= 0 ? __('Net profit') : __('Net loss'), abs($data['netProfit']));

        $this->finishSheet($sheet, $headerRow, ['A' => 12, 'B' => 38, 'C' => 18]);

        return $this->output($spreadsheet);
    }

    /**
     * One section (e.g. "Direct income") of the single-column detailed
     * layout: a bold full-width banner, then each category under it
     * with its own ledger lines and a "Total of :category" subtotal,
     * finally a "Total of :section" row summing every category subtotal
     * — same two-tier subtotal convention Trial Balance (Detailed)
     * already established.
     */
    private function writeDetailedSections(Worksheet $sheet, int $row, array $sections): int
    {
        foreach ($sections as $section) {
            $color = $section['color'];
            $sheet->setCellValue('A'.$row, mb_strtoupper($section['label']));
            $sheet->mergeCells('A'.$row.':C'.$row);
            $this->styleTypeHeaderRow($sheet, $row, 'A', 'C', $color);
            $row++;

            $categorySubtotalRows = [];

            foreach ($section['categories'] as $categoryLabel => $category) {
                $sheet->setCellValue('A'.$row, $categoryLabel);
                $this->styleCategoryLabelRow($sheet, $row, 'A', 'C', $color);
                $row++;

                $startRow = $row;
                foreach ($category['rows'] as $r) {
                    $sheet->setCellValue('A'.$row, $r['account']->code);
                    $sheet->setCellValue('B'.$row, $r['account']->name);
                    $sheet->setCellValue('C'.$row, (float) $r['typeSigned']);
                    $this->styleDataRow($sheet, $row, 'A', 'C', $color['fill']);
                    $row++;
                }
                $endRow = $row - 1;

                $sheet->setCellValue('A'.$row, __('Total of :category', ['category' => $categoryLabel]));
                $sheet->mergeCells('A'.$row.':B'.$row);
                $sheet->setCellValue('C'.$row, $endRow >= $startRow ? "=SUM(C{$startRow}:C{$endRow})" : 0);
                $this->styleTotalRow($sheet, $row, 'A', 'C', $color);
                $categorySubtotalRows[] = $row;
                $row++;
            }

            $sheet->setCellValue('A'.$row, __('Total of :section', ['section' => $section['label']]));
            $sheet->mergeCells('A'.$row.':B'.$row);
            $sheet->setCellValue('C'.$row, $categorySubtotalRows === [] ? 0 : '='.implode('+', array_map(fn ($r) => 'C'.$r, $categorySubtotalRows)));
            $this->styleTotalRow($sheet, $row, 'A', 'C', $color);
            $row++;
        }

        return $row;
    }

    private function writeHighlightRow(Worksheet $sheet, int $row, string $label, float $value): int
    {
        $sheet->setCellValue('A'.$row, $label);
        $sheet->mergeCells('A'.$row.':B'.$row);
        $sheet->setCellValue('C'.$row, $value);
        $this->styleGrandTotalRow($sheet, $row, 'A', 'C');

        return $row + 1;
    }

    /**
     * @param  array{from: Carbon, to: Carbon}  $period
     * @param  array{directIncome: Collection, otherIncome: Collection, directExpenses: Collection, indirectExpenses: Collection, grossProfit: float, grossTotal: float, netProfit: float, netTotal: float}  $data
     */
    public function twoSided(Company $company, array $period, array $data): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(__('Income Statement'));

        $subtitle = __('Period: :from to :to', ['from' => $period['from']->format('d M Y'), 'to' => $period['to']->format('d M Y')]);
        $columns = ['A', 'B', 'C', 'D'];
        $row = $this->writeDocumentHeader($sheet, $company, __('Income Statement'), $subtitle, $columns);
        $headerRow = $row;

        $sheet->fromArray([__('Expenses'), __('Amount'), __('Revenue'), __('Amount')], null, 'A'.$row);
        $this->styleColumnHeaderRow($sheet, $row, 'A', 'D');
        $row++;

        $expenseColor = $this->colorFor('expense');
        $revenueColor = $this->colorFor('revenue');

        // Trading Account: Direct Expenses (debit) against Direct
        // Income (credit) — each side's categories are independent, so
        // they're written with their own row cursor and only re-synced
        // once both are done, for the shared Gross profit/total line.
        $leftRow = $this->writeTwoSidedCategories($sheet, $row, 'A', 'B', $data['directExpenses'], $expenseColor);
        $rightRow = $this->writeTwoSidedCategories($sheet, $row, 'C', 'D', $data['directIncome'], $revenueColor);
        $row = max($leftRow, $rightRow);

        if ($data['grossProfit'] >= 0) {
            $sheet->setCellValue('A'.$row, __('Gross profit'));
            $sheet->setCellValue('B'.$row, (float) $data['grossProfit']);
        } else {
            $sheet->setCellValue('C'.$row, __('Gross loss'));
            $sheet->setCellValue('D'.$row, (float) abs($data['grossProfit']));
        }
        $this->styleHighlightPair($sheet, $row);
        $row++;

        $sheet->setCellValue('A'.$row, __('Gross total'));
        $sheet->setCellValue('B'.$row, (float) $data['grossTotal']);
        $sheet->setCellValue('C'.$row, __('Gross total'));
        $sheet->setCellValue('D'.$row, (float) $data['grossTotal']);
        $this->styleHighlightPair($sheet, $row);
        $row++;
        $row++; // blank spacer

        // Profit & Loss Account: Indirect Expenses (debit) against
        // Other Income (credit) — Gross profit/loss is carried forward
        // onto whichever side the Trading Account didn't already
        // balance against (income side on a profit, expense side on a
        // loss), same convention FinancialReportService::
        // incomeStatementGrouped() computed netTotal/netProfit with.
        $leftRow = $row;
        if ($data['grossProfit'] < 0) {
            $sheet->setCellValue('A'.$leftRow, __('Gross loss b/f'));
            $sheet->setCellValue('B'.$leftRow, (float) abs($data['grossProfit']));
            $leftRow++;
        }
        $leftRow = $this->writeTwoSidedCategories($sheet, $leftRow, 'A', 'B', $data['indirectExpenses'], $expenseColor);

        $rightRow = $row;
        if ($data['grossProfit'] >= 0) {
            $sheet->setCellValue('C'.$rightRow, __('Gross profit b/f'));
            $sheet->setCellValue('D'.$rightRow, (float) $data['grossProfit']);
            $rightRow++;
        }
        $rightRow = $this->writeTwoSidedCategories($sheet, $rightRow, 'C', 'D', $data['otherIncome'], $revenueColor);

        $row = max($leftRow, $rightRow);

        if ($data['netProfit'] >= 0) {
            $sheet->setCellValue('A'.$row, __('Net profit'));
            $sheet->setCellValue('B'.$row, (float) $data['netProfit']);
        } else {
            $sheet->setCellValue('C'.$row, __('Net loss'));
            $sheet->setCellValue('D'.$row, (float) abs($data['netProfit']));
        }
        $this->styleHighlightPair($sheet, $row);
        $row++;

        $sheet->setCellValue('A'.$row, __('Net total'));
        $sheet->setCellValue('B'.$row, (float) $data['netTotal']);
        $sheet->setCellValue('C'.$row, __('Net total'));
        $sheet->setCellValue('D'.$row, (float) $data['netTotal']);
        $this->styleHighlightPair($sheet, $row);

        $this->finishSheet($sheet, $headerRow, ['A' => 24, 'B' => 16, 'C' => 24, 'D' => 16]);

        return $this->output($spreadsheet);
    }

    /**
     * One side's category totals for the two-sided layout — a label
     * column and an amount column (either A/B or C/D), one row per
     * category, no individual ledger lines (that's detailed()'s job).
     */
    private function writeTwoSidedCategories(Worksheet $sheet, int $row, string $labelCol, string $amountCol, Collection $categories, array $color): int
    {
        foreach ($categories as $categoryLabel => $category) {
            $sheet->setCellValue($labelCol.$row, $categoryLabel);
            $sheet->setCellValue($amountCol.$row, (float) abs($category['total']));
            $this->styleDataRow($sheet, $row, $labelCol, $amountCol, $color['fill']);
            $row++;
        }

        return $row;
    }

    private function styleHighlightPair(Worksheet $sheet, int $row): void
    {
        foreach (['A', 'B', 'C', 'D'] as $col) {
            $sheet->getStyle($col.$row)->getFont()->setBold(true)->setSize(9.5);
            if (in_array($col, ['B', 'D'], true)) {
                $sheet->getStyle($col.$row)->getNumberFormat()->setFormatCode(self::NUMBER_FORMAT);
                $sheet->getStyle($col.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
        }
        $sheet->getStyle('A'.$row.':D'.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEF3C7');
    }
}
