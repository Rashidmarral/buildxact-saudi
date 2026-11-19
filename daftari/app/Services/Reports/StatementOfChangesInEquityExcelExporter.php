<?php

namespace App\Services\Reports;

use App\Models\Company;
use App\Services\Reports\Excel\Concerns\BuildsAccountingWorkbook;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Statement of Changes in Equity → real .xlsx. Every account's own
 * Closing cell is a formula (Opening + Movement), Retained earnings'
 * Closing cell is a formula (Opening + Net income), and the final Total
 * equity row is a formula chain matching
 * FinancialReportService::statementOfChangesInEquity()'s own identity
 * (totalOpening + totalMovement + netIncomeForPeriod) — not a flattened
 * number copied in from the service.
 */
class StatementOfChangesInEquityExcelExporter
{
    use BuildsAccountingWorkbook;

    /**
     * @param  array{from: \Carbon\Carbon, to: \Carbon\Carbon}  $period
     * @param  array{rows: Collection, openingRetainedEarnings: float, netIncomeForPeriod: float, totalOpening: float, totalMovement: float, totalClosing: float}  $data
     */
    public function export(Company $company, array $period, array $data): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(__('Statement of Changes in Equity'));

        $subtitle = __('Period: :from to :to', ['from' => $period['from']->format('d M Y'), 'to' => $period['to']->format('d M Y')]);
        $columns = ['A', 'B', 'C', 'D', 'E'];
        $row = $this->writeDocumentHeader($sheet, $company, __('Statement of Changes in Equity'), $subtitle, $columns);

        $headerRow = $row;
        $sheet->fromArray([__('Account'), __('Opening balance'), __('Movement'), __('Net income'), __('Closing balance')], null, 'A'.$row);
        $this->styleColumnHeaderRow($sheet, $row, 'A', 'E');
        $row++;

        $color = $this->colorFor('equity');
        $openingRows = [];
        $movementRows = [];

        foreach ($data['rows'] as $r) {
            $sheet->setCellValue('A'.$row, $r['account']->label());
            $sheet->setCellValue('B'.$row, (float) $r['opening']);
            $sheet->setCellValue('C'.$row, (float) $r['movement']);
            $sheet->setCellValue('E'.$row, "=B{$row}+C{$row}");
            $this->styleDataRow($sheet, $row, 'A', 'E', $color['fill']);
            $openingRows[] = $row;
            $movementRows[] = $row;
            $row++;
        }

        $sheet->setCellValue('A'.$row, __('Retained earnings'));
        $sheet->setCellValue('B'.$row, (float) $data['openingRetainedEarnings']);
        $sheet->setCellValue('D'.$row, (float) $data['netIncomeForPeriod']);
        $sheet->setCellValue('E'.$row, "=B{$row}+D{$row}");
        $this->styleDataRow($sheet, $row, 'A', 'E', $color['fill']);
        $retainedRow = $row;
        $openingRows[] = $row;
        $row++;

        $sheet->setCellValue('A'.$row, __('Total equity'));
        $sheet->setCellValue('B'.$row, '='.implode('+', array_map(fn ($r) => "B{$r}", $openingRows)));
        $sheet->setCellValue('C'.$row, $movementRows === [] ? 0 : '='.implode('+', array_map(fn ($r) => "C{$r}", $movementRows)));
        $sheet->setCellValue('D'.$row, "=D{$retainedRow}");
        $sheet->setCellValue('E'.$row, "=B{$row}+C{$row}+D{$row}");
        $this->styleGrandTotalRow($sheet, $row, 'A', 'E');

        $this->finishSheet($sheet, $headerRow, ['A' => 30, 'B' => 16, 'C' => 16, 'D' => 16, 'E' => 16]);

        return $this->output($spreadsheet);
    }
}
