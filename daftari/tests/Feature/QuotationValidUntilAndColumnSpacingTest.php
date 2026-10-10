<?php

namespace Tests\Feature;

use App\Http\Controllers\User\QuotationController;
use App\Models\Client;
use App\Models\Company;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reported bug: a real downloaded Quotation PDF (QTN-00006) had no "Valid
 * until" line anywhere — traced to expiry_date being nullable, so a user
 * who cleared the pre-filled default (or whose form submission otherwise
 * omitted it) silently saved a quotation with no validity date at all.
 * Quotation::isExpired() also depends on expiry_date, so a null value
 * quietly broke the "Expired" lifecycle too, not just the printout.
 * expiry_date is now required, matching the create form's own 30-day
 * default. Separately, the Qty and unit Price columns in the line-items
 * table sat right next to each other with almost no gap (2px padding and
 * no divider), which the report says reads as one blurred number to a new
 * user — both the mPDF print template and its on-screen/browser-print
 * counterpart now give that pair of columns a wider gutter and a divider.
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

    public function test_the_pdf_line_items_table_gives_qty_and_price_columns_a_visible_gutter(): void
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
        // this report's), $layout defaults to 'minimal'. Its Qty column
        // now carries a wider right-hand gutter plus a divider ahead of
        // the Unit price column, instead of identical 10px padding on
        // both sides with nothing between them.
        $this->assertStringContainsString('padding: 6px 16px 6px 10px; border-right: 0.5pt solid #e2e8f0;', $html);
        $this->assertStringContainsString('padding: 6px 10px 6px 16px;', $html);
    }

    public function test_the_on_screen_print_view_also_separates_qty_and_price_columns(): void
    {
        [$owner, $client] = $this->makeOwnerAndClient();
        $payload = $this->basePayload($client) + ['expiry_date' => now()->addDays(30)->toDateString()];
        $this->actingAs($owner)->post(route('app.quotations.store'), $payload);
        $quotation = Quotation::latest('id')->first();

        $response = $this->actingAs($owner)->get(route('app.quotations.show', $quotation));

        $response->assertOk();
        $response->assertSee('border-e border-slate-100', false);
    }
}
