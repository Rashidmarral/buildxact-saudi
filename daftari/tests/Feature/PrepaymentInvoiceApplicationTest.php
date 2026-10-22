<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PrepaymentInvoice;
use App\Models\PrepaymentInvoiceApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A genuine ZATCA 386 Prepayment Invoice could previously only ever be
 * applied once, in full, to a single final invoice — there was no way to
 * split one advance across several completion invoices the way real PO
 * billing works (a 20% advance, drawn down 30%+30% across two separate
 * milestone invoices, with the remainder held for a later one). This
 * mirrors AdvanceCreditTest's own reconciliation scenario, but on the
 * genuine 386 document, so every draw-down still carries full ZATCA
 * PrepaidAmount/document-reference linkage — see
 * ZatcaXmlGenerator::generate()'s $prepaymentApplications handling.
 */
class PrepaymentInvoiceApplicationTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompanyWithClient(): array
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'currency' => 'SAR']);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Modern Flour Mill']);

        return [$company, $owner, $client];
    }

    private function makePrepayment(Company $company, Client $client, float $amount = 100000): PrepaymentInvoice
    {
        return PrepaymentInvoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'prepayment_number' => 'PRE-00001',
            'type' => 'standard', 'status' => 'issued', 'issue_date' => now()->toDateString(),
            'amount' => $amount, 'vat_rate' => 15, 'vat_amount' => round($amount * 0.15, 2), 'total' => round($amount * 1.15, 2), 'currency' => 'SAR',
        ]);
    }

    public function test_applying_two_partial_amounts_matches_the_reconciliation(): void
    {
        [$company, $owner, $client] = $this->makeCompanyWithClient();
        $prepayment = $this->makePrepayment($company, $client, 100000); // total 115,000

        $completedWorks = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-00002',
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(),
            'currency' => 'SAR', 'subtotal' => 250000, 'vat_total' => 37500, 'total' => 287500,
        ]);
        InvoiceItem::create(['invoice_id' => $completedWorks->id, 'description' => 'Subgrade preparation completed', 'quantity' => 10000, 'unit_price' => 9, 'vat_rate' => 15, 'vat_amount' => 13500, 'line_total' => 103500]);
        InvoiceItem::create(['invoice_id' => $completedWorks->id, 'description' => 'Aggregate base course completed', 'quantity' => 10000, 'unit_price' => 16, 'vat_rate' => 15, 'vat_amount' => 24000, 'line_total' => 184000]);

        $this->actingAs($owner)->post(route('app.prepayment-invoices.apply', $completedWorks), [
            'prepayment_invoice_id' => $prepayment->id,
            'amount' => 57500,
        ])->assertRedirect();

        $completedWorks->refresh();
        $this->assertEqualsWithDelta(57500, $completedWorks->prepaymentAppliedTotal(), 0.01);
        $this->assertEqualsWithDelta(230000, $completedWorks->balanceDue(), 0.01);
        $this->assertEqualsWithDelta(57500, $prepayment->fresh()->remainingTotal(), 0.01);
    }

    public function test_an_application_cannot_exceed_the_prepayments_remaining_balance(): void
    {
        [$company, $owner, $client] = $this->makeCompanyWithClient();
        $prepayment = $this->makePrepayment($company, $client, 18000); // total 20,700
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-X',
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(),
            'currency' => 'SAR', 'subtotal' => 100000, 'vat_total' => 15000, 'total' => 115000,
        ]);

        $this->actingAs($owner)->post(route('app.prepayment-invoices.apply', $invoice), [
            'prepayment_invoice_id' => $prepayment->id,
            'amount' => 100000, // far more than the prepayment's own 20,700 total
        ])->assertRedirect();

        $this->assertEqualsWithDelta(20700, $invoice->fresh()->prepaymentAppliedTotal(), 0.01);
        $this->assertEqualsWithDelta(0, $prepayment->fresh()->remainingTotal(), 0.01);
    }

    public function test_an_application_cannot_exceed_the_invoices_own_balance_due(): void
    {
        [$company, $owner, $client] = $this->makeCompanyWithClient();
        $prepayment = $this->makePrepayment($company, $client, 100000); // total 115,000
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-Y',
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(),
            'currency' => 'SAR', 'subtotal' => 10000, 'vat_total' => 1500, 'total' => 11500,
        ]);

        $this->actingAs($owner)->post(route('app.prepayment-invoices.apply', $invoice), [
            'prepayment_invoice_id' => $prepayment->id,
            'amount' => 115000,
        ])->assertRedirect();

        $this->assertEqualsWithDelta(11500, $invoice->fresh()->prepaymentAppliedTotal(), 0.01);
        $this->assertEqualsWithDelta(0, $invoice->fresh()->balanceDue(), 0.01);
    }

    public function test_a_prepayment_cannot_be_applied_to_a_different_clients_invoice(): void
    {
        [$company, $owner, $client] = $this->makeCompanyWithClient();
        $otherClient = Client::create(['company_id' => $company->id, 'name' => 'A Different Client']);
        $prepayment = $this->makePrepayment($company, $client, 10000);
        $otherInvoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $otherClient->id, 'invoice_number' => 'INV-Z',
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(),
            'currency' => 'SAR', 'subtotal' => 10000, 'vat_total' => 1500, 'total' => 11500,
        ]);

        $response = $this->actingAs($owner)->post(route('app.prepayment-invoices.apply', $otherInvoice), [
            'prepayment_invoice_id' => $prepayment->id,
            'amount' => 11500,
        ]);

        $response->assertSessionHasErrors('prepayment_invoice_id');
        $this->assertEqualsWithDelta(0, $otherInvoice->fresh()->prepaymentAppliedTotal(), 0.01);
    }

    public function test_a_prepayment_already_linked_in_full_via_the_simple_fk_cannot_also_take_a_partial_application(): void
    {
        [$company, $owner, $client] = $this->makeCompanyWithClient();
        $prepayment = $this->makePrepayment($company, $client, 10000);
        $fullyAppliedTo = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'applies_prepayment_invoice_id' => $prepayment->id,
            'invoice_number' => 'INV-FULL', 'type' => 'standard', 'status' => 'sent',
            'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(),
            'currency' => 'SAR', 'subtotal' => 100000, 'vat_total' => 15000, 'total' => 115000,
        ]);
        $another = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-ANOTHER',
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(),
            'currency' => 'SAR', 'subtotal' => 10000, 'vat_total' => 1500, 'total' => 11500,
        ]);

        $response = $this->actingAs($owner)->post(route('app.prepayment-invoices.apply', $another), [
            'prepayment_invoice_id' => $prepayment->id,
            'amount' => 1000,
        ]);

        $response->assertSessionHasErrors('prepayment_invoice_id');
        $this->assertEqualsWithDelta(0, $another->fresh()->prepaymentAppliedTotal(), 0.01);
        $this->assertNotNull($fullyAppliedTo->fresh()->applies_prepayment_invoice_id);
    }

    public function test_an_application_can_be_removed_and_the_prepayment_becomes_available_again(): void
    {
        [$company, $owner, $client] = $this->makeCompanyWithClient();
        $prepayment = $this->makePrepayment($company, $client, 18000); // total 20,700
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-W',
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(),
            'currency' => 'SAR', 'subtotal' => 100000, 'vat_total' => 15000, 'total' => 115000,
        ]);

        $this->actingAs($owner)->post(route('app.prepayment-invoices.apply', $invoice), [
            'prepayment_invoice_id' => $prepayment->id, 'amount' => 20700,
        ])->assertRedirect();
        $this->assertEqualsWithDelta(0, $prepayment->fresh()->remainingTotal(), 0.01);

        $application = $invoice->fresh()->prepaymentInvoiceApplications->first();
        $this->actingAs($owner)->delete(route('app.prepayment-invoices.unapply', [$invoice, $application]))->assertRedirect();

        $this->assertEqualsWithDelta(20700, $prepayment->fresh()->remainingTotal(), 0.01);
        $this->assertEqualsWithDelta(115000, $invoice->fresh()->balanceDue(), 0.01);
    }

    public function test_the_invoice_show_page_renders_the_less_prepayment_and_balance_rows(): void
    {
        [$company, $owner, $client] = $this->makeCompanyWithClient();
        $prepayment = $this->makePrepayment($company, $client, 18000);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-00002',
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(),
            'currency' => 'SAR', 'subtotal' => 100000, 'vat_total' => 15000, 'total' => 115000,
        ]);
        $invoice->prepaymentInvoiceApplications()->create([
            'company_id' => $company->id, 'prepayment_invoice_id' => $prepayment->id,
            'amount' => 18000, 'vat_amount' => 2700, 'total' => 20700, 'created_by' => $owner->id,
        ]);

        $response = $this->actingAs($owner)->get(route('app.invoices.show', $invoice));

        $response->assertOk();
        $response->assertSee('PRE-00001');
        $response->assertSee(\App\Support\Money::format(94300)); // 115,000 - 20,700
    }

    public function test_the_downloaded_pdf_renders_without_error_when_a_prepayment_is_partially_applied(): void
    {
        [$company, $owner, $client] = $this->makeCompanyWithClient();
        $prepayment = $this->makePrepayment($company, $client, 18000);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-00002',
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(),
            'currency' => 'SAR', 'subtotal' => 100000, 'vat_total' => 15000, 'total' => 115000,
        ]);
        $invoice->prepaymentInvoiceApplications()->create([
            'company_id' => $company->id, 'prepayment_invoice_id' => $prepayment->id,
            'amount' => 18000, 'vat_amount' => 2700, 'total' => 20700, 'created_by' => $owner->id,
        ]);

        $response = $this->actingAs($owner)->get(route('app.invoices.pdf', $invoice));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_a_company_cannot_apply_another_companys_prepayment_invoice(): void
    {
        [$companyA, $ownerA, $clientA] = $this->makeCompanyWithClient();
        [$companyB, , $clientB] = $this->makeCompanyWithClient();
        $prepaymentB = $this->makePrepayment($companyB, $clientB, 10000);
        $invoiceA = Invoice::create([
            'company_id' => $companyA->id, 'client_id' => $clientA->id, 'invoice_number' => 'INV-A',
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(),
            'currency' => 'SAR', 'subtotal' => 10000, 'vat_total' => 1500, 'total' => 11500,
        ]);

        $response = $this->actingAs($ownerA)->post(route('app.prepayment-invoices.apply', $invoiceA), [
            'prepayment_invoice_id' => $prepaymentB->id,
            'amount' => 11500,
        ]);

        $response->assertSessionHasErrors('prepayment_invoice_id');
    }

    public function test_a_prepayment_invoice_with_applications_cannot_be_deleted(): void
    {
        [$company, $owner, $client] = $this->makeCompanyWithClient();
        $prepayment = $this->makePrepayment($company, $client, 10000);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-D',
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(),
            'currency' => 'SAR', 'subtotal' => 10000, 'vat_total' => 1500, 'total' => 11500,
        ]);
        PrepaymentInvoiceApplication::create(['company_id' => $company->id, 'prepayment_invoice_id' => $prepayment->id, 'invoice_id' => $invoice->id, 'amount' => 10000, 'vat_amount' => 1500, 'total' => 11500]);

        $this->actingAs($owner)->delete(route('app.prepayment-invoices.destroy', $prepayment))->assertRedirect();

        $this->assertNotNull($prepayment->fresh());
    }
}
