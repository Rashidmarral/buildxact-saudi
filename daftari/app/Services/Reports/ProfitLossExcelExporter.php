<?php

namespace App\Services\Reports;

use App\Models\Company;
use App\Services\Reports\Excel\Concerns\BuildsAccountingWorkbook;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Profit & Loss (Simple) → real .xlsx. A leaner sibling of the formal
 * Income Statement export: just Income and Expenses sections (no COGS
 * split, no prior-period comparison), with Net Profit written as a
 * formula (Total income − Total expenses) rather than a value.
 */
class ProfitLossExcelExporter
{
    use BuildsAccountingWorkbook;

    /**
     * @param  array{from: \Carbon\Carbon, to: \Carbon\Carbon}  $period
     */
    public function export(Company $company, array $period, Collection $revenueLines, Collection $expenseLines): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(__('Profit and Loss'));

        $subtitle = __('Period: :from to :to', ['from' => $period['from']->format('d M Y'), 'to' => $period['to']->format('d M Y')]);
        $columns = ['A', 'B', 'C', 'D'];
        $row = $this->writeDocumentHeader($sheet, $company, __('Profit and Loss'), $subtitle, $columns);
        $headerRow = $row;

        $sheet->fromArray([__('Section'), __('Code'), __('Account'), __('Amount')], null, 'A'.$row);
        $this->styleColumnHeaderRow($sheet, $row, 'A', 'D');
        $row++;

        $incomeColor = $this->colorFor('revenue');
        $sheet->setCellValue('A'.$row, __('Income'));
        $sheet->mergeCells('A'.$row.':D'.$row);
        $this->styleTypeHeaderRow($sheet, $row, 'A', 'D', $incomeColor);
        $row++;

        $incomeStartRow = $row;
        foreach ($revenueLines as $r) {
            $sheet->setCellValue('B'.$row, $r['account']->code);
            $sheet->setCellValue('C'.$row, $r['account']->name);
            $sheet->setCellValue('D'.$row, (float) $r['amount']);
            $this->styleDataRow($sheet, $row, 'A', 'D', $incomeColor['fill']);
            $row++;
        }
        $incomeEndRow = $row - 1;

        $sheet->setCellValue('A'.$row, __('Total income'));
        $sheet->mergeCells('A'.$row.':C'.$row);
        $sheet->setCellValue('D'.$row, $incomeEndRow >= $incomeStartRow ? "=SUM(D{$incomeStartRow}:D{$incomeEndRow})" : 0);
        $this->styleTotalRow($sheet, $row, 'A', 'D', $incomeColor);
        $totalIncomeRow = $row;
        $row++;
        $row++; // spacer

        $expenseColor = $this->colorFor('expense');
        $sheet->setCellValue('A'.$row, __('Expenses'));
        $sheet->mergeCells('A'.$row.':D'.$row);
        $this->styleTypeHeaderRow($sheet, $row, 'A', 'D', $expenseColor);
        $row++;

        $expenseStartRow = $row;
        foreach ($expenseLines as $r) {
            $sheet->setCellValue('B'.$row, $r['account']->code);
            $sheet->setCellValue('C'.$row, $r['account']->name);
            $sheet->setCellValue('D'.$row, (float) $r['amount']);
            $this->styleDataRow($sheet, $row, 'A', 'D', $expenseColor['fill']);
            $row++;
        }
        $expenseEndRow = $row - 1;

        $sheet->setCellValue('A'.$row, __('Total expenses'));
        $sheet->mergeCells('A'.$row.':C'.$row);
        $sheet->setCellValue('D'.$row, $expenseEndRow >= $expenseStartRow ? "=SUM(D{$expenseStartRow}:D{$expenseEndRow})" : 0);
        $this->styleTotalRow($sheet, $row, 'A', 'D', $expenseColor);
        $totalExpensesRow = $row;
        $row++;
        $row++; // spacer

        $sheet->setCellValue('A'.$row, __('Net profit'));
        $sheet->mergeCells('A'.$row.':C'.$row);
        $sheet->setCellValue('D'.$row, "=D{$totalIncomeRow}-D{$totalExpensesRow}");
        $this->styleGrandTotalRow($sheet, $row, 'A', 'D');

        $this->finishSheet($sheet, $headerRow, ['A' => 20, 'B' => 10, 'C' => 30, 'D' => 16]);

        return $this->output($spreadsheet);
    }
}
