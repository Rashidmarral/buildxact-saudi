<?php

namespace App\Services\Reports;

use App\Models\Company;
use App\Services\Reports\Excel\Concerns\BuildsAccountingWorkbook;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

/**
 * Journal Export (All Postings) → real .xlsx. A flat list mixing every
 * account type on one row, so no single type color applies (same
 * reasoning the web page's own design already follows) — but the
 * Debit/Credit grand totals are SUM() formulas, and every debit always
 * equals every credit, so this doubles as a live double-entry check:
 * edit one posting's figure and watch the two totals disagree.
 */
class JournalExportExcelExporter
{
    use BuildsAccountingWorkbook;

    /**
     * @param  array{from: \Carbon\Carbon, to: \Carbon\Carbon}  $period
     * @param  Collection<int, array>  $rows
     */
    public function export(Company $company, array $period, Collection $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(__('Journal Export'));

        $subtitle = __('Period: :from to :to', ['from' => $period['from']->format('d M Y'), 'to' => $period['to']->format('d M Y')]);
        $columns = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L'];
        $row = $this->writeDocumentHeader($sheet, $company, __('Journal Export (All Postings)'), $subtitle, $columns);
        $headerRow = $row;

        $sheet->fromArray([__('Date'), __('Reference'), __('Source'), __('Code'), __('Account'), __('Description'), __('Debit'), __('Credit'), __('Cost centre'), __('Contact'), __('Location'), __('User')], null, 'A'.$row);
        $this->styleColumnHeaderRow($sheet, $row, 'A', 'L');
        $row++;

        $startRow = $row;

        foreach ($rows as $r) {
            $sheet->setCellValue('A'.$row, $r['date']->format('Y-m-d'));
            $sheet->setCellValue('B'.$row, $r['reference']);
            $sheet->setCellValue('C'.$row, $r['source'] ? ucfirst(str_replace('_', ' ', $r['source'])) : '—');
            $sheet->setCellValue('D'.$row, $r['code']);
            $sheet->setCellValue('E'.$row, $r['account']);
            $sheet->setCellValue('F'.$row, $r['description']);
            $sheet->setCellValue('G'.$row, $r['debit'] > 0 ? (float) $r['debit'] : null);
            $sheet->setCellValue('H'.$row, $r['credit'] > 0 ? (float) $r['credit'] : null);
            $sheet->setCellValue('I'.$row, $r['costCentre'] ?? '—');
            $sheet->setCellValue('J'.$row, $r['contact'] ?? '—');
            $sheet->setCellValue('K'.$row, $r['location'] ?? '—');
            $sheet->setCellValue('L'.$row, $r['user'] ?? '—');

            $sheet->getStyle('A'.$row)->getNumberFormat()->setFormatCode('yyyy-mm-dd');
            foreach (['G', 'H'] as $col) {
                $sheet->getStyle($col.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle($col.$row)->getNumberFormat()->setFormatCode(self::NUMBER_FORMAT);
            }
            $sheet->getStyle('A'.$row.':L'.$row)->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_HAIR)->getColor()->setRGB('E2E8F0');
            $row++;
        }

        $endRow = $row - 1;

        $sheet->setCellValue('A'.$row, __('Total'));
        $sheet->mergeCells('A'.$row.':F'.$row);
        $sheet->setCellValue('G'.$row, $endRow >= $startRow ? "=SUM(G{$startRow}:G{$endRow})" : 0);
        $sheet->setCellValue('H'.$row, $endRow >= $startRow ? "=SUM(H{$startRow}:H{$endRow})" : 0);
        $this->styleGrandTotalRow($sheet, $row, 'A', 'L');

        $this->finishSheet($sheet, $headerRow, ['A' => 11, 'B' => 14, 'C' => 14, 'D' => 10, 'E' => 24, 'F' => 26, 'G' => 13, 'H' => 13, 'I' => 14, 'J' => 16, 'K' => 14, 'L' => 14]);

        return $this->output($spreadsheet);
    }
}
