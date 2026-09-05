<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Bill;
use App\Models\BillPayment;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Commercial audit finding: FX_GAINS/FX_LOSSES were mappable accounts in
 * the chart of accounts with no code ever posting to them — every
 * payment converted at the invoice's/bill's own booked exchange_rate, so
 * a real rate movement between issue and settlement was silently
 * absorbed instead of recognized as a gain or loss.
 */
class ForeignExchangeGainLossTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $company = Company::create(['name' => 'FX Co.', 'slug' => 'fx-'.uniqid()]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return $company;
    }

    public function test_a_stronger_settlement_rate_on_an_invoice_payment_posts_a_realized_gain(): void
    {
        $company = $this->makeCompany();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client']);

        // Invoice booked at 3.75 (USD->SAR), 1000 USD total. Paid in full
        // when the rate has strengthened to 3.80 — more SAR was actually
        // received than the AR balance represents, a gain.
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-1',
            'issue_date' => now(), 'due_date' => now()->addDays(30), 'status' => 'sent', 'currency' => 'USD', 'exchange_rate' => 3.75,
            'subtotal' => 1000, 'vat_total' => 0, 'total' => 1000,
        ]);

        $payment = $invoice->invoicePayments()->create(['amount' => 1000, 'exchange_rate' => 3.80, 'paid_at' => now(), 'method' => 'bank_transfer']);

        $entry = app(LedgerPostingService::class)->postInvoicePayment($payment);

        $fxGains = Account::where('company_id', $company->id)->where('code', '9100')->first();
        $ar = Account::where('company_id', $company->id)->where('code', '1200')->first();
        $bank = Account::where('company_id', $company->id)->where('code', '1100')->first();

        $this->assertSame(3800.0, (float) $entry->lines()->where('account_id', $bank->id)->sum('debit'));
        $this->assertSame(3750.0, (float) $entry->lines()->where('account_id', $ar->id)->sum('credit'));
        $this->assertSame(50.0, (float) $entry->lines()->where('account_id', $fxGains->id)->sum('credit'));
    }

    public function test_a_stronger_settlement_rate_on_a_bill_payment_posts_a_realized_loss(): void
    {
        $company = $this->makeCompany();
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Supplier']);

        // Bill booked at 3.75, paid when the rate has strengthened to
        // 3.80 — more SAR had to be paid out than the AP balance
        // represents (the foreign-currency payable got more expensive to
        // settle), a loss.
        $bill = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-1',
            'status' => 'posted', 'bill_date' => now()->toDateString(), 'currency' => 'USD', 'exchange_rate' => 3.75,
            'subtotal' => 1000, 'vat_total' => 0, 'total' => 1000,
        ]);

        $payment = $bill->billPayments()->create(['amount' => 1000, 'exchange_rate' => 3.80, 'paid_at' => now(), 'method' => 'bank_transfer']);

        $entry = app(LedgerPostingService::class)->postBillPayment($payment);

        $fxLosses = Account::where('company_id', $company->id)->where('code', '9200')->first();
        $ap = Account::where('company_id', $company->id)->where('code', '2000')->first();
        $bank = Account::where('company_id', $company->id)->where('code', '1100')->first();

        $this->assertSame(3750.0, (float) $entry->lines()->where('account_id', $ap->id)->sum('debit'));
        $this->assertSame(3800.0, (float) $entry->lines()->where('account_id', $bank->id)->sum('credit'));
        $this->assertSame(50.0, (float) $entry->lines()->where('account_id', $fxLosses->id)->sum('debit'));
    }

    public function test_same_currency_payments_never_produce_an_fx_line(): void
    {
        $company = $this->makeCompany();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client']);

        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-2',
            'issue_date' => now(), 'due_date' => now()->addDays(30), 'status' => 'sent', 'currency' => 'SAR', 'exchange_rate' => 1,
            'subtotal' => 500, 'vat_total' => 0, 'total' => 500,
        ]);

        $payment = $invoice->invoicePayments()->create(['amount' => 500, 'paid_at' => now(), 'method' => 'cash']);

        $entry = app(LedgerPostingService::class)->postInvoicePayment($payment);

        $this->assertCount(2, $entry->lines);
    }
}
