<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * "make a proper document saving module like, client po, suplier bill,
 * company invoices, expenses bill etc that are required for an audit
 * at the end of year" — Expense was the one real transaction type with
 * no attachment support at all (receipt_path existed on the column but
 * was never actually wired to anything). Mirrors the exact
 * storeAttachment/destroyAttachment shape every other document type
 * already uses (PurchaseOrder, Bill, Invoice, ReceiptVoucher,
 * PaymentVoucher, BankTransfer, MachineryAsset).
 */
class ExpenseAttachmentsTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $company = Company::create(['name' => 'Attachments Co.', 'slug' => 'attach-exp-'.uniqid()]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    private function makeExpense(Company $company, array $overrides = []): Expense
    {
        $bank = BankAccount::create(['company_id' => $company->id, 'name' => 'Petty Cash', 'type' => 'cash', 'currency' => 'SAR', 'is_active' => true]);

        return Expense::create(array_merge([
            'company_id' => $company->id, 'bank_account_id' => $bank->id,
            'description' => 'Fuel', 'amount' => 100, 'gross_amount' => 100, 'vat_amount' => 0,
            'tax_category' => 'zero_rated', 'status' => 'pending', 'expense_date' => now()->toDateString(),
        ], $overrides));
    }

    public function test_a_file_can_be_attached_to_and_removed_from_an_expense(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $expense = $this->makeExpense($company);

        $this->actingAs($owner)->post(route('app.expenses.attachments.store', $expense), [
            'file' => UploadedFile::fake()->image('fuel-receipt.jpg'),
        ])->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('attachments', [
            'attachable_type' => Expense::class, 'attachable_id' => $expense->id, 'original_name' => 'fuel-receipt.jpg',
        ]);

        $this->actingAs($owner)->get(route('app.expenses.edit', $expense))
            ->assertOk()->assertSee('fuel-receipt.jpg');

        $attachment = $expense->attachments()->first();
        $this->actingAs($owner)->delete(route('app.expenses.attachments.destroy', [$expense, $attachment]))
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
    }

    public function test_disallowed_file_types_are_rejected(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $expense = $this->makeExpense($company);

        $this->actingAs($owner)->post(route('app.expenses.attachments.store', $expense), [
            'file' => UploadedFile::fake()->create('malicious.exe', 10),
        ])->assertSessionHasErrors('file');
    }

    public function test_a_company_cannot_remove_another_companys_expense_attachment(): void
    {
        $companyA = $this->makeCompany();
        $companyB = $this->makeCompany();
        $ownerA = $this->makeOwner($companyA);
        $expenseB = $this->makeExpense($companyB);

        $this->actingAs($this->makeOwner($companyB))->post(route('app.expenses.attachments.store', $expenseB), [
            'file' => UploadedFile::fake()->image('file.jpg'),
        ]);
        $attachmentB = $expenseB->attachments()->first();

        $this->actingAs($ownerA)->delete(route('app.expenses.attachments.destroy', [$expenseB, $attachmentB]))
            ->assertNotFound();
    }

    public function test_an_attachment_cannot_be_removed_from_an_approved_expense_within_the_retention_window(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $expense = $this->makeExpense($company, ['status' => 'approved', 'expense_date' => now()->subYear()->toDateString()]);
        $this->actingAs($owner)->post(route('app.expenses.attachments.store', $expense), [
            'file' => UploadedFile::fake()->image('receipt.jpg'),
        ]);
        $attachment = $expense->attachments()->first();

        $response = $this->actingAs($owner)->delete(route('app.expenses.attachments.destroy', [$expense, $attachment]));

        $response->assertSessionHasErrors('attachment');
        $this->assertDatabaseHas('attachments', ['id' => $attachment->id]);
    }

    public function test_an_attachment_can_be_removed_from_an_approved_expense_outside_the_retention_window(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $expense = $this->makeExpense($company, ['status' => 'approved', 'expense_date' => now()->subYears(7)->toDateString()]);
        $this->actingAs($owner)->post(route('app.expenses.attachments.store', $expense), [
            'file' => UploadedFile::fake()->image('receipt.jpg'),
        ]);
        $attachment = $expense->attachments()->first();

        $response = $this->actingAs($owner)->delete(route('app.expenses.attachments.destroy', [$expense, $attachment]));

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
    }

    public function test_a_pending_not_yet_approved_expenses_attachment_can_always_be_removed(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $expense = $this->makeExpense($company, ['status' => 'pending', 'expense_date' => now()->toDateString()]);
        $this->actingAs($owner)->post(route('app.expenses.attachments.store', $expense), [
            'file' => UploadedFile::fake()->image('receipt.jpg'),
        ]);
        $attachment = $expense->attachments()->first();

        $response = $this->actingAs($owner)->delete(route('app.expenses.attachments.destroy', [$expense, $attachment]));

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
    }
}
