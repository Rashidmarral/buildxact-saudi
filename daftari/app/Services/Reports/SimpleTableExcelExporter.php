<?php

namespace App\Services\Reports;

use App\Models\Company;
use App\Services\Reports\Excel\Concerns\BuildsAccountingWorkbook;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

/**
 * Generic "flat table + SUM() total row" .xlsx export, shared by every
 * simple accounting report that is just a header, a list of rows, and
 * a grand total (Sales Report, Customer/Supplier Report, Employee
 * Advances, Withholding Tax, Uncleared Bank Items, Bank Reconciliation
 * Summary, P&L by Cost Centre/Location, Payment Mode Balances,
 * Receivables/Payables by Contact, Purchase & Sale Summary, Expense
 * Report, ...) rather than one near-duplicate exporter class per
 * report.
 *
 * A caller that needs a per-row formula (e.g. Net = Income − Expenses)
 * computes that formula string itself before building $rows — this
 * class only needs to know which column indices are numeric (for
 * alignment/format/the total row), not how any individual cell's value
 * was derived. PhpSpreadsheet treats any string starting with "="
 * written via setCellValue as a live formula automatically, so a
 * pre-built formula string works exactly like one built here.
 */
class SimpleTableExcelExporter
{
    use BuildsAccountingWorkbook;

    /**
     * @param  array<int, string>  $headers  column headers, left to right
     * @param  array<int, array<int, mixed>>  $rows  each row aligned to $headers; a numeric column
     *                                               takes a float or an "=..." formula string, a text column a
     *                                               string. A cell may instead be a `fn (int $row): string`
     *                                               closure — invoked with that cell's real (1-based) row number
     *                                               once it's known, for a per-row formula (e.g. "Net" = Income −
     *                                               Expenses) that needs to reference its own row without the
     *                                               caller having to count header rows to predict it.
     * @param  array<int, int>  $numericColumns  0-based column indices to right-align and money-format
     * @param  ?array<int, int>  $totalColumns  0-based column indices to SUM into the grand total row;
     *                                          defaults to $numericColumns. Pass a narrower list when a
     *                                          numeric-looking column shouldn't be summed (a rate/percentage,
     *                                          for instance — formatted as a number, but meaningless totaled).
     */
    public function export(Company $company, string $title, string $subtitle, array $headers, array $rows, array $numericColumns, ?string $colorType = null, bool $showTotalRow = true, ?array $totalColumns = null): string
    {
        $totalColumns ??= $numericColumns;
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($title, 0, 31));

        $columnCount = count($headers);
        $columns = array_map(fn ($i) => $this->columnLetter($i), range(0, $columnCount - 1));
        $lastColumn = end($columns);

        $row = $this->writeDocumentHeader($sheet, $company, $title, $subtitle, $columns);
        $headerRow = $row;

        $sheet->fromArray($headers, null, 'A'.$row);
        $this->styleColumnHeaderRow($sheet, $row, 'A', $lastColumn);
        $row++;

        $color = $colorType ? $this->colorFor($colorType) : ['fill' => 'F8FAFC', 'accent' => '94A3B8', 'text' => '475569'];
        $startRow = $row;

        foreach ($rows as $dataRow) {
            foreach ($dataRow as $i => $value) {
                $col = $columns[$i];
                $sheet->setCellValue($col.$row, $value instanceof \Closure ? $value($row) : $value);
            }
            $this->styleDataRow($sheet, $row, 'A', $lastColumn, $color['fill']);
            // styleDataRow right-aligns/money-formats every column after
            // the first — reset text columns (anything not declared
            // numeric) back to left-aligned plain text.
            foreach ($columns as $i => $col) {
                if (! in_array($i, $numericColumns, true)) {
                    $sheet->getStyle($col.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $sheet->getStyle($col.$row)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_GENERAL);
                }
            }
            $row++;
        }

        $endRow = $row - 1;

        if ($showTotalRow && $totalColumns !== []) {
            $firstTotalCol = $columns[min($totalColumns)];
            $sheet->setCellValue('A'.$row, __('Total'));
            if (min($totalColumns) > 0) {
                $sheet->mergeCells('A'.$row.':'.chr(ord($firstTotalCol) - 1).$row);
            }
            foreach ($totalColumns as $i) {
                $col = $columns[$i];
                $sheet->setCellValue($col.$row, $endRow >= $startRow ? "=SUM({$col}{$startRow}:{$col}{$endRow})" : 0);
            }
            $this->styleGrandTotalRow($sheet, $row, 'A', $lastColumn);
            $row++;
        }

        $widths = [];
        foreach ($columns as $i => $col) {
            $widths[$col] = in_array($i, $numericColumns, true) ? 15 : ($i === 0 ? 14 : 24);
        }

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
