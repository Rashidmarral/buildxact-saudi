<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
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

    /**
     * "retail_classic" is deliberately excluded here — see
     * RetailClassicPdfDensityTest. It mirrors a real dense, box-grid
     * POS-style tax invoice (seller + buyer national-address boxes
     * *and* a dedicated VAT Summary table, on top of the usual item
     * table and totals), so this suite's "every optional block at
     * once" stress fixture (long notes, long terms, signature, logo,
     * stamp) genuinely doesn't fit this one layout on a single page —
     * the same way that much real content wouldn't fit on one page of
     * actual paper either. Forcing it to match would mean shrinking
     * its type past legibility or dropping fields the reference
     * document actually shows. The other 6 layouts carry less
     * concurrent content and this exact fixture is representative for
     * them.
     */
    public static function layouts(): array
    {
        return [['minimal'], ['bordered'], ['boxed'], ['bilingual_classic'], ['custom_letterhead'], ['sage_classic']];
    }

    #[DataProvider('layouts')]
    public function test_a_three_item_invoice_with_full_content_fits_on_one_pdf_page(string $layout): void
    {
        $invoice = $this->makeRealisticInvoice($layout, 3);

        $pdf = app(MpdfRenderer::class)->render('documents.print.pdf', $invoice->pdfData());

        $this->assertSame(1, $this->countPdfPages($pdf), "Layout \"{$layout}\" spilled a 3-item invoice onto a second page.");
    }

    /**
     * User-reported regression: a real, QR-code-and-stamp-bearing, fully
     * paid 2-item invoice (bank details + a one-line note, no signature)
     * spilled its QR/stamp onto an otherwise-blank second page — adding
     * the Paid/Pending header badge (see pdf-payment-status-badge.blade.php)
     * was, on its own, just enough extra height to tip a borderline
     * invoice over. Fixed by trimming the badge's own padding/margin and
     * the gaps around the QR/stamp/notes/footer block, and dropping the
     * "Scan to verify invoice details" caption under the QR entirely.
     */
    #[DataProvider('layouts')]
    public function test_a_paid_two_item_invoice_with_the_header_badge_still_fits_on_one_page(string $layout): void
    {
        $invoice = $this->makeRealisticInvoice($layout, 2);
        $invoice->update(['status' => 'paid', 'amount_paid' => $invoice->total]);

        $pdf = app(MpdfRenderer::class)->render('documents.print.pdf', $invoice->fresh()->pdfData());

        $this->assertSame(1, $this->countPdfPages($pdf), "Layout \"{$layout}\" spilled a paid 2-item invoice (with its header badge) onto a second page.");
    }

    /**
     * User-reported regression: a bilingual_classic invoice with just 2
     * line items already spilled its company stamp and footer onto a
     * blank 2nd page, and real competitor invoicing tools comfortably
     * fit 5-8 line items on one page. Root causes were threefold: (1)
     * the new "Payment method" row (see InvoicePdfPaymentPresentationTest)
     * added just enough height to tip bilingual_classic's already-tall
     * bilingual rows over the edge; (2) mPDF's page-break sizing for a
     * bare trailing <div> right after a <table> at the very end of the
     * flow overestimates the space it needs — reproduced directly: it
     * forced a lone 2nd page for a one-line footer even with 20+mm of
     * visible room left on page 1, fixed by wrapping every layout's
     * closing footer note in a <table> instead; (3) bilingual_classic's
     * info/item table rows and bank-totals block had never been through
     * the same padding/font-size tightening pass sage_classic and
     * retail_classic already got. A paid 5-item invoice (logo, stamp,
     * signature, bank details, notes, terms, payment method — every
     * optional block at once) now fits on one page for every layout
     * except retail_classic (see its own dedicated, lighter test).
     */
    #[DataProvider('layouts')]
    public function test_a_paid_five_item_invoice_with_a_payment_method_still_fits_on_one_page(string $layout): void
    {
        $invoice = $this->makeRealisticInvoice($layout, 5);
        InvoicePayment::create(['invoice_id' => $invoice->id, 'amount' => $invoice->total, 'paid_at' => now(), 'method' => 'bank_transfer']);
        $invoice->update(['status' => 'paid', 'amount_paid' => $invoice->total]);

        $pdf = app(MpdfRenderer::class)->render('documents.print.pdf', $invoice->fresh()->pdfData());

        $this->assertSame(1, $this->countPdfPages($pdf), "Layout \"{$layout}\" spilled a paid 5-item invoice (with a payment method shown) onto a second page.");
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

        $this->assertStringContainsString('.notes-block { margin-top: 6px;', $compactHtml);
        $this->assertStringContainsString('.notes-block { margin-top: 14px;', $comfortableHtml);
        $this->assertStringNotContainsString('.notes-block { margin-top: 14px;', $compactHtml);
    }
}
