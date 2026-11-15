<?php

namespace App\Services\Reports;

use App\Models\Company;
use App\Services\Reports\Excel\Concerns\BuildsAccountingWorkbook;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * General Ledger → real .xlsx. Each account's running "Balance" column
 * is a genuine formula chain — line N's balance is a formula referencing
 * line N-1's balance cell plus that line's own debit/credit, exactly how
 * a ledger actually works — rather than a flattened running_balance
 * number copied in from the service. Edit a debit figure and every
 * balance below it in that account recalculates, same as the closing
 * balance and the account's own period Dr/Cr totals.
 */
class GeneralLedgerExcelExporter
{
    use BuildsAccountingWorkbook;

    /**
     * @param  array{from: \Carbon\Carbon, to: \Carbon\Carbon}  $period
     * @param  Collection<int, array{account: \App\Models\Account, openingBalance: float, lines: Collection, closingBalance: float, periodDebit: float, periodCredit: float}>  $accounts
     */
    public function export(Company $company, array $period, Collection $accounts): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(__('General Ledger'));

        $subtitle = __('Period: :from to :to', ['from' => $period['from']->format('d M Y'), 'to' => $period['to']->format('d M Y')]);
        $columns = ['A', 'B', 'C', 'D', 'E', 'F'];
        $row = $this->writeDocumentHeader($sheet, $company, __('General Ledger'), $subtitle, $columns);

        $headerRow = $row;
        $sheet->fromArray([__('Date'), __('Entry'), __('Description'), __('Debit'), __('Credit'), __('Balance')], null, 'A'.$row);
        $this->styleColumnHeaderRow($sheet, $row, 'A', 'F');
        $row++;

        foreach ($accounts as $accountRow) {
            $account = $accountRow['account'];
            $color = $this->colorFor($account->type);

            $sheet->setCellValue('A'.$row, $account->label());
            $sheet->mergeCells('A'.$row.':F'.$row);
            $this->styleTypeHeaderRow($sheet, $row, 'A', 'F', $color);
            $row++;

            $sheet->setCellValue('C'.$row, __('Opening balance'));
            $sheet->setCellValue('F'.$row, (float) $accountRow['openingBalance']);
            $this->styleDataRow($sheet, $row, 'A', 'F', $color['fill']);
            $sheet->getStyle('A'.$row.':E'.$row)->getFont()->setItalic(true)->getColor()->setRGB('94A3B8');
            $openingRow = $row;
            $row++;

            $debitStartRow = $row;
            $previousBalanceRow = $openingRow;

            foreach ($accountRow['lines'] as $line) {
                $sheet->setCellValue('A'.$row, $line->journalEntry->entry_date->format('Y-m-d'));
                $sheet->setCellValue('B'.$row, $line->journalEntry->entry_number);
                $sheet->setCellValue('C'.$row, $line->journalEntry->description);
                $sheet->setCellValue('D'.$row, $line->debit > 0 ? (float) $line->debit : null);
                $sheet->setCellValue('E'.$row, $line->credit > 0 ? (float) $line->credit : null);
                // Running balance: this line's own debit/credit applied
                // to the running total just above it — a true chain, not
                // a precomputed figure.
                $sheet->setCellValue('F'.$row, "=F{$previousBalanceRow}+D{$row}-E{$row}");
                $this->styleDataRow($sheet, $row, 'A', 'F', $color['fill']);
                // styleDataRow right-aligns/money-formats every column
                // after the first — correct for D/E/F (Debit/Credit/
                // Balance) but not for B/C (Entry/Description), which
                // are text, not figures.
                $sheet->getStyle('A'.$row)->getNumberFormat()->setFormatCode('yyyy-mm-dd');
                $sheet->getStyle('B'.$row.':C'.$row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle('B'.$row.':C'.$row)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_GENERAL);
                $previousBalanceRow = $row;
                $row++;
            }

            $debitEndRow = $row - 1;

            $sheet->setCellValue('A'.$row, __('Closing balance'));
            $sheet->mergeCells('A'.$row.':C'.$row);
            $sheet->setCellValue('D'.$row, $debitEndRow >= $debitStartRow ? "=SUM(D{$debitStartRow}:D{$debitEndRow})" : 0);
            $sheet->setCellValue('E'.$row, $debitEndRow >= $debitStartRow ? "=SUM(E{$debitStartRow}:E{$debitEndRow})" : 0);
            $sheet->setCellValue('F'.$row, "=F{$previousBalanceRow}");
            $this->styleTotalRow($sheet, $row, 'A', 'F', $color);
            $row++;
            $row++; // blank spacer between accounts
        }

        $this->finishSheet($sheet, $headerRow, ['A' => 12, 'B' => 14, 'C' => 34, 'D' => 15, 'E' => 15, 'F' => 15]);

        return $this->output($spreadsheet);
    }
}
