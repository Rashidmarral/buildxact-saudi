<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Company;
use App\Models\Item;
use App\Models\PaymentMode;
use App\Models\Plan;
use App\Models\PosRegister;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "add more... Payment Mode Statement" and "Payment Mode Balances" —
 * the two reports built on top of PaymentMode (task #124): a flat list
 * of every POS sale payment taken through a mode in a period, and a
 * one-row-per-mode summary tying what was collected to the linked GL
 * account's actual balance.
 */
class PaymentModeStatementAndBalancesReportTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompanyWithPos(): Company
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000,
            'is_active' => true, 'has_pos' => true,
        ]);

        $company = Company::create(['name' => 'Acme Retail', 'slug' => 'acme-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        Subscription::create([
            'company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
        ]);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    private function ringUpASale(Company $company, User $owner, string $method, float $amount): void
    {
        $warehouse = Warehouse::firstOrCreate(['company_id' => $company->id, 'name' => 'Main Store']);
        $register = PosRegister::firstOrCreate(['company_id' => $company->id, 'warehouse_id' => $warehouse->id, 'name' => 'Register 1'], ['is_active' => true]);

        if (! $register->openShift()) {
            $this->actingAs($owner)->post(route('app.pos.shift.open'), ['register_id' => $register->id, 'opening_cash' => 100]);
        }

        $item = Item::create(['company_id' => $company->id, 'name' => 'Item '.uniqid(), 'unit_price' => $amount, 'vat_rate' => 0, 'is_active' => true, 'track_inventory' => false]);

        $this->actingAs($owner)->postJson(route('app.pos.checkout'), [
            'register_id' => $register->id,
            'lines' => [['item_id' => $item->id, 'quantity' => 1, 'unit_price' => $amount]],
            'payments' => [['method' => $method, 'amount' => $amount]],
        ])->assertRedirect();
    }

    public function test_statement_lists_payments_for_the_selected_mode_with_a_running_total(): void
    {
        $company = $this->makeCompanyWithPos();
        $owner = $this->makeOwner($company);
        PaymentMode::seedDefaults($company->id);

        $this->ringUpASale($company, $owner, 'cash', 10);
        $this->ringUpASale($company, $owner, 'cash', 15);
        $this->ringUpASale($company, $owner, 'card', 20);

        $cashMode = PaymentMode::where('company_id', $company->id)->where('code', 'cash')->first();

        $page = $this->actingAs($owner)->get(route('app.reports.payment-mode-statement', ['payment_mode_id' => $cashMode->id]));

        $page->assertOk()->assertSee('10.00')->assertSee('25.00')->assertDontSee('20.00');
    }

    public function test_statement_with_no_mode_filter_shows_every_mode(): void
    {
        $company = $this->makeCompanyWithPos();
        $owner = $this->makeOwner($company);
        PaymentMode::seedDefaults($company->id);

        $this->ringUpASale($company, $owner, 'cash', 10);
        $this->ringUpASale($company, $owner, 'card', 20);

        $page = $this->actingAs($owner)->get(route('app.reports.payment-mode-statement'));

        $page->assertOk()->assertSee('Cash')->assertSee('Card');

        $csv = $this->actingAs($owner)->get(route('app.reports.payment-mode-statement', ['export' => 'csv']));
        $csv->assertOk();
        $csvBody = str_replace("\xEF\xBB\xBF", '', $csv->streamedContent());
        $this->assertStringContainsString('10.00', $csvBody);
        $this->assertStringContainsString('20.00', $csvBody);
    }

    public function test_balances_ties_each_modes_period_collections_to_its_account_balance(): void
    {
        $company = $this->makeCompanyWithPos();
        $owner = $this->makeOwner($company);
        PaymentMode::seedDefaults($company->id);

        $this->ringUpASale($company, $owner, 'cash', 12.5);
        $this->ringUpASale($company, $owner, 'card', 30);

        $page = $this->actingAs($owner)->get(route('app.reports.payment-mode-balances'));

        $page->assertOk()->assertSee('12.50')->assertSee('30.00');

        $pdf = $this->actingAs($owner)->get(route('app.reports.payment-mode-balances', ['export' => 'pdf']));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_a_voided_sales_payments_are_excluded_from_both_reports(): void
    {
        $company = $this->makeCompanyWithPos();
        $owner = $this->makeOwner($company);
        PaymentMode::seedDefaults($company->id);
        $this->ringUpASale($company, $owner, 'cash', 40);

        $sale = \App\Models\PosSale::first();
        $this->actingAs($owner)->post(route('app.pos.sales.void', $sale), ['void_reason' => 'Customer changed mind'])->assertRedirect();

        $statement = $this->actingAs($owner)->get(route('app.reports.payment-mode-statement'));
        $statement->assertOk()->assertDontSee('40.00');

        $balances = $this->actingAs($owner)->get(route('app.reports.payment-mode-balances'));
        $balances->assertOk()->assertDontSee('40.00');
    }
}
