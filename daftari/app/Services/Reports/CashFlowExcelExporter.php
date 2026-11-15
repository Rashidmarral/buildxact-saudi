<?php

namespace App\Services\Reports;

use App\Models\Company;
use App\Services\Reports\Excel\Concerns\BuildsAccountingWorkbook;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Cash Flow Statement → real .xlsx. Each section's own "Net cash from…"
 * row is a SUM() formula over the lines just above it, "Net change in
 * cash" is a formula adding the three section totals, and "Closing
 * cash & bank" is a formula (Opening + Net change) — the exact
 * identity chain FinancialReportService::cashFlow() computes, plus a
 * reconciliation check comparing that formula result against the
 * company's actual cash account balance, same convention as the
 * Balance Sheet's "Balance check" row.
 */
class CashFlowExcelExporter
{
    use BuildsAccountingWorkbook;

    /**
     * @param  array{from: \Carbon\Carbon, to: \Carbon\Carbon}  $period
     */
    public function export(Company $company, array $period, array $data): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(__('Cash Flow'));

        $subtitle = __('Period: :from to :to', ['from' => $period['from']->format('d M Y'), 'to' => $period['to']->format('d M Y')]);
        $columns = ['A', 'B'];
        $row = $this->writeDocumentHeader($sheet, $company, __('Cash Flow Statement'), $subtitle, $columns);

        $headerRow = $row;
        $sheet->fromArray([__('Line'), __('Amount')], null, 'A'.$row);
        $this->styleColumnHeaderRow($sheet, $row, 'A', 'B');
        $row++;

        // Operating activities — colorFor('revenue') (emerald): the
        // section that actually generates cash from the business.
        $operatingColor = $this->colorFor('revenue');
        $sheet->setCellValue('A'.$row, __('Operating activities'));
        $sheet->mergeCells('A'.$row.':B'.$row);
        $this->styleTypeHeaderRow($sheet, $row, 'A', 'B', $operatingColor);
        $row++;

        $operatingStartRow = $row;
        $sheet->setCellValue('A'.$row, __('Net income'));
        $sheet->setCellValue('B'.$row, (float) $data['netIncome']);
        $this->styleDataRow($sheet, $row, 'A', 'B', $operatingColor['fill']);
        $row++;
        $sheet->setCellValue('A'.$row, __('Depreciation'));
        $sheet->setCellValue('B'.$row, (float) $data['depreciation']);
        $this->styleDataRow($sheet, $row, 'A', 'B', $operatingColor['fill']);
        $row++;
        foreach ($data['workingCapitalLines'] as $r) {
            $sheet->setCellValue('A'.$row, $r['account']->name);
            $sheet->setCellValue('B'.$row, (float) $r['cashEffect']);
            $this->styleDataRow($sheet, $row, 'A', 'B', $operatingColor['fill']);
            $row++;
        }
        $operatingEndRow = $row - 1;

        $sheet->setCellValue('A'.$row, __('Net cash from operating activities'));
        $sheet->setCellValue('B'.$row, "=SUM(B{$operatingStartRow}:B{$operatingEndRow})");
        $this->styleTotalRow($sheet, $row, 'A', 'B', $operatingColor);
        $operatingTotalRow = $row;
        $row++;
        $row++; // spacer

        // Investing activities — colorFor('liability') (amber).
        $investingColor = $this->colorFor('liability');
        $sheet->setCellValue('A'.$row, __('Investing activities'));
        $sheet->mergeCells('A'.$row.':B'.$row);
        $this->styleTypeHeaderRow($sheet, $row, 'A', 'B', $investingColor);
        $row++;

        $investingStartRow = $row;
        $sheet->setCellValue('A'.$row, __('Fixed asset acquisitions'));
        $sheet->setCellValue('B'.$row, -1 * (float) $data['acquisitions']);
        $this->styleDataRow($sheet, $row, 'A', 'B', $investingColor['fill']);
        $row++;
        $sheet->setCellValue('A'.$row, __('Fixed asset disposal proceeds'));
        $sheet->setCellValue('B'.$row, (float) $data['disposalProceeds']);
        $this->styleDataRow($sheet, $row, 'A', 'B', $investingColor['fill']);
        $row++;
        $investingEndRow = $row - 1;

        $sheet->setCellValue('A'.$row, __('Net cash from investing activities'));
        $sheet->setCellValue('B'.$row, "=SUM(B{$investingStartRow}:B{$investingEndRow})");
        $this->styleTotalRow($sheet, $row, 'A', 'B', $investingColor);
        $investingTotalRow = $row;
        $row++;
        $row++; // spacer

        // Financing activities — colorFor('equity') (violet).
        $financingColor = $this->colorFor('equity');
        $sheet->setCellValue('A'.$row, __('Financing activities'));
        $sheet->mergeCells('A'.$row.':B'.$row);
        $this->styleTypeHeaderRow($sheet, $row, 'A', 'B', $financingColor);
        $row++;

        $financingStartRow = $row;
        foreach ($data['equityLines'] as $r) {
            $sheet->setCellValue('A'.$row, $r['account']->name);
            $sheet->setCellValue('B'.$row, (float) $r['change']);
            $this->styleDataRow($sheet, $row, 'A', 'B', $financingColor['fill']);
            $row++;
        }
        $financingEndRow = $row - 1;

        $sheet->setCellValue('A'.$row, __('Net cash from financing activities'));
        $sheet->setCellValue('B'.$row, $financingEndRow >= $financingStartRow ? "=SUM(B{$financingStartRow}:B{$financingEndRow})" : 0);
        $this->styleTotalRow($sheet, $row, 'A', 'B', $financingColor);
        $financingTotalRow = $row;
        $row++;
        $row++; // spacer

        $sheet->setCellValue('A'.$row, __('Net change in cash'));
        $sheet->setCellValue('B'.$row, "=B{$operatingTotalRow}+B{$investingTotalRow}+B{$financingTotalRow}");
        $this->styleGrandTotalRow($sheet, $row, 'A', 'B');
        $netChangeRow = $row;
        $row++;

        $sheet->setCellValue('A'.$row, __('Opening cash & bank'));
        $sheet->setCellValue('B'.$row, (float) $data['openingCash']);
        $this->styleDataRow($sheet, $row, 'A', 'B', 'F1F5F9');
        $openingCashRow = $row;
        $row++;

        $sheet->setCellValue('A'.$row, __('Closing cash & bank'));
        $sheet->setCellValue('B'.$row, "=B{$openingCashRow}+B{$netChangeRow}");
        $this->styleGrandTotalRow($sheet, $row, 'A', 'B');
        $closingCashRow = $row;
        $row++;

        $sheet->setCellValue('A'.$row, __('Actual closing cash & bank balance'));
        $sheet->setCellValue('B'.$row, (float) $data['actualClosingCash']);
        $this->styleDataRow($sheet, $row, 'A', 'B', 'F1F5F9');
        $actualClosingCashRow = $row;
        $row++;

        $sheet->setCellValue('A'.$row, __('Reconciliation check'));
        $sheet->setCellValue('B'.$row, "=IF(ROUND(B{$closingCashRow}-B{$actualClosingCashRow},2)=0,\"".__('Reconciled')."\",\"".__('NOT RECONCILED')."\")");
        $sheet->getStyle('A'.$row.':B'.$row)->getFont()->setBold(true)->setItalic(true)->setSize(9.5);
        $row++;

        $this->finishSheet($sheet, $headerRow, ['A' => 42, 'B' => 18]);

        return $this->output($spreadsheet);
    }
}
