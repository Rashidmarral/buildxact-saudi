<?php

namespace Tests\Feature;

use App\Http\Controllers\User\QuotationController;
use App\Models\Client;
use App\Models\Company;
use App\Models\InvoiceTemplate;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reported bug: a real downloaded Quotation PDF (QTN-00006) had no "Valid
 * until" line anywhere — two separate causes. (1) expiry_date was
 * nullable, so a user who cleared the pre-filled default (or whose form
 * submission otherwise omitted it) silently saved a quotation with no
 * validity date at all — Quotation::isExpired() also depends on
 * expiry_date, so a null value quietly broke the "Expired" lifecycle too,
 * not just the printout. expiry_date is now required, matching the create
 * form's own 30-day default. (2) Even with expiry_date set, the mPDF
 * download template's 'bilingual_classic' layout never had a "Valid
 * until" row at all in its header table — the on-screen/browser-print
 * counterpart (documents.print.body) always showed it there, so the
 * downloaded PDF alone was silently missing it. That row is now added to
 * match. Follow-up report: the Qty/Price gutter added for the first round
 * looked lopsided (one divider among otherwise-undivided columns), so
 * every column boundary in the line-items table now carries the same thin
 * divider, consistently, in both the mPDF template and the on-screen view.
 */
class QuotationValidUntilAndColumnSpacingTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwnerAndClient(): array
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting Co.', 'slug' => 'dynamic-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Sada Al Jeul Construction Establishment']);

        return [$owner, $client];
    }

    private function basePayload(Client $client): array
    {
        return [
            'client_id' => $client->id,
            'type' => 'quotation',
            'issue_date' => now()->toDateString(),
            'items' => [
                ['description' => 'Aggregate Base Course, 10 CM thickness', 'quantity' => 1, 'unit_price' => 7000, 'vat_rate' => 15],
                ['description' => 'Laying and compaction charges', 'quantity' => 1, 'unit_price' => 2500, 'vat_rate' => 15],
            ],
        ];
    }

    public function test_creating_a_quotation_without_an_expiry_date_is_rejected_instead_of_silently_saved(): void
    {
        [$owner, $client] = $this->makeOwnerAndClient();

        $response = $this->actingAs($owner)->post(route('app.quotations.store'), $this->basePayload($client));

        $response->assertSessionHasErrors('expiry_date');
        $this->assertSame(0, Quotation::count());
    }

    public function test_the_create_form_pre_fills_a_30_day_expiry_default(): void
    {
        [$owner] = $this->makeOwnerAndClient();

        $this->actingAs($owner)->get(route('app.quotations.create'))
            ->assertOk()
            ->assertSee(now()->addDays(30)->toDateString());
    }

    public function test_submitting_with_an_expiry_date_saves_it_and_the_downloaded_pdf_shows_valid_until(): void
    {
        [$owner, $client] = $this->makeOwnerAndClient();
        $payload = $this->basePayload($client) + ['expiry_date' => now()->addDays(30)->toDateString()];

        $this->actingAs($owner)->post(route('app.quotations.store'), $payload)->assertSessionDoesntHaveErrors();

        $quotation = Quotation::latest('id')->first();
        $this->assertNotNull($quotation->expiry_date);

        $method = new \ReflectionMethod(QuotationController::class, 'pdfData');
        $method->setAccessible(true);
        $data = $method->invoke(app(QuotationController::class), $quotation);

        $html = view('documents.print.pdf', $data + ['embed' => fn () => null])->render();

        $this->assertStringContainsString(__('Valid until'), $html);
        $this->assertStringContainsString(\App\Support\PlatformFormat::date($quotation->expiry_date), $html);
    }

    public function test_the_pdf_line_items_table_gives_every_column_a_matching_divider(): void
    {
        [$owner, $client] = $this->makeOwnerAndClient();
        $payload = $this->basePayload($client) + ['expiry_date' => now()->addDays(30)->toDateString()];
        $this->actingAs($owner)->post(route('app.quotations.store'), $payload);
        $quotation = Quotation::latest('id')->first();

        $method = new \ReflectionMethod(QuotationController::class, 'pdfData');
        $method->setAccessible(true);
        $data = $method->invoke(app(QuotationController::class), $quotation);

        $html = view('documents.print.pdf', $data + ['embed' => fn () => null])->render();

        // With no InvoiceTemplate configured (a fresh company, same as
        // this report's), $layout defaults to 'minimal'. Every column
        // boundary now carries the same divider — not just the one
        // between Qty and Unit price, which looked lopsided on its own.
        $this->assertGreaterThanOrEqual(3, substr_count($html, 'border-right: 0.5pt solid rgba(255,255,255,0.4);'), 'expected a header divider after Description, Qty and Unit price');
        $this->assertGreaterThanOrEqual(3, substr_count($html, 'border-right: 0.5pt solid #e2e8f0;'));
    }

    public function test_the_on_screen_print_view_gives_every_column_a_matching_divider(): void
    {
        [$owner, $client] = $this->makeOwnerAndClient();
        $payload = $this->basePayload($client) + ['expiry_date' => now()->addDays(30)->toDateString()];
        $this->actingAs($owner)->post(route('app.quotations.store'), $payload);
        $quotation = Quotation::latest('id')->first();

        $response = $this->actingAs($owner)->get(route('app.quotations.show', $quotation));

        $response->assertOk();
        // Description | Qty | Unit price headers all carry the divider
        // (VAT is off by default for a fresh company, so Unit price is
        // the last divided header before Total).
        $response->assertSee('border-e border-white/30', false);
        $this->assertGreaterThanOrEqual(3, substr_count($response->getContent(), 'border-e border-white/30'));
    }

    public function test_a_company_using_the_bilingual_classic_layout_also_shows_valid_until_in_the_downloaded_pdf(): void
    {
        [$owner, $client] = $this->makeOwnerAndClient();
        InvoiceTemplate::create([
            'company_id' => $owner->company_id, 'name' => 'Classic', 'document_type' => 'quotation',
            'layout' => 'bilingual_classic', 'is_default' => true,
        ]);
        $payload = $this->basePayload($client) + ['expiry_date' => now()->addDays(30)->toDateString()];
        $this->actingAs($owner)->post(route('app.quotations.store'), $payload);
        $quotation = Quotation::latest('id')->first();

        $method = new \ReflectionMethod(QuotationController::class, 'pdfData');
        $method->setAccessible(true);
        $data = $method->invoke(app(QuotationController::class), $quotation);

        $html = view('documents.print.pdf', $data + ['embed' => fn () => null])->render();

        $this->assertStringContainsString(__('Valid until'), $html);
        $this->assertStringContainsString(\App\Support\PlatformFormat::date($quotation->expiry_date), $html);
    }
}
