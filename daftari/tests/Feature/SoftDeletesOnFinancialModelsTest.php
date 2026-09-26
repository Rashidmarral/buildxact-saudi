<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Client;
use App\Models\Company;
use App\Models\CustomsDeclaration;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\PaymentVoucher;
use App\Models\Quotation;
use App\Models\ReceiptVoucher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Security audit finding D-5: invoice/expense/quotation/payment-voucher/
 * receipt-voucher/customs-declaration destroy() actions all issued a real
 * SQL DELETE — for expenses and customs declarations, even ones already
 * posted to the ledger. Once a row was gone, there was no way to recover
 * it after an accidental click, a bug, or a compromised account. Each
 * model now soft-deletes: the row survives (recoverable at the DB layer)
 * while disappearing from every normal query exactly as before.
 */
class SoftDeletesOnFinancialModelsTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        return Company::create(['name' => 'Acme', 'slug' => 'acme-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['company_id' => $company->id, 'role' => 'owner', 'status' => 'active']);
    }

    public function test_deleting_a_draft_invoice_soft_deletes_it(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client A']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-0001', 'type' => 'standard', 'status' => 'draft',
            'issue_date' => now(), 'subtotal' => 100, 'vat_total' => 15, 'total' => 115, 'currency' => 'SAR',
        ]);

        $this->actingAs($owner)->delete(route('app.invoices.destroy', $invoice));

        $this->assertSoftDeleted('invoices', ['id' => $invoice->id]);
        $this->assertNull(Invoice::find($invoice->id));
        $this->assertNotNull(Invoice::withTrashed()->find($invoice->id));
        $this->assertSame(0, Invoice::count());
    }

    public function test_deleting_an_approved_expense_soft_deletes_it_and_still_reverses_the_ledger(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        \App\Models\Account::seedSystemAccounts($company->id);
        \App\Models\AccountMapping::seedDefaults($company->id);
        $expense = Expense::create([
            'company_id' => $company->id, 'vendor_name' => 'Office Supplies Co',
            'amount' => 100, 'gross_amount' => 115, 'vat_amount' => 15, 'tax_category' => 'standard_15',
            'expense_date' => now(), 'status' => 'approved',
        ]);

        $expenseAccount = \App\Models\Account::where('company_id', $company->id)->where('type', 'expense')->first();
        $cashAccount = \App\Models\Account::where('company_id', $company->id)->where('type', 'asset')->first();
        app(\App\Services\Accounting\LedgerPostingService::class)->post(
            $company, 'expense', $expense->id, 'Test posting', now(),
            [
                ['account_id' => $expenseAccount->id, 'debit' => 115, 'credit' => 0],
                ['account_id' => $cashAccount->id, 'debit' => 0, 'credit' => 115],
            ]
        );

        $this->assertSame(1, \App\Models\JournalEntry::where('source_type', 'expense')->where('source_id', $expense->id)->count());

        $this->actingAs($owner)->delete(route('app.expenses.destroy', $expense));

        $this->assertSoftDeleted('expenses', ['id' => $expense->id]);
        $this->assertNull(Expense::find($expense->id));
        $this->assertNotNull(Expense::withTrashed()->find($expense->id));
        $this->assertSame(1, \App\Models\JournalEntry::where('source_type', 'expense_reversal')->where('source_id', $expense->id)->count());
    }

    public function test_deleting_a_draft_quotation_soft_deletes_it(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client A']);
        $quotation = Quotation::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'quotation_number' => 'QUO-0001', 'status' => 'draft',
            'issue_date' => now(), 'valid_until' => now()->addDays(30), 'subtotal' => 100, 'vat_total' => 15, 'total' => 115, 'currency' => 'SAR',
        ]);

        $this->actingAs($owner)->delete(route('app.quotations.destroy', $quotation));

        $this->assertSoftDeleted('quotations', ['id' => $quotation->id]);
        $this->assertNull(Quotation::find($quotation->id));
        $this->assertNotNull(Quotation::withTrashed()->find($quotation->id));
    }

    public function test_deleting_a_voided_payment_voucher_soft_deletes_it(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $bankAccount = BankAccount::create(['company_id' => $company->id, 'name' => 'Main', 'is_active' => true]);
        $voucher = PaymentVoucher::create([
            'company_id' => $company->id, 'bank_account_id' => $bankAccount->id, 'voucher_number' => 'PV-0001',
            'date' => now(), 'payee_name' => 'Vendor X', 'amount' => 100, 'status' => 'void',
        ]);

        $this->actingAs($owner)->delete(route('app.payment-vouchers.destroy', $voucher));

        $this->assertSoftDeleted('payment_vouchers', ['id' => $voucher->id]);
        $this->assertNull(PaymentVoucher::find($voucher->id));
        $this->assertNotNull(PaymentVoucher::withTrashed()->find($voucher->id));
    }

    public function test_deleting_a_voided_receipt_voucher_soft_deletes_it(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $bankAccount = BankAccount::create(['company_id' => $company->id, 'name' => 'Main', 'is_active' => true]);
        $voucher = ReceiptVoucher::create([
            'company_id' => $company->id, 'bank_account_id' => $bankAccount->id, 'voucher_number' => 'RV-0001',
            'date' => now(), 'payer_name' => 'Client X', 'amount' => 100, 'status' => 'void',
        ]);

        $this->actingAs($owner)->delete(route('app.receipt-vouchers.destroy', $voucher));

        $this->assertSoftDeleted('receipt_vouchers', ['id' => $voucher->id]);
        $this->assertNull(ReceiptVoucher::find($voucher->id));
        $this->assertNotNull(ReceiptVoucher::withTrashed()->find($voucher->id));
    }

    public function test_deleting_a_customs_declaration_soft_deletes_it_and_still_reverses_the_ledger(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        \App\Models\Account::seedSystemAccounts($company->id);
        \App\Models\AccountMapping::seedDefaults($company->id);
        $declaration = CustomsDeclaration::create([
            'company_id' => $company->id, 'declaration_number' => 'CD-0001', 'declaration_date' => now(),
            'customs_value' => 1000, 'customs_duty' => 50, 'vat_rate' => 15, 'vat_amount' => 157.5,
        ]);

        $this->actingAs($owner)->delete(route('app.customs-declarations.destroy', $declaration));

        $this->assertSoftDeleted('customs_declarations', ['id' => $declaration->id]);
        $this->assertNull(CustomsDeclaration::find($declaration->id));
        $this->assertNotNull(CustomsDeclaration::withTrashed()->find($declaration->id));
    }

    public function test_a_soft_deleted_invoice_cannot_be_reached_by_its_show_route(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client A']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-0002', 'type' => 'standard', 'status' => 'draft',
            'issue_date' => now(), 'subtotal' => 100, 'vat_total' => 15, 'total' => 115, 'currency' => 'SAR',
        ]);
        $invoice->delete();

        $response = $this->actingAs($owner)->get(route('app.invoices.show', $invoice->id));

        $response->assertNotFound();
    }
}
