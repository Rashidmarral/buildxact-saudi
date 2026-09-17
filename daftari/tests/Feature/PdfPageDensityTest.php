<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceTemplate;
use App\Services\MpdfRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Bug report: a downloaded PDF with only 2-3 line items still spilled
 * onto a second page, pushing the QR/stamp there and leaving the first
 * page looking sparse — every section (header, party/ZATCA box, bank
 * details, notes, terms, signature) carried more vertical padding/margin
 * than a printed business document needs, and it added up. Tightened
 * spacing across documents/print/pdf.blade.php and its partials for all
 * 5 layouts; these tests build a realistic invoice (logo, stamp, bank
 * account, template notes/terms, signature) and assert the real,
 * mPDF-rendered PDF is a single page.
 */
class PdfPageDensityTest extends TestCase
{
    use RefreshDatabase;

    private function tinyPngBytes(): string
    {
        // A 1x1 PNG — the template forces a fixed render box (e.g. 180x180
        // for the QR, 48px-tall for the logo) via CSS regardless of the
        // source image's real dimensions, so this occupies the same
        // layout space a real image would.
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
    }

    private function makeRealisticInvoice(string $layout, int $itemCount = 3, string $density = 'compact'): Invoice
    {
        $logoPath = 'logos/density-test-'.uniqid().'.png';
        $stampPath = 'stamps/density-test-'.uniqid().'.png';
        Storage::disk('public')->put($logoPath, $this->tinyPngBytes());
        Storage::disk('public')->put($stampPath, $this->tinyPngBytes());

        $company = Company::create([
            'name' => 'Dynamic Core Contracting Company', 'name_ar' => 'شركة داينمك كور للمقاولات',
            'slug' => 'density-'.uniqid(), 'vat_number' => '300000000000003',
            'address' => '1234 King Fahd Road, Riyadh 12345, Saudi Arabia',
            'logo_path' => $logoPath, 'stamp_path' => $stampPath,
        ]);
        $bankAccount = BankAccount::create([
            'company_id' => $company->id, 'name' => 'Main Account', 'bank_name' => 'Al Rajhi Bank',
            'account_holder_name' => 'Dynamic Core Contracting Company', 'account_number' => '1234567890',
            'iban' => 'SA0380000000608010167519',
        ]);
        $client = Client::create([
            'company_id' => $company->id, 'name' => 'Sample Client LLC', 'name_ar' => 'شركة العميل التجريبية',
            'vat_number' => '399999999900003', 'email' => 'billing@client.example',
        ]);
        InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Default', 'document_type' => 'all',
            'layout' => $layout, 'density' => $density, 'is_default' => true, 'show_signature' => true,
            'notes_en' => "Thank you for your business.\nAll amounts are in Saudi Riyals (SAR).",
            'terms_en' => "Payment is due within 15 days of the invoice date.\nGoods remain the property of the seller until paid in full.\nLate payments may incur a 2% monthly surcharge.",
        ]);

        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'bank_account_id' => $bankAccount->id,
            'invoice_number' => 'INV-DENSITY-'.uniqid(), 'type' => 'standard', 'status' => 'sent',
            'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(),
            'currency' => $company->currency ?? 'SAR', 'subtotal' => 0, 'discount_total' => 0, 'vat_total' => 0, 'total' => 0,
            'qr_code' => base64_encode($this->tinyPngBytes()),
        ]);

        for ($i = 0; $i < $itemCount; $i++) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id, 'description' => "Line item {$i}",
                'quantity' => 1, 'unit_price' => 500, 'vat_rate' => 15, 'vat_amount' => 75, 'line_total' => 575,
            ]);
        }
        $invoice->update(['subtotal' => 500 * $itemCount, 'vat_total' => 75 * $itemCount, 'total' => 575 * $itemCount]);

        return $invoice->fresh();
    }

    private function countPdfPages(string $pdf): int
    {
        preg_match_all('/\/Type\s*\/Page(?!s)/', $pdf, $matches);

        return count($matches[0]);
    }

    public static function layouts(): array
    {
        return [['minimal'], ['bordered'], ['boxed'], ['bilingual_classic'], ['custom_letterhead']];
    }

    #[DataProvider('layouts')]
    public function test_a_three_item_invoice_with_full_content_fits_on_one_pdf_page(string $layout): void
    {
        $invoice = $this->makeRealisticInvoice($layout, 3);

        $pdf = app(MpdfRenderer::class)->render('documents.print.pdf', $invoice->pdfData());

        $this->assertSame(1, $this->countPdfPages($pdf), "Layout \"{$layout}\" spilled a 3-item invoice onto a second page.");
    }

    /**
     * Flexibility request: a "density" toggle on the template, so a
     * company that doesn't mind an extra page can opt back into more
     * generous spacing. Comparing the same invoice at both settings
     * proves the toggle actually changes the rendered spacing rather
     * than being a no-op field.
     */
    public function test_comfortable_density_uses_visibly_more_spacing_than_compact(): void
    {
        $compactInvoice = $this->makeRealisticInvoice('minimal', 3, 'compact');
        $comfortableInvoice = $this->makeRealisticInvoice('minimal', 3, 'comfortable');

        $compactHtml = view('documents.print.pdf', $compactInvoice->pdfData() + ['embed' => fn () => null])->render();
        $comfortableHtml = view('documents.print.pdf', $comfortableInvoice->pdfData() + ['embed' => fn () => null])->render();

        $this->assertStringContainsString('.notes-block { margin-top: 8px;', $compactHtml);
        $this->assertStringContainsString('.notes-block { margin-top: 14px;', $comfortableHtml);
        $this->assertStringNotContainsString('.notes-block { margin-top: 14px;', $compactHtml);
    }
}
