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
use Database\Seeders\VatPaymentSettlementReclassificationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Master Report, Section 11 #11: the real SAR 3,113.41 "Paid Company
 * vat" expense settles a liability, not a new cost — this moves it
 * from Operating Expenses to VAT Output without touching the cash side.
 */
class VatPaymentSettlementReclassificationSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedCompany(): Company
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting Company', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR', 'vat_number' => '314526094900003']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return $company;
    }

    private function seedVatExpense(Company $company, BankAccount $bank, array $overrides = []): Expense
    {
        $category = ExpenseCategory::firstOrCreate(['company_id' => $company->id, 'name' => 'VAT']);

        $expense = Expense::create(array_merge([
            'company_id' => $company->id,
            'expense_category_id' => $category->id,
            'bank_account_id' => $bank->id,
            'description' => 'Paid Company vat',
            'amount' => 3113.41,
            'gross_amount' => 3113.41,
            'vat_amount' => 0,
            'tax_category' => 'zero_rated',
            'status' => 'approved',
            'expense_date' => '2026-10-06',
        ], $overrides));

        app(LedgerPostingService::class)->postExpense($expense->fresh(['account', 'bankAccount']));

        return $expense;
    }

    public function test_it_does_nothing_when_the_company_does_not_exist(): void
    {
        (new VatPaymentSettlementReclassificationSeeder)->run();

        $this->assertSame(0, Expense::count());
    }

    public function test_it_resettles_the_vat_payment_without_touching_the_cash_side(): void
    {
        $company = $this->seedCompany();
        $bank = BankAccount::create(['company_id' => $company->id, 'name' => 'SNB — Operating Account', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true]);
        $expense = $this->seedVatExpense($company, $bank);

        $cashBefore = JournalEntryLine::whereHas('journalEntry', fn ($q) => $q->where('source_type', 'expense')->where('source_id', $expense->id))
            ->whereHas('account', fn ($q) => $q->whereIn('code', ['1000', '1100']))
            ->sum('credit');

        (new VatPaymentSettlementReclassificationSeeder)->run();

        $expense->refresh();
        $vatOutput = Account::where('company_id', $company->id)->where('code', '2100')->first();
        $this->assertSame($vatOutput->id, $expense->account_id);

        $journalEntry = JournalEntry::where('source_type', 'expense')->where('source_id', $expense->id)->first();
        $this->assertNotNull($journalEntry);

        $vatOutputLine = JournalEntryLine::where('journal_entry_id', $journalEntry->id)->where('account_id', $vatOutput->id)->first();
        $this->assertNotNull($vatOutputLine);
        $this->assertEqualsWithDelta(3113.41, (float) $vatOutputLine->debit, 0.01);

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
        $expense = $this->seedVatExpense($company, $bank, ['description' => 'Unrelated VAT-category cost', 'amount' => 50.00, 'gross_amount' => 50.00]);

        (new VatPaymentSettlementReclassificationSeeder)->run();

        $expense->refresh();
        $this->assertNull($expense->account_id);
    }

    public function test_reseeding_does_not_resettle_twice(): void
    {
        $company = $this->seedCompany();
        $bank = BankAccount::create(['company_id' => $company->id, 'name' => 'SNB — Operating Account', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true]);
        $expense = $this->seedVatExpense($company, $bank);

        (new VatPaymentSettlementReclassificationSeeder)->run();
        (new VatPaymentSettlementReclassificationSeeder)->run();

        $this->assertSame(1, JournalEntry::where('source_type', 'expense')->where('source_id', $expense->id)->count());
    }
}
