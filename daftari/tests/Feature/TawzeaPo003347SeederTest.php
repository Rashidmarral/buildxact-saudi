<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Quotation;
use App\Models\User;
use Database\Seeders\TawzeaPo003347Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "can you add this as a quotation and performa invoice for my zubaidi
 * company" — the operator shared a real Tawzea PO (MAKKAH / PO-003347, 40
 * pump/spare-parts lines) and asked for both a Quotation and a Proforma
 * Invoice built from it, with the proforma noting the 50% advance already
 * received and the 50% balance still due.
 *
 * Follow-up report: after running the seeder, "it's not showing how much
 * paid and how much remain" — the first version of this seeder put that
 * split into free-text notes, which only ever showed as a small bulleted
 * line below the totals. Fixed by giving Quotation a real advance_received
 * field (Quotation::paymentExtraRows()), which — like Invoice's own
 * amount_paid/balanceDue() — renders as a "Paid" / "Balance due" row
 * directly under the Total on every print layout. This also covers the
 * seeder's backfill path: re-running it against a proforma created by the
 * old version (advance_received still null) must fill it in instead of
 * being skipped as "already exists".
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
        $this->assertNull($quotation->advance_received, 'a plain quotation has no advance/balance split');
    }

    public function test_the_proforma_invoice_has_a_structured_advance_received_and_balance_due(): void
    {
        $this->seedZubaidiCompany();

        (new TawzeaPo003347Seeder)->run();

        $proforma = Quotation::where('type', 'proforma')->first();

        $this->assertEqualsWithDelta($proforma->total / 2, $proforma->advance_received, 0.01);
        $this->assertEqualsWithDelta($proforma->total - $proforma->advance_received, $proforma->balanceDue(), 0.001);

        $rows = $proforma->paymentExtraRows();
        $paidRow = collect($rows)->firstWhere('label', __('Paid'));
        $balanceRow = collect($rows)->firstWhere('label', __('Balance due'));

        $this->assertNotNull($paidRow);
        $this->assertEqualsWithDelta($proforma->advance_received, $paidRow['value'], 0.001);
        $this->assertNotNull($balanceRow);
        $this->assertEqualsWithDelta($proforma->balanceDue(), $balanceRow['value'], 0.001);
    }

    public function test_the_proforma_show_page_renders_the_paid_and_balance_due_rows(): void
    {
        $company = $this->seedZubaidiCompany();
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        (new TawzeaPo003347Seeder)->run();

        $proforma = Quotation::where('type', 'proforma')->first();

        $response = $this->actingAs($owner)->get(route('app.quotations.show', $proforma));

        $response->assertOk();
        $response->assertSee(__('Paid'));
        $response->assertSee(__('Balance due'));
        $response->assertSee(number_format($proforma->advance_received, 2));
        $response->assertSee(number_format($proforma->balanceDue(), 2));
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

    public function test_reseeding_backfills_advance_received_on_a_proforma_created_by_an_older_run(): void
    {
        $this->seedZubaidiCompany();

        (new TawzeaPo003347Seeder)->run();

        // Simulate a proforma created before advance_received existed.
        $proforma = Quotation::where('type', 'proforma')->first();
        $proforma->advance_received = null;
        $proforma->save();

        (new TawzeaPo003347Seeder)->run();

        $this->assertSame(1, Quotation::where('type', 'proforma')->count(), 'the backfill must not create a duplicate');
        $proforma->refresh();
        $this->assertEqualsWithDelta($proforma->total / 2, $proforma->advance_received, 0.01);
    }

    public function test_the_show_page_renders_both_documents(): void
    {
        $company = $this->seedZubaidiCompany();
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        (new TawzeaPo003347Seeder)->run();

        $quotation = Quotation::where('type', 'quotation')->first();
        $proforma = Quotation::where('type', 'proforma')->first();

        $this->actingAs($owner)->get(route('app.quotations.show', $quotation))->assertOk();
        $this->actingAs($owner)->get(route('app.quotations.show', $proforma))->assertOk();
    }
}
