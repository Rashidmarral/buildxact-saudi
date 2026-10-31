<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Client;
use App\Models\Company;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "I transferred 5,000 riyal to my personal account from company cash,
 * that 5,000 is also used in the company's work" — a bank account can be
 * flagged personal/pass-through: still fully usable everywhere money is
 * tagged (expenses, transfers), but left out of the customer-facing
 * "pay us here" account lists on invoices/quotations.
 */
class BankAccountPersonalFlagTest extends TestCase
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

    public function test_a_bank_account_can_be_created_and_flagged_personal(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->post(route('app.bank-accounts.store'), [
            'name' => 'Ahmed Personal Account', 'type' => 'bank', 'currency' => 'SAR',
            'is_personal' => '1', 'personal_owner_name' => 'Ahmed Al-Qahtani',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $account = BankAccount::where('company_id', $company->id)->first();
        $this->assertTrue($account->is_personal);
        $this->assertSame('Ahmed Al-Qahtani', $account->personal_owner_name);
    }

    public function test_a_personal_account_is_excluded_from_the_invoice_bank_account_picker(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $personal = BankAccount::create(['company_id' => $company->id, 'name' => 'Personal Acct', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true, 'is_personal' => true]);
        $company3 = BankAccount::create(['company_id' => $company->id, 'name' => 'Main Current Account', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true]);

        $response = $this->actingAs($owner)->get(route('app.invoices.create'));

        $response->assertOk();
        $response->assertSee('Main Current Account');
        $response->assertDontSee('Personal Acct');
    }

    public function test_a_personal_account_is_excluded_from_the_quotation_bank_account_picker(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        BankAccount::create(['company_id' => $company->id, 'name' => 'Personal Acct', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true, 'is_personal' => true]);
        BankAccount::create(['company_id' => $company->id, 'name' => 'Main Current Account', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true]);

        $response = $this->actingAs($owner)->get(route('app.quotations.create'));

        $response->assertOk();
        $response->assertSee('Main Current Account');
        $response->assertDontSee('Personal Acct');
    }

    public function test_a_personal_account_still_appears_on_the_expense_form_for_tagging(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        BankAccount::create(['company_id' => $company->id, 'name' => 'Personal Acct', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true, 'is_personal' => true]);

        $response = $this->actingAs($owner)->get(route('app.expenses.create'));

        $response->assertOk();
        $response->assertSee('Personal Acct');
    }

    public function test_the_index_shows_a_personal_badge_with_the_owner_name(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        BankAccount::create(['company_id' => $company->id, 'name' => 'Personal Acct', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true, 'is_personal' => true, 'personal_owner_name' => 'Ahmed Al-Qahtani']);

        $this->actingAs($owner)->get(route('app.bank-accounts.index'))
            ->assertOk()
            ->assertSee(__('Personal / pass-through'))
            ->assertSee('Ahmed Al-Qahtani');
    }
}
