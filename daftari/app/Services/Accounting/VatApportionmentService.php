<?php

namespace App\Services\Accounting;

use App\Models\Company;
use App\Models\InvoiceItem;
use App\Models\TaxRate;
use Illuminate\Support\Collection;

/**
 * Audit finding MEDIUM-11: input VAT on bills/expenses was always treated
 * as 100% recoverable, even for a company that also makes VAT-exempt
 * supplies — where Saudi VAT law (the standard proportional-recovery
 * method) only allows recovering a ratio of general input VAT equal to
 * taxable sales ÷ total sales. Off by default (matching every company
 * that only makes taxable/zero-rated supplies, unaffected); a company
 * opts in via Settings > Tax rates.
 *
 * Extracted out of ReportController so the same apportionment math is
 * shared by the on-demand VAT report (Reports > VAT) and the persisted
 * VAT Return periods (Reports > VAT Return Filings) — both need to turn
 * "input VAT before apportionment" into "net recoverable input VAT" the
 * same way, for whatever set of sales rows and input VAT total they were
 * each computed over.
 */
class VatApportionmentService
{
    public function calculate(Company $company, Collection $salesRows, float $inputVatBeforeApportionment): array
    {
        $inputVatBeforeApportionment = round($inputVatBeforeApportionment, 2);

        if (! $company->vat_makes_exempt_supplies) {
            return [
                'vatApportionmentEnabled' => false,
                'exemptOutputSales' => 0.0,
                'taxableOutputSales' => (float) $salesRows->sum('subtotal'),
                'recoveryPercentage' => 100.0,
                'inputVatBeforeApportionment' => $inputVatBeforeApportionment,
                'nonRecoverableInputTax' => 0.0,
                'netRecoverableInputTax' => $inputVatBeforeApportionment,
            ];
        }

        $exemptOutputSales = (float) InvoiceItem::whereIn('invoice_id', $salesRows->pluck('id'))
            ->whereHas('taxRate', fn ($q) => $q->where('type', TaxRate::TYPE_EXEMPT))
            ->sum('line_total');

        $taxableOutputSales = max(0, (float) $salesRows->sum('subtotal') - $exemptOutputSales);
        $totalOutputSales = $taxableOutputSales + $exemptOutputSales;

        $recoveryPercentage = $company->vat_recovery_percentage !== null
            ? (float) $company->vat_recovery_percentage
            : ($totalOutputSales > 0 ? round(($taxableOutputSales / $totalOutputSales) * 100, 2) : 100.0);

        $netRecoverableInputTax = round($inputVatBeforeApportionment * ($recoveryPercentage / 100), 2);

        return [
            'vatApportionmentEnabled' => true,
            'exemptOutputSales' => $exemptOutputSales,
            'taxableOutputSales' => $taxableOutputSales,
            'recoveryPercentage' => $recoveryPercentage,
            'inputVatBeforeApportionment' => $inputVatBeforeApportionment,
            'nonRecoverableInputTax' => round($inputVatBeforeApportionment - $netRecoverableInputTax, 2),
            'netRecoverableInputTax' => $netRecoverableInputTax,
        ];
    }
}
