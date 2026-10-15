<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
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
 * The create form lets a user pick a Payment Mode (Cash/Bank transfer/
 * Card/Other) and a Payment Status (Unpaid/Paid/Partially paid) right
 * when writing the invoice — a cash-sale flow where the money already
 * changed hands and the user shouldn't have to save, then separately open
 * the invoice and use Record Payment. Paid/Partially paid record a real
 * InvoicePayment (not the separate, purely cosmetic payment_status_override
 * a user can already set after creation), which requires the invoice to
 * actually be sent first — same invariant storePayment() already enforces,
 * so revenue is never recognized without a matching GL posting.
 */
class InvoiceCreateTimePaymentStatusTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $company = Company::create(['name' => 'Cash Sale Co.', 'slug' => 'cash-sale-'.uniqid()]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    private function makeClient(Company $company): Client
    {
        return Client::create(['company_id' => $company->id, 'name' => 'Walk-in Customer']);
    }

    private function payload(Client $client, array $overrides = []): array
    {
        return array_merge([
            'client_id' => $client->id,
            'type' => 'standard',
            'issue_date' => now()->toDateString(),
            'items' => [
                ['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 1000, 'vat_rate' => 15],
            ],
        ], $overrides);
    }

    public function test_marking_paid_at_creation_sends_the_invoice_and_records_a_real_full_payment(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = $this->makeClient($company);

        $this->actingAs($owner)->post(route('app.invoices.store'), $this->payload($client, [
            'payment_mode' => 'cash',
            'payment_status' => 'paid',
        ]))->assertSessionDoesntHaveErrors();

        $invoice = Invoice::latest('id')->first();

        $this->assertSame('paid', $invoice->status);
        $this->assertEquals(1150.0, (float) $invoice->amount_paid);
        $this->assertSame(0.0, $invoice->balanceDue());

        $payment = InvoicePayment::where('invoice_id', $invoice->id)->sole();
        $this->assertEquals(1150.0, (float) $payment->amount);
        $this->assertSame('cash', $payment->method);
        $this->assertNull($payment->bank_account_id);
        $this->assertNull($invoice->payment_status_override);

        // A real GL posting exists — this is not the cosmetic override.
        $this->assertTrue(JournalEntry::where('company_id', $company->id)->where('source_type', 'invoice_payment')->where('source_id', $payment->id)->exists());
    }

    public function test_marking_partially_paid_records_only_the_given_amount_and_sets_status_accordingly(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = $this->makeClient($company);

        $this->actingAs($owner)->post(route('app.invoices.store'), $this->payload($client, [
            'payment_mode' => 'bank_transfer',
            'payment_status' => 'partially_paid',
            'amount_paid' => 400,
        ]))->assertSessionDoesntHaveErrors();

        $invoice = Invoice::latest('id')->first();

        $this->assertSame('partially_paid', $invoice->status);
        $this->assertEquals(400.0, (float) $invoice->amount_paid);
        $this->assertEquals(750.0, $invoice->balanceDue());

        $payment = InvoicePayment::where('invoice_id', $invoice->id)->sole();
        $this->assertEquals(400.0, (float) $payment->amount);
        $this->assertSame('bank_transfer', $payment->method);
    }

    public function test_bank_transfer_payment_attaches_the_invoices_own_bank_account(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = $this->makeClient($company);
        $bankAccount = BankAccount::create(['company_id' => $company->id, 'name' => 'Main Account', 'bank_name' => 'Al Rajhi', 'account_holder_name' => 'Cash Sale Co.', 'account_number' => '123', 'is_active' => true]);

        $this->actingAs($owner)->post(route('app.invoices.store'), $this->payload($client, [
            'bank_account_id' => $bankAccount->id,
            'payment_mode' => 'bank_transfer',
            'payment_status' => 'paid',
        ]))->assertSessionDoesntHaveErrors();

        $payment = InvoicePayment::where('invoice_id', Invoice::latest('id')->first()->id)->sole();
        $this->assertSame($bankAccount->id, $payment->bank_account_id);
    }

    public function test_a_cash_payment_does_not_attach_a_bank_account_even_if_one_was_picked_for_the_invoice(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = $this->makeClient($company);
        $bankAccount = BankAccount::create(['company_id' => $company->id, 'name' => 'Main Account', 'bank_name' => 'Al Rajhi', 'account_holder_name' => 'Cash Sale Co.', 'account_number' => '123', 'is_active' => true]);

        $this->actingAs($owner)->post(route('app.invoices.store'), $this->payload($client, [
            'bank_account_id' => $bankAccount->id,
            'payment_mode' => 'cash',
            'payment_status' => 'paid',
        ]))->assertSessionDoesntHaveErrors();

        $payment = InvoicePayment::where('invoice_id', Invoice::latest('id')->first()->id)->sole();
        $this->assertNull($payment->bank_account_id);
    }

    public function test_unpaid_status_leaves_the_invoice_a_normal_draft_with_no_payment_recorded(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = $this->makeClient($company);

        $this->actingAs($owner)->post(route('app.invoices.store'), $this->payload($client, [
            'payment_status' => 'unpaid',
        ]))->assertSessionDoesntHaveErrors();

        $invoice = Invoice::latest('id')->first();
        $this->assertSame('draft', $invoice->status);
        $this->assertEquals(0.0, (float) $invoice->amount_paid);
        $this->assertSame(0, InvoicePayment::where('invoice_id', $invoice->id)->count());
    }

    public function test_omitting_payment_status_entirely_behaves_exactly_as_before(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = $this->makeClient($company);

        $this->actingAs($owner)->post(route('app.invoices.store'), $this->payload($client))
            ->assertSessionDoesntHaveErrors();

        $invoice = Invoice::latest('id')->first();
        $this->assertSame('draft', $invoice->status);
        $this->assertSame(0, InvoicePayment::where('invoice_id', $invoice->id)->count());
    }

    public function test_partially_paid_amount_at_or_above_the_total_is_rejected_and_nothing_is_created(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = $this->makeClient($company);

        $this->actingAs($owner)->post(route('app.invoices.store'), $this->payload($client, [
            'payment_status' => 'partially_paid',
            'amount_paid' => 1150, // equals the full total — should be "paid", not "partially_paid"
        ]))->assertSessionHasErrors('amount_paid');

        $this->assertSame(0, Invoice::count(), 'The whole invoice+items insert should have rolled back with the validation failure.');
    }

    public function test_partially_paid_with_a_zero_amount_is_rejected(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = $this->makeClient($company);

        $this->actingAs($owner)->post(route('app.invoices.store'), $this->payload($client, [
            'payment_status' => 'partially_paid',
            'amount_paid' => 0,
        ]))->assertSessionHasErrors('amount_paid');

        $this->assertSame(0, Invoice::count());
    }

    public function test_a_credit_held_client_blocks_paid_at_creation_the_same_way_it_blocks_send_immediately(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Risky Client', 'credit_hold' => true, 'credit_hold_reason' => 'Overdue balance']);

        $this->actingAs($owner)->post(route('app.invoices.store'), $this->payload($client, [
            'payment_status' => 'paid',
        ]))->assertSessionHasErrors('invoice');

        $invoice = Invoice::latest('id')->first();
        $this->assertSame('draft', $invoice->status, 'The invoice is still saved as a draft — just not sent/paid.');
        $this->assertSame(0, InvoicePayment::where('invoice_id', $invoice->id)->count());
    }

    public function test_an_invoice_above_the_approval_threshold_is_parked_pending_approval_without_recording_a_payment(): void
    {
        $company = $this->makeCompany();
        $company->update(['invoice_approval_threshold' => 500]);
        $owner = $this->makeOwner($company);
        $client = $this->makeClient($company);

        $this->actingAs($owner)->post(route('app.invoices.store'), $this->payload($client, [
            'payment_mode' => 'cash',
            'payment_status' => 'paid',
        ]))->assertSessionDoesntHaveErrors();

        $invoice = Invoice::latest('id')->first();
        $this->assertSame('pending_approval', $invoice->status);
        $this->assertSame(0, InvoicePayment::where('invoice_id', $invoice->id)->count(), 'An unapproved invoice must never show a payment already recorded against it.');
    }
}
