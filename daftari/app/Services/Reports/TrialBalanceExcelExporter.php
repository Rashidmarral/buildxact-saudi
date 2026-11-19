<?php

namespace App\Services\Reports;

use App\Models\Company;
use App\Services\Reports\Excel\Concerns\BuildsAccountingWorkbook;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Trial Balance → real .xlsx, not just a CSV dump. Same Hamly-parity
 * type-color system the web/PDF Trial Balance already use
 * (blue/amber/violet/emerald/rose), but the real reason this exists
 * rather than reusing the CSV path: every subtotal, type total, and
 * grand total is a genuine Excel SUM() formula over the row range
 * written just above it, not a pre-computed literal. Open it, tweak a
 * category's closing balance to test a what-if, and every total above
 * it recalculates — the CSV export can never do that, it's just text.
 */
class TrialBalanceExcelExporter
{
    use BuildsAccountingWorkbook;

    /**
     * @param  array{from: \Carbon\Carbon, to: \Carbon\Carbon}  $period
     * @param  Collection<string, array>  $types  keyed by account type
     */
    public function summary(Company $company, array $period, Collection $types): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(__('Trial Balance'));

        $subtitle = __('Period: :from to :to', ['from' => $period['from']->format('d M Y'), 'to' => $period['to']->format('d M Y')]);
        $columns = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];
        $row = $this->writeDocumentHeader($sheet, $company, __('Trial Balance'), $subtitle, $columns);

        $headerRow = $row;
        $sheet->fromArray([
            __('Category'), __('Opening Dr'), __('Opening Cr'), __('Dr'), __('Cr'), __('Closing Dr'), __('Closing Cr'),
        ], null, 'A'.$row);
        $this->styleColumnHeaderRow($sheet, $row, 'A', 'G');
        $row++;

        $typeTotalRows = [];

        foreach ($types as $typeKey => $type) {
            $color = $this->colorFor($typeKey);

            $sheet->setCellValue('A'.$row, $type['label']);
            $this->styleTypeHeaderRow($sheet, $row, 'A', 'G', $color);
            $row++;

            $categoryStartRow = $row;

            foreach ($type['categories'] as $categoryLabel => $category) {
                $sheet->setCellValue('A'.$row, $categoryLabel.' ('.$category['rows']->count().')');
                $sheet->setCellValue('B'.$row, (float) $category['openingDebit']);
                $sheet->setCellValue('C'.$row, (float) $category['openingCredit']);
                $sheet->setCellValue('D'.$row, (float) $category['periodDebit']);
                $sheet->setCellValue('E'.$row, (float) $category['periodCredit']);
                $sheet->setCellValue('F'.$row, (float) $category['closingDebit']);
                $sheet->setCellValue('G'.$row, (float) $category['closingCredit']);
                $this->styleDataRow($sheet, $row, 'A', 'G', $color['fill']);
                $row++;
            }

            $categoryEndRow = $row - 1;

            $sheet->setCellValue('A'.$row, __('Total of :type', ['type' => $type['label']]));
            foreach (['B', 'C', 'D', 'E', 'F', 'G'] as $col) {
                $sheet->setCellValue($col.$row, $categoryEndRow >= $categoryStartRow
                    ? "=SUM({$col}{$categoryStartRow}:{$col}{$categoryEndRow})"
                    : 0);
            }
            $this->styleTotalRow($sheet, $row, 'A', 'G', $color);
            $typeTotalRows[] = $row;
            $row++;
        }

        $sheet->setCellValue('A'.$row, __('GRAND TOTAL'));
        foreach (['B', 'C', 'D', 'E', 'F', 'G'] as $col) {
            $sheet->setCellValue($col.$row, $typeTotalRows === []
                ? 0
                : '='.implode('+', array_map(fn ($r) => $col.$r, $typeTotalRows)));
        }
        $this->styleGrandTotalRow($sheet, $row, 'A', 'G');

        $this->finishSheet($sheet, $headerRow, ['A' => 34, 'B' => 15, 'C' => 15, 'D' => 15, 'E' => 15, 'F' => 15, 'G' => 15]);

        return $this->output($spreadsheet);
    }

    /**
     * @param  array{from: \Carbon\Carbon, to: \Carbon\Carbon}  $period
     * @param  Collection<string, array>  $types  keyed by account type
     */
    public function detailed(Company $company, array $period, Collection $types): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(__('Trial Balance'));

        $subtitle = __('Period: :from to :to', ['from' => $period['from']->format('d M Y'), 'to' => $period['to']->format('d M Y')]);
        $columns = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
        $row = $this->writeDocumentHeader($sheet, $company, __('Trial Balance (Detailed)'), $subtitle, $columns);

        $headerRow = $row;
        $sheet->fromArray([
            __('Code'), __('Account'), __('Opening Dr'), __('Opening Cr'), __('Dr'), __('Cr'), __('Closing Dr'), __('Closing Cr'),
        ], null, 'A'.$row);
        $this->styleColumnHeaderRow($sheet, $row, 'A', 'H');
        $row++;

        $typeTotalRows = [];
        // Closing-only figures per category, collected while walking the
        // main table, so the "Closing Balance Summary" sheet below can be
        // written as its own self-contained set of SUM() formulas instead
        // of a second pass over the report service's data.
        $closingSummary = [];

        foreach ($types as $typeKey => $type) {
            $color = $this->colorFor($typeKey);
            $closingSummary[$typeKey] = ['label' => $type['label'], 'color' => $color, 'categories' => []];

            $sheet->setCellValue('A'.$row, $type['label']);
            $this->styleTypeHeaderRow($sheet, $row, 'A', 'H', $color);
            $row++;

            $categorySubtotalRows = [];

            foreach ($type['categories'] as $categoryLabel => $category) {
                $sheet->setCellValue('A'.$row, $categoryLabel);
                $this->styleCategoryLabelRow($sheet, $row, 'A', 'H', $color);
                $row++;

                $accountStartRow = $row;

                foreach ($category['rows'] as $accountRow) {
                    $sheet->setCellValue('A'.$row, $accountRow['account']->code);
                    $sheet->setCellValue('B'.$row, $accountRow['account']->name);
                    $sheet->setCellValue('C'.$row, (float) $accountRow['openingDebit']);
                    $sheet->setCellValue('D'.$row, (float) $accountRow['openingCredit']);
                    $sheet->setCellValue('E'.$row, (float) $accountRow['periodDebit']);
                    $sheet->setCellValue('F'.$row, (float) $accountRow['periodCredit']);
                    $sheet->setCellValue('G'.$row, (float) $accountRow['closingDebit']);
                    $sheet->setCellValue('H'.$row, (float) $accountRow['closingCredit']);
                    $this->styleDataRow($sheet, $row, 'A', 'H', $color['fill']);
                    $row++;
                }

                $accountEndRow = $row - 1;

                $sheet->setCellValue('A'.$row, __('Subtotal — :category', ['category' => $categoryLabel]));
                foreach (['C', 'D', 'E', 'F', 'G', 'H'] as $col) {
                    $sheet->setCellValue($col.$row, $accountEndRow >= $accountStartRow
                        ? "=SUM({$col}{$accountStartRow}:{$col}{$accountEndRow})"
                        : 0);
                }
                $this->styleTotalRow($sheet, $row, 'A', 'H', $color);
                $categorySubtotalRows[] = $row;
                $closingSummary[$typeKey]['categories'][$categoryLabel] = ['closingDebitRow' => $row, 'closingDebitCol' => 'G', 'closingCreditCol' => 'H'];
                $row++;
            }

            $sheet->setCellValue('A'.$row, __('Total of :type', ['type' => $type['label']]));
            foreach (['C', 'D', 'E', 'F', 'G', 'H'] as $col) {
                $sheet->setCellValue($col.$row, $categorySubtotalRows === []
                    ? 0
                    : '='.implode('+', array_map(fn ($r) => $col.$r, $categorySubtotalRows)));
            }
            $this->styleTotalRow($sheet, $row, 'A', 'H', $color);
            $typeTotalRows[] = $row;
            $row++;
        }

        $sheet->setCellValue('A'.$row, __('GRAND TOTAL'));
        foreach (['C', 'D', 'E', 'F', 'G', 'H'] as $col) {
            $sheet->setCellValue($col.$row, $typeTotalRows === []
                ? 0
                : '='.implode('+', array_map(fn ($r) => $col.$r, $typeTotalRows)));
        }
        $this->styleGrandTotalRow($sheet, $row, 'A', 'H');

        $this->finishSheet($sheet, $headerRow, ['A' => 10, 'B' => 30, 'C' => 15, 'D' => 15, 'E' => 15, 'F' => 15, 'G' => 15, 'H' => 15]);

        $this->writeClosingSummarySheet($spreadsheet, $company, $subtitle, $closingSummary);

        return $this->output($spreadsheet);
    }

    /**
     * A condensed Debit/Credit-only recap of every category's closing
     * balance, matching the same section the web/PDF detailed Trial
     * Balance already end with — as its own sheet (not a second region
     * on the main sheet) so it reads as a clean standalone page when
     * printed. Pulls its figures with real cross-sheet formulas
     * (='Trial Balance'!G12) from the main sheet's own subtotal rows
     * rather than re-deriving them, so the two sheets can never drift
     * out of sync if a cell on the main sheet is edited.
     */
    private function writeClosingSummarySheet(Spreadsheet $spreadsheet, Company $company, string $subtitle, array $closingSummary): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle(__('Closing Summary'));
        $sourceSheetName = __('Trial Balance');

        $row = $this->writeDocumentHeader($sheet, $company, __('Closing Balance Summary'), $subtitle, ['A', 'B', 'C']);

        $headerRow = $row;
        $sheet->fromArray([__('Heads of Account'), __('Debit :currency', ['currency' => $company->currency]), __('Credit :currency', ['currency' => $company->currency])], null, 'A'.$row);
        $this->styleColumnHeaderRow($sheet, $row, 'A', 'C');
        $row++;

        $typeTotalRows = [];

        foreach ($closingSummary as $type) {
            $color = $type['color'];

            $sheet->setCellValue('A'.$row, $type['label']);
            $this->styleTypeHeaderRow($sheet, $row, 'A', 'C', $color);
            $row++;

            $categoryRows = [];

            foreach ($type['categories'] as $categoryLabel => $ref) {
                $sheet->setCellValue('A'.$row, $categoryLabel);
                $sheet->setCellValue('B'.$row, "='{$sourceSheetName}'!{$ref['closingDebitCol']}{$ref['closingDebitRow']}");
                $sheet->setCellValue('C'.$row, "='{$sourceSheetName}'!{$ref['closingCreditCol']}{$ref['closingDebitRow']}");
                $this->styleDataRow($sheet, $row, 'A', 'C', $color['fill']);
                $categoryRows[] = $row;
                $row++;
            }

            $sheet->setCellValue('A'.$row, __('Total of :type', ['type' => $type['label']]));
            foreach (['B', 'C'] as $col) {
                $sheet->setCellValue($col.$row, $categoryRows === []
                    ? 0
                    : '='.implode('+', array_map(fn ($r) => $col.$r, $categoryRows)));
            }
            $this->styleTotalRow($sheet, $row, 'A', 'C', $color);
            $typeTotalRows[] = $row;
            $row++;
        }

        $sheet->setCellValue('A'.$row, __('GRAND TOTAL'));
        foreach (['B', 'C'] as $col) {
            $sheet->setCellValue($col.$row, $typeTotalRows === []
                ? 0
                : '='.implode('+', array_map(fn ($r) => $col.$r, $typeTotalRows)));
        }
        $this->styleGrandTotalRow($sheet, $row, 'A', 'C');

        $this->finishSheet($sheet, $headerRow, ['A' => 34, 'B' => 18, 'C' => 18]);
    }
}
