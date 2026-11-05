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
use Database\Seeders\FakeInvoiceCostReclassificationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Master Report, Section 12: the real SAR 1,800.00 cost of acquiring
 * the fake BILL-00003 is a real cash outflow, not deductible — this
 * moves it to Non-Deductible Expenses without touching the cash side.
 */
class FakeInvoiceCostReclassificationSeederTest extends TestCase
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
        $category = ExpenseCategory::firstOrCreate(['company_id' => $company->id, 'name' => 'VAT']);

        $expense = Expense::create(array_merge([
            'company_id' => $company->id,
            'expense_category_id' => $category->id,
            'bank_account_id' => $bank->id,
            'vendor_name' => 'East West Establishment',
            'description' => 'Eng Khalid paid net cash for invoice issuing from East West Establishment',
            'amount' => 1800.00,
            'gross_amount' => 1800.00,
            'vat_amount' => 0,
            'tax_category' => 'zero_rated',
            'status' => 'approved',
            'expense_date' => '2026-09-30',
        ], $overrides));

        app(LedgerPostingService::class)->postExpense($expense->fresh(['account', 'bankAccount']));

        return $expense;
    }

    public function test_it_does_nothing_when_the_company_does_not_exist(): void
    {
        (new FakeInvoiceCostReclassificationSeeder)->run();

        $this->assertSame(0, Expense::count());
    }

    public function test_it_creates_the_non_deductible_expenses_account(): void
    {
        $company = $this->seedCompany();

        (new FakeInvoiceCostReclassificationSeeder)->run();

        $account = Account::where('company_id', $company->id)->where('code', '5900')->first();
        $this->assertNotNull($account);
        $this->assertSame('expense', $account->type);
    }

    public function test_it_reclassifies_the_matching_expense_without_touching_cash(): void
    {
        $company = $this->seedCompany();
        $bank = BankAccount::create(['company_id' => $company->id, 'name' => 'SNB — Operating Account', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true]);
        $expense = $this->seedExpense($company, $bank);

        $cashBefore = JournalEntryLine::whereHas('journalEntry', fn ($q) => $q->where('source_type', 'expense')->where('source_id', $expense->id))
            ->whereHas('account', fn ($q) => $q->whereIn('code', ['1000', '1100']))
            ->sum('credit');

        (new FakeInvoiceCostReclassificationSeeder)->run();

        $expense->refresh();
        $nonDeductible = Account::where('company_id', $company->id)->where('code', '5900')->first();
        $this->assertSame($nonDeductible->id, $expense->account_id);

        $journalEntry = JournalEntry::where('source_type', 'expense')->where('source_id', $expense->id)->first();
        $this->assertNotNull($journalEntry);
        $line = JournalEntryLine::where('journal_entry_id', $journalEntry->id)->where('account_id', $nonDeductible->id)->first();
        $this->assertNotNull($line);
        $this->assertEqualsWithDelta(1800.00, (float) $line->debit, 0.01);

        $operatingExpense = Account::where('company_id', $company->id)->where('code', '5100')->first();
        $this->assertNull(JournalEntryLine::where('journal_entry_id', $journalEntry->id)->where('account_id', $operatingExpense->id)->first());

        $cashAfter = JournalEntryLine::whereHas('journalEntry', fn ($q) => $q->where('source_type', 'expense')->where('source_id', $expense->id))
            ->whereHas('account', fn ($q) => $q->whereIn('code', ['1000', '1100']))
            ->sum('credit');
        $this->assertEqualsWithDelta((float) $cashBefore, (float) $cashAfter, 0.01);
    }

    public function test_it_leaves_an_unmatched_expense_alone(): void
    {
        $company = $this->seedCompany();
        $bank = BankAccount::create(['company_id' => $company->id, 'name' => 'SNB — Operating Account', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true]);
        $expense = $this->seedExpense($company, $bank, ['description' => 'Unrelated VAT-category cost', 'amount' => 50.00, 'gross_amount' => 50.00]);

        (new FakeInvoiceCostReclassificationSeeder)->run();

        $expense->refresh();
        $this->assertNull($expense->account_id);
    }

    public function test_reseeding_does_not_reclassify_twice(): void
    {
        $company = $this->seedCompany();
        $bank = BankAccount::create(['company_id' => $company->id, 'name' => 'SNB — Operating Account', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true]);
        $expense = $this->seedExpense($company, $bank);

        (new FakeInvoiceCostReclassificationSeeder)->run();
        (new FakeInvoiceCostReclassificationSeeder)->run();

        $this->assertSame(1, JournalEntry::where('source_type', 'expense')->where('source_id', $expense->id)->count());
    }
}
