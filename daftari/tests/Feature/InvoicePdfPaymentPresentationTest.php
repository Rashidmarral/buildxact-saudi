<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\InvoiceTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * User-reported issues with a downloaded, partially-paid invoice's PDF
 * (bilingual_classic layout, the app default): "Paid"/"Balance due" were
 * plain English-only text with no color, no "Partially Paid" indicator
 * anywhere, no record of how the payment was made, and the company stamp
 * was stretched into a square regardless of its real aspect ratio.
 */
class InvoicePdfPaymentPresentationTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): array
    {
        $company = Company::create(['name' => 'Presentation Co.', 'slug' => 'presentation-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        return [$company, $owner];
    }

    private function makePartiallyPaidInvoice(Company $company): Invoice
    {
        $client = Client::create(['company_id' => $company->id, 'name' => 'Sada Al Jeul Construction Establishment']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-00006',
            'status' => 'partially_paid', 'issue_date' => now(), 'due_date' => now()->addDays(30),
            'subtotal' => 456552.50, 'vat_total' => 68482.88, 'total' => 525035.38,
            'amount_paid' => 105007.08, 'currency' => 'SAR',
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id, 'description' => 'Subgrade Cutting, Leveling & Preparation',
            'quantity' => 10000, 'unit_price' => 9.00, 'vat_rate' => 15, 'vat_amount' => 13500, 'line_total' => 90000,
        ]);
        InvoicePayment::create([
            'invoice_id' => $invoice->id, 'amount' => 105007.08, 'paid_at' => now(),
            'method' => 'bank_transfer', 'reference' => 'REF-001',
        ]);

        return $invoice->fresh();
    }

    private function renderPdfHtml(Invoice $invoice): string
    {
        return view('documents.print.pdf', $invoice->pdfData() + ['embed' => fn () => null])->render();
    }

    public function test_paid_and_balance_due_render_bilingually_with_color_on_the_bilingual_classic_layout(): void
    {
        [$company] = $this->makeOwner();
        $company->invoiceTemplates()->updateOrCreate(
            ['document_type' => 'invoice'],
            ['name' => 'Bilingual', 'layout' => 'bilingual_classic', 'language_mode' => 'bilingual', 'is_default' => true]
        );
        $invoice = $this->makePartiallyPaidInvoice($company);

        $html = $this->renderPdfHtml($invoice);

        $this->assertStringContainsString('المدفوع', $html);
        $this->assertStringContainsString('الرصيد المستحق', $html);
        $this->assertStringContainsString('#059669', $html);
        $this->assertStringContainsString('#dc2626', $html);
    }

    public function test_paid_and_balance_due_render_bilingually_with_color_on_the_default_layout(): void
    {
        [$company] = $this->makeOwner();
        $company->invoiceTemplates()->updateOrCreate(
            ['document_type' => 'invoice'],
            ['name' => 'Minimal', 'layout' => 'minimal', 'language_mode' => 'bilingual', 'is_default' => true]
        );
        $invoice = $this->makePartiallyPaidInvoice($company);

        $html = $this->renderPdfHtml($invoice);

        $this->assertStringContainsString('المدفوع', $html);
        $this->assertStringContainsString('الرصيد المستحق', $html);
        $this->assertStringContainsString('#059669', $html);
        $this->assertStringContainsString('#dc2626', $html);
    }

    public function test_a_partially_paid_invoice_shows_the_partially_paid_badge(): void
    {
        [$company] = $this->makeOwner();
        $invoice = $this->makePartiallyPaidInvoice($company);

        $html = $this->renderPdfHtml($invoice);

        $this->assertStringContainsString('Partially Paid', $html);
        $this->assertStringContainsString('مدفوعة جزئيًا', $html);
    }

    public function test_a_fully_paid_invoice_shows_the_paid_badge_not_partially_paid(): void
    {
        [$company] = $this->makeOwner();
        $invoice = $this->makePartiallyPaidInvoice($company);
        $invoice->update(['status' => 'paid', 'amount_paid' => $invoice->total]);

        $html = $this->renderPdfHtml($invoice->fresh());

        $this->assertStringNotContainsString('Partially Paid', $html);
        $this->assertStringNotContainsString('مدفوعة جزئيًا', $html);
        $this->assertStringContainsString('مدفوعة بالكامل', $html);
    }

    public function test_a_draft_invoice_shows_no_payment_status_badge(): void
    {
        [$company] = $this->makeOwner();
        $invoice = $this->makePartiallyPaidInvoice($company);
        $invoice->update(['status' => 'draft', 'amount_paid' => 0]);
        $invoice->invoicePayments()->delete();

        $html = $this->renderPdfHtml($invoice->fresh());

        $this->assertStringNotContainsString('Partially Paid', $html);
        $this->assertStringNotContainsString('مدفوعة جزئيًا', $html);
    }

    public function test_the_payments_received_table_shows_the_payment_method_and_amount(): void
    {
        [$company] = $this->makeOwner();
        $invoice = $this->makePartiallyPaidInvoice($company);

        $html = $this->renderPdfHtml($invoice);

        $this->assertStringContainsString('Payments received', $html);
        $this->assertStringContainsString('Bank transfer', $html);
        $this->assertStringContainsString('105,007.08', $html);
    }

    public function test_no_payments_received_table_when_nothing_has_been_paid(): void
    {
        [$company] = $this->makeOwner();
        $invoice = $this->makePartiallyPaidInvoice($company);
        $invoice->invoicePayments()->delete();
        $invoice->update(['amount_paid' => 0, 'status' => 'sent']);

        $html = $this->renderPdfHtml($invoice->fresh());

        $this->assertStringNotContainsString('Payments received', $html);
    }

    public function test_the_company_stamp_scales_by_width_only_so_it_never_distorts(): void
    {
        [$company] = $this->makeOwner();
        $company->update(['stamp_path' => 'stamps/fake-stamp.png']);
        $invoice = $this->makePartiallyPaidInvoice($company);

        $html = view('documents.print.pdf', $invoice->pdfData() + [
            'embed' => fn ($path) => $path ? 'data:image/png;base64,fake' : null,
        ])->render();

        $this->assertMatchesRegularExpression('/class="stamp-img" style="width: \d+px;"/', $html);
        $this->assertDoesNotMatchRegularExpression('/class="stamp-img"[^>]*height:\s*\d+px/', $html);
    }

    public function test_a_company_stamp_size_override_is_used_instead_of_the_layout_default(): void
    {
        [$company] = $this->makeOwner();
        $company->update(['stamp_path' => 'stamps/fake-stamp.png', 'stamp_size' => 200]);
        $invoice = $this->makePartiallyPaidInvoice($company);

        $html = view('documents.print.pdf', $invoice->pdfData() + [
            'embed' => fn ($path) => $path ? 'data:image/png;base64,fake' : null,
        ])->render();

        $this->assertStringContainsString('width: 200px;', $html);
    }
}
