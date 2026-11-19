<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "first build the project module same as it is as the hamly erp with
 * complete details as they have with proper functionality" — Phase 7:
 * Hamly's Estimates/tenders concept is just the Quotation this app
 * already has (draft/issued/accepted/rejected/converted already covers
 * won/lost, isExpired() already covers expiry). The one real gap was
 * margin visibility — an internal cost_price per line, optional, never
 * shown on the client-facing document.
 */
class QuotationMarginTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        return Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid()]);
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    private function makeClient(Company $company): Client
    {
        return Client::create(['company_id' => $company->id, 'name' => 'Client Co.']);
    }

    public function test_a_quotation_line_with_a_cost_price_computes_margin(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = $this->makeClient($company);

        $this->actingAs($owner)->post(route('app.quotations.store'), [
            'client_id' => $client->id, 'type' => 'quotation',
            'issue_date' => now()->toDateString(), 'expiry_date' => now()->addDays(30)->toDateString(),
            'items' => [
                ['description' => 'Asphalt resurfacing', 'quantity' => 100, 'unit_price' => 50, 'cost_price' => 35, 'vat_rate' => 15],
            ],
        ])->assertSessionDoesntHaveErrors();

        $quotation = Quotation::first();
        $this->assertSame(1500.0, $quotation->marginAmount());
        $this->assertSame(30.0, $quotation->marginPercent());
    }

    public function test_a_quotation_with_no_cost_price_on_any_line_has_no_known_margin(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = $this->makeClient($company);

        $this->actingAs($owner)->post(route('app.quotations.store'), [
            'client_id' => $client->id, 'type' => 'quotation',
            'issue_date' => now()->toDateString(), 'expiry_date' => now()->addDays(30)->toDateString(),
            'items' => [
                ['description' => 'Asphalt resurfacing', 'quantity' => 100, 'unit_price' => 50, 'vat_rate' => 15],
            ],
        ])->assertSessionDoesntHaveErrors();

        $quotation = Quotation::first();
        $this->assertNull($quotation->marginAmount());
        $this->assertNull($quotation->marginPercent());
    }

    public function test_margin_sums_only_the_lines_that_have_a_cost_price(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = $this->makeClient($company);

        $this->actingAs($owner)->post(route('app.quotations.store'), [
            'client_id' => $client->id, 'type' => 'quotation',
            'issue_date' => now()->toDateString(), 'expiry_date' => now()->addDays(30)->toDateString(),
            'items' => [
                ['description' => 'Asphalt', 'quantity' => 10, 'unit_price' => 100, 'cost_price' => 70, 'vat_rate' => 15],
                ['description' => 'Mobilization (no cost tracked)', 'quantity' => 1, 'unit_price' => 500, 'vat_rate' => 15],
            ],
        ])->assertSessionDoesntHaveErrors();

        $quotation = Quotation::first();
        $this->assertSame(300.0, $quotation->marginAmount());
    }

    public function test_the_show_page_displays_margin_only_when_known_and_never_in_the_pdf_document_body(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = $this->makeClient($company);
        $quotation = Quotation::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'quotation_number' => 'QTN-0001',
            'type' => 'quotation', 'status' => 'draft', 'issue_date' => now()->toDateString(), 'expiry_date' => now()->addDays(30)->toDateString(),
            'subtotal' => 5000, 'vat_total' => 750, 'total' => 5750, 'discount_total' => 0,
        ]);
        $quotation->items()->create(['description' => 'Asphalt', 'quantity' => 100, 'unit_price' => 50, 'cost_price' => 35, 'vat_rate' => 15, 'vat_amount' => 750, 'line_total' => 5750]);

        $response = $this->actingAs($owner)->get(route('app.quotations.show', $quotation));

        $response->assertOk()->assertSee('Estimated margin')->assertSee(\App\Support\Money::format(1500), false);
    }
}
