<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\TaxRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Security audit finding M-02: a line's tax_rate_id and its numeric
 * vat_rate were accepted completely independently — a client could tag a
 * line as zero-rated (tax_rate_id pointing to a 0% TaxRate) while still
 * submitting a 15% vat_rate, producing an e-invoice whose reported tax
 * category doesn't match the VAT actually charged and recorded.
 */
class InvoiceVatRateMustMatchLinkedTaxRateTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        TaxRate::seedDefaults($company->id);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['company_id' => $company->id, 'role' => 'owner', 'status' => 'active']);
    }

    private function baseInvoicePayload(Company $company, array $lineOverrides): array
    {
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client A']);

        return [
            'client_id' => $client->id,
            'type' => 'standard',
            'issue_date' => now()->toDateString(),
            'items' => [array_merge([
                'description' => 'Widget', 'quantity' => 1, 'unit_price' => 100,
            ], $lineOverrides)],
        ];
    }

    public function test_a_zero_rated_tax_rate_id_with_a_mismatched_15_percent_vat_rate_is_rejected(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $zeroRated = TaxRate::where('company_id', $company->id)->where('type', TaxRate::TYPE_ZERO_RATED)->first();

        $response = $this->actingAs($owner)->post(route('app.invoices.store'), $this->baseInvoicePayload($company, [
            'vat_rate' => 15, 'tax_rate_id' => $zeroRated->id,
        ]));

        $response->assertSessionHasErrors('items.0.vat_rate');
        $this->assertSame(0, Invoice::where('company_id', $company->id)->count());
    }

    public function test_a_tax_rate_id_with_a_matching_vat_rate_is_accepted(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $standard = TaxRate::where('company_id', $company->id)->where('type', TaxRate::TYPE_STANDARD)->first();

        $response = $this->actingAs($owner)->post(route('app.invoices.store'), $this->baseInvoicePayload($company, [
            'vat_rate' => 15, 'tax_rate_id' => $standard->id,
        ]));

        $response->assertSessionDoesntHaveErrors();
        $this->assertSame(1, Invoice::where('company_id', $company->id)->count());
    }

    public function test_a_vat_rate_with_no_tax_rate_id_at_all_is_unaffected(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->post(route('app.invoices.store'), $this->baseInvoicePayload($company, [
            'vat_rate' => 15,
        ]));

        $response->assertSessionDoesntHaveErrors();
        $this->assertSame(1, Invoice::where('company_id', $company->id)->count());
    }
}
