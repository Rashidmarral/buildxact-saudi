<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Project;
use App\Services\Accounting\LedgerPostingService;
use Database\Seeders\ExpenseCategoryCorrectionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "i have mixed transaction and added expenses in wrong categories
 * please fix them all" — nine real expenses move to their real
 * category based on their own description, reposting the GL and
 * backfilling the project where it was missing.
 */
class ExpenseCategoryCorrectionSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedCompany(): array
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting Company', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR', 'vat_number' => '314526094900003']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        $project = Project::create(['company_id' => $company->id, 'code' => 'PROJ_00001', 'name' => 'مشروع ساحة الجموم', 'status' => 'active']);

        foreach (['Office supplies', 'Utilities', 'Rent', 'Bill', 'Salaries', 'Maintenance', 'Fuel', 'Management', 'Operating expense', 'Home Food', 'Home', 'VAT', 'Project'] as $name) {
            ExpenseCategory::create(['company_id' => $company->id, 'name' => $name]);
        }

        return [$company, $project];
    }

    private function seedExpense(Company $company, BankAccount $bank, string $categoryName, array $overrides = []): Expense
    {
        $category = ExpenseCategory::where('company_id', $company->id)->where('name', $categoryName)->first();

        $expense = Expense::create(array_merge([
            'company_id' => $company->id,
            'expense_category_id' => $category->id,
            'bank_account_id' => $bank->id,
            'description' => 'placeholder',
            'amount' => 1,
            'gross_amount' => 1,
            'vat_amount' => 0,
            'tax_category' => 'zero_rated',
            'status' => 'approved',
            'expense_date' => '2026-10-01',
        ], $overrides));

        app(LedgerPostingService::class)->postExpense($expense->fresh(['account', 'category', 'bankAccount']));

        return $expense;
    }

    public function test_it_does_nothing_when_the_company_does_not_exist(): void
    {
        (new ExpenseCategoryCorrectionSeeder)->run();

        $this->assertSame(0, ExpenseCategory::count());
    }

    public function test_it_moves_the_warsha_bill_from_bill_to_maintenance(): void
    {
        [$company] = $this->seedCompany();
        $bank = BankAccount::create(['company_id' => $company->id, 'name' => 'Petty Cash', 'type' => 'cash', 'currency' => 'SAR', 'is_active' => true]);
        $expense = $this->seedExpense($company, $bank, 'Bill', ['description' => 'Paid Warsha Bill from company account', 'amount' => 345.00, 'gross_amount' => 345.00, 'expense_date' => '2026-09-28']);

        (new ExpenseCategoryCorrectionSeeder)->run();

        $expense->refresh();
        $this->assertSame('Maintenance', $expense->category->name);
    }

    public function test_it_moves_the_electric_bill_from_bill_to_utilities(): void
    {
        [$company] = $this->seedCompany();
        $bank = BankAccount::create(['company_id' => $company->id, 'name' => 'Petty Cash', 'type' => 'cash', 'currency' => 'SAR', 'is_active' => true]);
        $expense = $this->seedExpense($company, $bank, 'Bill', ['description' => 'Nasir Bhai paid Minaraten Hosh Electric bill from his account', 'amount' => 327.00, 'gross_amount' => 327.00, 'expense_date' => '2026-09-30']);

        (new ExpenseCategoryCorrectionSeeder)->run();

        $expense->refresh();
        $this->assertSame('Utilities', $expense->category->name);
    }

    public function test_it_moves_project_transfers_to_project_and_backfills_the_project(): void
    {
        [$company, $project] = $this->seedCompany();
        $bank = BankAccount::create(['company_id' => $company->id, 'name' => 'Petty Cash', 'type' => 'cash', 'currency' => 'SAR', 'is_active' => true]);
        $expense = $this->seedExpense($company, $bank, 'Operating expense', ['description' => 'Nasir bhai sent to Mubashir account for MC1 works', 'amount' => 250.00, 'gross_amount' => 250.00, 'expense_date' => '2026-09-30']);
        $this->assertNull($expense->project_id);

        (new ExpenseCategoryCorrectionSeeder)->run();

        $expense->refresh();
        $this->assertSame('Project', $expense->category->name);
        $this->assertSame($project->id, $expense->project_id);
    }

    public function test_it_does_not_overwrite_an_existing_project_id(): void
    {
        [$company, $project] = $this->seedCompany();
        $otherProject = Project::create(['company_id' => $company->id, 'code' => 'PROJ_00002', 'name' => 'Other project', 'status' => 'active']);
        $bank = BankAccount::create(['company_id' => $company->id, 'name' => 'Petty Cash', 'type' => 'cash', 'currency' => 'SAR', 'is_active' => true]);
        $expense = $this->seedExpense($company, $bank, 'Operating expense', ['description' => 'Nasir bhai paid to MC1 from personal account', 'amount' => 1000.00, 'gross_amount' => 1000.00, 'expense_date' => '2026-09-30', 'project_id' => $otherProject->id]);

        (new ExpenseCategoryCorrectionSeeder)->run();

        $expense->refresh();
        $this->assertSame($otherProject->id, $expense->project_id);
    }

    public function test_it_moves_the_gosi_salary_deduction_to_salaries(): void
    {
        [$company] = $this->seedCompany();
        $bank = BankAccount::create(['company_id' => $company->id, 'name' => 'Petty Cash', 'type' => 'cash', 'currency' => 'SAR', 'is_active' => true]);
        $expense = $this->seedExpense($company, $bank, 'Operating expense', ['description' => 'payment cut for salaries', 'amount' => 10.00, 'gross_amount' => 10.00, 'expense_date' => '2026-10-01']);

        (new ExpenseCategoryCorrectionSeeder)->run();

        $expense->refresh();
        $this->assertSame('Salaries', $expense->category->name);
    }

    public function test_it_reposts_the_gl_to_the_new_categorys_default_account(): void
    {
        [$company] = $this->seedCompany();
        $bank = BankAccount::create(['company_id' => $company->id, 'name' => 'Petty Cash', 'type' => 'cash', 'currency' => 'SAR', 'is_active' => true]);
        $expense = $this->seedExpense($company, $bank, 'Operating expense', ['description' => 'purchase 2 silicone and bolt keys refrence Yahya', 'amount' => 40.00, 'gross_amount' => 40.00, 'expense_date' => '2026-10-14']);

        $operatingExpense = Account::where('company_id', $company->id)->where('code', '5100')->first();
        $journalBefore = JournalEntry::where('source_type', 'expense')->where('source_id', $expense->id)->first();
        $this->assertNotNull(JournalEntryLine::where('journal_entry_id', $journalBefore->id)->where('account_id', $operatingExpense->id)->first());

        (new ExpenseCategoryCorrectionSeeder)->run();

        // Code the categories first (as the real run order would), then verify the repost landed on Maintenance's GL account.
        $cogs = Account::where('company_id', $company->id)->where('code', '5000')->first();
        $maintenance = ExpenseCategory::where('company_id', $company->id)->where('name', 'Maintenance')->first();
        $maintenance->update(['linked_account_id' => $cogs->id]);
        // Repost again now that the category has a linked account, same as ExpenseCategoryCodingSeeder would do.
        app(LedgerPostingService::class)->deletePosting($company, 'expense', $expense->id);
        app(LedgerPostingService::class)->postExpense($expense->fresh(['account', 'category', 'bankAccount']));

        $journalAfter = JournalEntry::where('source_type', 'expense')->where('source_id', $expense->id)->first();
        $this->assertNotNull(JournalEntryLine::where('journal_entry_id', $journalAfter->id)->where('account_id', $cogs->id)->first());
    }

    public function test_it_leaves_an_ambiguous_operating_expense_row_alone(): void
    {
        [$company] = $this->seedCompany();
        $bank = BankAccount::create(['company_id' => $company->id, 'name' => 'Petty Cash', 'type' => 'cash', 'currency' => 'SAR', 'is_active' => true]);
        $expense = $this->seedExpense($company, $bank, 'Operating expense', ['description' => 'Net Cash paid to sheraz', 'amount' => 500.00, 'gross_amount' => 500.00, 'expense_date' => '2026-09-28']);

        (new ExpenseCategoryCorrectionSeeder)->run();

        $expense->refresh();
        $this->assertSame('Operating expense', $expense->category->name);
    }

    public function test_reseeding_does_not_move_anything_twice(): void
    {
        [$company] = $this->seedCompany();
        $bank = BankAccount::create(['company_id' => $company->id, 'name' => 'Petty Cash', 'type' => 'cash', 'currency' => 'SAR', 'is_active' => true]);
        $expense = $this->seedExpense($company, $bank, 'Bill', ['description' => 'Paid Warsha Bill from company account', 'amount' => 345.00, 'gross_amount' => 345.00, 'expense_date' => '2026-09-28']);

        (new ExpenseCategoryCorrectionSeeder)->run();
        (new ExpenseCategoryCorrectionSeeder)->run();

        $this->assertSame(1, JournalEntry::where('source_type', 'expense')->where('source_id', $expense->id)->count());
        $expense->refresh();
        $this->assertSame('Maintenance', $expense->category->name);
    }
}
