<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Concerns\ResolvesReportPeriod;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Setting;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    use ResolvesReportPeriod;

    /**
     * A single operator company can run two revenue streams under one CR/
     * VAT registration: its own real business, and Daftari subscription
     * resale (billed through PlatformInvoiceService into whichever company
     * Setting 'platform_billing_company_id' names). Both streams post real
     * output VAT to the same ZATCA registration, so the figure that must go
     * on the actual GAZT VAT return is the combined total — never either
     * stream alone. The split shown here is for internal bookkeeping only
     * (e.g. reconciling subscription revenue against Daftari's own books),
     * identified via Client::platform_billed_company_id rather than the
     * invoice_number prefix, which is admin-configurable and could change.
     */
    public function vat(Request $request)
    {
        $companies = Company::orderBy('name')->get(['id', 'name', 'vat_number']);
        $companyId = $request->filled('company_id')
            ? (int) $request->query('company_id')
            : (int) Setting::get('platform_billing_company_id');
        $company = $companyId ? $companies->firstWhere('id', $companyId) : null;
        $period = $this->resolvePeriod($request);

        if (! $company) {
            return view('admin.reports.vat', [
                'companies' => $companies,
                'company' => null,
                'period' => $period,
                'invoices' => null,
            ]);
        }

        $baseQuery = fn () => Invoice::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereBetween('issue_date', [$period['from'], $period['to']])
            ->whereNotIn('status', ['draft', 'cancelled']);

        $combinedOutputVat = (float) $baseQuery()->sum('vat_total');
        $combinedNetSales = (float) $baseQuery()->sum('subtotal');

        $subscriptionQuery = fn () => $baseQuery()->whereHas('client', fn ($q) => $q->whereNotNull('platform_billed_company_id'));
        $subscriptionOutputVat = (float) $subscriptionQuery()->sum('vat_total');
        $subscriptionNetSales = (float) $subscriptionQuery()->sum('subtotal');

        $invoices = $baseQuery()
            ->with('client')
            ->orderByDesc('issue_date')
            ->paginate(25)
            ->withQueryString();

        return view('admin.reports.vat', [
            'companies' => $companies,
            'company' => $company,
            'period' => $period,
            'invoices' => $invoices,
            'combinedOutputVat' => $combinedOutputVat,
            'combinedNetSales' => $combinedNetSales,
            'ownOutputVat' => $combinedOutputVat - $subscriptionOutputVat,
            'ownNetSales' => $combinedNetSales - $subscriptionNetSales,
            'subscriptionOutputVat' => $subscriptionOutputVat,
            'subscriptionNetSales' => $subscriptionNetSales,
        ]);
    }
}
