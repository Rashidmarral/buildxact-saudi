<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Client;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\User;
use App\Models\ZatcaCreditNoteLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Commercial audit finding A7: Invoice::cancel() already refuses to
 * proceed once isZatcaLocked() is true (cleared/reported invoices are an
 * immutable tax record — a Credit Note is the compliant way to correct
 * them), but CreditNoteController::void() had no equivalent guard. A
 * credit note that had itself already been cleared/reported to ZATCA
 * could still be voided in-app, reversing its GL entry while ZATCA's own
 * record of it stayed unchanged.
 */
class CreditNoteZatcaLockTest extends TestCase
{
    use RefreshDatabase;

    private function makeCreditNote(): CreditNote
    {
        $company = Company::create(['name' => 'ZATCA Lock Co.', 'slug' => 'zatca-lock-'.uniqid()]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $client = Client::create(['company_id' => $company->id, 'name' => 'Client']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-1',
            'issue_date' => now(), 'due_date' => now()->addDays(30), 'status' => 'sent', 'currency' => 'SAR',
        ]);

        $creditNote = CreditNote::create([
            'company_id' => $company->id, 'invoice_id' => $invoice->id, 'client_id' => $client->id,
            'credit_note_number' => 'CN-1', 'issue_date' => now(), 'status' => 'issued', 'currency' => 'SAR',
            'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
        ]);

        $this->owner = $owner;

        return $creditNote;
    }

    private User $owner;

    public function test_voiding_a_zatca_cleared_credit_note_is_refused(): void
    {
        $creditNote = $this->makeCreditNote();
        ZatcaCreditNoteLog::create([
            'company_id' => $creditNote->company_id, 'credit_note_id' => $creditNote->id,
            'environment' => 'production', 'invoice_type' => 'standard', 'direction' => 'outbound',
            'status' => 'cleared', 'request_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'submitted_at' => now(), 'cleared_at' => now(),
        ]);

        $response = $this->actingAs($this->owner)->post(route('app.credit-notes.void', $creditNote));

        $response->assertSessionHasErrors('credit_note');
        $creditNote->refresh();
        $this->assertSame('issued', $creditNote->status);
    }

    public function test_voiding_a_credit_note_never_synced_to_zatca_still_works(): void
    {
        $creditNote = $this->makeCreditNote();

        $response = $this->actingAs($this->owner)->post(route('app.credit-notes.void', $creditNote));

        $response->assertSessionDoesntHaveErrors('credit_note');
        $creditNote->refresh();
        $this->assertSame('void', $creditNote->status);
    }
}
