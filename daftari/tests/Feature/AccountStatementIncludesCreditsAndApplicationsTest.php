<?php

namespace Tests\Feature;

use App\Models\AdvanceCredit;
use App\Models\AdvanceCreditApplication;
use App\Models\AdvanceCreditPortion;
use App\Models\Client;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "please look another up to date accounting system how they show
 * client statement add if we are missing anything in our" — a client
 * statement's own closing balance must always tie out to the sum of
 * every invoice's own (already correct) balanceDue(), which already
 * subtracts creditedTotal()/advanceCreditAppliedTotal()/
 * prepaymentAppliedTotal() — but the statement's own running-balance
 * ledger only ever summed Invoice debits against InvoicePayment/
 * allocation credits, silently ignoring credit notes, Advance Credit
 * applications, and Prepayment Invoice applications entirely. A client
 * with any of those would see a statement balance that disagreed with
 * what they actually owe.
 */
class AccountStatementIncludesCreditsAndApplicationsTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): User
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);

        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    public function test_a_credit_note_reduces_the_statement_balance_and_matches_balance_due(): void
    {
        $owner = $this->makeOwner();
        $client = Client::create(['company_id' => $owner->company_id, 'name' => 'Sada Al Jeul Construction']);
        $invoice = Invoice::create(['company_id' => $owner->company_id, 'client_id' => $client->id, 'invoice_number' => 'INV-00001', 'issue_date' => now(), 'status' => 'sent', 'currency' => 'SAR', 'subtotal' => 100000, 'vat_total' => 15000, 'total' => 115000]);
        CreditNote::create([
            'company_id' => $owner->company_id, 'invoice_id' => $invoice->id, 'client_id' => $client->id,
            'credit_note_number' => 'CN-00001', 'issue_date' => now(), 'status' => 'issued',
            'subtotal' => 20000, 'vat_total' => 3000, 'total' => 23000, 'currency' => 'SAR',
        ]);

        $response = $this->actingAs($owner)->get(route('app.reports.account-statement', ['type' => 'customer', 'client_id' => $client->id]));

        $response->assertOk();
        $response->assertSee('Credit note CN-00001 — INV-00001');
        // The statement's own closing balance must agree with the
        // invoice's own balanceDue() (115,000 - 23,000 credited = 92,000).
        $this->assertEqualsWithDelta(92000.0, $invoice->fresh()->balanceDue(), 0.01);
        $response->assertSee('92,000.00');
    }

    public function test_an_advance_credit_application_reduces_the_statement_balance(): void
    {
        $owner = $this->makeOwner();
        $client = Client::create(['company_id' => $owner->company_id, 'name' => 'Sada Al Jeul Construction']);
        $invoice = Invoice::create(['company_id' => $owner->company_id, 'client_id' => $client->id, 'invoice_number' => 'INV-2026-00005', 'issue_date' => now(), 'status' => 'sent', 'currency' => 'SAR', 'subtotal' => 116539.50, 'vat_total' => 17480.93, 'total' => 134020.43]);
        $advanceCredit = AdvanceCredit::create(['company_id' => $owner->company_id, 'client_id' => $client->id, 'name' => 'Asphalt advance', 'created_by' => $owner->id]);
        $portion = AdvanceCreditPortion::create(['company_id' => $owner->company_id, 'advance_credit_id' => $advanceCredit->id, 'description' => 'Asphalt advance', 'amount' => 73497.39, 'vat_rate' => 15, 'vat_amount' => 11024.61, 'total' => 84522.11, 'sort_order' => 0]);
        AdvanceCreditApplication::create(['company_id' => $owner->company_id, 'advance_credit_portion_id' => $portion->id, 'invoice_id' => $invoice->id, 'amount' => 73497.39, 'vat_amount' => 11024.61, 'total' => 84522.11, 'created_by' => $owner->id]);

        $response = $this->actingAs($owner)->get(route('app.reports.account-statement', ['type' => 'customer', 'client_id' => $client->id]));

        $response->assertOk();
        $response->assertSee('Advance credit applied — INV-2026-00005');
        $this->assertEqualsWithDelta(49498.32, $invoice->fresh()->balanceDue(), 0.01);
        $response->assertSee('49,498.32');
    }
}
