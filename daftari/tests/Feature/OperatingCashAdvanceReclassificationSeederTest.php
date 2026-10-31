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
use App\Services\Accounting\LedgerPostingService;
use Database\Seeders\OperatingCashAdvanceReclassificationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Master Report, Section 12: the user confirmed the 9 "Operating
 * expense" cash-to-individual rows (SAR 16,350.00) are cash still
 * sitting with the person it was given to — this moves them to Due
 * from Employees (Advances) without touching the cash side.
 */
class OperatingCashAdvanceReclassificationSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedCompany(): Company
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting Company', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR', 'vat_number' => '314526094900003']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return $company;
    }

    private function seedExpense(Company $company, BankAccount $bank, array $overrides = []): Expense
    {
        $category = ExpenseCategory::firstOrCreate(['company_id' => $company->id, 'name' => $overrides['category'] ?? 'Operating expense']);

        $expense = Expense::create(array_merge([
            'company_id' => $company->id,
            'expense_category_id' => $category->id,
            'bank_account_id' => $bank->id,
            'description' => 'Net Cash paid to sheraz',
            'amount' => 500.00,
            'gross_amount' => 500.00,
            'vat_amount' => 0,
            'tax_category' => 'zero_rated',
            'status' => 'approved',
            'expense_date' => '2026-09-28',
        ], array_diff_key($overrides, ['category' => null])));

        app(LedgerPostingService::class)->postExpense($expense->fresh(['account', 'bankAccount']));

        return $expense;
    }

    public function test_it_does_nothing_when_the_company_does_not_exist(): void
    {
        (new OperatingCashAdvanceReclassificationSeeder)->run();

        $this->assertSame(0, Account::where('code', '1260')->count());
    }

    public function test_it_reclassifies_all_twelve_matching_rows_without_touching_cash(): void
    {
        $company = $this->seedCompany();
        $bank = BankAccount::create(['company_id' => $company->id, 'name' => 'Petty Cash', 'type' => 'cash', 'currency' => 'SAR', 'is_active' => true]);

        $rows = [
            ['category' => 'Operating expense', 'description' => 'Net Cash paid to sheraz', 'amount' => 500.00, 'expense_date' => '2026-09-28'],
            ['category' => 'Operating expense', 'description' => 'Net cash paid to Khalid shb Saudi', 'amount' => 2000.00, 'expense_date' => '2026-09-29'],
            ['category' => 'Operating expense', 'description' => 'Net cash paid to Rashid', 'amount' => 1000.00, 'expense_date' => '2026-09-30'],
            ['category' => 'Operating expense', 'description' => 'Net cash paid to Eng Khalid Shb', 'amount' => 10000.00, 'expense_date' => '2026-09-29'],
            ['category' => 'Operating expense', 'description' => 'payment cut for salaries', 'amount' => 10.00, 'expense_date' => '2026-10-01'],
            ['category' => 'Operating expense', 'description' => 'Nasir bhai paid to MC1 from personal account', 'amount' => 1000.00, 'expense_date' => '2026-09-30'],
            ['category' => 'Operating expense', 'description' => 'Nasir bhai sent to Mubashir account for MC1 works', 'amount' => 250.00, 'expense_date' => '2026-09-30'],
            ['category' => 'Operating expense', 'description' => 'Nasir bhai online transfer to Azmat Six wala account', 'amount' => 500.00, 'expense_date' => '2026-09-29'],
            ['category' => 'Management', 'description' => 'Nasir bhai withdrawal cash from his account and Paid to Khalid Saudi Cash', 'amount' => 500.00, 'expense_date' => '2026-09-30'],
            ['category' => 'Operating expense', 'description' => 'Paid to Sheraz for food and spare parts balance', 'amount' => 50.00, 'expense_date' => '2026-10-12'],
            ['category' => 'Operating expense', 'description' => 'purchase 2 silicone and bolt keys refrence Yahya', 'amount' => 40.00, 'expense_date' => '2026-10-14'],
            ['category' => 'Operating expense', 'description' => 'this amount paid for lab by khalid shb', 'amount' => 500.00, 'expense_date' => '2026-10-12'],
        ];

        $expenses = [];
        foreach ($rows as $row) {
            $expenses[] = $this->seedExpense($company, $bank, array_merge($row, ['gross_amount' => $row['amount']]));
        }

        $cashBefore = JournalEntryLine::whereHas('journalEntry', fn ($q) => $q->where('company_id', $company->id))
            ->whereHas('account', fn ($q) => $q->whereIn('code', ['1000', '1100']))
            ->sum('credit');

        (new OperatingCashAdvanceReclassificationSeeder)->run();

        $advances = Account::where('company_id', $company->id)->where('code', '1260')->first();
        $this->assertNotNull($advances);
        $this->assertSame('asset', $advances->type);
        $this->assertSame('debit', $advances->normal_balance);

        $totalDebited = 0.0;
        foreach ($expenses as $expense) {
            $expense->refresh();
            $this->assertSame($advances->id, $expense->account_id);

            $journalEntry = JournalEntry::where('source_type', 'expense')->where('source_id', $expense->id)->first();
            $this->assertNotNull($journalEntry);
            $line = JournalEntryLine::where('journal_entry_id', $journalEntry->id)->where('account_id', $advances->id)->first();
            $this->assertNotNull($line);
            $totalDebited += (float) $line->debit;
        }
        $this->assertEqualsWithDelta(16350.00, $totalDebited, 0.01);

        $cashAfter = JournalEntryLine::whereHas('journalEntry', fn ($q) => $q->where('company_id', $company->id))
            ->whereHas('account', fn ($q) => $q->whereIn('code', ['1000', '1100']))
            ->sum('credit');
        $this->assertEqualsWithDelta((float) $cashBefore, (float) $cashAfter, 0.01);
    }

    public function test_it_leaves_an_unmatched_expense_alone(): void
    {
        $company = $this->seedCompany();
        $bank = BankAccount::create(['company_id' => $company->id, 'name' => 'Petty Cash', 'type' => 'cash', 'currency' => 'SAR', 'is_active' => true]);
        $expense = $this->seedExpense($company, $bank, ['description' => 'Completely unrelated real business cost', 'amount' => 999.00, 'gross_amount' => 999.00]);

        (new OperatingCashAdvanceReclassificationSeeder)->run();

        $expense->refresh();
        $this->assertNull($expense->account_id);
    }

    public function test_reseeding_does_not_reclassify_twice(): void
    {
        $company = $this->seedCompany();
        $bank = BankAccount::create(['company_id' => $company->id, 'name' => 'Petty Cash', 'type' => 'cash', 'currency' => 'SAR', 'is_active' => true]);
        $expense = $this->seedExpense($company, $bank);

        (new OperatingCashAdvanceReclassificationSeeder)->run();
        (new OperatingCashAdvanceReclassificationSeeder)->run();

        $this->assertSame(1, JournalEntry::where('source_type', 'expense')->where('source_id', $expense->id)->count());
    }
}
