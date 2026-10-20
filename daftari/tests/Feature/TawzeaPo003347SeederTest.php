<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Quotation;
use Database\Seeders\TawzeaPo003347Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "can you add this as a quotation and performa invoice for my zubaidi
 * company" — the operator shared a real Tawzea PO (MAKKAH / PO-003347, 40
 * pump/spare-parts lines) and asked for both a Quotation and a Proforma
 * Invoice built from it, with the proforma noting the 50% advance already
 * received and the 50% balance still due. This locks in that the seeder
 * reconstructs both documents with the PO's own totals and surfaces the
 * advance/balance split through the proforma's notes (rendered as the
 * "Payment Terms" list on every print layout — there's no separate
 * amount-paid field on a quotation/proforma).
 */
class TawzeaPo003347SeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedZubaidiCompany(): Company
    {
        return Company::create([
            'name' => 'Zubaidi Test Co.',
            'slug' => 'zubaidi-test-'.uniqid(),
            'vat_number' => '310464560600003',
        ]);
    }

    public function test_it_creates_a_quotation_and_a_proforma_invoice_with_the_pos_own_totals(): void
    {
        $company = $this->seedZubaidiCompany();

        (new TawzeaPo003347Seeder)->run();

        $client = Client::where('company_id', $company->id)->where('vat_number', '300134471900003')->first();
        $this->assertNotNull($client, 'the Tawzea client should be resolved or created');
        $this->assertSame('International Water Distribution Company', $client->name);

        $quotation = Quotation::where('company_id', $company->id)->where('type', 'quotation')->first();
        $proforma = Quotation::where('company_id', $company->id)->where('type', 'proforma')->first();

        $this->assertNotNull($quotation);
        $this->assertNotNull($proforma);

        foreach ([$quotation, $proforma] as $doc) {
            $this->assertSame($client->id, $doc->client_id);
            $this->assertCount(40, $doc->items);
            $this->assertEqualsWithDelta(334201.19, $doc->subtotal, 0.02);
            $this->assertEqualsWithDelta(50130.18, $doc->vat_total, 0.05);
            $this->assertEqualsWithDelta(384331.37, $doc->total, 0.05);
        }

        $this->assertStringContainsString('MAKKAH / PO-003347', $quotation->notes);
        $this->assertStringContainsString('Our Quotation Ref: 208', $quotation->notes);
    }

    public function test_the_proforma_invoice_states_the_advance_received_and_balance_due(): void
    {
        $this->seedZubaidiCompany();

        (new TawzeaPo003347Seeder)->run();

        $proforma = Quotation::where('type', 'proforma')->first();
        $advance = round($proforma->total / 2, 2);
        $balance = round($proforma->total - $advance, 2);

        $this->assertStringContainsString('Advance Payment Received (50%): SAR '.number_format($advance, 2), $proforma->notes);
        $this->assertStringContainsString('Balance Due (remaining 50%): SAR '.number_format($balance, 2), $proforma->notes);
        $this->assertEqualsWithDelta($proforma->total, $advance + $balance, 0.001);
    }

    public function test_reseeding_does_not_duplicate_either_document(): void
    {
        $this->seedZubaidiCompany();

        (new TawzeaPo003347Seeder)->run();
        (new TawzeaPo003347Seeder)->run();

        $this->assertSame(1, Quotation::where('type', 'quotation')->count());
        $this->assertSame(1, Quotation::where('type', 'proforma')->count());
        $this->assertSame(1, Client::where('vat_number', '300134471900003')->count());
    }

    public function test_the_show_page_renders_both_documents(): void
    {
        $company = $this->seedZubaidiCompany();
        $owner = \App\Models\User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        (new TawzeaPo003347Seeder)->run();

        $quotation = Quotation::where('type', 'quotation')->first();
        $proforma = Quotation::where('type', 'proforma')->first();

        $this->actingAs($owner)->get(route('app.quotations.show', $quotation))->assertOk();
        $this->actingAs($owner)->get(route('app.quotations.show', $proforma))->assertOk();
    }
}
