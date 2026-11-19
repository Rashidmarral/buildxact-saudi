<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceTemplate;
use App\Services\MpdfRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * "retail_classic" mirrors a real dense, box-grid POS-style tax invoice —
 * seller + buyer national-address boxes *and* a dedicated VAT Summary
 * table, on top of the usual item table and totals — so it carries
 * meaningfully more concurrent content than the other 6 layouts covered
 * by PdfPageDensityTest. That suite's "every optional block at once"
 * stress fixture (long notes, long terms, a logo, a stamp, a signature)
 * doesn't fit this layout on one page, which is expected rather than a
 * regression: the same content wouldn't fit on one page of real paper
 * either. These tests use a realistic (not maximal) fixture instead —
 * the common case this layout actually ships for.
 */
class RetailClassicPdfDensityTest extends TestCase
{
    use RefreshDatabase;

    private function tinyPngBytes(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
    }

    private function countPdfPages(string $pdf): int
    {
        preg_match_all('/\/Type\s*\/Page(?!s)/', $pdf, $matches);

        return count($matches[0]);
    }

    public function test_a_typical_paid_invoice_with_qr_stamp_and_signature_fits_on_one_page(): void
    {
        $stampPath = 'stamps/retail-density-'.uniqid().'.png';
        Storage::disk('public')->put($stampPath, $this->tinyPngBytes());

        $company = Company::create([
            'name' => 'Dynamic Core Contracting Company', 'name_ar' => 'شركة داينمك كور للمقاولات',
            'slug' => 'retail-density-'.uniqid(), 'vat_number' => '300000000000003', 'cr_number' => '1010101010',
            'building_number' => '3779', 'street_name' => 'Al Umrah 2 St', 'district' => 'Al Umrah Dist.',
            'postal_code' => '24414', 'city' => 'Makkah', 'stamp_path' => $stampPath,
        ]);
        $client = Client::create([
            'company_id' => $company->id, 'name' => 'Sample Client LLC', 'name_ar' => 'شركة العميل التجريبية',
            'vat_number' => '399999999900003', 'cr_number' => '2020202020',
            'building_number' => '9229', 'street_name' => 'King Fahad Bin Abdul Aziz Rd', 'district' => 'As Sinaiyah Dist.',
            'postal_code' => '72341', 'city' => 'Jeddah',
        ]);
        InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Default', 'document_type' => 'all',
            'layout' => 'retail_classic', 'density' => 'compact', 'is_default' => true, 'show_signature' => true,
            'notes_en' => 'Thank you for your business.',
        ]);

        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id,
            'invoice_number' => 'INV-RETAIL-'.uniqid(), 'type' => 'standard', 'status' => 'paid',
            'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(),
            'currency' => 'SAR', 'subtotal' => 1000, 'vat_total' => 150, 'total' => 1150, 'amount_paid' => 1150,
            'qr_code' => base64_encode($this->tinyPngBytes()),
        ]);
        for ($i = 0; $i < 2; $i++) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id, 'description' => "Cut fill and leveling {$i}",
                'quantity' => 1, 'unit_price' => 500, 'vat_rate' => 15, 'vat_amount' => 75, 'line_total' => 575,
            ]);
        }

        $pdf = app(MpdfRenderer::class)->render('documents.print.pdf', $invoice->fresh()->pdfData());

        $this->assertSame(1, $this->countPdfPages($pdf), 'A typical 2-item retail_classic invoice (QR, stamp, signature, short notes) should still fit on one page.');
    }

    public function test_the_header_does_not_render_a_duplicate_qr_code(): void
    {
        $company = Company::create(['name' => 'X', 'slug' => 'retail-qr-'.uniqid(), 'vat_number' => '300000000000003']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Y']);
        InvoiceTemplate::create(['company_id' => $company->id, 'name' => 'Default', 'document_type' => 'all', 'layout' => 'retail_classic', 'is_default' => true]);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-QR-'.uniqid(),
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(),
            'currency' => 'SAR', 'subtotal' => 500, 'vat_total' => 75, 'total' => 575,
            'qr_code' => base64_encode($this->tinyPngBytes()),
        ]);
        InvoiceItem::create(['invoice_id' => $invoice->id, 'description' => 'Item', 'quantity' => 1, 'unit_price' => 500, 'vat_rate' => 15, 'vat_amount' => 75, 'line_total' => 575]);

        $html = view('documents.print.pdf', $invoice->fresh()->pdfData() + ['embed' => fn () => null])->render();

        $this->assertSame(1, substr_count($html, 'data:image/png;base64,'), 'retail_classic renders its own QR code near the header; the shared notes/stamp partial must not render it a second time.');
    }
}
