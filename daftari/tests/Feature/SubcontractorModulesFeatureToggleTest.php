<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\CompanyOverride;
use App\Models\Employee;
use App\Models\JournalEntry;
use App\Models\PaymentVoucher;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Subcontract;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Accounting\LedgerPostingService;
use App\Support\PlatformFeatureToggle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "i need to hide subcontractor and project manager budget from
 * company please make a system so i can off these features from super
 * admin and please verify it will not harm or disturb my ledger or
 * accounts" — reuses the existing Module 07 FeatureRegistry/
 * FeatureAccessService/CompanyOverride system (the same machinery
 * already gating Payroll, POS, Machinery & Equipment, Client
 * Statements) rather than inventing a new on/off mechanism: a Super
 * Admin flips the already-existing per-company Features override on
 * Admin > Companies > a company's own show page, which this registers
 * both new keys into automatically.
 *
 * Both plan columns backing these two keys (has_subcontractor_payments,
 * has_project_manager_budgets) default TRUE — unlike every other has_*
 * module column — specifically so introducing this toggle doesn't
 * silently lock any existing company with an active subscription out
 * of a feature it already relies on; see the migration's own docblock.
 *
 * Ledger safety: Subcontract/SubcontractPayment never writes a journal
 * entry of its own (see SubcontractController's docblock) — every real
 * payment is a Payment Voucher posted independently by
 * LedgerPostingService. So disabling either module can only ever block
 * the dedicated tracking/report routes and hide the nav link; it
 * cannot touch, re-post, or hide a single row already in the ledger.
 */
class SubcontractorModulesFeatureToggleTest extends TestCase
{
    use RefreshDatabase;

    private function asAdmin(User $admin)
    {
        return $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => now()->timestamp]);
    }

    private function makeCompanyAndOwner(): array
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        return [$company, $owner];
    }

    private function makeSubscribedCompanyAndOwner(): array
    {
        $plan = Plan::create(['name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000, 'is_active' => true]);
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Subscription::create([
            'company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
        ]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        return [$company, $owner];
    }

    private function makeSubcontract(Company $company, Project $project): Subcontract
    {
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'SAED EST']);

        return Subcontract::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'party_type' => 'supplier', 'supplier_id' => $supplier->id,
            'contract_number' => 'SC-0001', 'title' => 'Road works', 'contract_value' => 100000, 'retention_percent' => 5, 'status' => 'active',
        ]);
    }

    private function makePmBudget(Company $company, Project $project): Subcontract
    {
        $employee = Employee::create(['company_id' => $company->id, 'employee_number' => 'EMP-1', 'full_name' => 'Shahid', 'hire_date' => now(), 'basic_salary' => 500]);

        return Subcontract::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'party_type' => 'employee', 'employee_id' => $employee->id,
            'contract_number' => 'SC-0002', 'title' => 'PM budget', 'contract_value' => 50000, 'retention_percent' => 5, 'status' => 'active',
        ]);
    }

    public function test_both_modules_are_enabled_by_default_for_an_existing_company_with_no_subscription(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();

        $this->actingAs($owner)->get(route('app.subcontracts.index'))->assertOk();
        $this->actingAs($owner)->get(route('app.project-manager-budgets.index'))->assertOk();
    }

    public function test_both_modules_stay_enabled_by_default_for_a_company_on_a_paid_plan(): void
    {
        [$company, $owner] = $this->makeSubscribedCompanyAndOwner();

        $this->actingAs($owner)->get(route('app.subcontracts.index'))->assertOk();
        $this->actingAs($owner)->get(route('app.project-manager-budgets.index'))->assertOk();
    }

    public function test_the_sidebar_shows_both_links_by_default(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();

        $dashboard = $this->actingAs($owner)->get(route('app.dashboard'));

        $dashboard->assertOk()->assertSee(__('Subcontractor Payments'))->assertSee(__('Project Manager Budgets'));
    }

    public function test_a_super_admin_can_disable_subcontractor_payments_for_one_company(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $admin = User::factory()->create(['role' => 'super_admin', 'company_id' => null]);

        $this->asAdmin($admin)->post(route('admin.companies.overrides.set', $company), [
            'type' => 'feature', 'key' => 'subcontractor_payments', 'value' => '0',
        ])->assertRedirect();

        $this->actingAs($owner)->get(route('app.subcontracts.index'))->assertRedirect(route('app.dashboard'));
        // Project Manager Budgets is untouched by this override.
        $this->actingAs($owner)->get(route('app.project-manager-budgets.index'))->assertOk();

        $dashboard = $this->actingAs($owner)->get(route('app.dashboard'));
        $dashboard->assertDontSee(__('Subcontractor Payments'))->assertSee(__('Project Manager Budgets'));
    }

    public function test_a_super_admin_can_disable_project_manager_budgets_for_one_company(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $admin = User::factory()->create(['role' => 'super_admin', 'company_id' => null]);

        $this->asAdmin($admin)->post(route('admin.companies.overrides.set', $company), [
            'type' => 'feature', 'key' => 'project_manager_budgets', 'value' => '0',
        ])->assertRedirect();

        $this->actingAs($owner)->get(route('app.project-manager-budgets.index'))->assertRedirect(route('app.dashboard'));
        $this->actingAs($owner)->get(route('app.subcontracts.index'))->assertOk();

        $dashboard = $this->actingAs($owner)->get(route('app.dashboard'));
        $dashboard->assertSee(__('Subcontractor Payments'))->assertDontSee(__('Project Manager Budgets'));
    }

    public function test_clearing_the_override_restores_access(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $admin = User::factory()->create(['role' => 'super_admin', 'company_id' => null]);

        $this->asAdmin($admin)->post(route('admin.companies.overrides.set', $company), [
            'type' => 'feature', 'key' => 'subcontractor_payments', 'value' => '0',
        ]);
        $this->actingAs($owner)->get(route('app.subcontracts.index'))->assertRedirect(route('app.dashboard'));

        $override = CompanyOverride::where('company_id', $company->id)->where('type', 'feature')->where('key', 'subcontractor_payments')->first();
        $this->asAdmin($admin)->delete(route('admin.companies.overrides.clear', [$company, $override]))->assertRedirect();

        $this->actingAs($owner)->get(route('app.subcontracts.index'))->assertOk();
    }

    public function test_platform_wide_toggle_vetoes_the_module_for_every_company_regardless_of_override(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        PlatformFeatureToggle::setEnabled('subcontractor_payments', false);

        $this->actingAs($owner)->get(route('app.subcontracts.index'))->assertRedirect(route('app.dashboard'));
    }

    public function test_the_project_subcontracts_tab_hides_and_relinks_rows_by_module_state(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $admin = User::factory()->create(['role' => 'super_admin', 'company_id' => null]);
        $project = Project::create(['company_id' => $company->id, 'code' => 'PRJ-000001', 'name' => 'Test Project', 'status' => 'active']);
        $subcontract = $this->makeSubcontract($company, $project);
        $pmBudget = $this->makePmBudget($company, $project);

        $both = $this->actingAs($owner)->get(route('app.projects.show', $project));
        $both->assertOk()->assertSee('SC-0001')->assertSee('SC-0002');

        $this->asAdmin($admin)->post(route('admin.companies.overrides.set', $company), [
            'type' => 'feature', 'key' => 'project_manager_budgets', 'value' => '0',
        ]);

        $onlySubcontract = $this->actingAs($owner)->get(route('app.projects.show', $project));
        $onlySubcontract->assertOk()->assertSee('SC-0001')->assertDontSee('SC-0002');
    }

    /**
     * The core safety check: disabling a module after real cash has
     * already moved through a linked Payment Voucher must not touch
     * the voucher, its journal entry, or the bank balance it moved —
     * only the Subcontract tracking page becomes unreachable.
     */
    public function test_disabling_the_module_does_not_touch_the_ledger_any_voucher_or_any_journal_entry(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $admin = User::factory()->create(['role' => 'super_admin', 'company_id' => null]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        $bank = BankAccount::create(['company_id' => $company->id, 'name' => 'Main Account', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true]);
        $project = Project::create(['company_id' => $company->id, 'code' => 'PRJ-000001', 'name' => 'Test Project', 'status' => 'active']);
        $subcontract = $this->makeSubcontract($company, $project);

        $voucher = PaymentVoucher::create([
            'company_id' => $company->id, 'bank_account_id' => $bank->id, 'supplier_id' => $subcontract->supplier_id, 'project_id' => $project->id,
            'voucher_number' => 'PV-1', 'date' => now(), 'payee_name' => 'SAED EST', 'amount' => 20000,
            'method' => 'bank_transfer', 'status' => 'issued',
        ]);
        app(LedgerPostingService::class)->postPaymentVoucher($voucher->fresh(['bankAccount', 'counterAccount', 'bill', 'expense']));
        $subcontract->payments()->create(['company_id' => $company->id, 'amount' => 20000, 'paid_at' => now(), 'payment_voucher_id' => $voucher->id]);

        $voucherCountBefore = PaymentVoucher::count();
        $journalCountBefore = JournalEntry::where('company_id', $company->id)->count();
        $journalTotalBefore = JournalEntry::where('company_id', $company->id)->get()->sum(fn ($je) => $je->lines->sum('debit'));
        $bankBalanceBefore = $bank->fresh()->currentBalance();
        $subcontractPaymentsTotalBefore = $subcontract->fresh()->totalPaid();

        $this->asAdmin($admin)->post(route('admin.companies.overrides.set', $company), [
            'type' => 'feature', 'key' => 'subcontractor_payments', 'value' => '0',
        ])->assertRedirect();

        // The module route is now blocked...
        $this->actingAs($owner)->get(route('app.subcontracts.show', $subcontract))->assertRedirect(route('app.dashboard'));

        // ...but nothing about the real financial records moved.
        $this->assertSame($voucherCountBefore, PaymentVoucher::count());
        $this->assertSame($journalCountBefore, JournalEntry::where('company_id', $company->id)->count());
        $this->assertEquals($journalTotalBefore, JournalEntry::where('company_id', $company->id)->get()->sum(fn ($je) => $je->lines->sum('debit')));
        $this->assertEquals($bankBalanceBefore, $bank->fresh()->currentBalance());
        $this->assertNotNull($voucher->fresh());
        $this->assertDatabaseHas('subcontract_payments', ['subcontract_id' => $subcontract->id, 'payment_voucher_id' => $voucher->id]);
        $this->assertEquals($subcontractPaymentsTotalBefore, $subcontract->fresh()->totalPaid());
    }
}
