<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\Invoice;
use App\Models\User;
use App\Models\ZatcaCreditNoteLog;
use App\Models\ZatcaDebitNoteLog;
use App\Models\ZatcaInvoiceLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "how can i check in response if there is any warning or something
 * showing or not" — ZATCA's raw response (and anything inside its
 * validationResults beyond a bare pass/fail, like warningMessages it can
 * return even on an otherwise-successful clearance/reporting) was only
 * ever visible to Daftari's own platform admins, via the equivalent
 * admin.zatca.logs.show page. A company itself had no way to see its own
 * submission detail at all — only a flash "synced"/"failed: <error>"
 * message. Adds the company-facing mirror of that page.
 */
class ZatcaCompanyFacingLogDetailTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): array
    {
        $company = Company::create(['name' => 'ZATCA Log Co.', 'slug' => 'zatca-log-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        return [$company, $owner];
    }

    public function test_a_company_user_can_view_their_own_invoice_log_with_warnings_surfaced(): void
    {
        [$company, $owner] = $this->makeOwner();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client A']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-LOG-1',
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(),
            'currency' => $company->currency, 'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
        ]);
        $log = ZatcaInvoiceLog::create([
            'company_id' => $company->id, 'invoice_id' => $invoice->id,
            'environment' => 'simulation', 'invoice_type' => 'b2b', 'direction' => 'clearance',
            'status' => 'cleared', 'request_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'response_payload' => json_encode([
                'validationResults' => [
                    'warningMessages' => [['message' => 'Line item tax category looks unusual for this item type.']],
                    'infoMessages' => [],
                    'errorMessages' => [],
                ],
            ]),
        ]);

        $response = $this->actingAs($owner)->get(route('app.zatca.logs.show', ['type' => 'invoice', 'log' => $log->id]));

        $response->assertOk();
        $response->assertSee('INV-LOG-1');
        $response->assertSee('Line item tax category looks unusual for this item type.');
        $response->assertSee(__('Warnings from ZATCA'));
    }

    public function test_a_cleared_log_with_no_messages_shows_a_clean_result_banner(): void
    {
        [$company, $owner] = $this->makeOwner();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client A']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-LOG-2',
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(),
            'currency' => $company->currency, 'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
        ]);
        $log = ZatcaInvoiceLog::create([
            'company_id' => $company->id, 'invoice_id' => $invoice->id,
            'environment' => 'simulation', 'invoice_type' => 'b2b', 'direction' => 'clearance',
            'status' => 'cleared', 'request_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'response_payload' => json_encode(['clearanceStatus' => 'CLEARED']),
        ]);

        $response = $this->actingAs($owner)->get(route('app.zatca.logs.show', ['type' => 'invoice', 'log' => $log->id]));

        $response->assertOk();
        $response->assertSee(__('ZATCA returned no warnings or additional messages for this submission — a clean clearance/reporting result.'));
    }

    public function test_a_company_cannot_view_another_companys_zatca_log(): void
    {
        [$companyA, $ownerA] = $this->makeOwner();
        [$companyB] = $this->makeOwner();
        $clientB = Client::create(['company_id' => $companyB->id, 'name' => 'Client B']);
        $invoiceB = Invoice::create([
            'company_id' => $companyB->id, 'client_id' => $clientB->id, 'invoice_number' => 'INV-LOG-B',
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(),
            'currency' => $companyB->currency, 'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
        ]);
        $logB = ZatcaInvoiceLog::create([
            'company_id' => $companyB->id, 'invoice_id' => $invoiceB->id,
            'environment' => 'simulation', 'invoice_type' => 'b2b', 'direction' => 'clearance',
            'status' => 'cleared', 'request_uuid' => (string) \Illuminate\Support\Str::uuid(),
        ]);

        $this->actingAs($ownerA)->get(route('app.zatca.logs.show', ['type' => 'invoice', 'log' => $logB->id]))
            ->assertNotFound();
    }

    public function test_credit_note_and_debit_note_logs_render_through_the_same_page(): void
    {
        [$company, $owner] = $this->makeOwner();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client A']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-LOG-CD',
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(),
            'currency' => $company->currency, 'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
        ]);
        $creditNote = CreditNote::create([
            'company_id' => $company->id, 'invoice_id' => $invoice->id, 'client_id' => $client->id,
            'credit_note_number' => 'CN-LOG-1', 'issue_date' => now()->toDateString(), 'status' => 'issued',
            'subtotal' => 10, 'vat_total' => 1.5, 'total' => 11.5, 'currency' => $company->currency,
        ]);
        $debitNote = DebitNote::create([
            'company_id' => $company->id, 'invoice_id' => $invoice->id, 'client_id' => $client->id,
            'debit_note_number' => 'DN-LOG-1', 'issue_date' => now()->toDateString(), 'status' => 'issued',
            'subtotal' => 10, 'vat_total' => 1.5, 'total' => 11.5, 'currency' => $company->currency,
        ]);

        $creditLog = ZatcaCreditNoteLog::create([
            'company_id' => $company->id, 'credit_note_id' => $creditNote->id,
            'environment' => 'simulation', 'invoice_type' => 'b2b',
            'direction' => 'clearance', 'status' => 'failed', 'request_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'error_message' => 'Something went wrong.',
        ]);
        $debitLog = ZatcaDebitNoteLog::create([
            'company_id' => $company->id, 'debit_note_id' => $debitNote->id,
            'environment' => 'simulation', 'invoice_type' => 'b2b',
            'direction' => 'clearance', 'status' => 'cleared', 'request_uuid' => (string) \Illuminate\Support\Str::uuid(),
        ]);

        $this->actingAs($owner)->get(route('app.zatca.logs.show', ['type' => 'credit_note', 'log' => $creditLog->id]))->assertOk();
        $this->actingAs($owner)->get(route('app.zatca.logs.show', ['type' => 'debit_note', 'log' => $debitLog->id]))->assertOk();
    }

    public function test_an_invalid_log_type_404s(): void
    {
        [$company, $owner] = $this->makeOwner();

        $this->actingAs($owner)->get(route('app.zatca.logs.show', ['type' => 'not-a-real-type', 'log' => 1]))
            ->assertNotFound();
    }

    public function test_the_dashboard_links_to_the_log_detail_page(): void
    {
        [$company, $owner] = $this->makeOwner();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client A']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-LOG-3',
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(),
            'currency' => $company->currency, 'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
        ]);
        $log = ZatcaInvoiceLog::create([
            'company_id' => $company->id, 'invoice_id' => $invoice->id,
            'environment' => 'simulation', 'invoice_type' => 'b2b', 'direction' => 'clearance',
            'status' => 'cleared', 'request_uuid' => (string) \Illuminate\Support\Str::uuid(),
        ]);
        $company->update(['zatca_integration_mode' => Company::ZATCA_MODE_PHASE2]);

        $response = $this->actingAs($owner)->get(route('app.zatca.dashboard'));

        $response->assertOk();
        $response->assertSee(route('app.zatca.logs.show', ['type' => 'invoice', 'log' => $log->id]), false);
    }
}
