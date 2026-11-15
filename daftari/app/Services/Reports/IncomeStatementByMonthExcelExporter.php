<?php

namespace App\Services\Reports;

use App\Models\Company;
use App\Services\Reports\Excel\Concerns\BuildsAccountingWorkbook;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Profit & Loss by Month → real .xlsx pivot (one column per month).
 * TOTAL INCOME/TOTAL EXPENSES are SUM() formulas down each month
 * column, and NET PROFIT is a formula subtracting one total row from
 * the other in every month column — not the three parallel monthly
 * arrays FinancialReportService::incomeStatementByMonth() already
 * computed and this export would otherwise just transcribe.
 */
class IncomeStatementByMonthExcelExporter
{
    use BuildsAccountingWorkbook;

    /**
     * @param  array{from: \Carbon\Carbon, to: \Carbon\Carbon}  $period
     */
    public function export(Company $company, array $period, array $data): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(__('P&L by Month'));

        $subtitle = __('Period: :from to :to', ['from' => $period['from']->format('d M Y'), 'to' => $period['to']->format('d M Y')]);
        $monthKeys = $data['months']->map(fn ($m) => $m->format('Y-m'))->values();
        $monthCount = $monthKeys->count();
        $totalColIndex = $monthCount + 1; // 0 = Account column
        $columns = array_map(fn ($i) => $this->columnLetter($i), range(0, $totalColIndex));
        $lastColumn = end($columns);
        $totalColumn = $columns[$totalColIndex];

        $row = $this->writeDocumentHeader($sheet, $company, __('Profit & Loss by Month'), $subtitle, $columns);
        $headerRow = $row;

        $headerLabels = array_merge([__('Account')], $monthKeys->all(), [__('Total')]);
        $sheet->fromArray($headerLabels, null, 'A'.$row);
        $this->styleColumnHeaderRow($sheet, $row, 'A', $lastColumn);
        $row++;

        $monthColumns = array_slice($columns, 1, $monthCount);

        $writeSection = function (string $label, \Illuminate\Support\Collection $lines, array $color) use ($sheet, &$row, $monthColumns, $monthKeys, $totalColumn, $lastColumn) {
            $sheet->setCellValue('A'.$row, $label);
            $sheet->mergeCells('A'.$row.':'.$lastColumn.$row);
            $this->styleTypeHeaderRow($sheet, $row, 'A', $lastColumn, $color);
            $row++;

            $startRow = $row;
            foreach ($lines as $line) {
                $sheet->setCellValue('A'.$row, $line['account']->name);
                foreach ($monthKeys as $i => $key) {
                    $sheet->setCellValue($monthColumns[$i].$row, (float) $line['monthly'][$key]);
                }
                $sheet->setCellValue($totalColumn.$row, '=SUM('.$monthColumns[0].$row.':'.end($monthColumns).$row.')');
                $this->styleDataRow($sheet, $row, 'A', $lastColumn, $color['fill']);
                $row++;
            }
            $endRow = $row - 1;

            return [$startRow, $endRow];
        };

        $incomeColor = $this->colorFor('revenue');
        [$incomeStart, $incomeEnd] = $writeSection(__('Income'), $data['revenueLines'], $incomeColor);

        $sheet->setCellValue('A'.$row, __('TOTAL INCOME'));
        foreach ($monthColumns as $col) {
            $sheet->setCellValue($col.$row, $incomeEnd >= $incomeStart ? "=SUM({$col}{$incomeStart}:{$col}{$incomeEnd})" : 0);
        }
        $sheet->setCellValue($totalColumn.$row, '=SUM('.$monthColumns[0].$row.':'.end($monthColumns).$row.')');
        $this->styleTotalRow($sheet, $row, 'A', $lastColumn, $incomeColor);
        $totalIncomeRow = $row;
        $row++;

        $expenseColor = $this->colorFor('expense');
        [$expenseStart, $expenseEnd] = $writeSection(__('Expenses'), $data['expenseLines'], $expenseColor);

        $sheet->setCellValue('A'.$row, __('TOTAL EXPENSES'));
        foreach ($monthColumns as $col) {
            $sheet->setCellValue($col.$row, $expenseEnd >= $expenseStart ? "=SUM({$col}{$expenseStart}:{$col}{$expenseEnd})" : 0);
        }
        $sheet->setCellValue($totalColumn.$row, '=SUM('.$monthColumns[0].$row.':'.end($monthColumns).$row.')');
        $this->styleTotalRow($sheet, $row, 'A', $lastColumn, $expenseColor);
        $totalExpensesRow = $row;
        $row++;

        $sheet->setCellValue('A'.$row, __('NET PROFIT'));
        foreach ($monthColumns as $col) {
            $sheet->setCellValue($col.$row, "={$col}{$totalIncomeRow}-{$col}{$totalExpensesRow}");
        }
        $sheet->setCellValue($totalColumn.$row, "={$totalColumn}{$totalIncomeRow}-{$totalColumn}{$totalExpensesRow}");
        $this->styleGrandTotalRow($sheet, $row, 'A', $lastColumn);

        $widths = ['A' => 26];
        foreach ($monthColumns as $col) {
            $widths[$col] = 13;
        }
        $widths[$totalColumn] = 15;

        $this->finishSheet($sheet, $headerRow, $widths);

        return $this->output($spreadsheet);
    }

    private function columnLetter(int $index): string
    {
        $letter = '';
        $index++;
        while ($index > 0) {
            $remainder = ($index - 1) % 26;
            $letter = chr(65 + $remainder).$letter;
            $index = intdiv($index - 1, 26);
        }

        return $letter;
    }
}
