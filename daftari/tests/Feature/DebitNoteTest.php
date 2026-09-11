<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Client;
use App\Models\Company;
use App\Models\DebitNote;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\User;
use App\Models\ZatcaDebitNoteLog;
use App\Services\Zatca\ZatcaXmlGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit finding MEDIUM-10: ZATCA's e-invoicing spec treats Debit Notes as
 * a distinct sales-side document type (InvoiceTypeCode 383) from Credit
 * Notes (381) — a real charge raised on top of an already-issued invoice,
 * the mirror image of a credit note. This app had no such feature: the
 * only prior "Debit Notes" work was Purchase Return, which is a
 * buyer-side adjustment ZATCA never regulates (an internal document, not
 * a real 383 submission). This adds a genuine sales-side Debit Note
 * module mirroring Credit Notes end to end — creation, GL posting, ZATCA
 * XML with the correct type code, and void/lock behavior.
 */
class DebitNoteTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompanyWithInvoice(): array
    {
        $company = Company::create(['name' => 'Debit Note Co.', 'slug' => 'debit-note-'.uniqid()]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $client = Client::create(['company_id' => $company->id, 'name' => 'Big Client LLC']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-1',
            'type' => 'standard', 'issue_date' => now(), 'due_date' => now()->addDays(30),
            'status' => 'sent', 'currency' => 'SAR', 'subtotal' => 1000, 'vat_total' => 150, 'total' => 1150,
        ]);

        return [$company, $owner, $client, $invoice];
    }

    public function test_issuing_a_debit_note_creates_it_and_posts_a_balanced_ledger_entry_that_raises_receivables(): void
    {
        [, $owner, , $invoice] = $this->makeCompanyWithInvoice();

        $response = $this->actingAs($owner)->post(route('app.debit-notes.store'), [
            'invoice_id' => $invoice->id,
            'issue_date' => now()->toDateString(),
            'reason' => 'Under-billed freight charge',
            'items' => [
                ['description' => 'Additional freight', 'quantity' => 1, 'unit_price' => 200, 'vat_rate' => 15],
            ],
        ]);

        $response->assertSessionDoesntHaveErrors();

        $debitNote = DebitNote::latest('id')->first();
        $this->assertNotNull($debitNote);
        $this->assertSame('issued', $debitNote->status);
        $this->assertSame(200.0, (float) $debitNote->subtotal);
        $this->assertSame(30.0, (float) $debitNote->vat_total);
        $this->assertSame(230.0, (float) $debitNote->total);
        $this->assertSame($invoice->id, $debitNote->invoice_id);
        $this->assertSame($invoice->client_id, $debitNote->client_id);

        $entry = JournalEntry::where('company_id', $debitNote->company_id)
            ->where('source_type', 'debit_note')->where('source_id', $debitNote->id)
            ->with('lines')->first();

        $this->assertNotNull($entry);
        $totalDebit = $entry->lines->sum('debit');
        $totalCredit = $entry->lines->sum('credit');
        $this->assertEqualsWithDelta($totalDebit, $totalCredit, 0.01);
        $this->assertEqualsWithDelta(230.0, $totalDebit, 0.01);

        // Receivable is debited (raised), revenue and VAT output credited —
        // exactly the mirror image of postCreditNote()'s signs.
        $ar = AccountMapping::resolve($debitNote->company_id, 'ACCOUNTS_RECEIVABLE');
        $arLine = $entry->lines->firstWhere('account_id', $ar->id);
        $this->assertEqualsWithDelta(230.0, (float) $arLine->debit, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $arLine->credit, 0.01);
    }

    public function test_debit_note_xml_carries_invoice_type_code_383_and_references_the_original_invoice(): void
    {
        [$company, , $client, $invoice] = $this->makeCompanyWithInvoice();

        $debitNote = DebitNote::create([
            'company_id' => $company->id, 'invoice_id' => $invoice->id, 'client_id' => $client->id,
            'debit_note_number' => 'DN-1', 'issue_date' => now(), 'status' => 'issued', 'currency' => 'SAR',
            'subtotal' => 200, 'vat_total' => 30, 'total' => 230,
        ]);
        $debitNote->items()->create([
            'description' => 'Additional freight', 'quantity' => 1, 'unit_price' => 200,
            'vat_rate' => 15, 'vat_amount' => 30, 'line_total' => 230,
        ]);

        $xml = app(ZatcaXmlGenerator::class)->generateForDebitNote($debitNote, '0100000', null, (string) \Illuminate\Support\Str::uuid(), 1);

        $this->assertStringContainsString('<cbc:InvoiceTypeCode name="0100000">383</cbc:InvoiceTypeCode>', $xml);
        $this->assertStringContainsString('<cbc:ID>'.$invoice->invoice_number.'</cbc:ID>', $xml);
        $this->assertStringContainsString('<cbc:ID>DN-1</cbc:ID>', $xml);
    }

    public function test_voiding_a_zatca_cleared_debit_note_is_refused(): void
    {
        [$company, $owner, $client, $invoice] = $this->makeCompanyWithInvoice();

        $debitNote = DebitNote::create([
            'company_id' => $company->id, 'invoice_id' => $invoice->id, 'client_id' => $client->id,
            'debit_note_number' => 'DN-1', 'issue_date' => now(), 'status' => 'issued', 'currency' => 'SAR',
            'subtotal' => 200, 'vat_total' => 30, 'total' => 230,
        ]);
        ZatcaDebitNoteLog::create([
            'company_id' => $company->id, 'debit_note_id' => $debitNote->id,
            'environment' => 'production', 'invoice_type' => 'b2b', 'direction' => 'clearance',
            'status' => 'cleared', 'request_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'submitted_at' => now(), 'cleared_at' => now(),
        ]);

        $response = $this->actingAs($owner)->post(route('app.debit-notes.void', $debitNote));

        $response->assertSessionHasErrors('debit_note');
        $this->assertSame('issued', $debitNote->fresh()->status);
    }

    public function test_voiding_a_debit_note_never_synced_to_zatca_reverses_its_ledger_entry(): void
    {
        [, $owner, , $invoice] = $this->makeCompanyWithInvoice();

        $this->actingAs($owner)->post(route('app.debit-notes.store'), [
            'invoice_id' => $invoice->id,
            'issue_date' => now()->toDateString(),
            'items' => [
                ['description' => 'Correction', 'quantity' => 1, 'unit_price' => 100, 'vat_rate' => 15],
            ],
        ]);
        $debitNote = DebitNote::latest('id')->first();

        $response = $this->actingAs($owner)->post(route('app.debit-notes.void', $debitNote));

        $response->assertSessionDoesntHaveErrors('debit_note');
        $this->assertSame('void', $debitNote->fresh()->status);
        $this->assertTrue(JournalEntry::where('source_type', 'debit_note_reversal')->where('source_id', $debitNote->id)->exists());
    }

    public function test_eligible_invoices_endpoint_returns_issued_invoices_for_the_search(): void
    {
        [, $owner, , $invoice] = $this->makeCompanyWithInvoice();

        $response = $this->actingAs($owner)->getJson(route('app.debit-notes.eligible-invoices', ['q' => 'INV-1']));

        $response->assertOk();
        $response->assertJsonFragment(['invoice_number' => $invoice->invoice_number]);
    }
}
