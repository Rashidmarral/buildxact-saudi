<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\BankAccount;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\ReceiptVoucher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reported bug: the dashboard showed a wildly negative "Profit this
 * month" and the Company Audit page flagged "Invoice INV-00001 ...
 * marked posted but have no matching ledger entry". Root cause: both
 * InvoiceController::storePayment() and ReceiptVoucherController::
 * store()/update() let a payment be recorded against a still-DRAFT
 * invoice, jumping its status straight to paid/partially_paid without
 * ever calling postInvoiceIssued() — real cash came in, but the
 * invoice's own revenue never posted to the GL, so the income
 * statement (and the profit figure built from it) was missing real
 * revenue while real expenses still posted normally.
 */
class LedgerPostingIntegrityRemediationTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $company = Company::create(['name' => 'Zubaida', 'slug' => 'zubaida-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    private function makeDraftInvoice(Company $company): Invoice
    {
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-'.uniqid(),
            'type' => 'standard', 'status' => 'draft', 'issue_date' => now()->toDateString(), 'currency' => $company->currency,
            'subtotal' => 100, 'discount_total' => 0, 'vat_total' => 15, 'total' => 115,
        ]);

        return $invoice;
    }

    public function test_recording_a_payment_on_a_draft_invoice_sends_it_first_so_its_revenue_posts_to_the_ledger(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $invoice = $this->makeDraftInvoice($company);

        $this->assertFalse(JournalEntry::where('source_type', 'invoice')->where('source_id', $invoice->id)->exists());

        $response = $this->actingAs($owner)->post(route('app.invoices.payments.store', $invoice), [
            'amount' => 115, 'paid_at' => now()->toDateString(), 'method' => 'cash',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertSame('paid', $invoice->fresh()->status);
        // The invoice's own revenue entry now exists — not just the
        // payment's cash-received entry.
        $this->assertTrue(JournalEntry::where('source_type', 'invoice')->where('source_id', $invoice->id)->exists());
        $this->assertTrue(JournalEntry::where('source_type', 'invoice_payment')->exists());
    }

    public function test_a_receipt_voucher_cannot_be_created_linked_to_a_still_draft_invoice(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $invoice = $this->makeDraftInvoice($company);
        $account = BankAccount::create(['company_id' => $company->id, 'name' => 'Main', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true]);

        $response = $this->actingAs($owner)->post(route('app.receipt-vouchers.store'), [
            'bank_account_id' => $account->id, 'party_type' => 'manual', 'invoice_id' => $invoice->id,
            'date' => now()->toDateString(), 'payer_name' => 'Client', 'amount' => 115, 'method' => 'cash',
        ]);

        $response->assertSessionHasErrors('invoice_id');
        $this->assertSame('draft', $invoice->fresh()->status);
        $this->assertSame(0, ReceiptVoucher::count());
    }

    public function test_updating_a_receipt_voucher_to_link_a_draft_invoice_is_also_rejected(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $invoice = $this->makeDraftInvoice($company);
        $account = BankAccount::create(['company_id' => $company->id, 'name' => 'Main', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true]);
        $voucher = ReceiptVoucher::create([
            'company_id' => $company->id, 'bank_account_id' => $account->id, 'party_type' => 'manual',
            'voucher_number' => 'RV-1', 'date' => now()->toDateString(), 'payer_name' => 'Someone',
            'amount' => 50, 'method' => 'cash', 'status' => 'issued',
        ]);

        $response = $this->actingAs($owner)->put(route('app.receipt-vouchers.update', $voucher), [
            'bank_account_id' => $account->id, 'party_type' => 'manual', 'invoice_id' => $invoice->id,
            'date' => now()->toDateString(), 'payer_name' => 'Someone', 'amount' => 50, 'method' => 'cash',
        ]);

        $response->assertSessionHasErrors('invoice_id');
        $this->assertSame('draft', $invoice->fresh()->status);
    }

    public function test_the_audit_pages_repost_action_fixes_a_sent_invoice_with_no_ledger_entry(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-00001',
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(), 'currency' => $company->currency,
            'subtotal' => 100, 'discount_total' => 0, 'vat_total' => 15, 'total' => 115,
        ]);

        $this->actingAs($owner)->get(route('app.audit.index'))
            ->assertSee(__('Ledger posting integrity'))
            ->assertSee($invoice->invoice_number);

        $response = $this->actingAs($owner)->post(route('app.audit.repost'), [
            'source_type' => 'invoice', 'source_id' => $invoice->id,
        ]);

        $response->assertRedirect();
        $this->assertTrue(JournalEntry::where('source_type', 'invoice')->where('source_id', $invoice->id)->exists());

        $this->actingAs($owner)->get(route('app.audit.index'))
            ->assertSee(__('Every posted document in this period has a matching ledger entry.'));
    }

    public function test_reposting_someone_elses_invoice_is_blocked(): void
    {
        $companyA = $this->makeCompany();
        $companyB = $this->makeCompany();
        $ownerA = $this->makeOwner($companyA);
        $client = Client::create(['company_id' => $companyB->id, 'name' => 'Other Co Client']);
        $invoice = Invoice::create([
            'company_id' => $companyB->id, 'client_id' => $client->id, 'invoice_number' => 'INV-OTHER',
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(), 'currency' => $companyB->currency,
            'subtotal' => 100, 'discount_total' => 0, 'vat_total' => 15, 'total' => 115,
        ]);

        $this->actingAs($ownerA)->post(route('app.audit.repost'), [
            'source_type' => 'invoice', 'source_id' => $invoice->id,
        ])->assertNotFound();

        $this->assertFalse(JournalEntry::where('source_type', 'invoice')->where('source_id', $invoice->id)->exists());
    }
}
