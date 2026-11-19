<?php

namespace App\Services\Reports;

use App\Models\Company;
use App\Services\Reports\Excel\Concerns\BuildsAccountingWorkbook;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

/**
 * Aged Receivables/Payables → real .xlsx. Each party's own Total
 * column is a formula summing that row's five aging buckets (not a
 * value copied in from the controller's own bucket math), and the
 * grand Total row is a SUM() over every party row — colored the same
 * amber/red-for-risk convention the web page uses (61-90 days amber,
 * 90+ days red), rather than the account-type palette the rest of the
 * accounting reports use, since aging buckets have no account type of
 * their own.
 */
class AgingExcelExporter
{
    use BuildsAccountingWorkbook;

    private const RISK_AMBER = 'B45309';

    private const RISK_RED = 'B91C1C';

    /**
     * @param  Collection<int, array{party: \Illuminate\Database\Eloquent\Model, buckets: array, total: float}>  $rows
     */
    public function summary(Company $company, string $type, Collection $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $title = $type === 'receivables' ? __('Aged Receivables') : __('Aged Payables');
        $sheet->setTitle($title);

        $subtitle = __('As of :date', ['date' => now()->format('d M Y')]);
        $columns = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];
        $row = $this->writeDocumentHeader($sheet, $company, $title, $subtitle, $columns);
        $headerRow = $row;

        $partyLabel = $type === 'receivables' ? __('Customer') : __('Supplier');
        $sheet->fromArray([$partyLabel, __('Current'), __('1-30 days'), __('31-60 days'), __('61-90 days'), __('90+ days'), __('Total')], null, 'A'.$row);
        $this->styleColumnHeaderRow($sheet, $row, 'A', 'G');
        $row++;

        $startRow = $row;

        foreach ($rows as $r) {
            $sheet->setCellValue('A'.$row, $r['party']->name);
            $sheet->setCellValue('B'.$row, (float) $r['buckets']['current']);
            $sheet->setCellValue('C'.$row, (float) $r['buckets']['days_1_30']);
            $sheet->setCellValue('D'.$row, (float) $r['buckets']['days_31_60']);
            $sheet->setCellValue('E'.$row, (float) $r['buckets']['days_61_90']);
            $sheet->setCellValue('F'.$row, (float) $r['buckets']['days_over_90']);
            $sheet->setCellValue('G'.$row, "=SUM(B{$row}:F{$row})");

            foreach (['B', 'C', 'D', 'E', 'F', 'G'] as $col) {
                $sheet->getStyle($col.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle($col.$row)->getNumberFormat()->setFormatCode(self::NUMBER_FORMAT);
            }
            $sheet->getStyle('A'.$row.':G'.$row)->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_HAIR)->getColor()->setRGB('E2E8F0');
            if ((float) $r['buckets']['days_61_90'] > 0) {
                $sheet->getStyle('E'.$row)->getFont()->setBold(true)->getColor()->setRGB(self::RISK_AMBER);
            }
            if ((float) $r['buckets']['days_over_90'] > 0) {
                $sheet->getStyle('F'.$row)->getFont()->setBold(true)->getColor()->setRGB(self::RISK_RED);
            }
            $sheet->getStyle('G'.$row)->getFont()->setBold(true);
            $row++;
        }

        $endRow = $row - 1;

        $sheet->setCellValue('A'.$row, __('Total'));
        foreach (['B', 'C', 'D', 'E', 'F', 'G'] as $col) {
            $sheet->setCellValue($col.$row, $endRow >= $startRow ? "=SUM({$col}{$startRow}:{$col}{$endRow})" : 0);
        }
        $this->styleGrandTotalRow($sheet, $row, 'A', 'G');

        $this->finishSheet($sheet, $headerRow, ['A' => 28, 'B' => 15, 'C' => 15, 'D' => 15, 'E' => 15, 'F' => 15, 'G' => 15]);

        return $this->output($spreadsheet);
    }

    /**
     * @param  Collection<int, array{date: \Carbon\Carbon, transactionType: string, reference: string, party: string, dueDate: \Carbon\Carbon, due: float}>  $rows
     */
    public function details(Company $company, string $type, Collection $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $title = $type === 'receivables' ? __('Aged Receivables (Details)') : __('Aged Payables (Details)');
        $sheet->setTitle($type === 'receivables' ? __('Aged Receivables Detail') : __('Aged Payables Detail'));

        $subtitle = __('As of :date', ['date' => now()->format('d M Y')]);
        $columns = ['A', 'B', 'C', 'D', 'E', 'F'];
        $row = $this->writeDocumentHeader($sheet, $company, $title, $subtitle, $columns);
        $headerRow = $row;

        $referenceLabel = $type === 'receivables' ? __('Invoice No.') : __('Reference No');
        $partyLabel = $type === 'receivables' ? __('Customer') : __('Supplier');
        $sheet->fromArray([__('Date'), __('Transaction Type'), $referenceLabel, $partyLabel, __('Due Date'), __('Due')], null, 'A'.$row);
        $this->styleColumnHeaderRow($sheet, $row, 'A', 'F');
        $row++;

        $startRow = $row;

        foreach ($rows as $r) {
            $sheet->setCellValue('A'.$row, $r['date']->format('Y-m-d'));
            $sheet->setCellValue('B'.$row, $r['transactionType']);
            $sheet->setCellValue('C'.$row, $r['reference']);
            $sheet->setCellValue('D'.$row, $r['party']);
            $sheet->setCellValue('E'.$row, $r['dueDate']->format('Y-m-d'));
            $sheet->setCellValue('F'.$row, (float) $r['due']);
            $sheet->getStyle('A'.$row)->getNumberFormat()->setFormatCode('yyyy-mm-dd');
            $sheet->getStyle('E'.$row)->getNumberFormat()->setFormatCode('yyyy-mm-dd');
            $sheet->getStyle('F'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle('F'.$row)->getNumberFormat()->setFormatCode(self::NUMBER_FORMAT);
            $sheet->getStyle('F'.$row)->getFont()->setBold(true);
            if ($r['dueDate']->isPast()) {
                $sheet->getStyle('F'.$row)->getFont()->getColor()->setRGB(self::RISK_RED);
            }
            $sheet->getStyle('A'.$row.':F'.$row)->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_HAIR)->getColor()->setRGB('E2E8F0');
            $row++;
        }

        $endRow = $row - 1;

        $sheet->setCellValue('A'.$row, __('Total'));
        $sheet->mergeCells('A'.$row.':E'.$row);
        $sheet->setCellValue('F'.$row, $endRow >= $startRow ? "=SUM(F{$startRow}:F{$endRow})" : 0);
        $this->styleGrandTotalRow($sheet, $row, 'A', 'F');

        $this->finishSheet($sheet, $headerRow, ['A' => 12, 'B' => 18, 'C' => 16, 'D' => 24, 'E' => 12, 'F' => 15]);

        return $this->output($spreadsheet);
    }
}
