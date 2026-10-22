<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\BankAccount;
use App\Models\BankTransfer;
use App\Models\Company;
use App\Models\PaymentVoucher;
use App\Models\ReceiptVoucher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * "Every transaction should have an option to add an attachment" — the
 * Cash & Banks module's three money-movement records (ReceiptVoucher,
 * PaymentVoucher, BankTransfer) can now carry file attachments, mirroring
 * the existing PurchaseOrder::attachments() mechanism exactly (same
 * Attachment model, same storeAttachment/destroyAttachment shape, same
 * mimes/size validation). This is a core Cash & Banks capability, not
 * part of the paid Project Cash Flow module.
 */
class CashBankAttachmentsTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $company = Company::create(['name' => 'Attachments Co.', 'slug' => 'attach-cb-'.uniqid()]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    private function makeBankAccount(Company $company, string $type = 'bank'): BankAccount
    {
        return BankAccount::create(['company_id' => $company->id, 'name' => ucfirst($type).' Account', 'type' => $type, 'currency' => 'SAR', 'is_active' => true]);
    }

    public function test_a_file_can_be_attached_to_and_removed_from_a_receipt_voucher(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $account = $this->makeBankAccount($company);
        $voucher = ReceiptVoucher::create([
            'company_id' => $company->id, 'bank_account_id' => $account->id, 'party_type' => 'manual',
            'voucher_number' => 'RV-1', 'date' => now()->toDateString(), 'payer_name' => 'Client',
            'amount' => 1000, 'method' => 'cash', 'status' => 'issued',
        ]);

        $this->actingAs($owner)->post(route('app.receipt-vouchers.attachments.store', $voucher), [
            'file' => UploadedFile::fake()->image('deposit-slip.jpg'),
        ])->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('attachments', [
            'attachable_type' => ReceiptVoucher::class, 'attachable_id' => $voucher->id, 'original_name' => 'deposit-slip.jpg',
        ]);

        $this->actingAs($owner)->get(route('app.receipt-vouchers.show', $voucher))
            ->assertOk()->assertSee('deposit-slip.jpg');

        $attachment = $voucher->attachments()->first();
        $this->actingAs($owner)->delete(route('app.receipt-vouchers.attachments.destroy', [$voucher, $attachment]))
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
    }

    public function test_a_file_can_be_attached_to_and_removed_from_a_payment_voucher(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $account = $this->makeBankAccount($company);
        $voucher = PaymentVoucher::create([
            'company_id' => $company->id, 'bank_account_id' => $account->id, 'party_type' => 'manual',
            'voucher_number' => 'PV-1', 'date' => now()->toDateString(), 'payee_name' => 'Supplier',
            'amount' => 500, 'method' => 'cash', 'status' => 'issued',
        ]);

        $this->actingAs($owner)->post(route('app.payment-vouchers.attachments.store', $voucher), [
            'file' => UploadedFile::fake()->image('receipt.jpg'),
        ])->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('attachments', [
            'attachable_type' => PaymentVoucher::class, 'attachable_id' => $voucher->id, 'original_name' => 'receipt.jpg',
        ]);

        $attachment = $voucher->attachments()->first();
        $this->actingAs($owner)->delete(route('app.payment-vouchers.attachments.destroy', [$voucher, $attachment]))
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
    }

    public function test_a_file_can_be_attached_to_and_removed_from_a_bank_transfer(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $bank = $this->makeBankAccount($company, 'bank');
        $cash = $this->makeBankAccount($company, 'cash');
        $transfer = BankTransfer::create([
            'company_id' => $company->id, 'from_bank_account_id' => $bank->id, 'to_bank_account_id' => $cash->id,
            'amount' => 2000, 'date' => now()->toDateString(), 'created_by' => $owner->id,
        ]);

        $this->actingAs($owner)->get(route('app.bank-transfers.show', $transfer))
            ->assertOk()->assertSee(__('Withdrawal'));

        $this->actingAs($owner)->post(route('app.bank-transfers.attachments.store', $transfer), [
            'file' => UploadedFile::fake()->image('atm-slip.jpg'),
        ])->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('attachments', [
            'attachable_type' => BankTransfer::class, 'attachable_id' => $transfer->id, 'original_name' => 'atm-slip.jpg',
        ]);

        $this->actingAs($owner)->get(route('app.bank-transfers.show', $transfer))->assertSee('atm-slip.jpg');

        $attachment = $transfer->attachments()->first();
        $this->actingAs($owner)->delete(route('app.bank-transfers.attachments.destroy', [$transfer, $attachment]))
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
    }

    public function test_disallowed_file_types_are_rejected_on_every_transaction_type(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $bank = $this->makeBankAccount($company, 'bank');
        $cash = $this->makeBankAccount($company, 'cash');

        $voucher = ReceiptVoucher::create([
            'company_id' => $company->id, 'bank_account_id' => $bank->id, 'party_type' => 'manual',
            'voucher_number' => 'RV-2', 'date' => now()->toDateString(), 'payer_name' => 'Client',
            'amount' => 100, 'method' => 'cash', 'status' => 'issued',
        ]);
        $transfer = BankTransfer::create([
            'company_id' => $company->id, 'from_bank_account_id' => $bank->id, 'to_bank_account_id' => $cash->id,
            'amount' => 100, 'date' => now()->toDateString(), 'created_by' => $owner->id,
        ]);

        $this->actingAs($owner)->post(route('app.receipt-vouchers.attachments.store', $voucher), [
            'file' => UploadedFile::fake()->create('malicious.exe', 10),
        ])->assertSessionHasErrors('file');

        $this->actingAs($owner)->post(route('app.bank-transfers.attachments.store', $transfer), [
            'file' => UploadedFile::fake()->create('malicious.exe', 10),
        ])->assertSessionHasErrors('file');
    }

    public function test_a_company_cannot_remove_another_companys_attachment(): void
    {
        $companyA = $this->makeCompany();
        $companyB = $this->makeCompany();
        $ownerA = $this->makeOwner($companyA);
        $accountB = $this->makeBankAccount($companyB);
        $voucherB = ReceiptVoucher::create([
            'company_id' => $companyB->id, 'bank_account_id' => $accountB->id, 'party_type' => 'manual',
            'voucher_number' => 'RV-B', 'date' => now()->toDateString(), 'payer_name' => 'Client',
            'amount' => 100, 'method' => 'cash', 'status' => 'issued',
        ]);
        $this->actingAs($this->makeOwner($companyB))->post(route('app.receipt-vouchers.attachments.store', $voucherB), [
            'file' => UploadedFile::fake()->image('file.jpg'),
        ]);
        $attachmentB = $voucherB->attachments()->first();

        // Owner A can't even resolve voucherB via route-model binding
        // (BelongsToCompany scopes it out of their company), so this 404s.
        $this->actingAs($ownerA)->delete(route('app.receipt-vouchers.attachments.destroy', [$voucherB, $attachmentB]))
            ->assertNotFound();
    }
}
