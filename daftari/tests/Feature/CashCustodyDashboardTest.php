<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\BankTransfer;
use App\Models\Company;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "there is other person Rashid who manage daily basis local expenses how
 * can i track payment for him" — a custodian's float is just a BankAccount
 * flagged is_personal/personal_owner_name (see BankAccountPersonalFlagTest);
 * this page collects every such account into one glance with its current
 * balance, instead of opening each one's statement individually.
 */
class CashCustodyDashboardTest extends TestCase
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

    public function test_the_dashboard_lists_only_custody_accounts_with_their_balances_and_a_total(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $main = BankAccount::create(['company_id' => $company->id, 'name' => 'SNB Main Account', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true]);
        $khalid = BankAccount::create(['company_id' => $company->id, 'name' => 'Khalid - Site Custody', 'type' => 'cash', 'currency' => 'SAR', 'is_active' => true, 'is_personal' => true, 'personal_owner_name' => 'Khalid']);
        $rashid = BankAccount::create(['company_id' => $company->id, 'name' => 'Rashid - Daily Expenses', 'type' => 'cash', 'currency' => 'SAR', 'is_active' => true, 'is_personal' => true, 'personal_owner_name' => 'Rashid']);

        // The 10,000 advance to Khalid, then two real site costs out of it.
        BankTransfer::create(['company_id' => $company->id, 'from_bank_account_id' => $main->id, 'to_bank_account_id' => $khalid->id, 'amount' => 10000, 'date' => now()->toDateString(), 'created_by' => $owner->id]);
        Expense::create(['company_id' => $company->id, 'bank_account_id' => $khalid->id, 'vendor_name' => 'Site Inspector', 'amount' => 1000, 'gross_amount' => 1000, 'vat_amount' => 0, 'tax_category' => 'zero_rated', 'expense_date' => now()->toDateString(), 'status' => 'approved']);
        Expense::create(['company_id' => $company->id, 'bank_account_id' => $khalid->id, 'vendor_name' => 'Site Inspector', 'amount' => 1600, 'gross_amount' => 1600, 'vat_amount' => 0, 'tax_category' => 'zero_rated', 'expense_date' => now()->toDateString(), 'status' => 'approved']);

        // A small float for Rashid.
        BankTransfer::create(['company_id' => $company->id, 'from_bank_account_id' => $main->id, 'to_bank_account_id' => $rashid->id, 'amount' => 2000, 'date' => now()->toDateString(), 'created_by' => $owner->id]);

        $response = $this->actingAs($owner)->get(route('app.cash-custody.index'));

        $response->assertOk();
        $response->assertSee('Khalid');
        $response->assertSee('Rashid');
        $response->assertDontSee('SNB Main Account');
        // Khalid: 10,000 - 1,000 - 1,600 = 7,400 still unaccounted.
        $response->assertSee(\App\Support\Money::format(7400));
        $response->assertSee(\App\Support\Money::format(2000));
        // Total out with custodians: 7,400 + 2,000 = 9,400.
        $response->assertSee(\App\Support\Money::format(9400));
    }

    public function test_the_dashboard_shows_an_empty_state_with_no_custody_accounts(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        BankAccount::create(['company_id' => $company->id, 'name' => 'SNB Main Account', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true]);

        $response = $this->actingAs($owner)->get(route('app.cash-custody.index'));

        $response->assertOk();
        $response->assertSee(__('No custody accounts yet. Add a cash or bank account and mark it "Personal / held by" with the person\'s name — e.g. "Khalid – Site Custody".'));
    }
}
