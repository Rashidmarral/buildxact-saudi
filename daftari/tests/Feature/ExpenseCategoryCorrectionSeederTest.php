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
 * please fix them all" / "remove the old fake categories please set
 * all according to our account system in our app" — every real
 * expense is checked against its own description and set to its
 * correct category under the operator's final category list.
 *
 * Also covers a real bug a live run surfaced: a missing target
 * category (because the old unguarded destroy() deleted it, silently
 * nulling every expense that used it via nullOnDelete) used to abort
 * the whole seeder partway through. Target categories are now
 * firstOrCreate'd up front instead.
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

        foreach (['Office Supplies', 'Utilities', 'Rent', 'Bill', 'Salaries & Wages', 'Repairs & Maintenance', 'Fuel', 'Management', 'Operating expense', 'Home Food', 'Home', 'VAT', 'Project', 'Miscellaneous', 'Transportation'] as $name) {
            ExpenseCategory::create(['company_id' => $company->id, 'name' => $name]);
        }

        return [$company, $project];
    }

    private function seedExpense(Company $company, BankAccount $bank, ?string $categoryName, array $overrides = []): Expense
    {
        $categoryId = $categoryName
            ? ExpenseCategory::where('company_id', $company->id)->where('name', $categoryName)->first()->id
            : null;

        $expense = Expense::create(array_merge([
            'company_id' => $company->id,
            'expense_category_id' => $categoryId,
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

    public function test_it_moves_the_warsha_bill_from_bill_to_repairs_and_maintenance(): void
    {
        [$company] = $this->seedCompany();
        $bank = BankAccount::create(['company_id' => $company->id, 'name' => 'Petty Cash', 'type' => 'cash', 'currency' => 'SAR', 'is_active' => true]);
        $expense = $this->seedExpense($company, $bank, 'Bill', ['description' => 'Paid Warsha Bill from company account', 'amount' => 345.00, 'gross_amount' => 345.00, 'expense_date' => '2026-09-28']);

        (new ExpenseCategoryCorrectionSeeder)->run();

        $expense->refresh();
        $this->assertSame('Repairs & Maintenance', $expense->category->name);
    }

    public function test_it_recreates_a_missing_target_category_instead_of_aborting(): void
    {
        [$company] = $this->seedCompany();
        // Simulates the real bug: "Utilities" was deleted out from under its expenses.
        ExpenseCategory::where('company_id', $company->id)->where('name', 'Utilities')->delete();
        $bank = BankAccount::create(['company_id' => $company->id, 'name' => 'Petty Cash', 'type' => 'cash', 'currency' => 'SAR', 'is_active' => true]);
        // Orphaned by the deletion above (nullOnDelete), exactly like the real database.
        $electric = $this->seedExpense($company, $bank, null, ['description' => 'Nasir Bhai paid Minaraten Hosh Electric bill from his account', 'amount' => 327.00, 'gross_amount' => 327.00, 'expense_date' => '2026-09-30']);
        // A later row in the list — proves the run doesn't stop at the missing category.
        $lab = $this->seedExpense($company, $bank, 'Operating expense', ['description' => 'this amount paid for lab by khalid shb', 'amount' => 500.00, 'gross_amount' => 500.00, 'expense_date' => '2026-10-12']);

        (new ExpenseCategoryCorrectionSeeder)->run();

        $electric->refresh();
        $this->assertNotNull(ExpenseCategory::where('company_id', $company->id)->where('name', 'Utilities')->first(), 'the missing category is recreated');
        $this->assertSame('Utilities', $electric->category->name, 'the orphaned expense is repaired, not left uncategorized');

        $lab->refresh();
        $this->assertSame('Project', $lab->category->name, 'processing continues past the missing category');
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

    public function test_it_moves_the_gosi_salary_deduction_to_salaries_and_wages(): void
    {
        [$company] = $this->seedCompany();
        $bank = BankAccount::create(['company_id' => $company->id, 'name' => 'Petty Cash', 'type' => 'cash', 'currency' => 'SAR', 'is_active' => true]);
        $expense = $this->seedExpense($company, $bank, 'Operating expense', ['description' => 'payment cut for salaries', 'amount' => 10.00, 'gross_amount' => 10.00, 'expense_date' => '2026-10-01']);

        (new ExpenseCategoryCorrectionSeeder)->run();

        $expense->refresh();
        $this->assertSame('Salaries & Wages', $expense->category->name);
    }

    public function test_it_moves_fuel_to_transportation(): void
    {
        [$company] = $this->seedCompany();
        $bank = BankAccount::create(['company_id' => $company->id, 'name' => 'Petty Cash', 'type' => 'cash', 'currency' => 'SAR', 'is_active' => true]);
        $expense = $this->seedExpense($company, $bank, 'Fuel', ['description' => 'Yaris Fuel for Masim Pump delivery and food', 'amount' => 60.00, 'gross_amount' => 60.00, 'expense_date' => '2026-10-17']);

        (new ExpenseCategoryCorrectionSeeder)->run();

        $expense->refresh();
        $this->assertSame('Transportation', $expense->category->name);
    }

    public function test_it_moves_ambiguous_cash_handouts_to_miscellaneous(): void
    {
        [$company] = $this->seedCompany();
        $bank = BankAccount::create(['company_id' => $company->id, 'name' => 'Petty Cash', 'type' => 'cash', 'currency' => 'SAR', 'is_active' => true]);
        $expense = $this->seedExpense($company, $bank, 'Operating expense', ['description' => 'Net Cash paid to sheraz', 'amount' => 500.00, 'gross_amount' => 500.00, 'expense_date' => '2026-09-28']);

        (new ExpenseCategoryCorrectionSeeder)->run();

        $expense->refresh();
        $this->assertSame('Miscellaneous', $expense->category->name);
    }

    public function test_it_reposts_the_gl_only_for_expenses_it_actually_moves(): void
    {
        [$company] = $this->seedCompany();
        $bank = BankAccount::create(['company_id' => $company->id, 'name' => 'Petty Cash', 'type' => 'cash', 'currency' => 'SAR', 'is_active' => true]);
        $moved = $this->seedExpense($company, $bank, 'Operating expense', ['description' => 'purchase 2 silicone and bolt keys refrence Yahya', 'amount' => 40.00, 'gross_amount' => 40.00, 'expense_date' => '2026-10-14']);
        $correct = $this->seedExpense($company, $bank, 'Office Supplies', ['description' => 'Printer paper and toner', 'amount' => 449.57, 'gross_amount' => 449.57, 'expense_date' => '2026-08-17']);

        (new ExpenseCategoryCorrectionSeeder)->run();

        $this->assertSame(1, JournalEntry::where('source_type', 'expense')->where('source_id', $moved->id)->count());
        // Untouched: still exactly its original posting, never reposted.
        $this->assertSame(1, JournalEntry::where('source_type', 'expense')->where('source_id', $correct->id)->count());
    }

    public function test_it_leaves_an_expense_not_on_the_canonical_list_alone(): void
    {
        [$company] = $this->seedCompany();
        $bank = BankAccount::create(['company_id' => $company->id, 'name' => 'Petty Cash', 'type' => 'cash', 'currency' => 'SAR', 'is_active' => true]);
        $expense = $this->seedExpense($company, $bank, 'Operating expense', ['description' => 'Some unrelated real cost entered after this list was written', 'amount' => 777.00, 'gross_amount' => 777.00, 'expense_date' => '2026-11-01']);

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
        $this->assertSame('Repairs & Maintenance', $expense->category->name);
    }
}
