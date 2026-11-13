<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\Bill;
use App\Models\User;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Optional polish (task #136): a plain Income/Expenses/Net-Profit
 * "profit-loss" report distinct from the formal Income Statement; a
 * traditional two-sided ledger T-account alternate layout for it; and
 * a "Record VAT payment" quick-link on the Tax Report that deep-links
 * into the existing Payment Voucher flow (not a parallel, ungoverned
 * payment form) with the VAT Output account and net amount already
 * filled in.
 */
class ReportOptionalPolishTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompanyAndOwner(): array
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        return [$company, $owner];
    }

    public function test_the_simple_profit_loss_report_shows_income_expenses_and_net_profit(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'SAED EST']);
        $bill = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-1',
            'bill_date' => now(), 'status' => 'posted', 'currency' => 'SAR', 'subtotal' => 4000, 'vat_total' => 0, 'total' => 4000,
        ]);
        app(LedgerPostingService::class)->postBillPosted($bill->fresh());

        $page = $this->actingAs($owner)->get(route('app.reports.profit-loss'));
        $page->assertOk()->assertSee('Operating Expenses')->assertSee('Total expenses');

        $csv = $this->actingAs($owner)->get(route('app.reports.profit-loss', ['export' => 'csv']));
        $csv->assertOk();

        $pdf = $this->actingAs($owner)->get(route('app.reports.profit-loss', ['export' => 'pdf']));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_the_traditional_t_account_layout_balances_both_sides(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $client = \App\Models\Client::create(['company_id' => $company->id, 'name' => 'Al Jeul Construction']);
        $invoice = \App\Models\Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-1',
            'issue_date' => now(), 'status' => 'sent', 'currency' => 'SAR',
            'subtotal' => 10000, 'vat_total' => 1500, 'total' => 11500, 'amount_paid' => 0,
        ]);
        app(LedgerPostingService::class)->postInvoiceIssued($invoice->fresh());

        $page = $this->actingAs($owner)->get(route('app.reports.income-statement-traditional'));
        $page->assertOk()->assertSee('Dr (Expenses)')->assertSee('Cr (Revenue)')->assertSee('Net Profit c/d');
    }

    public function test_vat_report_shows_a_record_payment_link_prefilled_with_the_vat_output_account_and_amount_when_payable(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $client = \App\Models\Client::create(['company_id' => $company->id, 'name' => 'Al Jeul Construction']);
        $invoice = \App\Models\Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-1',
            'issue_date' => now(), 'status' => 'sent', 'currency' => 'SAR',
            'subtotal' => 10000, 'vat_total' => 1500, 'total' => 11500, 'amount_paid' => 0,
        ]);
        app(LedgerPostingService::class)->postInvoiceIssued($invoice->fresh());

        $vatOutput = \App\Models\AccountMapping::resolve($company->id, 'VAT_OUTPUT');

        $page = $this->actingAs($owner)->get(route('app.reports.vat'));
        $page->assertOk()
            ->assertSee('Record VAT payment')
            ->assertSee('counter_account_id='.$vatOutput->id, false)
            ->assertSee('amount=1500.00', false)
            ->assertSee('payee_name=ZATCA', false);
    }

    public function test_the_prefilled_link_actually_fills_in_the_payment_voucher_form(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $vatOutput = \App\Models\AccountMapping::resolve($company->id, 'VAT_OUTPUT');

        $page = $this->actingAs($owner)->get(route('app.payment-vouchers.create', [
            'counter_account_id' => $vatOutput->id,
            'amount' => '1500.00',
            'payee_name' => 'ZATCA',
            'notes' => 'VAT payment for 2026-01-01 to 2026-01-31',
        ]));
        $page->assertOk()
            ->assertSee('value="1500.00"', false)
            ->assertSee('value="ZATCA"', false)
            ->assertSee('VAT payment for 2026-01-01 to 2026-01-31');
    }
}
