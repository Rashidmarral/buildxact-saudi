<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Client;
use App\Models\Company;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\LoyaltyCard;
use App\Models\LoyaltyCardTransaction;
use App\Models\PayrollRun;
use App\Models\Plan;
use App\Models\PosRegister;
use App\Models\PosSale;
use App\Models\PosShift;
use App\Models\Project;
use App\Models\RepairJob;
use App\Models\RestaurantOrder;
use App\Models\Subscription;
use App\Models\User;
use App\Models\ZatcaInvoiceLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The main dashboard previously had zero presence for any paid module
 * except Machinery & Equipment. This exercises the "Your Modules" section
 * added for the rest of the product's verticals (Project Cash Flow,
 * Restaurant, Repair Shop, Coffee Shop, Payroll, POS, ZATCA) — each
 * widget gated on the same permission + FeatureAccessService check as
 * its own routes, and hidden entirely for a company with none installed.
 */
class DashboardModuleWidgetsTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(array $moduleFlags = []): Company
    {
        $plan = Plan::create(array_merge([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000, 'is_active' => true,
        ], $moduleFlags));

        $company = Company::create(['name' => 'Widgets Co.', 'slug' => 'widgets-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
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

    public function test_dashboard_shows_every_module_widget_once_installed_and_populated(): void
    {
        $company = $this->makeCompany([
            'has_project_cash_flow' => true, 'has_restaurant' => true, 'has_repair_shop' => true,
            'has_coffee_shop' => true, 'has_payroll' => true, 'has_pos' => true, 'has_zatca_phase2' => true,
        ]);
        $owner = $this->makeOwner($company);

        $client = Client::create(['company_id' => $company->id, 'name' => 'Widgets Client']);
        $project = Project::create(['company_id' => $company->id, 'code' => 'PRJ-1', 'name' => 'Widgets Site', 'status' => 'active']);
        Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'project_id' => $project->id, 'invoice_number' => 'INV-W1',
            'status' => 'sent', 'issue_date' => now(), 'due_date' => now()->addDays(30),
            'subtotal' => 1000, 'vat_total' => 0, 'total' => 1000, 'currency' => 'SAR',
        ]);
        Expense::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'vendor_name' => 'Supplier',
            'amount' => 200, 'gross_amount' => 200, 'vat_amount' => 0, 'tax_category' => 'zero_rated',
            'expense_date' => now()->toDateString(), 'status' => 'approved',
        ]);

        RestaurantOrder::create(['company_id' => $company->id, 'order_number' => 'RO-1', 'order_type' => 'dine_in', 'status' => 'completed']);
        RestaurantOrder::create(['company_id' => $company->id, 'order_number' => 'RO-2', 'order_type' => 'takeaway', 'status' => 'cancelled']);

        RepairJob::create(['company_id' => $company->id, 'job_number' => 'RJ-1', 'item_description' => 'Phone', 'status' => 'received']);
        RepairJob::create(['company_id' => $company->id, 'job_number' => 'RJ-2', 'item_description' => 'Watch', 'status' => 'collected']);

        $card = LoyaltyCard::create(['company_id' => $company->id, 'card_number' => 'LC-1', 'balance' => 30]);
        LoyaltyCardTransaction::create(['company_id' => $company->id, 'loyalty_card_id' => $card->id, 'type' => 'top_up', 'amount' => 50]);
        LoyaltyCardTransaction::create(['company_id' => $company->id, 'loyalty_card_id' => $card->id, 'type' => 'redeem', 'amount' => 20]);

        PayrollRun::create([
            'company_id' => $company->id, 'run_number' => 'PR-1', 'period_month' => now()->month, 'period_year' => now()->year,
            'pay_date' => now()->toDateString(), 'status' => 'paid', 'total_gross' => 5000, 'total_net' => 4200,
        ]);

        $register = PosRegister::create(['company_id' => $company->id, 'name' => 'Main Till']);
        $shift = PosShift::create(['company_id' => $company->id, 'register_id' => $register->id, 'opened_by' => $owner->id, 'opened_at' => now()]);
        PosSale::create(['company_id' => $company->id, 'shift_id' => $shift->id, 'register_id' => $register->id, 'sale_number' => 'POS-1', 'status' => 'completed', 'total' => 350]);

        $zatcaInvoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-Z1', 'status' => 'sent',
            'issue_date' => now(), 'due_date' => now()->addDays(30), 'subtotal' => 100, 'vat_total' => 15, 'total' => 115, 'currency' => 'SAR',
        ]);
        ZatcaInvoiceLog::create([
            'company_id' => $company->id, 'invoice_id' => $zatcaInvoice->id, 'environment' => 'production',
            'invoice_type' => 'b2c', 'direction' => 'reporting', 'status' => 'reported',
            'request_uuid' => (string) \Illuminate\Support\Str::uuid(), 'submitted_at' => now(),
        ]);

        $response = $this->actingAs($owner)->get(route('app.dashboard'));
        $response->assertOk()
            ->assertSee(__('Your Modules'))
            ->assertSee(__('Project Cash Flow'))
            ->assertSee(__('Restaurant Orders'))
            ->assertSee(__('Repair Jobs'))
            ->assertSee(__('Loyalty Program'))
            ->assertSee(__('Payroll'))
            ->assertSee(__('POS Sales'))
            ->assertSee(__('ZATCA Submissions'));

        $charts = $response->original->getData()['charts'];
        $this->assertTrue($charts['anyModuleEnabled']);
        $this->assertSame('Widgets Site', $charts['projectCashFlowTopProjects'][0]['label']);
        $this->assertEqualsWithDelta(1000, $charts['projectCashFlowTopProjects'][0]['revenue'], 0.01);
        $this->assertEqualsWithDelta(200, $charts['projectCashFlowTopProjects'][0]['costs'], 0.01);
        $this->assertSame(1, $charts['restaurantOrdersByStatus']->firstWhere('label', __('Completed'))['count']);
        $this->assertSame(1, $charts['repairJobsByStatus']->firstWhere('label', __('Received'))['count']);
        $this->assertEqualsWithDelta(50, $charts['loyaltyTransactionsByType']->firstWhere('label', __('Top-ups'))['amount'], 0.01);
        $this->assertSame(5000.0, (float) $charts['payrollTrend']['gross'][0]);
        $this->assertEqualsWithDelta(350, end($charts['posSalesTrend']['sales']), 0.01);
        $this->assertSame(1, $charts['zatcaSubmissionsByStatus']->firstWhere('label', __('Reported'))['count']);
    }

    public function test_dashboard_hides_the_modules_section_entirely_when_nothing_is_installed(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->get(route('app.dashboard'));
        $response->assertOk()->assertDontSee(__('Your Modules'));

        $this->assertFalse($response->original->getData()['charts']['anyModuleEnabled']);
    }
}
