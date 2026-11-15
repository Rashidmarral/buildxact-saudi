<?php

namespace App\Services\Reports;

use App\Models\Company;
use App\Services\Reports\Excel\Concerns\BuildsAccountingWorkbook;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Balance Sheet → real .xlsx with functional formulas, same treatment
 * as Trial Balance. Covers all three variants the web/PDF pages offer:
 * the plain 3-section view (Assets / Liabilities / Equity, each
 * account listed under its category), and the two-sided "T-account"
 * view shared by -detailed (account-level) and -summary (category-level
 * only).
 */
class BalanceSheetExcelExporter
{
    use BuildsAccountingWorkbook;

    /**
     * @param  array{assets: Collection, liabilities: Collection, equity: Collection, totalAssets: float, totalLiabilities: float, totalEquity: float, balanced: bool, priorTotalAssets?: float, priorTotalLiabilities?: float, priorTotalEquity?: float}  $data
     */
    public function plain(Company $company, Carbon $asOf, ?Carbon $priorAsOf, array $data): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(__('Balance Sheet'));

        $hasPrior = $priorAsOf !== null;
        $subtitle = __('As of :date', ['date' => $asOf->format('d M Y')]).($hasPrior ? ' — '.__('compared to :date', ['date' => $priorAsOf->format('d M Y')]) : '');
        $columns = $hasPrior ? ['A', 'B', 'C'] : ['A', 'B'];
        $row = $this->writeDocumentHeader($sheet, $company, __('Balance Sheet'), $subtitle, $columns);

        $headerRow = $row;
        $headerLabels = $hasPrior ? [__('Account'), __('Balance'), __('Prior')] : [__('Account'), __('Balance')];
        $sheet->fromArray($headerLabels, null, 'A'.$row);
        $this->styleColumnHeaderRow($sheet, $row, 'A', end($columns));
        $row++;

        $sections = [
            'asset' => ['label' => __('Assets'), 'rows' => $data['assets']],
            'liability' => ['label' => __('Liabilities'), 'rows' => $data['liabilities']],
            'equity' => ['label' => __('Equity'), 'rows' => $data['equity']],
        ];

        $typeTotalRows = [];

        foreach ($sections as $typeKey => $section) {
            $color = $this->colorFor($typeKey);

            $sheet->setCellValue('A'.$row, $section['label']);
            $this->styleTypeHeaderRow($sheet, $row, 'A', end($columns), $color);
            $row++;

            $categories = $this->groupByCategory($section['rows']);
            $categorySubtotalRows = [];

            foreach ($categories as $categoryLabel => $categoryRows) {
                $sheet->setCellValue('A'.$row, $categoryLabel);
                $this->styleCategoryLabelRow($sheet, $row, 'A', end($columns), $color);
                $row++;

                $accountStartRow = $row;

                foreach ($categoryRows as $r) {
                    $sheet->setCellValue('A'.$row, $r['account']?->label() ?? $r['label']);
                    $sheet->setCellValue('B'.$row, (float) $r['balance']);
                    if ($hasPrior) {
                        $sheet->setCellValue('C'.$row, (float) ($r['priorAmount'] ?? 0));
                    }
                    $this->styleDataRow($sheet, $row, 'A', end($columns), $color['fill']);
                    $row++;
                }

                $accountEndRow = $row - 1;

                $sheet->setCellValue('A'.$row, __('Subtotal — :category', ['category' => $categoryLabel]));
                foreach (array_slice($columns, 1) as $col) {
                    $sheet->setCellValue($col.$row, $accountEndRow >= $accountStartRow ? "=SUM({$col}{$accountStartRow}:{$col}{$accountEndRow})" : 0);
                }
                $this->styleTotalRow($sheet, $row, 'A', end($columns), $color);
                $categorySubtotalRows[] = $row;
                $row++;
            }

            $sheet->setCellValue('A'.$row, __('Total of :type', ['type' => $section['label']]));
            foreach (array_slice($columns, 1) as $col) {
                $sheet->setCellValue($col.$row, $categorySubtotalRows === []
                    ? 0
                    : '='.implode('+', array_map(fn ($r) => $col.$r, $categorySubtotalRows)));
            }
            $this->styleTotalRow($sheet, $row, 'A', end($columns), $color);
            $typeTotalRows[$typeKey] = $row;
            $row++;
        }

        [$row] = $this->writeBalanceCheck($sheet, $row, $columns, $typeTotalRows['asset'], $typeTotalRows['liability'], $typeTotalRows['equity']);

        $this->finishSheet($sheet, $headerRow, $hasPrior ? ['A' => 36, 'B' => 18, 'C' => 18] : ['A' => 36, 'B' => 18]);

        return $this->output($spreadsheet);
    }

    public function twoSided(Company $company, Carbon $asOf, Collection $assetCategories, Collection $liabilityCategories, Collection $equityCategories, bool $detailed): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $title = $detailed ? __('Balance Sheet (Detailed)') : __('Balance Sheet (Summary)');
        $sheet->setTitle($title);

        $subtitle = __('As of :date', ['date' => $asOf->format('d M Y')]);
        $columns = $detailed ? ['A', 'B', 'C', 'D'] : ['A', 'B'];
        $row = $this->writeDocumentHeader($sheet, $company, $title, $subtitle, $columns);

        $headerRow = $row;
        $headerLabels = $detailed ? [__('Code'), __('Account'), '', __('Amount')] : [__('Category'), __('Amount')];
        $sheet->fromArray($headerLabels, null, 'A'.$row);
        $this->styleColumnHeaderRow($sheet, $row, 'A', end($columns));
        $row++;

        $amountColumn = end($columns);

        // Hamly-parity: Capital (equity) is listed before Liabilities,
        // matching the reference report's right-hand column order —
        // Owner's Equity and the Profit & loss account breakdown read
        // before Accounts Payable and the other true liabilities.
        $sections = [
            'asset' => ['label' => __('Assets'), 'categories' => $assetCategories],
            'equity' => ['label' => __('Capital'), 'categories' => $equityCategories],
            'liability' => ['label' => __('Liabilities'), 'categories' => $liabilityCategories],
        ];

        $typeTotalRows = [];

        foreach ($sections as $typeKey => $section) {
            $color = $this->colorFor($typeKey);

            $sheet->setCellValue('A'.$row, $section['label']);
            $this->styleTypeHeaderRow($sheet, $row, 'A', end($columns), $color);
            $row++;

            $categorySubtotalRows = [];

            foreach ($section['categories'] as $categoryLabel => $category) {
                if ($detailed) {
                    $sheet->setCellValue('A'.$row, $categoryLabel);
                    $this->styleCategoryLabelRow($sheet, $row, 'A', end($columns), $color);
                    $row++;

                    $accountStartRow = $row;

                    foreach ($category['rows'] as $r) {
                        $sheet->setCellValue('A'.$row, $r['account']?->code ?? '');
                        $sheet->setCellValue('B'.$row, $r['account']?->label() ?? $r['label']);
                        $sheet->setCellValue($amountColumn.$row, (float) $r['balance']);
                        $this->styleDataRow($sheet, $row, 'A', end($columns), $color['fill']);
                        $row++;
                    }

                    $accountEndRow = $row - 1;

                    $sheet->setCellValue('A'.$row, __('Subtotal — :category', ['category' => $categoryLabel]));
                    $sheet->mergeCells('A'.$row.':'.chr(ord($amountColumn) - 1).$row);
                    $sheet->setCellValue($amountColumn.$row, $accountEndRow >= $accountStartRow ? "=SUM({$amountColumn}{$accountStartRow}:{$amountColumn}{$accountEndRow})" : 0);
                    $this->styleTotalRow($sheet, $row, 'A', end($columns), $color);
                    $categorySubtotalRows[] = $row;
                    $row++;
                } else {
                    $sheet->setCellValue('A'.$row, $categoryLabel);
                    $sheet->setCellValue('B'.$row, (float) $category['total']);
                    $this->styleDataRow($sheet, $row, 'A', end($columns), $color['fill']);
                    $categorySubtotalRows[] = $row;
                    $row++;
                }
            }

            $sheet->setCellValue('A'.$row, __('Total of :type', ['type' => $section['label']]));
            if ($detailed) {
                $sheet->mergeCells('A'.$row.':'.chr(ord($amountColumn) - 1).$row);
            }
            $sheet->setCellValue($amountColumn.$row, $categorySubtotalRows === []
                ? 0
                : '='.implode('+', array_map(fn ($r) => $amountColumn.$r, $categorySubtotalRows)));
            $this->styleTotalRow($sheet, $row, 'A', end($columns), $color);
            $typeTotalRows[$typeKey] = $row;
            $row++;
        }

        [$row] = $this->writeBalanceCheck($sheet, $row, $columns, $typeTotalRows['asset'], $typeTotalRows['liability'], $typeTotalRows['equity'], $detailed);

        $this->finishSheet($sheet, $headerRow, $detailed ? ['A' => 10, 'B' => 30, 'C' => 4, 'D' => 18] : ['A' => 36, 'B' => 18]);

        return $this->output($spreadsheet);
    }

    /**
     * @return Collection<string, Collection>
     */
    private function groupByCategory(Collection $rows): Collection
    {
        return $rows->groupBy(fn ($r) => $r['category'] ?? $r['account']?->category ?? ($r['label'] ?? __('Other')));
    }

    /**
     * "Total assets", "Total liabilities + equity", and the same
     * self-check line every real balance sheet ends with — as formulas
     * referencing the type-total rows already written above, so it
     * stays correct if any figure above it is edited.
     *
     * @return array{0: int} the next free row after what this wrote
     */
    private function writeBalanceCheck(Worksheet $sheet, int $row, array $columns, int $assetTotalRow, int $liabilityTotalRow, int $equityTotalRow, bool $mergeLabel = false): array
    {
        $amountColumn = end($columns);
        $labelEndColumn = $mergeLabel ? chr(ord($amountColumn) - 1) : 'A';

        $row++; // blank spacer

        $sheet->setCellValue('A'.$row, __('Total assets'));
        if ($mergeLabel) {
            $sheet->mergeCells('A'.$row.':'.$labelEndColumn.$row);
        }
        $sheet->setCellValue($amountColumn.$row, "={$amountColumn}{$assetTotalRow}");
        $this->styleGrandTotalRow($sheet, $row, 'A', $amountColumn);
        $assetsRow = $row;
        $row++;

        $sheet->setCellValue('A'.$row, __('Total liabilities + equity'));
        if ($mergeLabel) {
            $sheet->mergeCells('A'.$row.':'.$labelEndColumn.$row);
        }
        $sheet->setCellValue($amountColumn.$row, "={$amountColumn}{$liabilityTotalRow}+{$amountColumn}{$equityTotalRow}");
        $this->styleGrandTotalRow($sheet, $row, 'A', $amountColumn);
        $liabEquityRow = $row;
        $row++;

        $sheet->setCellValue('A'.$row, __('Balance check'));
        if ($mergeLabel) {
            $sheet->mergeCells('A'.$row.':'.$labelEndColumn.$row);
        }
        $sheet->setCellValue($amountColumn.$row, "=IF(ROUND({$amountColumn}{$assetsRow}-{$amountColumn}{$liabEquityRow},2)=0,\"".__('Balanced').'","'.__('NOT BALANCED').'")');
        $sheet->getStyle('A'.$row.':'.$amountColumn.$row)->getFont()->setBold(true)->setItalic(true)->setSize(9.5);
        $row++;

        return [$row];
    }
}
