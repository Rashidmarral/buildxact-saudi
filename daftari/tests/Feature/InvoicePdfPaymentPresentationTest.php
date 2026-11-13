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

    /**
     * "Subtotal / Total VAT / Total still coming in english only" — the
     * $lbl() helper used for every structural label in this template
     * (Subtotal, Discount, Total VAT/Tax, Total, table headers, ...)
     * previously always returned plain English outside arabic_only mode,
     * even on a "bilingual" layout. Only the dynamic $primary/$secondary
     * content (party name, item description, the Paid/Balance due extra
     * rows) was actually bilingual.
     */
    public function test_the_core_totals_labels_render_bilingually_on_the_bilingual_classic_layout(): void
    {
        [$company] = $this->makeOwner();
        $company->invoiceTemplates()->updateOrCreate(
            ['document_type' => 'invoice'],
            ['name' => 'Bilingual', 'layout' => 'bilingual_classic', 'language_mode' => 'bilingual', 'is_default' => true]
        );
        $invoice = $this->makePartiallyPaidInvoice($company);

        $html = $this->renderPdfHtml($invoice);

        $this->assertStringContainsString('Subtotal / المجموع الفرعي', $html);
        $this->assertStringContainsString('Total VAT / إجمالي ضريبة القيمة المضافة', $html);
        $this->assertStringContainsString('Total / الإجمالي', $html);
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

    /**
     * "don't show payment details in the invoice only status shows" — the
     * itemized payment history (date/method/reference/amount per
     * payment) used to print on 3 of 4 layouts whenever any payment was
     * recorded; the client/company only needs the collection status
     * (Paid/Partially Paid/Pending/Overdue — see the badge tests below),
     * not a breakdown of how it got there. That breakdown stays
     * in-app-only, on the invoice's own dedicated Payments panel
     * (InvoiceOnScreenPaymentsTableTest), which was never part of this
     * printed document template to begin with.
     */
    public function test_the_payments_received_table_no_longer_prints_on_any_layout(): void
    {
        [$company] = $this->makeOwner();
        $invoice = $this->makePartiallyPaidInvoice($company);

        foreach (['bilingual_classic', 'custom_letterhead', 'minimal', 'bordered', 'boxed'] as $layout) {
            $company->invoiceTemplates()->updateOrCreate(
                ['document_type' => 'invoice'], ['name' => 'T', 'layout' => $layout, 'is_default' => true]
            );

            $html = $this->renderPdfHtml($invoice->fresh());

            $this->assertStringNotContainsString('Payments received', $html, "Still printing payment details on layout: {$layout}");
            $this->assertStringNotContainsString('REF-001', $html, "Still printing a payment reference on layout: {$layout}");
        }
    }

    /**
     * Previously only sage_classic/retail_classic printed how the money
     * actually arrived — the other 5 layouts showed the Paid/Partially
     * Paid badge but nothing about the payment mode itself, even though
     * a user can now pick it right on the create form. Every invoice
     * layout now shows it the same way the payment-status badge already
     * did, each using its own existing field styling.
     */
    public function test_every_layout_shows_the_payment_method_once_a_payment_is_recorded(): void
    {
        [$company] = $this->makeOwner();
        $invoice = $this->makePartiallyPaidInvoice($company);

        foreach (['bilingual_classic', 'custom_letterhead', 'minimal', 'bordered', 'boxed', 'sage_classic', 'retail_classic'] as $layout) {
            $company->invoiceTemplates()->updateOrCreate(
                ['document_type' => 'invoice'], ['name' => 'T', 'layout' => $layout, 'is_default' => true]
            );

            $html = $this->renderPdfHtml($invoice->fresh());

            $this->assertStringContainsString('Bank transfer', $html, "Layout \"{$layout}\" doesn't show the payment method.");
        }
    }

    public function test_no_payment_method_row_appears_before_any_payment_is_recorded(): void
    {
        [$company] = $this->makeOwner();
        $invoice = $this->makePartiallyPaidInvoice($company);
        $invoice->update(['status' => 'sent', 'amount_paid' => 0]);
        $invoice->invoicePayments()->delete();

        foreach (['bilingual_classic', 'custom_letterhead', 'minimal', 'sage_classic', 'retail_classic'] as $layout) {
            $company->invoiceTemplates()->updateOrCreate(
                ['document_type' => 'invoice'], ['name' => 'T', 'layout' => $layout, 'is_default' => true]
            );

            $html = $this->renderPdfHtml($invoice->fresh());

            $this->assertStringNotContainsString('Payment method', $html, "Layout \"{$layout}\" shows a payment method label with nothing to show.");
        }
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
