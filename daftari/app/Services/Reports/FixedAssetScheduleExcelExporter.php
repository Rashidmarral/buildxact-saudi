<?php

namespace App\Services\Reports;

use App\Models\Company;
use App\Services\Reports\Excel\Concerns\BuildsAccountingWorkbook;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Fixed Asset Schedule → real .xlsx. Every asset's own Closing NBV cell
 * is a formula (Opening NBV + Additions − Depreciation − Disposals) —
 * the actual roll-forward identity this report exists to prove, not a
 * value FinancialReportService::fixedAssetSchedule() already computed
 * and this export just copies in. The grand totals are SUM() formulas
 * over those same rows.
 */
class FixedAssetScheduleExcelExporter
{
    use BuildsAccountingWorkbook;

    /**
     * @param  array{from: \Carbon\Carbon, to: \Carbon\Carbon}  $period
     * @param  Collection<int, array{asset: \App\Models\FixedAsset, openingNbv: float, additions: float, depreciation: float, disposals: float, closingNbv: float}>  $rows
     */
    public function export(Company $company, array $period, Collection $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(__('Fixed Asset Schedule'));

        $subtitle = __('Period: :from to :to', ['from' => $period['from']->format('d M Y'), 'to' => $period['to']->format('d M Y')]);
        $columns = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
        $row = $this->writeDocumentHeader($sheet, $company, __('Fixed Asset Schedule'), $subtitle, $columns);

        $headerRow = $row;
        $sheet->fromArray([__('Asset code'), __('Name'), __('Category'), __('Opening NBV'), __('Additions'), __('Depreciation'), __('Disposals'), __('Closing NBV')], null, 'A'.$row);
        $this->styleColumnHeaderRow($sheet, $row, 'A', 'H');
        $row++;

        $color = $this->colorFor('asset');
        $dataStartRow = $row;

        foreach ($rows as $r) {
            $sheet->setCellValue('A'.$row, $r['asset']->asset_code);
            $sheet->setCellValue('B'.$row, $r['asset']->name);
            $sheet->setCellValue('C'.$row, $r['asset']->category);
            $sheet->setCellValue('D'.$row, (float) $r['openingNbv']);
            $sheet->setCellValue('E'.$row, (float) $r['additions']);
            $sheet->setCellValue('F'.$row, (float) $r['depreciation']);
            $sheet->setCellValue('G'.$row, (float) $r['disposals']);
            // The roll-forward identity itself, as a formula: opening +
            // additions − depreciation − disposals.
            $sheet->setCellValue('H'.$row, "=D{$row}+E{$row}-F{$row}-G{$row}");
            $this->styleDataRow($sheet, $row, 'A', 'H', $color['fill']);
            $sheet->getStyle('A'.$row.':C'.$row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle('A'.$row.':C'.$row)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_GENERAL);
            $row++;
        }

        $dataEndRow = $row - 1;

        $sheet->setCellValue('A'.$row, __('Total'));
        $sheet->mergeCells('A'.$row.':C'.$row);
        foreach (['D', 'E', 'F', 'G', 'H'] as $col) {
            $sheet->setCellValue($col.$row, $dataEndRow >= $dataStartRow ? "=SUM({$col}{$dataStartRow}:{$col}{$dataEndRow})" : 0);
        }
        $this->styleGrandTotalRow($sheet, $row, 'A', 'H');

        $this->finishSheet($sheet, $headerRow, ['A' => 14, 'B' => 28, 'C' => 16, 'D' => 15, 'E' => 15, 'F' => 15, 'G' => 15, 'H' => 15]);

        return $this->output($spreadsheet);
    }
}
