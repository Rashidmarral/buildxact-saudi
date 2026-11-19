<?php

namespace App\Services\Reports;

use App\Models\Company;
use App\Services\Reports\Excel\Concerns\BuildsAccountingWorkbook;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Income Statement (Traditional T-Account) → real .xlsx. The same
 * two-sided ledger layout the web page renders — Expenses on the debit
 * (left) side, Revenue on the credit (right) side, with the balancing
 * Net Profit/Net Loss line carried on whichever side is smaller —
 * except both side totals here are SUM() formulas, and the balancing
 * line's own amount is a formula (the two sides' difference), so the
 * sheet keeps proving its own balance rather than asserting it.
 */
class IncomeStatementTraditionalExcelExporter
{
    use BuildsAccountingWorkbook;

    /**
     * @param  array{from: \Carbon\Carbon, to: \Carbon\Carbon}  $period
     */
    public function export(Company $company, array $period, Collection $revenueLines, Collection $expenseLines, float $netProfit): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(__('Income Statement (T-Account)'));

        $subtitle = __('Period: :from to :to', ['from' => $period['from']->format('d M Y'), 'to' => $period['to']->format('d M Y')]);
        $columns = ['A', 'B', 'C', 'D'];
        $row = $this->writeDocumentHeader($sheet, $company, __('Income Statement (Traditional T-Account)'), $subtitle, $columns);
        $headerRow = $row;

        $sheet->fromArray([__('Debit (Expenses)'), __('Amount'), __('Credit (Revenue)'), __('Amount')], null, 'A'.$row);
        $this->styleColumnHeaderRow($sheet, $row, 'A', 'D');
        $row++;

        $expenseColor = $this->colorFor('expense');
        $revenueColor = $this->colorFor('revenue');
        $startRow = $row;

        $maxLines = max($expenseLines->count(), $revenueLines->count());
        $expenseValues = $expenseLines->values();
        $revenueValues = $revenueLines->values();

        for ($i = 0; $i < $maxLines; $i++) {
            if ($expense = $expenseValues->get($i)) {
                $sheet->setCellValue('A'.$row, $expense['account']->label());
                $sheet->setCellValue('B'.$row, (float) $expense['amount']);
            }
            if ($revenue = $revenueValues->get($i)) {
                $sheet->setCellValue('C'.$row, $revenue['account']->label());
                $sheet->setCellValue('D'.$row, (float) $revenue['amount']);
            }
            $this->styleDataRow($sheet, $row, 'A', 'D', 'F8FAFC');
            $sheet->getStyle('B'.$row)->getFont()->getColor()->setRGB($expenseColor['text']);
            $sheet->getStyle('D'.$row)->getFont()->getColor()->setRGB($revenueColor['text']);
            $row++;
        }

        $endRow = $row - 1;

        // The balancing line: added to the debit side when the period
        // made a profit (expenses + profit = revenue), or to the credit
        // side on a loss (revenue + loss = expenses) — same convention
        // IncomeStatementTraditional() already follows, but here the
        // amount itself is a formula (the two raw sums' difference),
        // not the controller's own precomputed $netProfit.
        $expenseSumRange = $endRow >= $startRow ? "SUM(B{$startRow}:B{$endRow})" : '0';
        $revenueSumRange = $endRow >= $startRow ? "SUM(D{$startRow}:D{$endRow})" : '0';

        if ($netProfit >= 0) {
            $sheet->setCellValue('A'.$row, __('Net Profit'));
            $sheet->setCellValue('B'.$row, "=({$revenueSumRange})-({$expenseSumRange})");
            $sheet->getStyle('A'.$row.':B'.$row)->getFont()->setBold(true)->setItalic(true);
        } else {
            $sheet->setCellValue('C'.$row, __('Net Loss'));
            $sheet->setCellValue('D'.$row, "=({$expenseSumRange})-({$revenueSumRange})");
            $sheet->getStyle('C'.$row.':D'.$row)->getFont()->setBold(true)->setItalic(true);
        }
        foreach (['B', 'D'] as $col) {
            $sheet->getStyle($col.$row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle($col.$row)->getNumberFormat()->setFormatCode(self::NUMBER_FORMAT);
        }
        $balancingRow = $row;
        $row++;

        $sheet->setCellValue('A'.$row, __('Total'));
        $sheet->setCellValue('B'.$row, "=SUM(B{$startRow}:B{$balancingRow})");
        $sheet->setCellValue('C'.$row, __('Total'));
        $sheet->setCellValue('D'.$row, "=SUM(D{$startRow}:D{$balancingRow})");
        $this->styleGrandTotalRow($sheet, $row, 'A', 'D');

        $this->finishSheet($sheet, $headerRow, ['A' => 30, 'B' => 15, 'C' => 30, 'D' => 15]);

        return $this->output($spreadsheet);
    }
}
