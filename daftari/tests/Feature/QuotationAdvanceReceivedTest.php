<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Client;
use App\Models\Company;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "in performa invoice it's not showing how much paid and how much
 * remain" — a proforma invoice previously had nowhere to record an
 * advance already received; the only option was burying it in free-text
 * notes, which print as a small line below the totals, easy to miss.
 * Quotation::advance_received now drives a real "Paid" / "Balance due"
 * row (Quotation::paymentExtraRows(), mirroring Invoice's own
 * amount_paid/balanceDue() pair) shown directly under the Total on every
 * print layout. Meaningful for a Proforma Invoice only — an ordinary
 * Quotation has no concept of a payment already received against it.
 */
class QuotationAdvanceReceivedTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompanyWithClient(): array
    {
        $company = Company::create(['name' => 'Advance Co.', 'slug' => 'advance-'.uniqid()]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client A']);

        return [$company, $owner, $client];
    }

    public function test_an_advance_received_can_be_set_on_a_proforma_invoice(): void
    {
        [, $owner, $client] = $this->makeCompanyWithClient();

        $this->actingAs($owner)->post(route('app.quotations.store'), [
            'client_id' => $client->id,
            'type' => 'proforma',
            'issue_date' => now()->toDateString(),
            'expiry_date' => now()->addDays(30)->toDateString(),
            'advance_received' => 500,
            'items' => [['description' => 'Pump spares', 'quantity' => 1, 'unit_price' => 1000, 'vat_rate' => 15]],
        ])->assertSessionDoesntHaveErrors();

        $proforma = Quotation::where('type', 'proforma')->latest('id')->first();
        $this->assertEqualsWithDelta(500, $proforma->advance_received, 0.01);
        $this->assertEqualsWithDelta($proforma->total - 500, $proforma->balanceDue(), 0.01);
    }

    public function test_an_advance_received_is_ignored_for_a_plain_quotation(): void
    {
        [, $owner, $client] = $this->makeCompanyWithClient();

        $this->actingAs($owner)->post(route('app.quotations.store'), [
            'client_id' => $client->id,
            'type' => 'quotation',
            'issue_date' => now()->toDateString(),
            'expiry_date' => now()->addDays(30)->toDateString(),
            'advance_received' => 500,
            'items' => [['description' => 'Pump spares', 'quantity' => 1, 'unit_price' => 1000, 'vat_rate' => 15]],
        ])->assertSessionDoesntHaveErrors();

        $quotation = Quotation::where('type', 'quotation')->latest('id')->first();
        $this->assertNull($quotation->advance_received);
    }

    public function test_the_proforma_show_page_prints_a_paid_and_balance_due_row(): void
    {
        [$company, $owner, $client] = $this->makeCompanyWithClient();
        $proforma = Quotation::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'quotation_number' => 'PRO-1',
            'type' => 'proforma', 'status' => 'draft', 'issue_date' => now(), 'expiry_date' => now()->addDays(10),
            'currency' => 'SAR', 'subtotal' => 1000, 'vat_total' => 150, 'total' => 1150, 'advance_received' => 575,
        ]);
        $proforma->items()->create(['description' => 'Item', 'quantity' => 1, 'unit_price' => 1000, 'vat_rate' => 15, 'vat_amount' => 150, 'line_total' => 1150]);

        $response = $this->actingAs($owner)->get(route('app.quotations.show', $proforma));

        $response->assertOk();
        $response->assertSee(__('Paid'));
        $response->assertSee('575.00');
        $response->assertSee(__('Balance due'));
        $response->assertSee('575.00');
    }

    public function test_a_fully_paid_proforma_shows_no_outstanding_balance(): void
    {
        [$company, $owner, $client] = $this->makeCompanyWithClient();
        $proforma = Quotation::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'quotation_number' => 'PRO-2',
            'type' => 'proforma', 'status' => 'draft', 'issue_date' => now(), 'expiry_date' => now()->addDays(10),
            'currency' => 'SAR', 'subtotal' => 1000, 'vat_total' => 150, 'total' => 1150, 'advance_received' => 1150,
        ]);

        $this->assertEqualsWithDelta(0, $proforma->balanceDue(), 0.001);
    }

    public function test_the_create_form_exposes_the_advance_received_field_for_a_proforma(): void
    {
        [, $owner] = $this->makeCompanyWithClient();

        $response = $this->actingAs($owner)->get(route('app.quotations.create', ['type' => 'proforma']));

        $response->assertOk();
        $response->assertSee('advance_received', false);
    }

    public function test_updating_a_draft_proforma_can_change_its_advance_received(): void
    {
        [$company, $owner, $client] = $this->makeCompanyWithClient();
        $proforma = Quotation::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'quotation_number' => 'PRO-3',
            'type' => 'proforma', 'status' => 'draft', 'issue_date' => now(), 'expiry_date' => now()->addDays(10),
            'currency' => 'SAR', 'subtotal' => 1000, 'vat_total' => 150, 'total' => 1150, 'advance_received' => 500,
        ]);
        $proforma->items()->create(['description' => 'Item', 'quantity' => 1, 'unit_price' => 1000, 'vat_rate' => 15, 'vat_amount' => 150, 'line_total' => 1150]);

        $this->actingAs($owner)->put(route('app.quotations.update', $proforma), [
            'client_id' => $client->id,
            'type' => 'proforma',
            'issue_date' => now()->toDateString(),
            'expiry_date' => now()->addDays(10)->toDateString(),
            'advance_received' => 1150,
            'items' => [['description' => 'Item', 'quantity' => 1, 'unit_price' => 1000, 'vat_rate' => 15]],
        ])->assertSessionDoesntHaveErrors();

        $this->assertEqualsWithDelta(1150, $proforma->fresh()->advance_received, 0.01);
    }
}
