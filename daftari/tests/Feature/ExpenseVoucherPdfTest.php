<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PaymentVoucher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "if i create any expense how can i get it's voucher to record it as pdf
 * or in hard form" — a printable voucher for an Expense, in the same
 * visual style as Payment/Receipt Vouchers. Deliberately read-only: no
 * PaymentVoucher record is created (the expense already posts straight to
 * the ledger itself — a second real voucher document would double-post).
 */
class ExpenseVoucherPdfTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): User
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting Company', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    private function makeExpense(User $owner, array $overrides = []): Expense
    {
        $category = ExpenseCategory::create(['company_id' => $owner->company_id, 'name' => 'Office Supplies']);
        $bankAccount = BankAccount::create(['company_id' => $owner->company_id, 'name' => 'Main Bank', 'type' => 'bank', 'currency' => 'SAR']);

        return Expense::create(array_merge([
            'company_id' => $owner->company_id,
            'expense_category_id' => $category->id,
            'bank_account_id' => $bankAccount->id,
            'vendor_name' => 'Jarir Bookstore',
            'description' => 'Printer paper and toner',
            'amount' => 100, 'gross_amount' => 115, 'vat_amount' => 15,
            'tax_category' => 'standard_15',
            'expense_date' => now()->toDateString(),
            'status' => 'approved',
        ], $overrides));
    }

    public function test_downloading_the_voucher_pdf_renders_successfully(): void
    {
        $owner = $this->makeOwner();
        $expense = $this->makeExpense($owner);

        $response = $this->actingAs($owner)->get(route('app.expenses.pdf', $expense));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_downloading_the_voucher_pdf_never_creates_a_payment_voucher(): void
    {
        $owner = $this->makeOwner();
        $expense = $this->makeExpense($owner);

        $this->actingAs($owner)->get(route('app.expenses.pdf', $expense));
        $this->actingAs($owner)->get(route('app.expenses.pdf', $expense));

        $this->assertSame(0, PaymentVoucher::where('expense_id', $expense->id)->count());
    }

    public function test_the_pdf_filename_uses_a_padded_expense_number(): void
    {
        $owner = $this->makeOwner();
        $expense = $this->makeExpense($owner);

        $response = $this->actingAs($owner)->get(route('app.expenses.pdf', $expense));

        $expectedNumber = 'EXP-'.str_pad((string) $expense->id, 5, '0', STR_PAD_LEFT);
        $response->assertHeader('content-disposition', 'attachment; filename="'.$expectedNumber.'.pdf"');
    }

    public function test_a_company_cannot_download_another_companys_expense_voucher(): void
    {
        $owner = $this->makeOwner();
        $otherOwner = $this->makeOwner();
        $otherExpense = $this->makeExpense($otherOwner);

        $response = $this->actingAs($owner)->get(route('app.expenses.pdf', $otherExpense));

        $response->assertNotFound();
    }
}
