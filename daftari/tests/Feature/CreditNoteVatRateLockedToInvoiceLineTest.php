<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Client;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\TaxRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Deep ZATCA correction-document audit finding: a credit note's VAT%
 * field was freely user-editable (resources/views/user/credit-notes/
 * form.blade.php) with no check against the invoice line it corrects —
 * a user could credit a line at a different rate than it was actually
 * billed at, or credit an exempt/zero-rated line as standard-rated,
 * producing a correction document whose e-invoice tax category silently
 * disagrees with the invoice it corrects. The rate a line was actually
 * billed under isn't something a credit note gets to renegotiate — it's
 * always taken from the original invoice line now, same as
 * name_ar/item_description already were (ItemNameSnapshotFreezeTest).
 */
class CreditNoteVatRateLockedToInvoiceLineTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $company = Company::create(['name' => 'VAT Lock Co.', 'slug' => 'vat-lock-'.uniqid()]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return $company;
    }

    public function test_a_credit_notes_submitted_vat_rate_and_tax_category_are_ignored_in_favor_of_the_original_invoice_lines(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client']);

        $exemptRate = TaxRate::create([
            'company_id' => $company->id, 'name' => 'Exempt', 'rate' => 0, 'type' => TaxRate::TYPE_EXEMPT, 'is_active' => true,
        ]);

        $this->actingAs($owner)->post(route('app.invoices.store'), [
            'client_id' => $client->id,
            'type' => 'standard',
            'issue_date' => now()->toDateString(),
            'send_immediately' => '1',
            'items' => [
                ['description' => 'Exempt service', 'quantity' => 1, 'unit_price' => 100, 'vat_rate' => 0, 'tax_rate_id' => $exemptRate->id],
            ],
        ])->assertSessionDoesntHaveErrors();

        $invoice = Invoice::latest('id')->first();
        $invoiceItem = $invoice->items()->first();
        $this->assertSame($exemptRate->id, $invoiceItem->tax_rate_id, 'Sanity check: the invoice line really is tagged exempt.');

        // Tamper attempt: submit a wildly different VAT% (as if trying to
        // credit the line at the standard rate instead of its real,
        // exempt rate).
        $this->actingAs($owner)->post(route('app.credit-notes.store'), [
            'invoice_id' => $invoice->id,
            'issue_date' => now()->toDateString(),
            'items' => [
                ['invoice_item_id' => $invoiceItem->id, 'description' => 'Exempt service', 'quantity' => 1, 'unit_price' => 100, 'vat_rate' => 15],
            ],
        ])->assertSessionDoesntHaveErrors();

        $creditNote = CreditNote::latest('id')->first();
        $creditNoteItem = $creditNote->items()->first();

        $this->assertSame(0.0, (float) $creditNoteItem->vat_rate, 'The submitted 15% must be ignored — the line was actually billed exempt at 0%.');
        $this->assertSame($exemptRate->id, $creditNoteItem->tax_rate_id, 'The credit note line must inherit the original line\'s tax category, not fall back to a standard-rate guess.');
        $this->assertSame(0.0, (float) $creditNoteItem->vat_amount);
        $this->assertSame(100.0, (float) $creditNote->total, 'Total must reflect the real (exempt) rate, not the tampered 15% submission.');
    }
}
