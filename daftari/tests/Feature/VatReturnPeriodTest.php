<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Client;
use App\Models\Company;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VatReturnPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "we have submit vat returns for 2 quarters and third we have paid now
 * so we have already some advance vat ... how to tackle this" — the
 * on-demand VAT report has no memory between periods, so an input-VAT
 * credit (input exceeding output, a refundable position) had nowhere to
 * be carried forward. A VatReturnPeriod record freezes one period's
 * figures and chains credit_brought_forward/credit_carried_forward so
 * each quarter starts from where the last one left off.
 */
class VatReturnPeriodTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(bool $withFeature = true): Company
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000,
            'is_active' => true, 'has_vat_return_report' => $withFeature,
        ]);
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Subscription::create([
            'company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active',
            'starts_at' => now()->subDay(), 'ends_at' => now()->addYear(),
        ]);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    private function makeInvoice(Company $company, float $netAmount, float $vat, string $date): Invoice
    {
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client '.uniqid()]);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-'.uniqid(),
            'issue_date' => $date, 'due_date' => now()->addDays(30), 'status' => 'sent', 'currency' => 'SAR',
        ]);
        $invoice->items()->create(['description' => 'Item', 'quantity' => 1, 'unit_price' => $netAmount, 'vat_rate' => 15, 'vat_amount' => $vat, 'line_total' => $netAmount + $vat]);
        $invoice->recalculateTotals();
        $invoice->save();

        return $invoice;
    }

    private function makeBill(Company $company, float $netAmount, float $vat, string $date): Bill
    {
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Supplier '.uniqid()]);
        $bill = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-'.uniqid(),
            'bill_date' => $date, 'due_date' => now()->addDays(30), 'status' => 'posted', 'currency' => 'SAR',
        ]);
        $bill->items()->create(['description' => 'Purchase', 'quantity' => 1, 'unit_price' => $netAmount, 'vat_rate' => 15, 'vat_amount' => $vat, 'line_total' => $netAmount + $vat]);
        $bill->recalculateTotals();
        $bill->save();

        return $bill;
    }

    public function test_recording_a_period_with_output_exceeding_input_computes_an_amount_payable(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $this->makeInvoice($company, 10000, 1500, '2026-01-15');
        $this->makeBill($company, 2000, 300, '2026-01-10');

        $response = $this->actingAs($owner)->post(route('app.reports.vat-returns.store'), [
            'period_start' => '2026-01-01', 'period_end' => '2026-03-31', 'credit_brought_forward' => 0,
        ]);

        $response->assertSessionDoesntHaveErrors()->assertRedirect();
        $period = VatReturnPeriod::first();
        $this->assertEqualsWithDelta(1500, $period->output_tax, 0.01);
        $this->assertEqualsWithDelta(300, $period->net_recoverable_input_tax, 0.01);
        $this->assertEqualsWithDelta(1200, $period->amount_payable, 0.01);
        $this->assertEqualsWithDelta(0, $period->credit_carried_forward, 0.01);
    }

    /**
     * The exact scenario described: input VAT (a purchase of 50,400 gross,
     * i.e. 6,574 VAT at 15%, approximated here as a round input tax figure
     * for clarity) exceeds output VAT for the period — a refundable
     * credit, not a loss — and it must carry forward rather than vanish.
     */
    public function test_input_vat_exceeding_output_vat_carries_the_credit_forward_instead_of_a_negative_payable(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $this->makeInvoice($company, 1000, 150, '2026-01-15');
        $this->makeBill($company, 10000, 1500, '2026-01-10');

        $this->actingAs($owner)->post(route('app.reports.vat-returns.store'), [
            'period_start' => '2026-01-01', 'period_end' => '2026-03-31', 'credit_brought_forward' => 0,
        ]);

        $period = VatReturnPeriod::first();
        $this->assertEqualsWithDelta(0, $period->amount_payable, 0.01);
        // 150 output - 1500 input = -1350, carried forward as a credit.
        $this->assertEqualsWithDelta(1350, $period->credit_carried_forward, 0.01);
    }

    public function test_the_next_periods_create_form_auto_fills_the_prior_periods_carried_forward_credit(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        VatReturnPeriod::create([
            'company_id' => $company->id, 'period_start' => '2026-01-01', 'period_end' => '2026-03-31',
            'output_tax' => 150, 'input_tax_purchases' => 1500, 'expense_tax' => 0, 'net_recoverable_input_tax' => 1500,
            'credit_brought_forward' => 0, 'amount_payable' => 0, 'credit_carried_forward' => 1350,
        ]);

        $response = $this->actingAs($owner)->get(route('app.reports.vat-returns.create', ['period_start' => '2026-04-01', 'period_end' => '2026-06-30']));

        $response->assertOk();
        $response->assertSee('value="1350.00"', false);
    }

    public function test_a_second_quarter_nets_the_brought_forward_credit_against_its_own_payable(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        // Q2 on its own: 800 output - 300 input = 500 payable, but a 1,350
        // credit from Q1 more than covers it, so nothing is due and 850
        // carries forward again.
        $this->makeInvoice($company, 5000, 800, '2026-04-15');
        $this->makeBill($company, 2000, 300, '2026-04-10');

        $response = $this->actingAs($owner)->post(route('app.reports.vat-returns.store'), [
            'period_start' => '2026-04-01', 'period_end' => '2026-06-30', 'credit_brought_forward' => 1350,
        ]);

        $response->assertSessionDoesntHaveErrors();
        $period = VatReturnPeriod::first();
        $this->assertEqualsWithDelta(0, $period->amount_payable, 0.01);
        $this->assertEqualsWithDelta(850, $period->credit_carried_forward, 0.01);
    }

    public function test_the_show_page_displays_the_recorded_figures(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $period = VatReturnPeriod::create([
            'company_id' => $company->id, 'created_by' => $owner->id, 'period_start' => '2026-01-01', 'period_end' => '2026-03-31',
            'output_tax' => 1500, 'input_tax_purchases' => 300, 'expense_tax' => 0, 'net_recoverable_input_tax' => 300,
            'credit_brought_forward' => 0, 'amount_payable' => 1200, 'credit_carried_forward' => 0,
        ]);

        $response = $this->actingAs($owner)->get(route('app.reports.vat-returns.show', $period));

        $response->assertOk();
        $response->assertSee(\App\Support\Money::format(1200));
    }

    public function test_a_company_without_the_feature_is_blocked(): void
    {
        $company = $this->makeCompany(withFeature: false);
        $owner = $this->makeOwner($company);

        $this->actingAs($owner)->get(route('app.reports.vat-returns.index'))->assertRedirect(route('app.dashboard'));
        $this->actingAs($owner)->get(route('app.reports.vat-returns.create'))->assertRedirect(route('app.dashboard'));
    }
}
