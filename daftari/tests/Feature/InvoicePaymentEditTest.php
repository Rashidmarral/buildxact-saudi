<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "i have checke invouce status paid but payment is not linked to any
 * account how can i manage this" — a payment recorded before
 * bank_account_id existed (or with the wrong account/amount) had no way
 * to be corrected: InvoiceController only ever had storePayment(), never
 * an update. Adds updatePayment() plus an inline edit row on the
 * invoice's Payments table.
 */
class InvoicePaymentEditTest extends TestCase
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

    private function makeInvoice(Company $company): Invoice
    {
        $client = Client::create(['company_id' => $company->id, 'name' => 'Sada Al Jeul Construction']);

        return Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-1',
            'status' => 'sent', 'issue_date' => now(), 'due_date' => now()->addDays(30),
            'subtotal' => 91310.50, 'vat_total' => 13696.58, 'total' => 105007.08, 'currency' => 'SAR',
        ]);
    }

    public function test_an_unlinked_payment_can_be_corrected_to_a_real_bank_account(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $invoice = $this->makeInvoice($company);
        $account = BankAccount::create(['company_id' => $company->id, 'name' => 'SNB Current Account', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true]);

        // Recorded exactly like the pre-existing production payment: no
        // bank_account_id at all.
        $this->actingAs($owner)->post(route('app.invoices.payments.store', $invoice), [
            'amount' => 105007.08, 'paid_at' => '2026-09-26', 'method' => 'bank_transfer',
        ]);
        $payment = InvoicePayment::first();
        $this->assertNull($payment->bank_account_id);
        $this->assertEqualsWithDelta(0.0, $account->fresh()->currentBalance(), 0.01);

        $response = $this->actingAs($owner)->put(route('app.invoices.payments.update', [$invoice, $payment]), [
            'amount' => 105007.08, 'paid_at' => '2026-09-26', 'method' => 'bank_transfer',
            'bank_account_id' => $account->id,
        ]);

        $response->assertRedirect();
        $payment->refresh();
        $this->assertSame($account->id, $payment->bank_account_id);
        $this->assertEqualsWithDelta(105007.08, $account->fresh()->currentBalance(), 0.01);

        // Exactly one journal entry for this payment — the old one was
        // deleted and rebuilt, not left alongside a duplicate.
        $this->assertCount(1, JournalEntry::where('source_type', 'invoice_payment')->where('source_id', $payment->id)->get());

        $this->assertDatabaseHas('audit_logs', ['action' => 'invoice.payment_update', 'subject_type' => Invoice::class, 'subject_id' => $invoice->id]);
    }

    public function test_editing_a_payments_amount_recomputes_the_invoice_status(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $invoice = $this->makeInvoice($company);

        $this->actingAs($owner)->post(route('app.invoices.payments.store', $invoice), [
            'amount' => 105007.08, 'paid_at' => '2026-09-26', 'method' => 'bank_transfer',
        ]);
        $payment = InvoicePayment::first();
        $this->assertSame('paid', $invoice->fresh()->status);

        $this->actingAs($owner)->put(route('app.invoices.payments.update', [$invoice, $payment]), [
            'amount' => 50000, 'paid_at' => '2026-09-26', 'method' => 'bank_transfer',
        ])->assertSessionDoesntHaveErrors();

        $this->assertSame('partially_paid', $invoice->fresh()->status);
        $this->assertEqualsWithDelta(50000, $invoice->fresh()->amount_paid, 0.01);
    }

    public function test_a_payment_belonging_to_a_different_invoice_cannot_be_edited_through_it(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $invoiceA = $this->makeInvoice($company);
        $invoiceB = Invoice::create([
            'company_id' => $company->id, 'client_id' => $invoiceA->client_id, 'invoice_number' => 'INV-2',
            'status' => 'sent', 'issue_date' => now(), 'due_date' => now()->addDays(30),
            'subtotal' => 1000, 'vat_total' => 150, 'total' => 1150, 'currency' => 'SAR',
        ]);

        $this->actingAs($owner)->post(route('app.invoices.payments.store', $invoiceB), [
            'amount' => 1150, 'paid_at' => now()->toDateString(), 'method' => 'cash',
        ]);
        $payment = InvoicePayment::first();

        $this->actingAs($owner)->put(route('app.invoices.payments.update', [$invoiceA, $payment]), [
            'amount' => 1150, 'paid_at' => now()->toDateString(), 'method' => 'cash',
        ])->assertNotFound();
    }
}
