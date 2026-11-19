<?php

namespace App\Services\Reports;

use App\Models\Bill;
use App\Models\Company;
use App\Models\Expense;
use App\Models\Invoice;
use App\Services\Reports\Excel\Concerns\BuildsAccountingWorkbook;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Tax Report (VAT) → real .xlsx, four sheets: a Summary that nets
 * output tax the same way the web page's "advance/prepayment
 * adjustment" box does (as a visible formula chain — gross invoice
 * VAT plus prepayment VAT due, minus VAT already reported earlier via
 * Advance Credit/Prepayment), minus recoverable input VAT after
 * apportionment, plus one sheet per transaction tab (Sales/Purchases/
 * Expenses) whose own tax-amount total the Summary sheet pulls from
 * with a cross-sheet formula rather than repeating the sum.
 */
class VatExcelExporter
{
    use BuildsAccountingWorkbook;

    /**
     * @param  array{from: \Carbon\Carbon, to: \Carbon\Carbon}  $period
     * @param  Collection<int, Invoice>  $salesRows
     * @param  Collection<int, Bill>  $purchaseRows
     * @param  Collection<int, Expense>  $expenseRows
     */
    public function export(Company $company, array $period, Collection $salesRows, Collection $purchaseRows, Collection $expenseRows, array $summary): string
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        $this->writeSalesSheet($spreadsheet, $company, $period, $salesRows);
        $purchasesTaxRef = $this->writePurchasesSheet($spreadsheet, $company, $period, $purchaseRows);
        $expensesTaxRef = $this->writeExpensesSheet($spreadsheet, $company, $period, $expenseRows);
        $this->writeSummarySheet($spreadsheet, $company, $period, $summary, $purchasesTaxRef, $expensesTaxRef);

        $spreadsheet->setActiveSheetIndex(0);

        return $this->output($spreadsheet);
    }

    private function writeSummarySheet(Spreadsheet $spreadsheet, Company $company, array $period, array $summary, string $purchasesTaxRef, string $expensesTaxRef): void
    {
        $sheet = $spreadsheet->createSheet(0);
        $sheet->setTitle(__('Summary'));

        $subtitle = __('Period: :from to :to', ['from' => $period['from']->format('d M Y'), 'to' => $period['to']->format('d M Y')]);
        $columns = ['A', 'B'];
        $row = $this->writeDocumentHeader($sheet, $company, __('Tax Report'), $subtitle, $columns);
        $headerRow = $row;

        $revenueColor = $this->colorFor('revenue');
        $liabilityColor = $this->colorFor('liability');

        $sheet->setCellValue('A'.$row, __('Output tax (Sales)'));
        $sheet->mergeCells('A'.$row.':B'.$row);
        $this->styleTypeHeaderRow($sheet, $row, 'A', 'B', $revenueColor);
        $row++;

        $outputRows = [];
        $write = function (string $label, float $value) use ($sheet, &$row, $revenueColor, &$outputRows) {
            $sheet->setCellValue('A'.$row, $label);
            $sheet->setCellValue('B'.$row, $value);
            $this->styleDataRow($sheet, $row, 'A', 'B', $revenueColor['fill']);
            $outputRows[$label] = $row;
            $row++;
        };
        $write(__('VAT on sales invoices (gross)'), (float) $summary['outputTaxFromInvoices']);
        $write(__('+ VAT due on prepayment invoices issued this period'), (float) $summary['prepaymentVatDueThisPeriod']);
        $write(__('− VAT already reported via Advance Credit (earlier period)'), (float) $summary['advanceCreditVatAlreadyReported']);
        $write(__('− VAT already reported via Prepayment Invoice (earlier period)'), (float) $summary['prepaymentVatAppliedThisPeriod']);

        $sheet->setCellValue('A'.$row, __('Net output tax for this period'));
        $sheet->setCellValue('B'.$row, "=B{$outputRows[__('VAT on sales invoices (gross)')]}+B{$outputRows[__('+ VAT due on prepayment invoices issued this period')]}-B{$outputRows[__('− VAT already reported via Advance Credit (earlier period)')]}-B{$outputRows[__('− VAT already reported via Prepayment Invoice (earlier period)')]}");
        $this->styleTotalRow($sheet, $row, 'A', 'B', $revenueColor);
        $netOutputRow = $row;
        $row++;
        $row++; // spacer

        $sheet->setCellValue('A'.$row, __('Input tax (recoverable)'));
        $sheet->mergeCells('A'.$row.':B'.$row);
        $this->styleTypeHeaderRow($sheet, $row, 'A', 'B', $liabilityColor);
        $row++;

        $sheet->setCellValue('A'.$row, __('Input tax — purchases'));
        $sheet->setCellValue('B'.$row, "='{$purchasesTaxRef}");
        $this->styleDataRow($sheet, $row, 'A', 'B', $liabilityColor['fill']);
        $inputPurchasesRow = $row;
        $row++;

        $sheet->setCellValue('A'.$row, __('Input tax — expenses'));
        $sheet->setCellValue('B'.$row, "='{$expensesTaxRef}");
        $this->styleDataRow($sheet, $row, 'A', 'B', $liabilityColor['fill']);
        $inputExpensesRow = $row;
        $row++;

        $sheet->setCellValue('A'.$row, __('Input VAT before apportionment'));
        $sheet->setCellValue('B'.$row, "=B{$inputPurchasesRow}+B{$inputExpensesRow}");
        $this->styleTotalRow($sheet, $row, 'A', 'B', $liabilityColor);
        $inputBeforeApportionmentRow = $row;
        $row++;

        $sheet->setCellValue('A'.$row, __('Non-recoverable input VAT (partial exemption)'));
        $sheet->setCellValue('B'.$row, (float) ($summary['nonRecoverableInputTax'] ?? 0));
        $this->styleDataRow($sheet, $row, 'A', 'B', $liabilityColor['fill']);
        $nonRecoverableRow = $row;
        $row++;

        $sheet->setCellValue('A'.$row, __('Net recoverable input tax'));
        $sheet->setCellValue('B'.$row, "=B{$inputBeforeApportionmentRow}-B{$nonRecoverableRow}");
        $this->styleTotalRow($sheet, $row, 'A', 'B', $liabilityColor);
        $netRecoverableRow = $row;
        $row++;
        $row++; // spacer

        $sheet->setCellValue('A'.$row, __('Net Tax Position'));
        $sheet->setCellValue('B'.$row, "=B{$netOutputRow}-B{$netRecoverableRow}");
        $this->styleGrandTotalRow($sheet, $row, 'A', 'B');
        $row++;

        $this->finishSheet($sheet, $headerRow, ['A' => 48, 'B' => 18]);
    }

    /**
     * @return string the cell reference (SheetName'!CellAddr, without
     *                the leading quote) the Summary sheet should pull
     *                this tab's own "Tax Amount" grand total from.
     */
    private function writeSalesSheet(Spreadsheet $spreadsheet, Company $company, array $period, Collection $rows): string
    {
        $sheet = $spreadsheet->createSheet();
        $title = __('Sales');
        $sheet->setTitle($title);

        $subtitle = __('Period: :from to :to', ['from' => $period['from']->format('d M Y'), 'to' => $period['to']->format('d M Y')]);
        $columns = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
        $row = $this->writeDocumentHeader($sheet, $company, __('Tax Report — Output Tax (Sales)'), $subtitle, $columns);
        $headerRow = $row;

        $sheet->fromArray([__('Date'), __('Reference'), __('Customer'), __('Tax Number'), __('Salesperson'), __('Net Amount Excl. Tax'), __('Discount'), __('Tax Amount'), __('Total Amount'), __('Payment Status')], null, 'A'.$row);
        $this->styleColumnHeaderRow($sheet, $row, 'A', 'J');
        $row++;

        $color = $this->colorFor('revenue');
        $startRow = $row;

        foreach ($rows as $invoice) {
            $sheet->setCellValue('A'.$row, $invoice->issue_date?->format('Y-m-d'));
            $sheet->setCellValue('B'.$row, $invoice->invoice_number);
            $sheet->setCellValue('C'.$row, $invoice->displayClientName());
            $sheet->setCellValue('D'.$row, $invoice->client->vat_number ?? '');
            $sheet->setCellValue('E'.$row, $invoice->salesperson->name ?? '');
            $sheet->setCellValue('F'.$row, (float) $invoice->subtotal);
            $sheet->setCellValue('G'.$row, (float) $invoice->discount_total);
            $sheet->setCellValue('H'.$row, (float) $invoice->vat_total);
            $sheet->setCellValue('I'.$row, (float) $invoice->total);
            $sheet->setCellValue('J'.$row, (float) $invoice->amount_paid >= (float) $invoice->total && (float) $invoice->total > 0 ? __('Paid') : ((float) $invoice->amount_paid > 0 ? __('Partial') : __('Unpaid')));
            $this->styleDataRow($sheet, $row, 'A', 'J', $color['fill']);
            $this->resetTextColumns($sheet, $row, ['B', 'C', 'D', 'E', 'J']);
            $row++;
        }

        $endRow = $row - 1;
        $taxTotalCell = $this->writeTotalsFooter($sheet, $row, $color, $startRow, $endRow, ['F', 'G', 'H', 'I'], 'H');

        $this->finishSheet($sheet, $headerRow, ['A' => 12, 'B' => 14, 'C' => 24, 'D' => 14, 'E' => 16, 'F' => 15, 'G' => 13, 'H' => 13, 'I' => 15, 'J' => 10]);

        return "{$title}'!{$taxTotalCell}";
    }

    private function writePurchasesSheet(Spreadsheet $spreadsheet, Company $company, array $period, Collection $rows): string
    {
        $sheet = $spreadsheet->createSheet();
        $title = __('Purchases');
        $sheet->setTitle($title);

        $subtitle = __('Period: :from to :to', ['from' => $period['from']->format('d M Y'), 'to' => $period['to']->format('d M Y')]);
        $columns = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'];
        $row = $this->writeDocumentHeader($sheet, $company, __('Tax Report — Input Tax (Purchases)'), $subtitle, $columns);
        $headerRow = $row;

        $sheet->fromArray([__('Date'), __('Reference'), __('Supplier'), __('Tax Number'), __('Net Amount Excl. Tax'), __('Discount'), __('Tax Amount'), __('Total Amount'), __('Payment Status')], null, 'A'.$row);
        $this->styleColumnHeaderRow($sheet, $row, 'A', 'I');
        $row++;

        $color = $this->colorFor('liability');
        $startRow = $row;

        foreach ($rows as $bill) {
            $sheet->setCellValue('A'.$row, $bill->bill_date?->format('Y-m-d'));
            $sheet->setCellValue('B'.$row, $bill->bill_number);
            $sheet->setCellValue('C'.$row, $bill->supplier->name ?? '');
            $sheet->setCellValue('D'.$row, $bill->supplier->vat_number ?? '');
            $sheet->setCellValue('E'.$row, (float) $bill->subtotal);
            $sheet->setCellValue('F'.$row, (float) $bill->discount_total);
            $sheet->setCellValue('G'.$row, (float) $bill->vat_total);
            $sheet->setCellValue('H'.$row, (float) $bill->total);
            $sheet->setCellValue('I'.$row, (float) $bill->amount_paid >= (float) $bill->total && (float) $bill->total > 0 ? __('Paid') : ((float) $bill->amount_paid > 0 ? __('Partial') : __('Unpaid')));
            $this->styleDataRow($sheet, $row, 'A', 'I', $color['fill']);
            $this->resetTextColumns($sheet, $row, ['B', 'C', 'D', 'I']);
            $row++;
        }

        $endRow = $row - 1;
        $taxTotalCell = $this->writeTotalsFooter($sheet, $row, $color, $startRow, $endRow, ['E', 'F', 'G', 'H'], 'G');

        $this->finishSheet($sheet, $headerRow, ['A' => 12, 'B' => 14, 'C' => 24, 'D' => 14, 'E' => 15, 'F' => 13, 'G' => 13, 'H' => 15, 'I' => 10]);

        return "{$title}'!{$taxTotalCell}";
    }

    private function writeExpensesSheet(Spreadsheet $spreadsheet, Company $company, array $period, Collection $rows): string
    {
        $sheet = $spreadsheet->createSheet();
        $title = __('Expenses');
        $sheet->setTitle($title);

        $subtitle = __('Period: :from to :to', ['from' => $period['from']->format('d M Y'), 'to' => $period['to']->format('d M Y')]);
        $columns = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
        $row = $this->writeDocumentHeader($sheet, $company, __('Tax Report — Expense Tax'), $subtitle, $columns);
        $headerRow = $row;

        $sheet->fromArray([__('Date'), __('Reference'), __('Vendor'), __('Category'), __('Net Amount Excl. Tax'), __('Tax Amount'), __('Total Amount'), __('Status')], null, 'A'.$row);
        $this->styleColumnHeaderRow($sheet, $row, 'A', 'H');
        $row++;

        $color = $this->colorFor('expense');
        $startRow = $row;

        foreach ($rows as $expense) {
            $sheet->setCellValue('A'.$row, $expense->expense_date?->format('Y-m-d'));
            $sheet->setCellValue('B'.$row, $expense->reference);
            $sheet->setCellValue('C'.$row, $expense->vendor_name);
            $sheet->setCellValue('D'.$row, $expense->category->name ?? '');
            $sheet->setCellValue('E'.$row, (float) $expense->amount);
            $sheet->setCellValue('F'.$row, (float) $expense->vat_amount);
            $sheet->setCellValue('G'.$row, (float) ($expense->gross_amount ?? $expense->amount));
            $sheet->setCellValue('H'.$row, ucfirst((string) $expense->status));
            $this->styleDataRow($sheet, $row, 'A', 'H', $color['fill']);
            $this->resetTextColumns($sheet, $row, ['B', 'C', 'D', 'H']);
            $row++;
        }

        $endRow = $row - 1;
        $taxTotalCell = $this->writeTotalsFooter($sheet, $row, $color, $startRow, $endRow, ['E', 'F', 'G'], 'F');

        $this->finishSheet($sheet, $headerRow, ['A' => 12, 'B' => 14, 'C' => 20, 'D' => 18, 'E' => 15, 'F' => 13, 'G' => 15, 'H' => 10]);

        return "{$title}'!{$taxTotalCell}";
    }

    /**
     * Writes a "Total" row summing each given numeric column over
     * [$startRow, $endRow], and returns the cell address of
     * $taxColumn's total — the "Tax Amount" column the Summary sheet
     * needs, which sits at a different position on each tab (Sales and
     * Purchases both carry a Discount column before it; Expenses does
     * not), so it's named explicitly rather than inferred by position.
     *
     * @param  array<int, string>  $numericColumns
     */
    private function writeTotalsFooter(Worksheet $sheet, int $row, array $color, int $startRow, int $endRow, array $numericColumns, string $taxColumn): string
    {
        $firstCol = $numericColumns[0];
        $sheet->setCellValue('A'.$row, __('Total'));
        $sheet->mergeCells('A'.$row.':'.chr(ord($firstCol) - 1).$row);

        foreach ($numericColumns as $col) {
            $sheet->setCellValue($col.$row, $endRow >= $startRow ? "=SUM({$col}{$startRow}:{$col}{$endRow})" : 0);
        }
        $this->styleTotalRow($sheet, $row, 'A', end($numericColumns), $color);

        return $taxColumn.$row;
    }

    /**
     * styleDataRow right-aligns/money-formats every column after the
     * first — correct for the numeric columns but not for text columns
     * (reference, name, status, ...) interleaved among them on these
     * wider transaction sheets.
     *
     * @param  array<int, string>  $textColumns
     */
    private function resetTextColumns(Worksheet $sheet, int $row, array $textColumns): void
    {
        foreach ($textColumns as $col) {
            $sheet->getStyle($col.$row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle($col.$row)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_GENERAL);
        }
    }
}
