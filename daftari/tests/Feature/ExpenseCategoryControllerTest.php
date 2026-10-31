<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "add proper expenses categories with code recommended by zatca" — a
 * real page to manage expense categories (name, code, grouping, GL
 * default, VAT/direct-cost flags) instead of the old inline
 * quick-add/delete-only list on the Expenses page.
 */
class ExpenseCategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): User
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting Company', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    public function test_the_index_page_lists_categories(): void
    {
        $owner = $this->makeOwner();
        ExpenseCategory::create(['company_id' => $owner->company_id, 'name' => 'Maintenance', 'code' => 'DIRECT-MAINT']);

        $response = $this->actingAs($owner)->get(route('app.expense-categories.index'));

        $response->assertOk();
        $response->assertSee('Maintenance');
        $response->assertSee('DIRECT-MAINT');
    }

    public function test_the_index_page_can_be_searched(): void
    {
        $owner = $this->makeOwner();
        ExpenseCategory::create(['company_id' => $owner->company_id, 'name' => 'Maintenance', 'code' => 'DIRECT-MAINT']);
        ExpenseCategory::create(['company_id' => $owner->company_id, 'name' => 'Rent', 'code' => 'ADMIN-RENT']);

        $response = $this->actingAs($owner)->get(route('app.expense-categories.index', ['search' => 'Rent']));

        $response->assertOk();
        $response->assertSee('Rent');
        $response->assertDontSee('Maintenance');
    }

    public function test_it_creates_a_category_with_a_code_and_gl_link(): void
    {
        $owner = $this->makeOwner();
        $cogs = Account::where('company_id', $owner->company_id)->where('code', '5000')->first();

        $response = $this->actingAs($owner)->post(route('app.expense-categories.store'), [
            'name' => 'Fuel', 'code' => 'DIRECT-FUEL', 'linked_account_id' => $cogs->id,
            'is_direct_cost' => '1', 'requires_project' => '1', 'vat_eligible' => '1', 'is_active' => '1',
        ]);

        $response->assertRedirect(route('app.expense-categories.index'));
        $category = ExpenseCategory::where('company_id', $owner->company_id)->where('name', 'Fuel')->first();
        $this->assertNotNull($category);
        $this->assertSame('DIRECT-FUEL', $category->code);
        $this->assertSame($cogs->id, $category->linked_account_id);
        $this->assertTrue((bool) $category->is_direct_cost);
    }

    public function test_it_rejects_a_duplicate_code_within_the_same_company(): void
    {
        $owner = $this->makeOwner();
        ExpenseCategory::create(['company_id' => $owner->company_id, 'name' => 'Rent', 'code' => 'ADMIN-RENT']);

        $response = $this->actingAs($owner)->post(route('app.expense-categories.store'), [
            'name' => 'Office supplies', 'code' => 'ADMIN-RENT',
        ]);

        $response->assertSessionHasErrors('code');
        $this->assertSame(1, ExpenseCategory::where('company_id', $owner->company_id)->where('code', 'ADMIN-RENT')->count());
    }

    public function test_it_updates_a_category_keeping_its_own_code_valid(): void
    {
        $owner = $this->makeOwner();
        $category = ExpenseCategory::create(['company_id' => $owner->company_id, 'name' => 'Rent', 'code' => 'ADMIN-RENT']);

        $response = $this->actingAs($owner)->put(route('app.expense-categories.update', $category), [
            'name' => 'Rent (Warehouse)', 'code' => 'ADMIN-RENT',
        ]);

        $response->assertRedirect(route('app.expense-categories.index'));
        $this->assertSame('Rent (Warehouse)', $category->fresh()->name);
    }

    public function test_it_deletes_a_category_with_no_expenses(): void
    {
        $owner = $this->makeOwner();
        $category = ExpenseCategory::create(['company_id' => $owner->company_id, 'name' => 'Unused']);

        $response = $this->actingAs($owner)->delete(route('app.expense-categories.destroy', $category));

        $response->assertRedirect(route('app.expense-categories.index'));
        $this->assertNull(ExpenseCategory::find($category->id));
    }

    public function test_it_refuses_to_delete_a_category_with_expenses(): void
    {
        $owner = $this->makeOwner();
        $category = ExpenseCategory::create(['company_id' => $owner->company_id, 'name' => 'Fuel']);
        $bank = BankAccount::create(['company_id' => $owner->company_id, 'name' => 'Petty Cash', 'type' => 'cash', 'currency' => 'SAR', 'is_active' => true]);
        $expense = Expense::create([
            'company_id' => $owner->company_id, 'expense_category_id' => $category->id, 'bank_account_id' => $bank->id,
            'description' => 'Fuel cost', 'amount' => 50, 'gross_amount' => 50, 'vat_amount' => 0,
            'tax_category' => 'zero_rated', 'status' => 'approved', 'expense_date' => now()->toDateString(),
        ]);
        app(LedgerPostingService::class)->postExpense($expense->fresh(['account', 'category', 'bankAccount']));

        $response = $this->actingAs($owner)->delete(route('app.expense-categories.destroy', $category));

        $response->assertSessionHasErrors('expense_category');
        $this->assertNotNull(ExpenseCategory::find($category->id));
    }

    public function test_it_refuses_to_delete_a_category_with_child_categories(): void
    {
        $owner = $this->makeOwner();
        $parent = ExpenseCategory::create(['company_id' => $owner->company_id, 'name' => 'Direct Project Costs']);
        ExpenseCategory::create(['company_id' => $owner->company_id, 'name' => 'Fuel', 'parent_category_id' => $parent->id]);

        $response = $this->actingAs($owner)->delete(route('app.expense-categories.destroy', $parent));

        $response->assertSessionHasErrors('expense_category');
        $this->assertNotNull(ExpenseCategory::find($parent->id));
    }
}
