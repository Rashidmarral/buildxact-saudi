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
use App\Models\TaxRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit finding MEDIUM-11: input VAT on bills/expenses was always treated
 * as 100% recoverable, even for a company that also makes VAT-exempt
 * supplies — where Saudi VAT law (the standard proportional-recovery
 * method) only allows recovering a ratio of general input VAT equal to
 * taxable sales ÷ total sales. Off by default (matching every company
 * that only makes taxable/zero-rated supplies, unaffected); a company
 * opts in via Settings > Tax rates, and the VAT report then apportions
 * the period's Purchases + Expenses input VAT accordingly.
 */
class VatApportionmentTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompanyWithAccess(bool $makesExemptSupplies = false, ?float $recoveryOverride = null): Company
    {
        $company = Company::create([
            'name' => 'Apportionment Co.', 'slug' => 'apportionment-'.uniqid(),
            'vat_makes_exempt_supplies' => $makesExemptSupplies,
            'vat_recovery_percentage' => $recoveryOverride,
        ]);
        TaxRate::seedDefaults($company->id);
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000,
            'is_active' => true, 'has_vat_return_report' => true,
        ]);
        Subscription::create([
            'company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active',
            'starts_at' => now()->subDay(), 'ends_at' => now()->addYear(),
        ]);

        return $company;
    }

    private function periodQuery(): array
    {
        return ['period' => 'custom', 'from' => now()->subYear()->toDateString(), 'to' => now()->addYear()->toDateString()];
    }

    public function test_apportionment_is_off_by_default_and_all_input_vat_stays_recoverable(): void
    {
        $company = $this->makeCompanyWithAccess(makesExemptSupplies: false);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $client = Client::create(['company_id' => $company->id, 'name' => 'Client A']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-1',
            'issue_date' => now(), 'due_date' => now()->addDays(30), 'status' => 'sent', 'currency' => 'SAR',
        ]);
        $invoice->items()->create(['description' => 'Item', 'quantity' => 1, 'unit_price' => 1000, 'vat_rate' => 15, 'vat_amount' => 150, 'line_total' => 1150]);
        $invoice->recalculateTotals();
        $invoice->save();

        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Supplier A']);
        $bill = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-1',
            'bill_date' => now(), 'due_date' => now()->addDays(30), 'status' => 'posted', 'currency' => 'SAR',
        ]);
        $bill->items()->create(['description' => 'Purchase', 'quantity' => 1, 'unit_price' => 500, 'vat_rate' => 15, 'vat_amount' => 75, 'line_total' => 575]);
        $bill->recalculateTotals();
        $bill->save();

        $response = $this->actingAs($owner)->get(route('app.reports.vat', $this->periodQuery() + ['tab' => 'sales']));

        $response->assertOk();
        $response->assertDontSee(__('Input VAT apportionment (partial exemption)'));
        $response->assertSee('75.00'); // full input VAT still recoverable
    }

    public function test_a_company_making_exempt_supplies_gets_input_vat_apportioned_by_the_auto_computed_ratio(): void
    {
        $company = $this->makeCompanyWithAccess(makesExemptSupplies: true);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $exemptRate = TaxRate::where('company_id', $company->id)->where('type', TaxRate::TYPE_EXEMPT)->first();

        $client = Client::create(['company_id' => $company->id, 'name' => 'Client A']);

        // Taxable sale: 800 net, 15% VAT.
        $taxableInvoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-TAX-1',
            'issue_date' => now(), 'due_date' => now()->addDays(30), 'status' => 'sent', 'currency' => 'SAR',
        ]);
        $taxableInvoice->items()->create(['description' => 'Taxable', 'quantity' => 1, 'unit_price' => 800, 'vat_rate' => 15, 'vat_amount' => 120, 'line_total' => 920]);
        $taxableInvoice->recalculateTotals();
        $taxableInvoice->save();

        // Exempt sale: 200 net, 0% VAT — taxable ratio should be 800/1000 = 80%.
        $exemptInvoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-EXEMPT-1',
            'issue_date' => now(), 'due_date' => now()->addDays(30), 'status' => 'sent', 'currency' => 'SAR',
        ]);
        $exemptInvoice->items()->create(['description' => 'Exempt', 'quantity' => 1, 'unit_price' => 200, 'vat_rate' => 0, 'tax_rate_id' => $exemptRate->id, 'vat_amount' => 0, 'line_total' => 200]);
        $exemptInvoice->recalculateTotals();
        $exemptInvoice->save();

        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Supplier A']);
        $bill = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-1',
            'bill_date' => now(), 'due_date' => now()->addDays(30), 'status' => 'posted', 'currency' => 'SAR',
        ]);
        $bill->items()->create(['description' => 'General overhead', 'quantity' => 1, 'unit_price' => 1000, 'vat_rate' => 15, 'vat_amount' => 150, 'line_total' => 1150]);
        $bill->recalculateTotals();
        $bill->save();

        $response = $this->actingAs($owner)->get(route('app.reports.vat', $this->periodQuery() + ['tab' => 'sales']));

        $response->assertOk();
        $response->assertSee(__('Input VAT apportionment (partial exemption)'));
        $response->assertSee('80.00%');
        // 150 input VAT * 80% = 120 recoverable, 30 disallowed.
        $response->assertSee('30.00'); // non-recoverable line
    }

    public function test_a_manual_recovery_percentage_override_takes_precedence_over_the_auto_computed_ratio(): void
    {
        $company = $this->makeCompanyWithAccess(makesExemptSupplies: true, recoveryOverride: 50.0);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $client = Client::create(['company_id' => $company->id, 'name' => 'Client A']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-1',
            'issue_date' => now(), 'due_date' => now()->addDays(30), 'status' => 'sent', 'currency' => 'SAR',
        ]);
        $invoice->items()->create(['description' => 'Item', 'quantity' => 1, 'unit_price' => 1000, 'vat_rate' => 15, 'vat_amount' => 150, 'line_total' => 1150]);
        $invoice->recalculateTotals();
        $invoice->save();

        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Supplier A']);
        $bill = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-1',
            'bill_date' => now(), 'due_date' => now()->addDays(30), 'status' => 'posted', 'currency' => 'SAR',
        ]);
        $bill->items()->create(['description' => 'Purchase', 'quantity' => 1, 'unit_price' => 1000, 'vat_rate' => 15, 'vat_amount' => 150, 'line_total' => 1150]);
        $bill->recalculateTotals();
        $bill->save();

        $response = $this->actingAs($owner)->get(route('app.reports.vat', $this->periodQuery() + ['tab' => 'sales']));

        $response->assertOk();
        $response->assertSee('50.00%');
        $response->assertSee('75.00'); // 150 * 50% disallowed
    }

    public function test_settings_page_saves_the_vat_recovery_toggle_and_override(): void
    {
        $company = $this->makeCompanyWithAccess();
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $response = $this->actingAs($owner)->post(route('app.tax-rates.vat-recovery'), [
            'vat_makes_exempt_supplies' => '1',
            'vat_recovery_percentage' => '65.5',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $company->refresh();
        $this->assertTrue($company->vat_makes_exempt_supplies);
        $this->assertEqualsWithDelta(65.5, (float) $company->vat_recovery_percentage, 0.01);
    }
}
