<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Client;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "can we add multiple debit and credit note for same invoice and if i
 * issued debit note of invoice can i issue it's credit note as well?" —
 * all three were already supported with no code change needed; this
 * just locks the behavior in with tests rather than leaving it
 * unverified. Credit notes are capped by Invoice::remainingCreditableTotal()
 * (original invoice total minus credit notes already issued — a debit
 * note never reduces that room, since it's a separate document adding
 * new charges, not something being credited back). Debit notes have no
 * cap at all: each one is a new charge on top, so nothing stops issuing
 * as many as needed.
 */
class MultipleCreditDebitNotesPerInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompanyWithInvoice(float $total = 1000, float $vat = 150): array
    {
        $company = Company::create(['name' => 'Multi Note Co.', 'slug' => 'multi-note-'.uniqid()]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $client = Client::create(['company_id' => $company->id, 'name' => 'Client A']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-1',
            'type' => 'standard', 'issue_date' => now(), 'due_date' => now()->addDays(30),
            'status' => 'sent', 'currency' => 'SAR', 'subtotal' => $total, 'vat_total' => $vat, 'total' => $total + $vat,
        ]);

        return [$company, $owner, $client, $invoice];
    }

    public function test_two_credit_notes_can_be_issued_against_the_same_invoice_while_within_its_total(): void
    {
        [, $owner, , $invoice] = $this->makeCompanyWithInvoice(1000, 150); // total 1150

        $this->actingAs($owner)->post(route('app.credit-notes.store'), [
            'invoice_id' => $invoice->id, 'issue_date' => now()->toDateString(),
            'items' => [['description' => 'Partial credit 1', 'quantity' => 1, 'unit_price' => 400, 'vat_rate' => 15]],
        ])->assertSessionDoesntHaveErrors();

        $this->actingAs($owner)->post(route('app.credit-notes.store'), [
            'invoice_id' => $invoice->id, 'issue_date' => now()->toDateString(),
            'items' => [['description' => 'Partial credit 2', 'quantity' => 1, 'unit_price' => 400, 'vat_rate' => 15]],
        ])->assertSessionDoesntHaveErrors();

        $this->assertSame(2, CreditNote::where('invoice_id', $invoice->id)->count());
        $this->assertEqualsWithDelta(230.0, $invoice->fresh()->remainingCreditableTotal(), 0.01); // 1150 - 460 - 460
    }

    public function test_a_credit_note_exceeding_the_remaining_creditable_total_is_rejected(): void
    {
        [, $owner, , $invoice] = $this->makeCompanyWithInvoice(1000, 150); // total 1150

        $this->actingAs($owner)->post(route('app.credit-notes.store'), [
            'invoice_id' => $invoice->id, 'issue_date' => now()->toDateString(),
            'items' => [['description' => 'Big credit', 'quantity' => 1, 'unit_price' => 900, 'vat_rate' => 15]], // 1035
        ])->assertSessionDoesntHaveErrors();

        $response = $this->actingAs($owner)->post(route('app.credit-notes.store'), [
            'invoice_id' => $invoice->id, 'issue_date' => now()->toDateString(),
            'items' => [['description' => 'Over the limit', 'quantity' => 1, 'unit_price' => 200, 'vat_rate' => 15]], // 230 more, only 115 left
        ]);

        $response->assertSessionHasErrors('items');
        $this->assertSame(1, CreditNote::where('invoice_id', $invoice->id)->count());
    }

    public function test_two_debit_notes_can_be_issued_against_the_same_invoice_with_no_cap(): void
    {
        [, $owner, , $invoice] = $this->makeCompanyWithInvoice(1000, 150);

        $this->actingAs($owner)->post(route('app.debit-notes.store'), [
            'invoice_id' => $invoice->id, 'issue_date' => now()->toDateString(),
            'items' => [['description' => 'Extra freight', 'quantity' => 1, 'unit_price' => 500, 'vat_rate' => 15]],
        ])->assertSessionDoesntHaveErrors();

        $this->actingAs($owner)->post(route('app.debit-notes.store'), [
            'invoice_id' => $invoice->id, 'issue_date' => now()->toDateString(),
            'items' => [['description' => 'Price correction', 'quantity' => 1, 'unit_price' => 500, 'vat_rate' => 15]],
        ])->assertSessionDoesntHaveErrors();

        $this->assertSame(2, DebitNote::where('invoice_id', $invoice->id)->count());
    }

    public function test_a_credit_note_and_a_debit_note_can_both_be_issued_against_the_same_invoice(): void
    {
        [, $owner, , $invoice] = $this->makeCompanyWithInvoice(1000, 150);

        $this->actingAs($owner)->post(route('app.debit-notes.store'), [
            'invoice_id' => $invoice->id, 'issue_date' => now()->toDateString(),
            'items' => [['description' => 'Under-billed freight', 'quantity' => 1, 'unit_price' => 100, 'vat_rate' => 15]],
        ])->assertSessionDoesntHaveErrors();

        $this->actingAs($owner)->post(route('app.credit-notes.store'), [
            'invoice_id' => $invoice->id, 'issue_date' => now()->toDateString(),
            'items' => [['description' => 'Unrelated overcharge', 'quantity' => 1, 'unit_price' => 100, 'vat_rate' => 15]],
        ])->assertSessionDoesntHaveErrors();

        $this->assertSame(1, DebitNote::where('invoice_id', $invoice->id)->count());
        $this->assertSame(1, CreditNote::where('invoice_id', $invoice->id)->count());
        // A debit note already issued doesn't shrink how much the invoice
        // can still be credited — the two are independent, each capped
        // (or not) against the invoice's own original total.
        $this->assertEqualsWithDelta(1150 - 115, $invoice->fresh()->remainingCreditableTotal(), 0.01);
    }
}
