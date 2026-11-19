<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Client;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * "nothing stops you from issuing a new invoice to someone who's badly
 * overdue" — a credit-hold flag a company can set on a client, enforced
 * when actually sending a new invoice (a draft can still be prepared),
 * plus a collection-notes log with an optional promise-to-pay so the
 * next person chasing the same client isn't starting from zero. Also
 * covers emailing a Statement of Account directly — previously
 * download-only.
 */
class ClientCollectionsTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(bool $withStatements = false): Company
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000,
            'is_active' => true, 'has_client_statements' => $withStatements,
        ]);
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        Subscription::create([
            'company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
        ]);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    // ------------------------------------------------------------------
    // Credit hold
    // ------------------------------------------------------------------

    public function test_putting_a_client_on_credit_hold_blocks_sending_a_new_invoice(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Overdue Co.']);

        $this->actingAs($owner)->post(route('app.clients.credit-hold', $client), [
            'credit_hold' => '1', 'credit_hold_reason' => '90+ days overdue',
        ])->assertRedirect();
        $this->assertTrue($client->fresh()->credit_hold);

        $response = $this->actingAs($owner)->post(route('app.invoices.store'), [
            'client_id' => $client->id, 'type' => 'standard', 'issue_date' => now()->toDateString(),
            'items' => [['description' => 'Work', 'quantity' => 1, 'unit_price' => 1000, 'vat_rate' => 15]],
            'send_immediately' => '1',
        ]);

        $response->assertSessionHasErrors('invoice');
        $invoice = $client->invoices()->first();
        $this->assertNotNull($invoice, 'The invoice should still be saved as a draft.');
        $this->assertSame('draft', $invoice->status);
    }

    public function test_an_existing_draft_invoice_cannot_be_sent_while_the_client_is_on_credit_hold(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Overdue Co.']);
        $client->update(['credit_hold' => true, 'credit_hold_reason' => 'Disputed invoice']);

        $this->actingAs($owner)->post(route('app.invoices.store'), [
            'client_id' => $client->id, 'type' => 'standard', 'issue_date' => now()->toDateString(),
            'items' => [['description' => 'Work', 'quantity' => 1, 'unit_price' => 1000, 'vat_rate' => 15]],
        ])->assertRedirect();
        $invoice = $client->invoices()->first();

        $this->actingAs($owner)->post(route('app.invoices.send', $invoice))
            ->assertSessionHasErrors('invoice');

        $this->assertSame('draft', $invoice->fresh()->status);
    }

    public function test_releasing_the_hold_allows_sending_again(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Formerly Overdue Co.', 'credit_hold' => true]);
        $this->actingAs($owner)->post(route('app.invoices.store'), [
            'client_id' => $client->id, 'type' => 'standard', 'issue_date' => now()->toDateString(),
            'items' => [['description' => 'Work', 'quantity' => 1, 'unit_price' => 1000, 'vat_rate' => 15]],
        ]);
        $invoice = $client->invoices()->first();

        $this->actingAs($owner)->post(route('app.clients.credit-hold', $client), ['credit_hold' => '0'])->assertRedirect();
        $this->assertFalse($client->fresh()->credit_hold);

        $this->actingAs($owner)->post(route('app.invoices.send', $invoice))->assertSessionDoesntHaveErrors();
        $this->assertNotSame('draft', $invoice->fresh()->status);
    }

    public function test_a_client_not_on_hold_can_be_invoiced_normally(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Good Standing Co.']);

        $response = $this->actingAs($owner)->post(route('app.invoices.store'), [
            'client_id' => $client->id, 'type' => 'standard', 'issue_date' => now()->toDateString(),
            'items' => [['description' => 'Work', 'quantity' => 1, 'unit_price' => 1000, 'vat_rate' => 15]],
            'send_immediately' => '1',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertNotSame('draft', $client->invoices()->first()->status);
    }

    // ------------------------------------------------------------------
    // Collection notes
    // ------------------------------------------------------------------

    public function test_a_collection_note_can_be_added_with_a_promise_to_pay_and_removed(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Slow Payer Co.']);

        $this->actingAs($owner)->post(route('app.clients.collection-notes.store', $client), [
            'note' => 'Called the client, they confirmed receipt.',
            'promise_to_pay_date' => now()->addDays(7)->toDateString(),
            'promise_to_pay_amount' => 5000,
        ])->assertRedirect();

        $note = $client->collectionNotes()->first();
        $this->assertNotNull($note);
        $this->assertSame($owner->id, $note->created_by);
        $this->assertSame('5000.00', (string) $note->promise_to_pay_amount);

        $this->actingAs($owner)->delete(route('app.collection-notes.destroy', $note))->assertRedirect();
        $this->assertDatabaseMissing('collection_notes', ['id' => $note->id]);
    }

    public function test_a_note_requires_text(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Slow Payer Co.']);

        $this->actingAs($owner)->post(route('app.clients.collection-notes.store', $client), ['note' => ''])
            ->assertSessionHasErrors('note');
    }

    public function test_the_client_edit_page_shows_existing_notes_and_hold_status(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Slow Payer Co.', 'credit_hold' => true, 'credit_hold_reason' => 'Chronic late payer']);
        $client->collectionNotes()->create(['company_id' => $company->id, 'note' => 'Promised to pay by month end.', 'created_by' => $owner->id]);

        $response = $this->actingAs($owner)->get(route('app.clients.edit', $client));

        $response->assertOk();
        $response->assertSee('Chronic late payer');
        $response->assertSee('Promised to pay by month end.');
    }

    // ------------------------------------------------------------------
    // Emailing the Statement of Account
    // ------------------------------------------------------------------

    public function test_emailing_a_statement_sends_to_the_clients_address(): void
    {
        Mail::fake();
        $company = $this->makeCompany(withStatements: true);
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Al Khuraif Water Tech', 'email' => 'ap@khuraif.example']);

        $this->actingAs($owner)->post(route('app.client-statements.email', $client))
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect();

        // StatementMail implements ShouldQueue, so Mail::fake() records it
        // under "queued" rather than "sent" — same as every other queued
        // mailable in this app (InvoiceMail, OverdueInvoiceReminderMail).
        Mail::assertQueued(\App\Mail\StatementMail::class, fn ($mail) => $mail->hasTo('ap@khuraif.example'));
    }

    public function test_emailing_a_statement_without_a_client_email_fails_cleanly(): void
    {
        Mail::fake();
        $company = $this->makeCompany(withStatements: true);
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'No Email Co.']);

        $this->actingAs($owner)->post(route('app.client-statements.email', $client))
            ->assertSessionHasErrors('client');

        Mail::assertNothingSent();
    }
}
