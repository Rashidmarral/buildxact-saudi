<?php

namespace App\Services\Billing;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\TaxRate;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Turns a subscription Payment into a real ZATCA-compliant tax invoice,
 * by billing it through whichever of the operator's own tenant Company
 * records they've designated (Setting 'platform_billing_company_id',
 * set from Admin -> Platform Settings -> Identity) as their real
 * business. This is deliberately never hard-coded to a specific company
 * — this codebase ships to many buyers, each with their own operator
 * company — and is entirely inert (returns null, no rows written) until
 * a super admin explicitly configures it, so every existing install's
 * current plain-receipt behavior is unaffected by default.
 *
 * The invoice is created, sent, and paid in one pass through the exact
 * same Invoice/InvoiceItem/LedgerPostingService pipeline a tenant's own
 * invoices use — the whole point being that this is one real invoice in
 * the billing company's own books, not a parallel document format, so
 * it automatically gets ZATCA Phase 1 QR codes today and Phase 2 signed/
 * cleared submission the moment that company completes its own ZATCA
 * onboarding.
 */
class PlatformInvoiceService
{
    public function __construct(private readonly LedgerPostingService $ledger) {}

    public function createInvoiceForPayment(Payment $payment): ?Invoice
    {
        $billingCompany = $this->billingCompany();

        if (! $billingCompany) {
            return null;
        }

        $payingCompany = $payment->company;

        if (! $payingCompany || $payingCompany->id === $billingCompany->id) {
            // Never bill the operator's own company for its own
            // subscription — this can only happen if a super admin
            // designated their own demo/testing usage of the platform.
            return null;
        }

        return DB::transaction(function () use ($billingCompany, $payingCompany, $payment) {
            $client = $this->findOrCreateClient($billingCompany, $payingCompany);

            $payment->loadMissing('plan');
            $planName = $payment->plan?->name ?? __('Subscription');
            $cycleLabel = $payment->subscription?->billing_cycle === 'yearly' ? __('Annual') : __('Monthly');

            $vatRate = TaxRate::defaultRate($billingCompany->id) ?: 15.0;
            $total = round((float) $payment->amount, 2);
            // Prices are VAT-inclusive: derive the VAT amount from the
            // amount actually charged, then back into the VAT-exclusive
            // unit price as the remainder — rather than dividing and
            // rounding both pieces independently — so unit_price +
            // vat_amount always equals $total exactly, with no stray
            // cent of drift against what the customer was actually
            // charged and what InvoicePayment records as received.
            $vatAmount = round($total - ($total / (1 + $vatRate / 100)), 2);
            $unitPrice = round($total - $vatAmount, 2);

            $invoice = Invoice::create([
                'company_id' => $billingCompany->id,
                'client_id' => $client->id,
                'invoice_number' => $billingCompany->nextInvoiceNumber(),
                'type' => 'standard',
                'status' => 'draft',
                'issue_date' => ($payment->paid_at ?? now())->toDateString(),
                'currency' => $billingCompany->currency,
                'exchange_rate' => 1,
                'notes' => __('Subscription payment reference: :reference', ['reference' => $payment->reference ?? $payment->id]),
            ]);

            $item = new InvoiceItem([
                'invoice_id' => $invoice->id,
                'description' => __(':plan subscription (:cycle)', ['plan' => $planName, 'cycle' => $cycleLabel]),
                'quantity' => 1,
                'unit_price' => $unitPrice,
                'vat_rate' => $vatRate,
                'sort_order' => 0,
            ]);
            $item->recalculate();
            $item->save();

            $invoice->recalculateTotals();

            $invoice->update(['status' => 'sent']);
            $this->ledger->postInvoiceIssued($invoice);

            $invoicePayment = $invoice->invoicePayments()->create([
                'amount' => $total,
                'paid_at' => $payment->paid_at ?? now(),
                'method' => 'bank_transfer',
                'reference' => $payment->reference,
            ]);
            $invoice->amount_paid = $invoice->invoicePayments()->sum('amount');
            $invoice->status = $invoice->isFullyPaid() ? 'paid' : 'partially_paid';
            $invoice->save();
            $this->ledger->postInvoicePayment($invoicePayment);

            return $invoice;
        });
    }

    private function billingCompany(): ?Company
    {
        $companyId = Setting::get('platform_billing_company_id');

        if (! $companyId) {
            return null;
        }

        $company = Company::withoutGlobalScopes()->find($companyId);

        if (! $company) {
            Log::warning('platform_billing_company_id is set but no matching company exists', ['company_id' => $companyId]);
        }

        return $company;
    }

    /**
     * One Client per paying tenant, reused across every renewal payment
     * — matched on platform_billed_company_id rather than name/email/VAT
     * (all of which can change or be absent), since it's a plain column
     * added for exactly this lookup.
     */
    private function findOrCreateClient(Company $billingCompany, Company $payingCompany): Client
    {
        $client = Client::withoutGlobalScopes()
            ->where('company_id', $billingCompany->id)
            ->where('platform_billed_company_id', $payingCompany->id)
            ->first();

        if ($client) {
            return $client;
        }

        $ownerEmail = $payingCompany->owners()->value('email');

        return Client::create([
            'company_id' => $billingCompany->id,
            'platform_billed_company_id' => $payingCompany->id,
            'type' => 'company',
            'name' => $payingCompany->name,
            'email' => $ownerEmail,
            'is_vat_registered' => (bool) $payingCompany->vat_number,
            'vat_number' => $payingCompany->vat_number,
            'cr_number' => $payingCompany->cr_number,
        ]);
    }
}
