<?php

namespace App\Services\Reports;

use App\Models\Company;
use App\Services\Reports\Excel\Concerns\BuildsAccountingWorkbook;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Income Statement (P&L) → real .xlsx. Beyond the usual SUM() totals
 * for the Revenue/Expenses columns, the KPI rows at the bottom (Net
 * sales, Gross profit, Operating profit, Net profit) are written as
 * formulas chained exactly the way FinancialReportService::
 * incomeStatement() actually derives them — gross profit from net
 * sales minus COGS, operating profit from gross profit minus the
 * remaining operating expenses — rather than flattened to static
 * numbers, so the relationship between the figures stays visible and
 * recalculates if a line is edited.
 */
class IncomeStatementExcelExporter
{
    use BuildsAccountingWorkbook;

    /**
     * @param  array{from: \Carbon\Carbon, to: \Carbon\Carbon}  $period
     */
    public function export(Company $company, array $period, Collection $revenueLines, Collection $expenseLines, bool $hasPrior): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(__('Income Statement'));

        $subtitle = __('Period: :from to :to', ['from' => $period['from']->format('d M Y'), 'to' => $period['to']->format('d M Y')]);
        $columns = $hasPrior ? ['A', 'B', 'C', 'D'] : ['A', 'B', 'C'];
        $row = $this->writeDocumentHeader($sheet, $company, __('Income Statement (P&L)'), $subtitle, $columns);

        $headerRow = $row;
        $headerLabels = $hasPrior ? [__('Code'), __('Account'), __('Amount'), __('Prior')] : [__('Code'), __('Account'), __('Amount')];
        $sheet->fromArray($headerLabels, null, 'A'.$row);
        $this->styleColumnHeaderRow($sheet, $row, 'A', end($columns));
        $row++;

        $amountColumn = 'C';

        $revenueColor = $this->colorFor('revenue');
        $sheet->setCellValue('A'.$row, __('Revenue'));
        $sheet->mergeCells('A'.$row.':'.end($columns).$row);
        $this->styleTypeHeaderRow($sheet, $row, 'A', end($columns), $revenueColor);
        $row++;

        $revenueStartRow = $row;
        foreach ($revenueLines as $r) {
            $sheet->setCellValue('A'.$row, $r['account']->code);
            $sheet->setCellValue('B'.$row, $r['account']->name);
            $sheet->setCellValue('C'.$row, (float) $r['amount']);
            if ($hasPrior) {
                $sheet->setCellValue('D'.$row, (float) ($r['priorAmount'] ?? 0));
            }
            $this->styleDataRow($sheet, $row, 'A', end($columns), $revenueColor['fill']);
            $row++;
        }
        $revenueEndRow = $row - 1;

        $sheet->setCellValue('A'.$row, __('Total revenue'));
        $sheet->mergeCells('A'.$row.':B'.$row);
        foreach (array_slice($columns, 2) as $col) {
            $sheet->setCellValue($col.$row, $revenueEndRow >= $revenueStartRow ? "=SUM({$col}{$revenueStartRow}:{$col}{$revenueEndRow})" : 0);
        }
        $this->styleTotalRow($sheet, $row, 'A', end($columns), $revenueColor);
        $totalRevenueRow = $row;
        $row++;
        $row++; // blank spacer

        $expenseColor = $this->colorFor('expense');
        $sheet->setCellValue('A'.$row, __('Expenses'));
        $sheet->mergeCells('A'.$row.':'.end($columns).$row);
        $this->styleTypeHeaderRow($sheet, $row, 'A', end($columns), $expenseColor);
        $row++;

        $expenseStartRow = $row;
        $cogsRow = null;
        foreach ($expenseLines as $r) {
            $sheet->setCellValue('A'.$row, $r['account']->code);
            $sheet->setCellValue('B'.$row, $r['account']->name);
            $sheet->setCellValue('C'.$row, (float) $r['amount']);
            if ($hasPrior) {
                $sheet->setCellValue('D'.$row, (float) ($r['priorAmount'] ?? 0));
            }
            $this->styleDataRow($sheet, $row, 'A', end($columns), $expenseColor['fill']);
            if ($r['account']->code === '5000') {
                $cogsRow = $row;
            }
            $row++;
        }
        $expenseEndRow = $row - 1;

        $sheet->setCellValue('A'.$row, __('Total expenses'));
        $sheet->mergeCells('A'.$row.':B'.$row);
        foreach (array_slice($columns, 2) as $col) {
            $sheet->setCellValue($col.$row, $expenseEndRow >= $expenseStartRow ? "=SUM({$col}{$expenseStartRow}:{$col}{$expenseEndRow})" : 0);
        }
        $this->styleTotalRow($sheet, $row, 'A', end($columns), $expenseColor);
        $totalExpensesRow = $row;
        $row++;
        $row++; // blank spacer

        // KPI chain, each formula referencing the one before it exactly
        // the way FinancialReportService::incomeStatement() derives it:
        // gross profit = net sales − COGS; operating/net profit = gross
        // profit − the remaining (non-COGS) operating expenses.
        $netSalesRow = $row;
        $cogsKpiRow = $row + 1;
        $grossProfitRow = $row + 2;
        $operatingExpensesRow = $row + 3;
        $operatingProfitRow = $row + 4;
        $netProfitRow = $row + 5;

        $cogsRef = $cogsRow ? "{$amountColumn}{$cogsRow}" : '0';

        $kpiRows = [
            $netSalesRow => [__('Net sales'), "={$amountColumn}{$totalRevenueRow}"],
            $cogsKpiRow => [__('Cost of goods sold'), "={$cogsRef}"],
            $grossProfitRow => [__('Gross profit'), "={$amountColumn}{$netSalesRow}-{$amountColumn}{$cogsKpiRow}"],
            $operatingExpensesRow => [__('Operating expenses'), "={$amountColumn}{$totalExpensesRow}-{$amountColumn}{$cogsKpiRow}"],
            $operatingProfitRow => [__('Operating profit'), "={$amountColumn}{$grossProfitRow}-{$amountColumn}{$operatingExpensesRow}"],
            $netProfitRow => [__('Net profit'), "={$amountColumn}{$operatingProfitRow}"],
        ];

        foreach ($kpiRows as $kpiRow => [$label, $formula]) {
            $sheet->setCellValue('A'.$kpiRow, $label);
            $sheet->mergeCells('A'.$kpiRow.':B'.$kpiRow);
            $sheet->setCellValue($amountColumn.$kpiRow, $formula);
            $sheet->getStyle($amountColumn.$kpiRow)->getNumberFormat()->setFormatCode(self::NUMBER_FORMAT);
            $sheet->getStyle($amountColumn.$kpiRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

            if ($kpiRow === $netProfitRow) {
                // Reads as the document's grand total — dark banner,
                // same convention as every other export.
                $this->styleGrandTotalRow($sheet, $kpiRow, 'A', end($columns));
            } else {
                $sheet->getStyle('A'.$kpiRow.':'.end($columns).$kpiRow)->getFont()->setBold(true)->setSize(9.5);
            }
        }

        $row = $netProfitRow + 1;

        $this->finishSheet($sheet, $headerRow, $hasPrior ? ['A' => 10, 'B' => 32, 'C' => 16, 'D' => 16] : ['A' => 10, 'B' => 32, 'C' => 16]);

        return $this->output($spreadsheet);
    }
}
