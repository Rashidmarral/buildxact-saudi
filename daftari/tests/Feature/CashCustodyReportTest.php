<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\BankTransfer;
use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "how can i get custody account reports" — a cross-custodian summary
 * (advanced/spent/returned/balance) on top of the plain glance
 * CashCustodyDashboardTest already covers, plus the quick-action links
 * and the idle-float alert that make the whole page more usable.
 */
class CashCustodyReportTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        return Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    public function test_the_report_shows_advanced_spent_returned_and_balance_per_custodian(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $main = BankAccount::create(['company_id' => $company->id, 'name' => 'Main', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true]);
        $khalid = BankAccount::create(['company_id' => $company->id, 'name' => 'Khalid - Site Custody', 'type' => 'cash', 'currency' => 'SAR', 'is_active' => true, 'is_personal' => true, 'personal_owner_name' => 'Khalid']);
        $fuel = ExpenseCategory::create(['company_id' => $company->id, 'name' => 'Fuel']);

        BankTransfer::create(['company_id' => $company->id, 'from_bank_account_id' => $main->id, 'to_bank_account_id' => $khalid->id, 'amount' => 10000, 'date' => now()->toDateString(), 'created_by' => $owner->id]);
        Expense::create(['company_id' => $company->id, 'bank_account_id' => $khalid->id, 'expense_category_id' => $fuel->id, 'vendor_name' => 'Fuel stop', 'amount' => 1000, 'gross_amount' => 1000, 'vat_amount' => 0, 'tax_category' => 'zero_rated', 'expense_date' => now()->toDateString(), 'status' => 'approved']);
        BankTransfer::create(['company_id' => $company->id, 'from_bank_account_id' => $khalid->id, 'to_bank_account_id' => $main->id, 'amount' => 2000, 'date' => now()->toDateString(), 'created_by' => $owner->id]);

        $response = $this->actingAs($owner)->get(route('app.cash-custody.report'));

        $response->assertOk();
        $response->assertSee('Khalid');
        $response->assertSee(\App\Support\Money::format(10000)); // advanced
        $response->assertSee(\App\Support\Money::format(1000)); // spent
        $response->assertSee(\App\Support\Money::format(2000)); // returned
        // Balance: 10000 - 1000 - 2000 = 7000.
        $response->assertSee(\App\Support\Money::format(7000));
        $response->assertSee('Fuel');
    }

    public function test_the_pdf_downloads(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        BankAccount::create(['company_id' => $company->id, 'name' => 'Khalid - Site Custody', 'type' => 'cash', 'currency' => 'SAR', 'is_active' => true, 'is_personal' => true, 'personal_owner_name' => 'Khalid']);

        $response = $this->actingAs($owner)->get(route('app.cash-custody.pdf'));

        $response->assertOk();
        $this->assertStringStartsWith('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_quick_action_links_prefill_the_right_account(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        BankAccount::create(['company_id' => $company->id, 'name' => 'Main', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true]);
        $khalid = BankAccount::create(['company_id' => $company->id, 'name' => 'Khalid - Site Custody', 'type' => 'cash', 'currency' => 'SAR', 'is_active' => true, 'is_personal' => true, 'personal_owner_name' => 'Khalid']);

        $this->actingAs($owner)->get(route('app.cash-custody.index'))
            ->assertSee(route('app.bank-transfers.create', ['to_bank_account_id' => $khalid->id]), false)
            ->assertSee(route('app.expenses.create', ['bank_account_id' => $khalid->id]), false)
            ->assertSee(route('app.bank-transfers.create', ['from_bank_account_id' => $khalid->id]), false);

        $this->actingAs($owner)->get(route('app.bank-transfers.create', ['to_bank_account_id' => $khalid->id]))
            ->assertOk()
            ->assertSee('<option value="'.$khalid->id.'" selected', false);

        $this->actingAs($owner)->get(route('app.expenses.create', ['bank_account_id' => $khalid->id]))
            ->assertOk()
            ->assertSee('<option value="'.$khalid->id.'" selected', false);
    }

    public function test_a_custodian_idle_past_the_threshold_is_flagged(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $main = BankAccount::create(['company_id' => $company->id, 'name' => 'Main', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true]);
        $khalid = BankAccount::create(['company_id' => $company->id, 'name' => 'Khalid - Site Custody', 'type' => 'cash', 'currency' => 'SAR', 'is_active' => true, 'is_personal' => true, 'personal_owner_name' => 'Khalid']);

        BankTransfer::create(['company_id' => $company->id, 'from_bank_account_id' => $main->id, 'to_bank_account_id' => $khalid->id, 'amount' => 5000, 'date' => now()->subDays(45)->toDateString(), 'created_by' => $owner->id]);

        $response = $this->actingAs($owner)->get(route('app.cash-custody.index'));

        $response->assertOk();
        $response->assertSee(__('Outstanding :days days', ['days' => 45]));
    }

    public function test_a_recently_active_custodian_is_not_flagged(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $main = BankAccount::create(['company_id' => $company->id, 'name' => 'Main', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true]);
        $khalid = BankAccount::create(['company_id' => $company->id, 'name' => 'Khalid - Site Custody', 'type' => 'cash', 'currency' => 'SAR', 'is_active' => true, 'is_personal' => true, 'personal_owner_name' => 'Khalid']);

        BankTransfer::create(['company_id' => $company->id, 'from_bank_account_id' => $main->id, 'to_bank_account_id' => $khalid->id, 'amount' => 5000, 'date' => now()->toDateString(), 'created_by' => $owner->id]);

        $response = $this->actingAs($owner)->get(route('app.cash-custody.index'));

        $response->assertOk();
        $response->assertDontSee(__('Outstanding :days days', ['days' => 0]));
    }

    public function test_the_dashboard_shows_a_custody_tile_only_when_a_custody_account_exists(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);

        $this->actingAs($owner)->get(route('app.dashboard'))->assertDontSee(__('Cash out with custodians'));

        $main = BankAccount::create(['company_id' => $company->id, 'name' => 'Main', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true]);
        $khalid = BankAccount::create(['company_id' => $company->id, 'name' => 'Khalid - Site Custody', 'type' => 'cash', 'currency' => 'SAR', 'is_active' => true, 'is_personal' => true, 'personal_owner_name' => 'Khalid']);
        BankTransfer::create(['company_id' => $company->id, 'from_bank_account_id' => $main->id, 'to_bank_account_id' => $khalid->id, 'amount' => 3000, 'date' => now()->toDateString(), 'created_by' => $owner->id]);

        $this->actingAs($owner)->get(route('app.dashboard'))
            ->assertSee(__('Cash out with custodians'))
            ->assertSee(\App\Support\Money::format(3000));
    }
}
