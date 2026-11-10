<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "add more... Chart of Accounts tree view toggle" — the chart of
 * accounts was a flat list with no way to nest a sub-account under a
 * parent. parent_account_id (self-referencing, nullable) lets a
 * company group accounts (e.g. "Accumulated Depreciation" under
 * "Fixed Assets"), rendered as a tree per type when Tree View is
 * selected; List View is unchanged.
 */
class ChartOfAccountsTreeViewTest extends TestCase
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

    public function test_an_account_can_be_created_under_a_parent_and_shown_nested_in_tree_view(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $fixedAssets = Account::where('company_id', $company->id)->where('code', '1500')->first();

        $this->actingAs($owner)->post(route('app.accounts.store'), [
            'code' => '1501', 'name' => 'Vehicles', 'type' => 'asset', 'normal_balance' => 'debit',
            'parent_account_id' => $fixedAssets->id,
        ])->assertRedirect();

        $child = Account::where('company_id', $company->id)->where('code', '1501')->first();
        $this->assertSame($fixedAssets->id, $child->parent_account_id);

        $treePage = $this->actingAs($owner)->get(route('app.accounts.index', ['view' => 'tree']));
        $treePage->assertOk()->assertSee('1500')->assertSee('1501');

        $listPage = $this->actingAs($owner)->get(route('app.accounts.index', ['view' => 'list']));
        $listPage->assertOk()->assertSee('1501');
    }

    public function test_an_account_cannot_be_set_as_its_own_parent(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $account = Account::where('company_id', $company->id)->where('code', '1500')->first();

        $response = $this->actingAs($owner)->put(route('app.accounts.update', $account), [
            'code' => $account->code, 'name' => $account->name, 'type' => $account->type,
            'normal_balance' => $account->normal_balance, 'parent_account_id' => $account->id,
        ]);

        $response->assertSessionHasErrors('parent_account_id');
        $this->assertNull($account->fresh()->parent_account_id);
    }

    public function test_deleting_a_parent_account_leaves_its_children_as_top_level_accounts(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $parent = Account::create(['company_id' => $company->id, 'code' => '1991', 'name' => 'Custom Group', 'type' => 'asset', 'normal_balance' => 'debit']);
        $child = Account::create(['company_id' => $company->id, 'code' => '1992', 'name' => 'Custom Child', 'type' => 'asset', 'normal_balance' => 'debit', 'parent_account_id' => $parent->id]);

        $this->actingAs($owner)->delete(route('app.accounts.destroy', $parent))->assertRedirect();

        $this->assertDatabaseMissing('accounts', ['id' => $parent->id]);
        $this->assertNull($child->fresh()->parent_account_id);
    }

    public function test_a_system_account_can_still_only_have_its_name_and_parent_changed(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $cash = Account::where('company_id', $company->id)->where('code', '1000')->first();
        $assetsGroup = Account::create(['company_id' => $company->id, 'code' => '1990', 'name' => 'Current Assets Group', 'type' => 'asset', 'normal_balance' => 'debit']);

        $this->actingAs($owner)->put(route('app.accounts.update', $cash), [
            'code' => '9999', 'name' => 'Renamed Cash', 'name_ar' => null, 'type' => 'liability',
            'normal_balance' => 'credit', 'parent_account_id' => $assetsGroup->id,
        ])->assertRedirect();

        $cash->refresh();
        $this->assertSame('1000', $cash->code);
        $this->assertSame('asset', $cash->type);
        $this->assertSame('debit', $cash->normal_balance);
        $this->assertSame('Renamed Cash', $cash->name);
        $this->assertSame($assetsGroup->id, $cash->parent_account_id);
    }
}
