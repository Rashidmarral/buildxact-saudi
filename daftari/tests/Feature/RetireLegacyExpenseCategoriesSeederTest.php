<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\JournalEntry;
use App\Services\Accounting\LedgerPostingService;
use Database\Seeders\RetireLegacyExpenseCategoriesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "please remove my added categories because i have create them
 * randomly i don't know the accounts... remove the old fake
 * categories" — the last step: delete whatever's left over once
 * nothing real points at it, merge the one confirmed accidental
 * duplicate ("Rent" x2) rather than delete it blind, and never touch
 * a category that still has real expenses.
 */
class RetireLegacyExpenseCategoriesSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedCompany(): Company
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting Company', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR', 'vat_number' => '314526094900003']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return $company;
    }

    public function test_it_does_nothing_when_the_company_does_not_exist(): void
    {
        (new RetireLegacyExpenseCategoriesSeeder)->run();

        $this->assertSame(0, ExpenseCategory::count());
    }

    public function test_it_deletes_an_empty_category_outside_the_final_list(): void
    {
        $company = $this->seedCompany();
        ExpenseCategory::create(['company_id' => $company->id, 'name' => 'Random Category I Made Up']);

        (new RetireLegacyExpenseCategoriesSeeder)->run();

        $this->assertNull(ExpenseCategory::where('company_id', $company->id)->where('name', 'Random Category I Made Up')->first());
    }

    public function test_it_keeps_a_final_list_category_even_if_empty(): void
    {
        $company = $this->seedCompany();
        ExpenseCategory::create(['company_id' => $company->id, 'name' => 'Travel']);

        (new RetireLegacyExpenseCategoriesSeeder)->run();

        $this->assertNotNull(ExpenseCategory::where('company_id', $company->id)->where('name', 'Travel')->first());
    }

    public function test_it_refuses_to_delete_a_category_that_still_has_expenses(): void
    {
        $company = $this->seedCompany();
        $bank = BankAccount::create(['company_id' => $company->id, 'name' => 'Petty Cash', 'type' => 'cash', 'currency' => 'SAR', 'is_active' => true]);
        $category = ExpenseCategory::create(['company_id' => $company->id, 'name' => 'Operating expense']);
        $expense = Expense::create([
            'company_id' => $company->id, 'expense_category_id' => $category->id, 'bank_account_id' => $bank->id,
            'description' => 'Still real, not yet moved', 'amount' => 100, 'gross_amount' => 100, 'vat_amount' => 0,
            'tax_category' => 'zero_rated', 'status' => 'approved', 'expense_date' => '2026-10-01',
        ]);
        app(LedgerPostingService::class)->postExpense($expense->fresh(['account', 'category', 'bankAccount']));

        (new RetireLegacyExpenseCategoriesSeeder)->run();

        $this->assertNotNull(ExpenseCategory::where('company_id', $company->id)->where('name', 'Operating expense')->first());
    }

    public function test_it_merges_the_duplicate_rent_row_without_losing_its_expense(): void
    {
        $company = $this->seedCompany();
        $bank = BankAccount::create(['company_id' => $company->id, 'name' => 'Petty Cash', 'type' => 'cash', 'currency' => 'SAR', 'is_active' => true]);
        $realRent = ExpenseCategory::create(['company_id' => $company->id, 'name' => 'Rent']);
        $duplicateRent = ExpenseCategory::create(['company_id' => $company->id, 'name' => 'Rent']);
        $expense = Expense::create([
            'company_id' => $company->id, 'expense_category_id' => $duplicateRent->id, 'bank_account_id' => $bank->id,
            'description' => 'Accidentally booked under the duplicate Rent row', 'amount' => 500, 'gross_amount' => 500, 'vat_amount' => 0,
            'tax_category' => 'zero_rated', 'status' => 'approved', 'expense_date' => '2026-10-01',
        ]);
        app(LedgerPostingService::class)->postExpense($expense->fresh(['account', 'category', 'bankAccount']));

        (new RetireLegacyExpenseCategoriesSeeder)->run();

        $this->assertNull(ExpenseCategory::find($duplicateRent->id));
        $this->assertNotNull(ExpenseCategory::find($realRent->id));
        $expense->refresh();
        $this->assertSame($realRent->id, $expense->expense_category_id);
        $this->assertSame(1, JournalEntry::where('source_type', 'expense')->where('source_id', $expense->id)->count());
    }

    public function test_it_deletes_the_empty_duplicate_rent_row_with_no_expenses(): void
    {
        $company = $this->seedCompany();
        ExpenseCategory::create(['company_id' => $company->id, 'name' => 'Rent']);
        $duplicate = ExpenseCategory::create(['company_id' => $company->id, 'name' => 'Rent']);

        (new RetireLegacyExpenseCategoriesSeeder)->run();

        $this->assertSame(1, ExpenseCategory::where('company_id', $company->id)->where('name', 'Rent')->count());
        $this->assertNull(ExpenseCategory::find($duplicate->id));
    }

    public function test_reseeding_does_not_error_once_everything_is_already_clean(): void
    {
        $company = $this->seedCompany();
        ExpenseCategory::create(['company_id' => $company->id, 'name' => 'Rent']);

        (new RetireLegacyExpenseCategoriesSeeder)->run();
        (new RetireLegacyExpenseCategoriesSeeder)->run();

        $this->assertSame(1, ExpenseCategory::where('company_id', $company->id)->where('name', 'Rent')->count());
    }
}
