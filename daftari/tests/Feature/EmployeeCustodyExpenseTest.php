<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\PaymentVoucher;
use App\Models\ReceiptVoucher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "i have withdrawl amount and keep it as petty cash... paid to khalid
 * 10000 then i have record expenses for that 10000 cash of khalid
 * account where amount used then i open ecpenses list it shows expense
 * tranfer to khalid 10000 and also show khalid account expenses in the
 * same list i need to get exact expenses that is used for company
 * expenses" — handing cash to a custodian isn't a company expense yet
 * (nothing has been bought), but with no "Due from Employees" account,
 * a Payment Voucher to an employee with nothing else attached posted
 * straight to Operating Expenses by default, and a Receipt Voucher
 * repaying it posted to Other Income — both real GL miscategorizations,
 * not just a display problem. Expense::employee_id now lets the real
 * downstream purchase settle against that same advance instead of
 * hitting a bank account a second time.
 */
class EmployeeCustodyExpenseTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    private function makeEmployee(Company $company): Employee
    {
        return Employee::create([
            'company_id' => $company->id, 'employee_number' => 'EMP-'.uniqid(),
            'full_name' => 'Khalid', 'hire_date' => now()->subYear()->toDateString(), 'status' => 'active',
        ]);
    }

    public function test_advancing_cash_to_an_employee_debits_due_from_employees_not_operating_expenses(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $operating = BankAccount::create(['company_id' => $company->id, 'name' => 'Operating Account', 'type' => 'bank', 'is_active' => true]);
        $khalid = $this->makeEmployee($company);

        $this->actingAs($owner)->post(route('app.payment-vouchers.store'), [
            'party_type' => 'employee', 'employee_id' => $khalid->id,
            'payee_name' => $khalid->full_name, 'bank_account_id' => $operating->id,
            'date' => now()->toDateString(), 'amount' => 10000, 'method' => 'cash',
        ])->assertRedirect();

        $voucher = PaymentVoucher::first();
        $entry = JournalEntry::where('source_type', 'payment_voucher')->where('source_id', $voucher->id)->first();
        $this->assertNotNull($entry);

        $debitLine = $entry->lines()->where('debit', '>', 0)->first();
        $this->assertSame('1260', $debitLine->account->code);
        $this->assertEqualsWithDelta(10000, (float) $debitLine->debit, 0.01);

        // Not a company expense yet — nothing was actually bought.
        $this->assertSame(0, Account::where('company_id', $company->id)->where('code', '5100')->first()
            ->journalEntryLines()->whereHas('journalEntry', fn ($q) => $q->where('source_type', 'payment_voucher'))->count());
    }

    public function test_an_employee_returning_unused_cash_credits_due_from_employees_not_other_income(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $operating = BankAccount::create(['company_id' => $company->id, 'name' => 'Operating Account', 'type' => 'bank', 'is_active' => true]);
        $khalid = $this->makeEmployee($company);

        $this->actingAs($owner)->post(route('app.receipt-vouchers.store'), [
            'party_type' => 'employee', 'employee_id' => $khalid->id,
            'payer_name' => $khalid->full_name, 'bank_account_id' => $operating->id,
            'date' => now()->toDateString(), 'amount' => 1500, 'method' => 'cash',
        ])->assertRedirect();

        $voucher = ReceiptVoucher::first();
        $entry = JournalEntry::where('source_type', 'receipt_voucher')->where('source_id', $voucher->id)->first();
        $creditLine = $entry->lines()->where('credit', '>', 0)->first();

        $this->assertSame('1260', $creditLine->account->code);
        $this->assertEqualsWithDelta(1500, (float) $creditLine->credit, 0.01);
    }

    public function test_a_real_purchase_from_an_employees_custody_settles_against_their_advance(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $operating = BankAccount::create(['company_id' => $company->id, 'name' => 'Operating Account', 'type' => 'bank', 'is_active' => true]);
        $khalid = $this->makeEmployee($company);

        // Give Khalid 10,000 petty cash.
        $this->actingAs($owner)->post(route('app.payment-vouchers.store'), [
            'party_type' => 'employee', 'employee_id' => $khalid->id,
            'payee_name' => $khalid->full_name, 'bank_account_id' => $operating->id,
            'date' => now()->toDateString(), 'amount' => 10000, 'method' => 'cash',
        ]);

        // He actually spends 6,000 of it on real materials.
        $expense = Expense::create([
            'company_id' => $company->id, 'employee_id' => $khalid->id, 'expense_date' => now(),
            'vendor_name' => 'Hardware Store', 'description' => 'Tools for site', 'amount' => 6000, 'vat_amount' => 0, 'gross_amount' => 6000,
            'tax_category' => 'standard_15', 'status' => 'approved',
        ]);
        app(\App\Services\Accounting\LedgerPostingService::class)->postExpense($expense);

        $entry = JournalEntry::where('source_type', 'expense')->where('source_id', $expense->id)->first();
        $this->assertNotNull($entry);
        $creditLine = $entry->lines()->where('credit', '>', 0)->first();
        $this->assertSame('1260', $creditLine->account->code);
        $this->assertEqualsWithDelta(6000, (float) $creditLine->credit, 0.01);

        $khalid->refresh();
        $this->assertEqualsWithDelta(10000, $khalid->totalAdvanced(), 0.01);
        $this->assertEqualsWithDelta(6000, $khalid->totalSpent(), 0.01);
        $this->assertEqualsWithDelta(4000, $khalid->outstandingAdvanceTotal(), 0.01);
    }

    public function test_the_employee_advances_report_shows_spent_and_reduces_outstanding(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $operating = BankAccount::create(['company_id' => $company->id, 'name' => 'Operating Account', 'type' => 'bank', 'is_active' => true]);
        $khalid = $this->makeEmployee($company);

        $this->actingAs($owner)->post(route('app.payment-vouchers.store'), [
            'party_type' => 'employee', 'employee_id' => $khalid->id,
            'payee_name' => $khalid->full_name, 'bank_account_id' => $operating->id,
            'date' => now()->toDateString(), 'amount' => 10000, 'method' => 'cash',
        ]);
        Expense::create([
            'company_id' => $company->id, 'employee_id' => $khalid->id, 'expense_date' => now(),
            'vendor_name' => 'Hardware Store', 'description' => 'Tools', 'amount' => 6000, 'vat_amount' => 0, 'gross_amount' => 6000,
            'tax_category' => 'standard_15', 'status' => 'approved',
        ]);

        $response = $this->actingAs($owner)->get(route('app.reports.employee-advances'));

        $response->assertOk();
        $response->assertSee('Khalid');
        $response->assertSeeText('4,000.00');
    }

    public function test_a_normal_supplier_payment_voucher_still_defaults_to_operating_expenses(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $operating = BankAccount::create(['company_id' => $company->id, 'name' => 'Operating Account', 'type' => 'bank', 'is_active' => true]);

        $this->actingAs($owner)->post(route('app.payment-vouchers.store'), [
            'party_type' => 'manual', 'payee_name' => 'Walk-in Vendor', 'bank_account_id' => $operating->id,
            'date' => now()->toDateString(), 'amount' => 400, 'method' => 'cash',
        ])->assertRedirect();

        $voucher = PaymentVoucher::first();
        $entry = JournalEntry::where('source_type', 'payment_voucher')->where('source_id', $voucher->id)->first();
        $debitLine = $entry->lines()->where('debit', '>', 0)->first();

        $this->assertSame('5100', $debitLine->account->code);
    }

    public function test_the_expenses_list_labels_a_custody_expense_distinctly_from_an_unpaid_payable(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $khalid = $this->makeEmployee($company);

        Expense::create([
            'company_id' => $company->id, 'employee_id' => $khalid->id, 'expense_date' => now(),
            'vendor_name' => 'Hardware Store', 'description' => 'Tools', 'amount' => 6000, 'vat_amount' => 0, 'gross_amount' => 6000,
            'tax_category' => 'standard_15', 'status' => 'approved',
        ]);
        Expense::create([
            'company_id' => $company->id, 'expense_date' => now(),
            'vendor_name' => 'Unpaid Vendor', 'description' => 'Still owed', 'amount' => 500, 'vat_amount' => 0, 'gross_amount' => 500,
            'tax_category' => 'standard_15', 'status' => 'approved',
        ]);

        $response = $this->actingAs($owner)->get(route('app.expenses.index'));

        $response->assertOk();
        $response->assertSee("Khalid&#039;s custody", false);
        $response->assertSee('Unpaid (payable)');
    }
}
