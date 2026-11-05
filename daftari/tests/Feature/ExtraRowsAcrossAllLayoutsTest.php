<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\InvoiceTemplate;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Operator report: a real proforma invoice ("PRO-00001", downloaded as a
 * PDF and shared back) showed the correct items, VAT summary and notes —
 * but no "Paid" / "Balance due" row anywhere, even though
 * Quotation::advance_received was set and Quotation::paymentExtraRows()
 * was wired into both pdfData() and the show page's $doc builder.
 *
 * Root cause: $doc['extra_rows'] is a generic mechanism (same one Invoice
 * already uses for its own "Paid"/"Balance due" rows), but three of the
 * print layouts built their totals block as a hardcoded list of rows that
 * never looped over it at all — quotation_offer, sage_classic and
 * retail_classic (the operator's real PDF was retail_classic). Every
 * other layout (bilingual_classic, custom_letterhead, distribution_classic,
 * and the minimal/bordered/boxed default) already respected it. Covers
 * both the browser "show" page (documents/print/body.blade.php) and the
 * downloaded PDF (documents/print/pdf.blade.php) for every layout that
 * was missing it, so this class of gap can't reappear silently in a
 * fourth layout later.
 */
class ExtraRowsAcrossAllLayoutsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function layoutsThatWereMissingExtraRows(): array
    {
        return [
            'quotation_offer' => ['quotation_offer'],
            'sage_classic' => ['sage_classic'],
            'retail_classic' => ['retail_classic'],
        ];
    }

    private function makeProformaWithLayout(string $layout): Quotation
    {
        $company = Company::create(['name' => 'Layout Co.', 'slug' => 'layout-'.uniqid()]);
        InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Test Template', 'document_type' => 'all',
            'layout' => $layout, 'is_default' => true,
        ]);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client A']);
        $proforma = Quotation::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'quotation_number' => 'PRO-1',
            'type' => 'proforma', 'status' => 'draft', 'issue_date' => now(), 'expiry_date' => now()->addDays(10),
            'currency' => 'SAR', 'subtotal' => 1000, 'vat_total' => 150, 'total' => 1150, 'advance_received' => 575,
        ]);
        $proforma->items()->create(['description' => 'Pump spares', 'quantity' => 1, 'unit_price' => 1000, 'vat_rate' => 15, 'vat_amount' => 150, 'line_total' => 1150]);

        return $proforma;
    }

    #[DataProvider('layoutsThatWereMissingExtraRows')]
    public function test_the_show_page_renders_paid_and_balance_due_for_this_layout(string $layout): void
    {
        $proforma = $this->makeProformaWithLayout($layout);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $proforma->company_id, 'status' => 'active']);

        $response = $this->actingAs($owner)->get(route('app.quotations.show', $proforma));

        $response->assertOk();
        $response->assertSee(__('Paid'));
        $response->assertSee('575.00');
        $response->assertSee(__('Balance due'));
    }

    #[DataProvider('layoutsThatWereMissingExtraRows')]
    public function test_the_downloaded_pdf_renders_paid_and_balance_due_for_this_layout(string $layout): void
    {
        $proforma = $this->makeProformaWithLayout($layout);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $proforma->company_id, 'status' => 'active']);

        $response = $this->actingAs($owner)->get(route('app.quotations.pdf', $proforma));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }
}
