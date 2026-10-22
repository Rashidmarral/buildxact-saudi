<?php

namespace Tests\Feature;

use App\Models\AdvanceCredit;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "can we do this in our accounting system" — a project manager's real
 * scenario: an advance (issued as a regular invoice, not through the
 * dedicated Prepayment Invoice feature, which only supports a single
 * non-itemized amount applied once, in full, to one invoice) needs to be
 * credited — partly, split by scope of work, referencing the original
 * advance invoice — against a new completed-works invoice, while the
 * rest of that same advance stays in reserve for a later invoice.
 *
 * Reconciliation this locks in (the operator's own numbers):
 * Completed work total:      SAR 287,500.00 (Subgrade 103,500 + Aggregate base 184,000)
 * Less: subgrade advance:    SAR  20,700.00 (INV-00001)
 * Less: aggregate base adv.: SAR  36,800.00 (INV-00001)
 * Balance after adjustments: SAR 230,000.00
 * Asphalt/MC1 advance (SAR 47,507.08) stays untouched, for a later invoice.
 */
class AdvanceCreditTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompanyWithClient(): array
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'currency' => 'SAR']);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Modern Flour Mill']);

        return [$company, $owner, $client];
    }

    private function makeAdvanceInvoice(Company $company, Client $client): Invoice
    {
        return Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-00001',
            'type' => 'standard', 'status' => 'sent', 'issue_date' => '2026-09-29', 'due_date' => '2026-10-29',
            'currency' => 'SAR', 'subtotal' => 338338.65, 'vat_total' => 50750.80, 'total' => 389089.45,
        ]);
    }

    public function test_an_advance_credit_can_be_created_with_several_portions(): void
    {
        [$company, $owner, $client] = $this->makeCompanyWithClient();
        $advanceInvoice = $this->makeAdvanceInvoice($company, $client);

        $response = $this->actingAs($owner)->post(route('app.advance-credits.store'), [
            'name' => 'INV-00001 advance payment',
            'client_id' => $client->id,
            'source_invoice_id' => $advanceInvoice->id,
            'portions' => [
                ['description' => 'Subgrade advance', 'amount' => 18000, 'vat_rate' => 15],
                ['description' => 'Aggregate base advance', 'amount' => 32000, 'vat_rate' => 15],
                ['description' => 'Asphalt/MC1 advance', 'amount' => 41310.50, 'vat_rate' => 15],
            ],
        ]);

        $response->assertRedirect();
        $credit = AdvanceCredit::where('company_id', $company->id)->first();
        $this->assertNotNull($credit);
        $this->assertSame($advanceInvoice->id, $credit->source_invoice_id);
        $this->assertCount(3, $credit->portions);
        $subgrade = $credit->portions->firstWhere('description', 'Subgrade advance');
        $this->assertEqualsWithDelta(2700, $subgrade->vat_amount, 0.01);
        $this->assertEqualsWithDelta(20700, $subgrade->total, 0.01);
    }

    public function test_applying_two_portions_to_a_completed_works_invoice_matches_the_reconciliation(): void
    {
        [$company, $owner, $client] = $this->makeCompanyWithClient();
        $advanceInvoice = $this->makeAdvanceInvoice($company, $client);

        $credit = AdvanceCredit::create(['company_id' => $company->id, 'client_id' => $client->id, 'source_invoice_id' => $advanceInvoice->id, 'name' => 'INV-00001 advance']);
        $subgrade = $credit->portions()->create(['company_id' => $company->id, 'description' => 'Subgrade advance', 'amount' => 18000, 'vat_rate' => 15, 'vat_amount' => 2700, 'total' => 20700]);
        $aggregate = $credit->portions()->create(['company_id' => $company->id, 'description' => 'Aggregate base advance', 'amount' => 32000, 'vat_rate' => 15, 'vat_amount' => 4800, 'total' => 36800]);
        $credit->portions()->create(['company_id' => $company->id, 'description' => 'Asphalt/MC1 advance', 'amount' => 41310.50, 'vat_rate' => 15, 'vat_amount' => 6196.58, 'total' => 47507.08]);

        $completedWorks = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-00002',
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(),
            'currency' => 'SAR', 'subtotal' => 250000, 'vat_total' => 37500, 'total' => 287500,
        ]);
        InvoiceItem::create(['invoice_id' => $completedWorks->id, 'description' => 'Subgrade preparation completed', 'quantity' => 10000, 'unit_price' => 9, 'vat_rate' => 15, 'vat_amount' => 13500, 'line_total' => 103500]);
        InvoiceItem::create(['invoice_id' => $completedWorks->id, 'description' => 'Aggregate base course completed', 'quantity' => 10000, 'unit_price' => 16, 'vat_rate' => 15, 'vat_amount' => 24000, 'line_total' => 184000]);

        $this->actingAs($owner)->post(route('app.advance-credits.apply', $completedWorks), [
            'advance_credit_portion_id' => $subgrade->id,
            'amount' => 20700,
        ])->assertRedirect();
        $this->actingAs($owner)->post(route('app.advance-credits.apply', $completedWorks), [
            'advance_credit_portion_id' => $aggregate->id,
            'amount' => 36800,
        ])->assertRedirect();

        $completedWorks->refresh();
        $this->assertEqualsWithDelta(57500, $completedWorks->advanceCreditAppliedTotal(), 0.01);
        $this->assertEqualsWithDelta(230000, $completedWorks->balanceDue(), 0.01);

        // The asphalt/MC1 portion is untouched, available for a later invoice.
        $this->assertEqualsWithDelta(47507.08, $credit->fresh()->remainingTotal(), 0.01);
    }

    public function test_an_application_cannot_exceed_the_portions_remaining_balance(): void
    {
        [$company, $owner, $client] = $this->makeCompanyWithClient();
        $credit = AdvanceCredit::create(['company_id' => $company->id, 'client_id' => $client->id, 'name' => 'Advance']);
        $portion = $credit->portions()->create(['company_id' => $company->id, 'description' => 'Subgrade advance', 'amount' => 18000, 'vat_rate' => 15, 'vat_amount' => 2700, 'total' => 20700]);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-X',
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(),
            'currency' => 'SAR', 'subtotal' => 100000, 'vat_total' => 15000, 'total' => 115000,
        ]);

        $this->actingAs($owner)->post(route('app.advance-credits.apply', $invoice), [
            'advance_credit_portion_id' => $portion->id,
            'amount' => 100000, // far more than the portion's own 20,700 total
        ])->assertRedirect();

        // Capped at the portion's remaining total, not the requested amount.
        $this->assertEqualsWithDelta(20700, $invoice->fresh()->advanceCreditAppliedTotal(), 0.01);
        $this->assertEqualsWithDelta(0, $portion->fresh()->remainingTotal(), 0.01);
    }

    public function test_an_application_cannot_exceed_the_invoices_own_balance_due(): void
    {
        [$company, $owner, $client] = $this->makeCompanyWithClient();
        $credit = AdvanceCredit::create(['company_id' => $company->id, 'client_id' => $client->id, 'name' => 'Advance']);
        $portion = $credit->portions()->create(['company_id' => $company->id, 'description' => 'Big advance', 'amount' => 100000, 'vat_rate' => 15, 'vat_amount' => 15000, 'total' => 115000]);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-Y',
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(),
            'currency' => 'SAR', 'subtotal' => 10000, 'vat_total' => 1500, 'total' => 11500,
        ]);

        $this->actingAs($owner)->post(route('app.advance-credits.apply', $invoice), [
            'advance_credit_portion_id' => $portion->id,
            'amount' => 115000,
        ])->assertRedirect();

        // Capped at the invoice's own total (11,500), not the portion's.
        $this->assertEqualsWithDelta(11500, $invoice->fresh()->advanceCreditAppliedTotal(), 0.01);
        $this->assertEqualsWithDelta(0, $invoice->fresh()->balanceDue(), 0.01);
    }

    public function test_a_portion_cannot_be_applied_to_a_different_clients_invoice(): void
    {
        [$company, $owner, $client] = $this->makeCompanyWithClient();
        $otherClient = Client::create(['company_id' => $company->id, 'name' => 'A Different Client']);
        $credit = AdvanceCredit::create(['company_id' => $company->id, 'client_id' => $client->id, 'name' => 'Advance']);
        $portion = $credit->portions()->create(['company_id' => $company->id, 'description' => 'Advance', 'amount' => 10000, 'vat_rate' => 15, 'vat_amount' => 1500, 'total' => 11500]);
        $otherInvoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $otherClient->id, 'invoice_number' => 'INV-Z',
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(),
            'currency' => 'SAR', 'subtotal' => 10000, 'vat_total' => 1500, 'total' => 11500,
        ]);

        $response = $this->actingAs($owner)->post(route('app.advance-credits.apply', $otherInvoice), [
            'advance_credit_portion_id' => $portion->id,
            'amount' => 11500,
        ]);

        $response->assertSessionHasErrors('advance_credit_portion_id');
        $this->assertEqualsWithDelta(0, $otherInvoice->fresh()->advanceCreditAppliedTotal(), 0.01);
    }

    public function test_an_application_can_be_removed_and_the_portion_becomes_available_again(): void
    {
        [$company, $owner, $client] = $this->makeCompanyWithClient();
        $credit = AdvanceCredit::create(['company_id' => $company->id, 'client_id' => $client->id, 'name' => 'Advance']);
        $portion = $credit->portions()->create(['company_id' => $company->id, 'description' => 'Subgrade advance', 'amount' => 18000, 'vat_rate' => 15, 'vat_amount' => 2700, 'total' => 20700]);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-W',
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(),
            'currency' => 'SAR', 'subtotal' => 100000, 'vat_total' => 15000, 'total' => 115000,
        ]);

        $this->actingAs($owner)->post(route('app.advance-credits.apply', $invoice), [
            'advance_credit_portion_id' => $portion->id, 'amount' => 20700,
        ])->assertRedirect();
        $this->assertEqualsWithDelta(0, $portion->fresh()->remainingTotal(), 0.01);

        $application = $invoice->fresh()->advanceCreditApplications->first();
        $this->actingAs($owner)->delete(route('app.advance-credits.unapply', [$invoice, $application]))->assertRedirect();

        $this->assertEqualsWithDelta(20700, $portion->fresh()->remainingTotal(), 0.01);
        $this->assertEqualsWithDelta(115000, $invoice->fresh()->balanceDue(), 0.01);
    }

    public function test_the_invoice_show_page_renders_the_less_advance_and_balance_rows(): void
    {
        [$company, $owner, $client] = $this->makeCompanyWithClient();
        $advanceInvoice = $this->makeAdvanceInvoice($company, $client);
        $credit = AdvanceCredit::create(['company_id' => $company->id, 'client_id' => $client->id, 'source_invoice_id' => $advanceInvoice->id, 'name' => 'Advance']);
        $portion = $credit->portions()->create(['company_id' => $company->id, 'description' => 'Subgrade advance', 'amount' => 18000, 'vat_rate' => 15, 'vat_amount' => 2700, 'total' => 20700]);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-00002',
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(),
            'currency' => 'SAR', 'subtotal' => 100000, 'vat_total' => 15000, 'total' => 115000,
        ]);
        $invoice->advanceCreditApplications()->create([
            'company_id' => $company->id, 'advance_credit_portion_id' => $portion->id,
            'amount' => 18000, 'vat_amount' => 2700, 'total' => 20700, 'created_by' => $owner->id,
        ]);

        $response = $this->actingAs($owner)->get(route('app.invoices.show', $invoice));

        $response->assertOk();
        $response->assertSee('Subgrade advance');
        $response->assertSee('INV-00001');
        $response->assertSee(\App\Support\Money::format(94300)); // 115,000 - 20,700
    }

    public function test_the_downloaded_pdf_renders_without_error_when_advance_credits_are_applied(): void
    {
        [$company, $owner, $client] = $this->makeCompanyWithClient();
        $credit = AdvanceCredit::create(['company_id' => $company->id, 'client_id' => $client->id, 'name' => 'Advance']);
        $portion = $credit->portions()->create(['company_id' => $company->id, 'description' => 'Subgrade advance', 'amount' => 18000, 'vat_rate' => 15, 'vat_amount' => 2700, 'total' => 20700]);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-00002',
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(),
            'currency' => 'SAR', 'subtotal' => 100000, 'vat_total' => 15000, 'total' => 115000,
        ]);
        $invoice->advanceCreditApplications()->create([
            'company_id' => $company->id, 'advance_credit_portion_id' => $portion->id,
            'amount' => 18000, 'vat_amount' => 2700, 'total' => 20700, 'created_by' => $owner->id,
        ]);

        $response = $this->actingAs($owner)->get(route('app.invoices.pdf', $invoice));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_a_company_cannot_apply_another_companys_advance_portion(): void
    {
        [$companyA, $ownerA, $clientA] = $this->makeCompanyWithClient();
        [$companyB, , $clientB] = $this->makeCompanyWithClient();
        $creditB = AdvanceCredit::create(['company_id' => $companyB->id, 'client_id' => $clientB->id, 'name' => 'Advance B']);
        $portionB = $creditB->portions()->create(['company_id' => $companyB->id, 'description' => 'Advance', 'amount' => 10000, 'vat_rate' => 15, 'vat_amount' => 1500, 'total' => 11500]);
        $invoiceA = Invoice::create([
            'company_id' => $companyA->id, 'client_id' => $clientA->id, 'invoice_number' => 'INV-A',
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(),
            'currency' => 'SAR', 'subtotal' => 10000, 'vat_total' => 1500, 'total' => 11500,
        ]);

        $response = $this->actingAs($ownerA)->post(route('app.advance-credits.apply', $invoiceA), [
            'advance_credit_portion_id' => $portionB->id,
            'amount' => 11500,
        ]);

        $response->assertSessionHasErrors('advance_credit_portion_id');
    }

    public function test_deleting_an_advance_credit_removes_its_portions_and_applications(): void
    {
        [$company, $owner, $client] = $this->makeCompanyWithClient();
        $credit = AdvanceCredit::create(['company_id' => $company->id, 'client_id' => $client->id, 'name' => 'Advance']);
        $portion = $credit->portions()->create(['company_id' => $company->id, 'description' => 'Advance', 'amount' => 10000, 'vat_rate' => 15, 'vat_amount' => 1500, 'total' => 11500]);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-D',
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(),
            'currency' => 'SAR', 'subtotal' => 10000, 'vat_total' => 1500, 'total' => 11500,
        ]);
        $portion->applications()->create(['company_id' => $company->id, 'invoice_id' => $invoice->id, 'amount' => 10000, 'vat_amount' => 1500, 'total' => 11500]);

        $this->actingAs($owner)->delete(route('app.advance-credits.destroy', $credit))->assertRedirect();

        $this->assertSame(0, \App\Models\AdvanceCreditPortion::where('advance_credit_id', $credit->id)->count());
        $this->assertSame(0, \App\Models\AdvanceCreditApplication::where('advance_credit_portion_id', $portion->id)->count());
    }

    public function test_the_create_form_and_index_page_render(): void
    {
        [, $owner, $client] = $this->makeCompanyWithClient();

        $this->actingAs($owner)->get(route('app.advance-credits.index'))->assertOk();
        $this->actingAs($owner)->get(route('app.advance-credits.create'))->assertOk();
        $this->actingAs($owner)->get(route('app.advance-credits.create', ['client_id' => $client->id]))->assertOk();
    }
}
