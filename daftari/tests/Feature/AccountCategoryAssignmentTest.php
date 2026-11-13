<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "check... if we missing something... please add it" — a custom
 * account had no way to be filed under a category at all; it always
 * fell into the generic "Other :type" catch-all on Trial Balance and
 * Balance Sheet, no matter how the company tried to organize its own
 * chart. A plain, free-text category field (with existing values
 * offered via a datalist) now lets a custom account join any existing
 * category — or start a new one — same as a seeded system account
 * already can via Account::categoryForCode().
 */
class AccountCategoryAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompanyAndOwner(): array
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        return [$company, $owner];
    }

    public function test_a_custom_account_can_be_created_with_an_existing_category(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();

        $this->actingAs($owner)->post(route('app.accounts.store'), [
            'code' => '1105', 'name' => 'Petty Cash Box', 'type' => 'asset', 'normal_balance' => 'debit',
            'category' => 'Cash and cash equivalents',
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $account = Account::where('company_id', $company->id)->where('code', '1105')->first();
        $this->assertSame('Cash and cash equivalents', $account->category);
    }

    public function test_a_custom_account_can_be_created_with_a_brand_new_category(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();

        $this->actingAs($owner)->post(route('app.accounts.store'), [
            'code' => '1600', 'name' => 'Prepaid Insurance', 'type' => 'asset', 'normal_balance' => 'debit',
            'category' => 'Prepaid Expenses',
        ])->assertRedirect();

        $account = Account::where('company_id', $company->id)->where('code', '1600')->first();
        $this->assertSame('Prepaid Expenses', $account->category);
    }

    public function test_leaving_the_category_blank_still_works_and_falls_back_to_other(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();

        $this->actingAs($owner)->post(route('app.accounts.store'), [
            'code' => '1999', 'name' => 'Uncategorized Thing', 'type' => 'asset', 'normal_balance' => 'debit',
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $account = Account::where('company_id', $company->id)->where('code', '1999')->first();
        $this->assertNull($account->category);
    }

    public function test_a_custom_accounts_category_can_be_edited(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $account = Account::create(['company_id' => $company->id, 'code' => '1998', 'name' => 'Custom Account', 'type' => 'asset', 'normal_balance' => 'debit', 'category' => 'Old Category']);

        $this->actingAs($owner)->put(route('app.accounts.update', $account), [
            'code' => '1998', 'name' => 'Custom Account', 'type' => 'asset', 'normal_balance' => 'debit',
            'category' => 'Current Assets',
        ])->assertRedirect();

        $this->assertSame('Current Assets', $account->fresh()->category);
    }

    public function test_a_system_accounts_category_cannot_be_changed_through_the_edit_form(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $bank = Account::where('company_id', $company->id)->where('code', '1100')->first();
        $this->assertSame('Cash and cash equivalents', $bank->category);

        $this->actingAs($owner)->put(route('app.accounts.update', $bank), [
            'code' => $bank->code, 'name' => $bank->name, 'name_ar' => $bank->name_ar, 'type' => $bank->type,
            'normal_balance' => $bank->normal_balance, 'category' => 'Something Else', 'parent_account_id' => null,
        ])->assertRedirect();

        $this->assertSame('Cash and cash equivalents', $bank->fresh()->category);
    }

    public function test_a_custom_account_in_its_own_category_shows_up_on_the_trial_balance(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $account = Account::create(['company_id' => $company->id, 'code' => '1997', 'name' => 'Marketable Securities', 'type' => 'asset', 'normal_balance' => 'debit', 'category' => 'Investments']);
        $capital = Account::where('company_id', $company->id)->where('code', '3000')->first();
        app(\App\Services\Accounting\LedgerPostingService::class)->post($company, 'manual', 1, 'Buy securities', now(), [
            ['account_id' => $account->id, 'debit' => 5000],
            ['account_id' => $capital->id, 'credit' => 5000],
        ]);

        $data = app(\App\Services\Reports\FinancialReportService::class)->trialBalanceGrouped($company, now()->startOfMonth(), now()->endOfMonth());

        $this->assertArrayHasKey('Investments', $data['types']['asset']['categories']->toArray());
    }
}
