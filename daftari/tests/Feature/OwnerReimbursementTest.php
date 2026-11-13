<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Company;
use App\Models\Expense;
use App\Models\PaymentVoucher;
use App\Models\User;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "how can i add or track company owner expenses" — money the owner (or
 * any staff member) paid personally, out of their own pocket, that the
 * company owes back. This reuses the existing accrued-expense mechanism
 * exactly: an Expense with no bank_account_id posts to Accounts Payable
 * (see LedgerPostingService::postExpense()) and is settled later with a
 * Payment Voucher referencing it, the same path any unpaid supplier bill
 * already uses — paid_by_owner just tags it as reportable on its own.
 */
class OwnerReimbursementTest extends TestCase
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

    public function test_checking_paid_personally_forces_the_expense_unpaid_regardless_of_bank_account_submitted(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $bankAccount = \App\Models\BankAccount::create(['company_id' => $company->id, 'name' => 'Main', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true]);

        $this->actingAs($owner)->post(route('app.expenses.store'), [
            'bank_account_id' => $bankAccount->id, // a malicious/confused client sending both
            'paid_by_owner' => '1',
            'gross_amount' => 115, 'tax_category' => 'standard_15', 'expense_date' => now()->toDateString(),
            'vendor_name' => 'Rashid', 'description' => 'Site fuel paid personally',
        ])->assertRedirect();

        $expense = Expense::first();
        $this->assertTrue($expense->paid_by_owner);
        $this->assertNull($expense->bank_account_id);
    }

    public function test_an_owner_paid_expense_posts_to_accounts_payable_not_a_bank_account(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $ledger = app(LedgerPostingService::class);

        $expense = Expense::create([
            'company_id' => $company->id, 'paid_by_owner' => true, 'vendor_name' => 'Rashid',
            'description' => 'Fuel', 'amount' => 100, 'gross_amount' => 115, 'vat_amount' => 15,
            'tax_category' => 'standard_15', 'expense_date' => now()->toDateString(), 'status' => 'approved',
        ]);
        $entry = $ledger->postExpense($expense);

        $ap = Account::where('company_id', $company->id)->where('code', '2000')->first();
        $this->assertNotNull($entry);
        $this->assertTrue($entry->lines->contains(fn ($l) => $l->account_id === $ap->id && (float) $l->credit === 115.0));
    }

    public function test_a_payment_voucher_against_it_marks_it_settled(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $bankAccount = \App\Models\BankAccount::create(['company_id' => $company->id, 'name' => 'Main', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true, 'opening_balance' => 10000]);
        $expense = Expense::create([
            'company_id' => $company->id, 'paid_by_owner' => true, 'vendor_name' => 'Rashid',
            'description' => 'Fuel', 'amount' => 100, 'gross_amount' => 115, 'vat_amount' => 15,
            'tax_category' => 'standard_15', 'expense_date' => now()->toDateString(), 'status' => 'approved',
        ]);
        app(LedgerPostingService::class)->postExpense($expense);
        $this->assertFalse($expense->fresh()->isSettled());

        $this->actingAs($owner)->post(route('app.payment-vouchers.store'), [
            'bank_account_id' => $bankAccount->id, 'party_type' => 'manual', 'expense_id' => $expense->id,
            'payee_name' => 'Rashid', 'amount' => 115, 'date' => now()->toDateString(), 'method' => 'cash',
        ])->assertRedirect();

        $this->assertTrue($expense->fresh()->isSettled());
    }

    public function test_the_settle_link_prefills_the_payment_voucher_from_the_expense(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        \App\Models\BankAccount::create(['company_id' => $company->id, 'name' => 'Main', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true]);
        $expense = Expense::create([
            'company_id' => $company->id, 'paid_by_owner' => true, 'vendor_name' => 'Khalid',
            'description' => 'Hotel', 'amount' => 200, 'gross_amount' => 230, 'vat_amount' => 30,
            'tax_category' => 'standard_15', 'expense_date' => now()->toDateString(), 'status' => 'approved',
        ]);

        $response = $this->actingAs($owner)->get(route('app.payment-vouchers.create', ['expense_id' => $expense->id]));

        $response->assertOk();
        $response->assertSee('value="230"', false);
        $response->assertSee('value="Khalid"', false);
    }

    public function test_the_index_lists_outstanding_and_settled_claims_with_the_right_total(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $bankAccount = \App\Models\BankAccount::create(['company_id' => $company->id, 'name' => 'Main', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true, 'opening_balance' => 10000]);

        $outstanding = Expense::create([
            'company_id' => $company->id, 'paid_by_owner' => true, 'vendor_name' => 'Rashid',
            'description' => 'Fuel', 'amount' => 100, 'gross_amount' => 115, 'vat_amount' => 15,
            'tax_category' => 'standard_15', 'expense_date' => now()->toDateString(), 'status' => 'approved',
        ]);
        $settled = Expense::create([
            'company_id' => $company->id, 'paid_by_owner' => true, 'vendor_name' => 'Khalid',
            'description' => 'Parts', 'amount' => 300, 'gross_amount' => 345, 'vat_amount' => 45,
            'tax_category' => 'standard_15', 'expense_date' => now()->toDateString(), 'status' => 'approved',
        ]);
        $ledger = app(LedgerPostingService::class);
        $ledger->postExpense($outstanding);
        $ledger->postExpense($settled);
        PaymentVoucher::create([
            'company_id' => $company->id, 'bank_account_id' => $bankAccount->id, 'party_type' => 'manual',
            'expense_id' => $settled->id, 'voucher_number' => 'PV-1', 'date' => now()->toDateString(),
            'payee_name' => 'Khalid', 'amount' => 345, 'method' => 'cash', 'status' => 'issued',
        ]);

        // A regular company expense (not owner-paid) must never show up here.
        Expense::create([
            'company_id' => $company->id, 'vendor_name' => 'Supplier Co', 'bank_account_id' => $bankAccount->id,
            'description' => 'Materials', 'amount' => 1000, 'gross_amount' => 1150, 'vat_amount' => 150,
            'tax_category' => 'standard_15', 'expense_date' => now()->toDateString(), 'status' => 'approved',
        ]);

        $response = $this->actingAs($owner)->get(route('app.owner-reimbursements.index'));

        $response->assertOk();
        $response->assertSee(\App\Support\Money::format(115)); // still owed
        $response->assertSee(__('Outstanding'));
        $response->assertSee(__('Reimbursed'));
        $response->assertDontSee('Supplier Co');
    }

    public function test_the_pdf_downloads(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        Expense::create([
            'company_id' => $company->id, 'paid_by_owner' => true, 'vendor_name' => 'Rashid',
            'description' => 'Fuel', 'amount' => 100, 'gross_amount' => 115, 'vat_amount' => 15,
            'tax_category' => 'standard_15', 'expense_date' => now()->toDateString(), 'status' => 'approved',
        ]);

        $response = $this->actingAs($owner)->get(route('app.owner-reimbursements.pdf'));

        $response->assertOk();
        $this->assertStringStartsWith('application/pdf', $response->headers->get('Content-Type'));
    }
}
