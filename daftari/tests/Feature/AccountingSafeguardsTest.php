<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Commercial audit findings A1 and A4.
 *
 * A1: AccountController::deactivate() used to unconditionally flip
 * is_active despite the page's own copy claiming a mapped/system account
 * "cannot be deactivated once used" — nothing enforced that, and since
 * every GL report (Trial Balance, Balance Sheet, Income Statement, Zakat)
 * filters on is_active while AccountMapping::resolve() doesn't, a
 * deactivated-but-still-mapped account would keep receiving postings that
 * then silently vanished from every report.
 *
 * A4: AccountController::updateMapping() accepted any account of any type
 * (or an inactive one) for any semantic role — e.g. VAT Output, which the
 * posting engine always credits as a liability, could be pointed at an
 * expense account.
 */
class AccountingSafeguardsTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): User
    {
        $company = Company::create(['name' => 'Safeguards Co.', 'slug' => 'safeguards-'.uniqid()]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    public function test_a_mapped_account_cannot_be_deactivated(): void
    {
        $owner = $this->makeOwner();
        $revenueAccount = Account::where('company_id', $owner->company_id)->where('code', '4000')->first();

        $response = $this->actingAs($owner)->post(route('app.accounts.deactivate', $revenueAccount));

        $response->assertSessionHasErrors('account');
        $this->assertTrue($revenueAccount->fresh()->is_active);
    }

    public function test_an_unmapped_account_with_no_journal_history_can_be_deactivated(): void
    {
        $owner = $this->makeOwner();
        $custom = Account::create([
            'company_id' => $owner->company_id, 'code' => '9999', 'name' => 'Unused Account',
            'type' => 'expense', 'normal_balance' => 'debit', 'is_active' => true,
        ]);

        $response = $this->actingAs($owner)->post(route('app.accounts.deactivate', $custom));

        $response->assertSessionDoesntHaveErrors();
        $this->assertFalse($custom->fresh()->is_active);
    }

    public function test_a_system_account_cannot_be_deactivated(): void
    {
        $owner = $this->makeOwner();
        // Unmap 1000 (Cash) first so the mapping check alone doesn't
        // explain the refusal — is_system must independently block it.
        AccountMapping::where('company_id', $owner->company_id)->where('key', 'DEFAULT_CASH')->delete();
        $cash = Account::where('company_id', $owner->company_id)->where('code', '1000')->first();

        $response = $this->actingAs($owner)->post(route('app.accounts.deactivate', $cash));

        $response->assertSessionHasErrors('account');
        $this->assertTrue($cash->fresh()->is_active);
    }

    public function test_mapping_vat_output_to_an_expense_account_is_rejected(): void
    {
        $owner = $this->makeOwner();
        $expenseAccount = Account::where('company_id', $owner->company_id)->where('code', '5100')->first();

        $response = $this->actingAs($owner)->post(route('app.accounts.mappings.update'), [
            'key' => 'VAT_OUTPUT',
            'account_id' => $expenseAccount->id,
        ]);

        $response->assertSessionHasErrors('account_id');
        $mapping = AccountMapping::where('company_id', $owner->company_id)->where('key', 'VAT_OUTPUT')->first();
        $this->assertNotSame($expenseAccount->id, $mapping->account_id);
    }

    public function test_mapping_vat_output_to_a_liability_account_is_accepted(): void
    {
        $owner = $this->makeOwner();
        $liabilityAccount = Account::create([
            'company_id' => $owner->company_id, 'code' => '2999', 'name' => 'Alt VAT Output',
            'type' => 'liability', 'normal_balance' => 'credit', 'is_active' => true,
        ]);

        $response = $this->actingAs($owner)->post(route('app.accounts.mappings.update'), [
            'key' => 'VAT_OUTPUT',
            'account_id' => $liabilityAccount->id,
        ]);

        $response->assertSessionDoesntHaveErrors();
        $mapping = AccountMapping::where('company_id', $owner->company_id)->where('key', 'VAT_OUTPUT')->first();
        $this->assertSame($liabilityAccount->id, $mapping->account_id);
    }

    public function test_mapping_to_an_inactive_account_is_rejected(): void
    {
        $owner = $this->makeOwner();
        $inactive = Account::create([
            'company_id' => $owner->company_id, 'code' => '2998', 'name' => 'Inactive Liability',
            'type' => 'liability', 'normal_balance' => 'credit', 'is_active' => false,
        ]);

        $response = $this->actingAs($owner)->post(route('app.accounts.mappings.update'), [
            'key' => 'VAT_OUTPUT',
            'account_id' => $inactive->id,
        ]);

        $response->assertSessionHasErrors('account_id');
    }
}
