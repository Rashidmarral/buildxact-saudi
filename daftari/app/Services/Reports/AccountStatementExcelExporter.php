<?php

namespace App\Services\Reports;

use App\Models\Client;
use App\Models\Company;
use App\Models\Supplier;
use App\Services\Reports\Excel\Concerns\BuildsAccountingWorkbook;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Account Statement → real .xlsx. The running "Balance" column is a
 * genuine formula chain (each line references the balance just above
 * it plus its own debit/credit) — same convention as General Ledger —
 * so it recalculates if a line's figure is edited, rather than copying
 * in the balance the controller already walked line-by-line to
 * compute.
 */
class AccountStatementExcelExporter
{
    use BuildsAccountingWorkbook;

    /**
     * @param  array{from: \Carbon\Carbon, to: \Carbon\Carbon}  $period
     * @param  Client|Supplier  $party
     * @param  Collection<int, object{date: \Carbon\Carbon, description: string, debit: float, credit: float}>  $lines
     */
    public function export(Company $company, array $period, string $type, Client|Supplier $party, Collection $lines, float $openingBalance): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(__('Account Statement'));

        $title = $type === 'customer' ? __('Customer Statement') : __('Supplier Statement');
        $subtitle = $party->name.' — '.__('Period: :from to :to', ['from' => $period['from']->format('d M Y'), 'to' => $period['to']->format('d M Y')]);
        $columns = ['A', 'B', 'C', 'D', 'E'];
        $row = $this->writeDocumentHeader($sheet, $company, $title, $subtitle, $columns);
        $headerRow = $row;

        $sheet->fromArray([__('Date'), __('Description'), __('Debit'), __('Credit'), __('Balance')], null, 'A'.$row);
        $this->styleColumnHeaderRow($sheet, $row, 'A', 'E');
        $row++;

        $color = $this->colorFor($type === 'customer' ? 'asset' : 'liability');

        $sheet->setCellValue('B'.$row, __('Opening balance'));
        $sheet->setCellValue('E'.$row, (float) $openingBalance);
        $this->styleDataRow($sheet, $row, 'A', 'E', $color['fill']);
        $sheet->getStyle('A'.$row.':D'.$row)->getFont()->setItalic(true)->getColor()->setRGB('94A3B8');
        $previousBalanceRow = $row;
        $row++;

        $debitStartRow = $row;

        foreach ($lines as $line) {
            $sheet->setCellValue('A'.$row, $line->date->format('Y-m-d'));
            $sheet->setCellValue('B'.$row, $line->description);
            $sheet->setCellValue('C'.$row, $line->debit > 0 ? (float) $line->debit : null);
            $sheet->setCellValue('D'.$row, $line->credit > 0 ? (float) $line->credit : null);
            $sheet->setCellValue('E'.$row, "=E{$previousBalanceRow}+C{$row}-D{$row}");
            $this->styleDataRow($sheet, $row, 'A', 'E', $color['fill']);
            $sheet->getStyle('A'.$row)->getNumberFormat()->setFormatCode('yyyy-mm-dd');
            $sheet->getStyle('B'.$row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle('B'.$row)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_GENERAL);
            $previousBalanceRow = $row;
            $row++;
        }

        $debitEndRow = $row - 1;

        $sheet->setCellValue('A'.$row, __('Closing balance'));
        $sheet->mergeCells('A'.$row.':B'.$row);
        $sheet->setCellValue('C'.$row, $debitEndRow >= $debitStartRow ? "=SUM(C{$debitStartRow}:C{$debitEndRow})" : 0);
        $sheet->setCellValue('D'.$row, $debitEndRow >= $debitStartRow ? "=SUM(D{$debitStartRow}:D{$debitEndRow})" : 0);
        $sheet->setCellValue('E'.$row, "=E{$previousBalanceRow}");
        $this->styleGrandTotalRow($sheet, $row, 'A', 'E');

        $this->finishSheet($sheet, $headerRow, ['A' => 12, 'B' => 42, 'C' => 15, 'D' => 15, 'E' => 15]);

        return $this->output($spreadsheet);
    }
}
