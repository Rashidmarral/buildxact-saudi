<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\BankAccount;
use App\Models\Bill;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\PaymentVoucher;
use App\Models\PendingManualJournalEntry;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit finding SEC-06: "Approval thresholds do not enforce an independent
 * approver" — a submission routed to a pending-approval queue (manual
 * journal entries, payment/receipt vouchers, bills) could be approved by
 * the exact same user who created it, which defeats the point of the
 * threshold. A company with only one active user is exempted — there is
 * no second person available to require.
 */
class ApprovalCreatorSeparationTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(array $overrides = []): Company
    {
        $company = Company::create(array_merge([
            'name' => 'Separation Co.', 'slug' => 'separation-co-'.uniqid(),
        ], $overrides));

        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        Role::seedSystemRoles($company->id);

        return $company;
    }

    private function makeUser(Company $company, array $overrides = []): User
    {
        return User::factory()->create(array_merge(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active'], $overrides));
    }

    // --- Manual journal entries ---

    public function test_the_creator_of_a_pending_manual_journal_entry_cannot_approve_it_when_a_second_user_exists(): void
    {
        $company = $this->makeCompany(['manual_journal_approval_threshold' => 10000]);
        $creator = $this->makeUser($company);
        $this->makeUser($company); // second active user makes separation required
        $cash = Account::where('company_id', $company->id)->where('code', '1000')->first();
        $equity = Account::where('company_id', $company->id)->where('code', '3000')->first();

        $this->actingAs($creator)->post(route('app.journals.manual.store'), [
            'entry_date' => now()->toDateString(),
            'description' => 'Large adjustment',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => 20000, 'credit' => 0],
                ['account_id' => $equity->id, 'debit' => 0, 'credit' => 20000],
            ],
        ]);
        $pending = PendingManualJournalEntry::first();

        $response = $this->actingAs($creator)->post(route('app.journals.pending.approve', $pending));

        $response->assertSessionHasErrors('approve');
        $pending->refresh();
        $this->assertSame('pending_approval', $pending->status);
        $this->assertSame(0, JournalEntry::where('source_type', 'manual')->count());
    }

    public function test_a_different_user_can_approve_the_same_pending_manual_journal_entry(): void
    {
        $company = $this->makeCompany(['manual_journal_approval_threshold' => 10000]);
        $creator = $this->makeUser($company);
        $approver = $this->makeUser($company);
        $cash = Account::where('company_id', $company->id)->where('code', '1000')->first();
        $equity = Account::where('company_id', $company->id)->where('code', '3000')->first();

        $this->actingAs($creator)->post(route('app.journals.manual.store'), [
            'entry_date' => now()->toDateString(),
            'description' => 'Large adjustment',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => 20000, 'credit' => 0],
                ['account_id' => $equity->id, 'debit' => 0, 'credit' => 20000],
            ],
        ]);
        $pending = PendingManualJournalEntry::first();

        $response = $this->actingAs($approver)->post(route('app.journals.pending.approve', $pending));

        $response->assertRedirect();
        $pending->refresh();
        $this->assertSame('approved', $pending->status);
        $this->assertSame($approver->id, $pending->approved_by);
    }

    public function test_a_sole_owner_may_approve_their_own_pending_manual_journal_entry(): void
    {
        $company = $this->makeCompany(['manual_journal_approval_threshold' => 10000]);
        $owner = $this->makeUser($company); // the only active user in this company
        $cash = Account::where('company_id', $company->id)->where('code', '1000')->first();
        $equity = Account::where('company_id', $company->id)->where('code', '3000')->first();

        $this->actingAs($owner)->post(route('app.journals.manual.store'), [
            'entry_date' => now()->toDateString(),
            'description' => 'Large adjustment',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => 20000, 'credit' => 0],
                ['account_id' => $equity->id, 'debit' => 0, 'credit' => 20000],
            ],
        ]);
        $pending = PendingManualJournalEntry::first();

        $response = $this->actingAs($owner)->post(route('app.journals.pending.approve', $pending));

        $response->assertRedirect();
        $pending->refresh();
        $this->assertSame('approved', $pending->status);
    }

    public function test_an_inactive_second_user_does_not_count_toward_requiring_separation(): void
    {
        $company = $this->makeCompany(['manual_journal_approval_threshold' => 10000]);
        $owner = $this->makeUser($company);
        $this->makeUser($company, ['status' => 'inactive']);
        $cash = Account::where('company_id', $company->id)->where('code', '1000')->first();
        $equity = Account::where('company_id', $company->id)->where('code', '3000')->first();

        $this->actingAs($owner)->post(route('app.journals.manual.store'), [
            'entry_date' => now()->toDateString(),
            'description' => 'Large adjustment',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => 20000, 'credit' => 0],
                ['account_id' => $equity->id, 'debit' => 0, 'credit' => 20000],
            ],
        ]);
        $pending = PendingManualJournalEntry::first();

        $response = $this->actingAs($owner)->post(route('app.journals.pending.approve', $pending));

        $response->assertRedirect();
        $pending->refresh();
        $this->assertSame('approved', $pending->status);
    }

    // --- Payment vouchers ---

    public function test_the_creator_of_a_pending_payment_voucher_cannot_approve_it_when_a_second_user_exists(): void
    {
        $company = $this->makeCompany(['payment_voucher_approval_threshold' => 10000]);
        $creator = $this->makeUser($company);
        $this->makeUser($company);
        $account = BankAccount::create(['company_id' => $company->id, 'name' => 'Main', 'bank_name' => 'Al Rajhi Bank', 'type' => 'bank', 'is_active' => true]);

        $this->actingAs($creator)->post(route('app.payment-vouchers.store'), [
            'party_type' => 'manual', 'payee_name' => 'Payee', 'bank_account_id' => $account->id,
            'date' => now()->toDateString(), 'amount' => 20000, 'method' => 'cash',
        ]);
        $voucher = PaymentVoucher::first();

        $response = $this->actingAs($creator)->post(route('app.payment-vouchers.approve', $voucher));

        $response->assertSessionHasErrors('approve');
        $voucher->refresh();
        $this->assertSame('pending_approval', $voucher->status);
    }

    public function test_a_different_user_can_approve_the_same_pending_payment_voucher(): void
    {
        $company = $this->makeCompany(['payment_voucher_approval_threshold' => 10000]);
        $creator = $this->makeUser($company);
        $approver = $this->makeUser($company);
        $account = BankAccount::create(['company_id' => $company->id, 'name' => 'Main', 'bank_name' => 'Al Rajhi Bank', 'type' => 'bank', 'is_active' => true]);

        $this->actingAs($creator)->post(route('app.payment-vouchers.store'), [
            'party_type' => 'manual', 'payee_name' => 'Payee', 'bank_account_id' => $account->id,
            'date' => now()->toDateString(), 'amount' => 20000, 'method' => 'cash',
        ]);
        $voucher = PaymentVoucher::first();

        $response = $this->actingAs($approver)->post(route('app.payment-vouchers.approve', $voucher));

        $response->assertRedirect();
        $voucher->refresh();
        $this->assertSame('issued', $voucher->status);
        $this->assertSame($approver->id, $voucher->approved_by);
    }

    // --- Receipt vouchers ---

    public function test_the_creator_of_a_pending_receipt_voucher_cannot_approve_it_when_a_second_user_exists(): void
    {
        $company = $this->makeCompany(['receipt_voucher_approval_threshold' => 10000]);
        $creator = $this->makeUser($company);
        $this->makeUser($company);
        $account = BankAccount::create(['company_id' => $company->id, 'name' => 'Main', 'bank_name' => 'Al Rajhi Bank', 'type' => 'bank', 'is_active' => true]);

        $this->actingAs($creator)->post(route('app.receipt-vouchers.store'), [
            'party_type' => 'manual', 'payer_name' => 'Payer', 'bank_account_id' => $account->id,
            'date' => now()->toDateString(), 'amount' => 20000, 'method' => 'cash',
        ]);
        $voucher = \App\Models\ReceiptVoucher::first();

        $response = $this->actingAs($creator)->post(route('app.receipt-vouchers.approve', $voucher));

        $response->assertSessionHasErrors('approve');
        $voucher->refresh();
        $this->assertSame('pending_approval', $voucher->status);
    }

    // --- Bills ---

    public function test_the_creator_of_a_pending_bill_cannot_approve_it_when_a_second_user_exists(): void
    {
        $company = $this->makeCompany(['bill_approval_threshold' => 10000]);
        $creator = $this->makeUser($company);
        $this->makeUser($company);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Supplier']);

        $this->actingAs($creator)->post(route('app.bills.store'), [
            'supplier_id' => $supplier->id, 'bill_date' => now()->toDateString(), 'post_immediately' => '1',
            'items' => [['description' => 'Item', 'quantity' => 1, 'unit_price' => 15000, 'vat_rate' => 0]],
        ]);
        $bill = Bill::first();
        $this->assertSame('pending_approval', $bill->status);

        $response = $this->actingAs($creator)->post(route('app.bills.approve', $bill));

        $response->assertSessionHasErrors('approve');
        $bill->refresh();
        $this->assertSame('pending_approval', $bill->status);
    }

    public function test_a_different_user_can_approve_the_same_pending_bill(): void
    {
        $company = $this->makeCompany(['bill_approval_threshold' => 10000]);
        $creator = $this->makeUser($company);
        $approver = $this->makeUser($company);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Supplier']);

        $this->actingAs($creator)->post(route('app.bills.store'), [
            'supplier_id' => $supplier->id, 'bill_date' => now()->toDateString(), 'post_immediately' => '1',
            'items' => [['description' => 'Item', 'quantity' => 1, 'unit_price' => 15000, 'vat_rate' => 0]],
        ]);
        $bill = Bill::first();

        $response = $this->actingAs($approver)->post(route('app.bills.approve', $bill));

        $response->assertRedirect();
        $bill->refresh();
        $this->assertSame('posted', $bill->status);
        $this->assertSame($approver->id, $bill->approved_by);
    }
}
