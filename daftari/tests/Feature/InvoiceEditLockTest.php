<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit finding CRITICAL-1: InvoiceController::update() rebuilt an
 * invoice's items/totals in place but never rebuilt its ledger posting or
 * re-applied stock — editing an already-sent invoice silently desynced the
 * journal entry (and any deducted stock) from what the invoice itself then
 * showed. Fixed by restricting edit()/update() to draft invoices only,
 * mirroring destroy()'s existing status check. This locks that fix in.
 */
class InvoiceEditLockTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $company = Company::create(['name' => 'Edit Lock Co.', 'slug' => 'edit-lock-'.uniqid(), 'vat_number' => '300012345600003']);
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
        $client = Client::create(['company_id' => $company->id, 'name' => 'Edit Lock Client']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-LOCK-1',
            'type' => 'standard', 'status' => 'draft', 'issue_date' => now()->toDateString(),
            'currency' => $company->currency, 'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id, 'description' => 'Original line', 'quantity' => 1,
            'unit_price' => 100, 'vat_rate' => 15, 'vat_amount' => 15, 'line_total' => 115,
        ]);

        return $invoice;
    }

    public function test_a_draft_invoice_can_still_be_edited(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $invoice = $this->makeDraftInvoice($company);

        $this->actingAs($owner)->get(route('app.invoices.edit', $invoice))->assertOk();
    }

    public function test_a_sent_invoice_cannot_be_edited_and_its_ledger_entry_stays_untouched(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $invoice = $this->makeDraftInvoice($company);

        $this->actingAs($owner)->post(route('app.invoices.send', $invoice));
        $invoice->refresh();
        $this->assertSame('sent', $invoice->status);

        $entry = JournalEntry::where('source_type', 'invoice')->where('source_id', $invoice->id)->first();
        $this->assertNotNull($entry);
        $originalDebit = (float) $entry->lines()->sum('debit');
        $this->assertSame(115.0, $originalDebit);

        $editResponse = $this->actingAs($owner)->get(route('app.invoices.edit', $invoice));
        $editResponse->assertRedirect(route('app.invoices.show', $invoice));
        $editResponse->assertSessionHasErrors('invoice');

        $updateResponse = $this->actingAs($owner)->put(route('app.invoices.update', $invoice), [
            'client_id' => $invoice->client_id, 'type' => 'standard', 'issue_date' => now()->toDateString(),
            'items' => [['description' => 'Tampered line', 'quantity' => 1, 'unit_price' => 999]],
        ]);
        $updateResponse->assertRedirect(route('app.invoices.show', $invoice));
        $updateResponse->assertSessionHasErrors('invoice');

        $invoice->refresh();
        $this->assertSame(115.0, (float) $invoice->total);
        $this->assertSame('Original line', $invoice->items()->first()->description);

        $entry->refresh();
        $this->assertSame($originalDebit, (float) $entry->lines()->sum('debit'));
        $this->assertSame(1, JournalEntry::where('source_type', 'invoice')->where('source_id', $invoice->id)->count());
    }
}
