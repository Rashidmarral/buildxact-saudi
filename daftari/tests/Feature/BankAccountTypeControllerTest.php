<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\BankAccount;
use App\Models\BankAccountType;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "there is no option to add account type ... there is two account type
 * cash and bank only" — Bank Account's `type` select was hardcoded to
 * exactly bank/cash (BankAccountController::validated()'s `in:bank,cash`
 * rule), tied directly to a GL code range. This module lets a company add
 * its own labels (e.g. "Digital Wallet", "POS Till") without touching that
 * underlying GL behavior: every custom type still resolves to a plain
 * `nature` of bank or cash under the hood.
 */
class BankAccountTypeControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): User
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting Company', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    public function test_the_index_page_lists_custom_types(): void
    {
        $owner = $this->makeOwner();
        BankAccountType::create(['company_id' => $owner->company_id, 'name' => 'Digital Wallet', 'nature' => 'bank']);

        $response = $this->actingAs($owner)->get(route('app.bank-account-types.index'));

        $response->assertOk()->assertSee('Digital Wallet');
    }

    public function test_a_custom_type_can_be_created(): void
    {
        $owner = $this->makeOwner();

        $response = $this->actingAs($owner)->post(route('app.bank-account-types.store'), [
            'name' => 'POS Till', 'nature' => 'cash',
        ]);

        $response->assertRedirect(route('app.bank-account-types.index'));
        $this->assertDatabaseHas('bank_account_types', [
            'company_id' => $owner->company_id, 'name' => 'POS Till', 'nature' => 'cash',
        ]);
    }

    public function test_type_names_are_unique_per_company(): void
    {
        $owner = $this->makeOwner();
        BankAccountType::create(['company_id' => $owner->company_id, 'name' => 'Digital Wallet', 'nature' => 'bank']);

        $response = $this->actingAs($owner)->post(route('app.bank-account-types.store'), [
            'name' => 'Digital Wallet', 'nature' => 'cash',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_a_type_assigned_to_an_account_cannot_be_deleted(): void
    {
        $owner = $this->makeOwner();
        $type = BankAccountType::create(['company_id' => $owner->company_id, 'name' => 'Digital Wallet', 'nature' => 'bank']);
        BankAccount::create(['company_id' => $owner->company_id, 'name' => 'STC Pay', 'type' => 'bank', 'bank_account_type_id' => $type->id, 'currency' => 'SAR']);

        $response = $this->actingAs($owner)->delete(route('app.bank-account-types.destroy', $type));

        $response->assertSessionHasErrors('bank_account_type');
        $this->assertDatabaseHas('bank_account_types', ['id' => $type->id]);
    }

    public function test_an_unused_type_can_be_deleted(): void
    {
        $owner = $this->makeOwner();
        $type = BankAccountType::create(['company_id' => $owner->company_id, 'name' => 'Digital Wallet', 'nature' => 'bank']);

        $response = $this->actingAs($owner)->delete(route('app.bank-account-types.destroy', $type));

        $response->assertRedirect(route('app.bank-account-types.index'));
        $this->assertDatabaseMissing('bank_account_types', ['id' => $type->id]);
    }

    // ---------------------------------------------------------------
    // BankAccountController integration: creating an account with a
    // custom type resolves its real nature, independent of the raw
    // submitted value.
    // ---------------------------------------------------------------

    public function test_creating_a_bank_account_with_a_custom_cash_like_type_resolves_its_nature(): void
    {
        $owner = $this->makeOwner();
        $type = BankAccountType::create(['company_id' => $owner->company_id, 'name' => 'POS Till', 'nature' => 'cash']);

        $response = $this->actingAs($owner)->post(route('app.bank-accounts.store'), [
            'name' => 'Till 1', 'type' => "custom:{$type->id}", 'currency' => 'SAR',
        ]);

        $response->assertRedirect(route('app.bank-accounts.index'));
        $account = BankAccount::where('name', 'Till 1')->firstOrFail();
        $this->assertSame('cash', $account->type);
        $this->assertSame($type->id, $account->bank_account_type_id);
        $this->assertSame('POS Till', $account->displayTypeLabel());
    }

    public function test_creating_a_bank_account_with_a_custom_bank_like_type_resolves_its_nature(): void
    {
        $owner = $this->makeOwner();
        $type = BankAccountType::create(['company_id' => $owner->company_id, 'name' => 'Digital Wallet', 'nature' => 'bank']);

        $this->actingAs($owner)->post(route('app.bank-accounts.store'), [
            'name' => 'Wallet 1', 'type' => "custom:{$type->id}", 'currency' => 'SAR',
        ]);

        $account = BankAccount::where('name', 'Wallet 1')->firstOrFail();
        $this->assertSame('bank', $account->type);

        // Its dedicated GL code falls in the Bank sub-range (1101-1149),
        // exactly as a plain built-in "Bank" account would — a custom
        // type never gets its own accounting treatment, only its parent
        // nature's.
        $code = (int) $account->glAccount()->code;
        $this->assertGreaterThanOrEqual(1101, $code);
        $this->assertLessThanOrEqual(1149, $code);
    }

    public function test_an_invalid_custom_type_id_is_rejected(): void
    {
        $owner = $this->makeOwner();
        $otherCompany = Company::create(['name' => 'Other Co', 'slug' => 'other-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        $foreignType = BankAccountType::create(['company_id' => $otherCompany->id, 'name' => 'Foreign Type', 'nature' => 'bank']);

        $response = $this->actingAs($owner)->post(route('app.bank-accounts.store'), [
            'name' => 'Sneaky', 'type' => "custom:{$foreignType->id}", 'currency' => 'SAR',
        ]);

        $response->assertSessionHasErrors('type');
        $this->assertDatabaseMissing('bank_accounts', ['name' => 'Sneaky']);
    }

    public function test_plain_built_in_bank_and_cash_types_still_work_unchanged(): void
    {
        $owner = $this->makeOwner();

        $this->actingAs($owner)->post(route('app.bank-accounts.store'), [
            'name' => 'Main Bank', 'type' => 'bank', 'currency' => 'SAR',
        ])->assertRedirect();

        $account = BankAccount::where('name', 'Main Bank')->firstOrFail();
        $this->assertSame('bank', $account->type);
        $this->assertNull($account->bank_account_type_id);
        $this->assertSame('Bank', $account->displayTypeLabel());
    }
}
