<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyLetter;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceTemplate;
use App\Models\PurchaseOrder;
use App\Models\Quotation;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * "i need to send my client a document with attach agreement p/o order
 * and quotations in a single pdf document" — a letter can reference
 * existing Quotations/Purchase Orders/Invoices (CompanyLetterLink), and
 * "Download PDF" folds each one's own live-rendered PDF in behind the
 * letter itself via MpdfRenderer::mergePdfs(), the same mechanism
 * PurchaseOrderController::downloadPdf() already uses for its own PDF
 * attachments. Also covers the optional custom letterhead
 * ("letter pad should be optional to attach"), that Letters &
 * Agreements is no longer gated behind the Machinery & Equipment module
 * ("other companies can also upload for there documents"), and — "i
 * will attach my client p/o not my issued p/o" — that a client's own
 * PO (never a Daftari PurchaseOrder record, which is always this
 * company's own issued order to its supplier) goes through the
 * ordinary file attachments() upload instead, and that upload is ALSO
 * merged into "Download PDF".
 */
class CompanyLetterReferenceDocumentsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A hand-built, minimal-but-valid single-page PDF — small enough to
     * embed as a literal and reliably parseable by mPDF's bundled FPDI
     * parser (same fixture used in PurchaseOrderPdfLayoutAndAttachmentMergeTest),
     * used to simulate a real PDF attachment such as a client's own PO.
     */
    private const MINIMAL_PDF = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]>>endobj\nxref\n0 4\n0000000000 65535 f \n0000000009 00000 n \n0000000052 00000 n \n0000000101 00000 n \ntrailer<</Size 4/Root 1 0 R>>\nstartxref\n164\n%%EOF";

    private function pdfPageCount(string $bytes): int
    {
        preg_match_all('/\/Type\s*\/Page(?!s)\b/', $bytes, $matches);

        return count($matches[0]);
    }

    private function makeOwner(): array
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        return [$company, $owner];
    }

    private function basePayload(array $overrides = []): array
    {
        return array_merge([
            'document_type' => 'custom',
            'title' => 'Completion Agreement',
            'letter_date' => now()->toDateString(),
            'party_a_role' => 'First Party',
            'party_b_role' => 'Second Party',
            'party_b_name' => 'Sada Al Jeul Construction Est.',
            'language_mode' => 'bilingual',
            'content' => [['text_en' => 'Terms.', 'text_ar' => 'الشروط.', 'is_heading' => false]],
        ], $overrides);
    }

    public function test_a_letter_can_be_created_with_linked_quotations_and_purchase_orders(): void
    {
        [$company, $owner] = $this->makeOwner();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Al Jeul Construction']);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Steel Supplies Co.']);
        $quotation = Quotation::create(['company_id' => $company->id, 'client_id' => $client->id, 'quotation_number' => 'QTN-0001', 'type' => 'quotation', 'status' => 'issued', 'issue_date' => now(), 'currency' => 'SAR', 'subtotal' => 1000, 'vat_total' => 150, 'total' => 1150]);
        $order = PurchaseOrder::create(['company_id' => $company->id, 'supplier_id' => $supplier->id, 'po_number' => 'PO-0001', 'order_date' => now()->toDateString(), 'status' => 'draft', 'subtotal' => 500, 'vat_total' => 75, 'total' => 575]);

        $response = $this->actingAs($owner)->post(route('app.machinery.letters.store'), $this->basePayload([
            'linked_quotations' => [$quotation->id],
            'linked_purchase_orders' => [$order->id],
        ]));

        $response->assertSessionDoesntHaveErrors();
        $letter = CompanyLetter::latest('id')->first();
        $this->assertCount(2, $letter->links);
        $this->assertTrue($letter->links->pluck('linkable_id')->contains($quotation->id));
        $this->assertTrue($letter->links->pluck('linkable_id')->contains($order->id));
    }

    public function test_editing_a_letter_replaces_its_linked_documents(): void
    {
        [$company, $owner] = $this->makeOwner();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Al Jeul Construction']);
        $quotationA = Quotation::create(['company_id' => $company->id, 'client_id' => $client->id, 'quotation_number' => 'QTN-A', 'type' => 'quotation', 'status' => 'issued', 'issue_date' => now(), 'currency' => 'SAR', 'subtotal' => 100, 'vat_total' => 15, 'total' => 115]);
        $quotationB = Quotation::create(['company_id' => $company->id, 'client_id' => $client->id, 'quotation_number' => 'QTN-B', 'type' => 'quotation', 'status' => 'issued', 'issue_date' => now(), 'currency' => 'SAR', 'subtotal' => 200, 'vat_total' => 30, 'total' => 230]);

        $this->actingAs($owner)->post(route('app.machinery.letters.store'), $this->basePayload(['linked_quotations' => [$quotationA->id]]));
        $letter = CompanyLetter::latest('id')->first();

        $this->actingAs($owner)->put(route('app.machinery.letters.update', $letter), $this->basePayload(['linked_quotations' => [$quotationB->id]]))
            ->assertSessionDoesntHaveErrors();

        $letter->refresh();
        $this->assertCount(1, $letter->links);
        $this->assertSame($quotationB->id, $letter->links->first()->linkable_id);
    }

    public function test_a_company_cannot_link_another_companys_quotation(): void
    {
        [$companyA, $ownerA] = $this->makeOwner();
        [$companyB] = $this->makeOwner();
        $clientB = Client::create(['company_id' => $companyB->id, 'name' => 'Other Co Client']);
        $foreignQuotation = Quotation::create(['company_id' => $companyB->id, 'client_id' => $clientB->id, 'quotation_number' => 'QTN-FOREIGN', 'type' => 'quotation', 'status' => 'issued', 'issue_date' => now(), 'currency' => 'SAR', 'subtotal' => 100, 'vat_total' => 15, 'total' => 115]);

        $response = $this->actingAs($ownerA)->post(route('app.machinery.letters.store'), $this->basePayload([
            'linked_quotations' => [$foreignQuotation->id],
        ]));

        $response->assertSessionHasErrors('linked_quotations.0');
        $this->assertSame(0, CompanyLetter::count());
    }

    public function test_the_combined_pdf_download_merges_in_every_linked_document(): void
    {
        [$company, $owner] = $this->makeOwner();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Al Jeul Construction']);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Steel Supplies Co.']);
        $quotation = Quotation::create(['company_id' => $company->id, 'client_id' => $client->id, 'quotation_number' => 'QTN-0001', 'type' => 'quotation', 'status' => 'issued', 'issue_date' => now(), 'currency' => 'SAR', 'subtotal' => 1000, 'vat_total' => 150, 'total' => 1150]);
        $order = PurchaseOrder::create(['company_id' => $company->id, 'supplier_id' => $supplier->id, 'po_number' => 'PO-0001', 'order_date' => now()->toDateString(), 'status' => 'draft', 'subtotal' => 500, 'vat_total' => 75, 'total' => 575]);

        $this->actingAs($owner)->post(route('app.machinery.letters.store'), $this->basePayload([
            'linked_quotations' => [$quotation->id],
            'linked_purchase_orders' => [$order->id],
        ]));
        $letter = CompanyLetter::latest('id')->first();

        $withLinks = $this->actingAs($owner)->get(route('app.machinery.letters.pdf', $letter));
        $withLinks->assertOk();
        $this->assertStringStartsWith('application/pdf', $withLinks->headers->get('Content-Type'));

        // A standalone letter with no links, for a same-content size comparison.
        $this->actingAs($owner)->post(route('app.machinery.letters.store'), $this->basePayload(['title' => 'Solo Letter']));
        $solo = CompanyLetter::where('title', 'Solo Letter')->first();
        $withoutLinks = $this->actingAs($owner)->get(route('app.machinery.letters.pdf', $solo));
        $withoutLinks->assertOk();

        // Merging in two more documents' worth of pages must make the file
        // meaningfully bigger than the same letter alone.
        $this->assertGreaterThan(strlen($withoutLinks->getContent()), strlen($withLinks->getContent()));
    }

    public function test_deleting_a_linked_quotation_does_not_break_the_pdf_download(): void
    {
        [$company, $owner] = $this->makeOwner();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Al Jeul Construction']);
        $quotation = Quotation::create(['company_id' => $company->id, 'client_id' => $client->id, 'quotation_number' => 'QTN-0001', 'type' => 'quotation', 'status' => 'draft', 'issue_date' => now(), 'currency' => 'SAR', 'subtotal' => 100, 'vat_total' => 15, 'total' => 115]);

        $this->actingAs($owner)->post(route('app.machinery.letters.store'), $this->basePayload(['linked_quotations' => [$quotation->id]]));
        $letter = CompanyLetter::latest('id')->first();

        $quotation->delete();

        $response = $this->actingAs($owner)->get(route('app.machinery.letters.pdf', $letter));
        $response->assertOk();
    }

    public function test_a_company_without_the_machinery_module_can_still_generate_and_download_a_letter(): void
    {
        [$company, $owner] = $this->makeOwner();

        $createResponse = $this->actingAs($owner)->get(route('app.machinery.letters.create'));
        $createResponse->assertOk();

        $storeResponse = $this->actingAs($owner)->post(route('app.machinery.letters.store'), $this->basePayload());
        $storeResponse->assertSessionDoesntHaveErrors();

        $letter = CompanyLetter::latest('id')->first();
        $this->actingAs($owner)->get(route('app.machinery.letters.pdf', $letter))->assertOk();
    }

    public function test_a_custom_letterhead_is_used_in_place_of_the_auto_generated_header_when_uploaded(): void
    {
        Storage::fake('public');
        [$company, $owner] = $this->makeOwner();
        $letterhead = UploadedFile::fake()->image('letterhead.png', 1000, 300);
        $letterheadPath = Storage::disk('public')->putFile('letterheads', $letterhead);

        InvoiceTemplate::create([
            'company_id' => $company->id, 'document_type' => 'letter', 'name' => 'Our Letterhead',
            'layout' => 'custom_letterhead', 'is_default' => true, 'letterhead_path' => $letterheadPath,
        ]);

        $this->actingAs($owner)->post(route('app.machinery.letters.store'), $this->basePayload());
        $letter = CompanyLetter::latest('id')->first();
        $letter->load('machinery', 'project', 'client', 'supplier');

        $html = view('documents.print.letter-pdf', [
            'title' => $letter->title,
            'subject' => $letter->reference_number,
            'company' => $company,
            'template' => $company->defaultTemplateFor('letter'),
            'letter' => $letter,
            'embed' => fn ($path) => $path ? 'data:image/png;base64,fake-letterhead-data' : null,
        ])->render();

        $this->assertStringContainsString('fake-letterhead-data', $html);
    }

    public function test_without_a_custom_letterhead_the_auto_generated_bilingual_header_is_used(): void
    {
        [$company, $owner] = $this->makeOwner();

        $this->actingAs($owner)->post(route('app.machinery.letters.store'), $this->basePayload());
        $letter = CompanyLetter::latest('id')->first();
        $letter->load('machinery', 'project', 'client', 'supplier');

        $html = view('documents.print.letter-pdf', [
            'title' => $letter->title,
            'subject' => $letter->reference_number,
            'company' => $company,
            'template' => $company->defaultTemplateFor('letter'),
            'letter' => $letter,
            'embed' => fn () => null,
        ])->render();

        // The auto-generated header renders the company's bilingual
        // "Kingdom of Saudi Arabia" line — never present when a real
        // letterhead image took over instead.
        $this->assertStringContainsString('Kingdom of Saudi Arabia', $html);
    }

    public function test_a_letter_can_link_an_invoice_and_it_is_merged_into_the_download(): void
    {
        [$company, $owner] = $this->makeOwner();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Al Jeul Construction']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-0001',
            'status' => 'sent', 'issue_date' => now(), 'due_date' => now()->addDays(30),
            'subtotal' => 1000, 'vat_total' => 150, 'total' => 1150, 'currency' => 'SAR',
        ]);
        InvoiceItem::create(['invoice_id' => $invoice->id, 'description' => 'Work', 'quantity' => 1, 'unit_price' => 1000, 'vat_rate' => 15, 'vat_amount' => 150, 'line_total' => 1150]);

        $this->actingAs($owner)->post(route('app.machinery.letters.store'), $this->basePayload([
            'linked_invoices' => [$invoice->id],
        ]))->assertSessionDoesntHaveErrors();

        $letter = CompanyLetter::latest('id')->first();
        $this->assertCount(1, $letter->links);
        $this->assertSame(Invoice::class, $letter->links->first()->linkable_type);

        $response = $this->actingAs($owner)->get(route('app.machinery.letters.pdf', $letter));
        $response->assertOk();
    }

    public function test_a_company_cannot_link_another_companys_invoice(): void
    {
        [$companyA, $ownerA] = $this->makeOwner();
        [$companyB] = $this->makeOwner();
        $clientB = Client::create(['company_id' => $companyB->id, 'name' => 'Other Co Client']);
        $foreignInvoice = Invoice::create([
            'company_id' => $companyB->id, 'client_id' => $clientB->id, 'invoice_number' => 'INV-FOREIGN',
            'status' => 'sent', 'issue_date' => now(), 'due_date' => now()->addDays(30),
            'subtotal' => 1000, 'vat_total' => 150, 'total' => 1150, 'currency' => 'SAR',
        ]);

        $response = $this->actingAs($ownerA)->post(route('app.machinery.letters.store'), $this->basePayload([
            'linked_invoices' => [$foreignInvoice->id],
        ]));

        $response->assertSessionHasErrors('linked_invoices.0');
        $this->assertSame(0, CompanyLetter::count());
    }

    /**
     * "i will attach my client p/o not my issued p/o" — a client's own
     * PO is an external document (never a Daftari PurchaseOrder record,
     * which is always this company's own issued order to a supplier),
     * so it's uploaded as an ordinary file attachment. That attachment
     * must itself be folded into "Download PDF" too, not left as a
     * separate download.
     */
    public function test_an_uploaded_pdf_attachment_such_as_a_clients_own_po_is_merged_into_the_download(): void
    {
        Storage::fake('public');
        [$company, $owner] = $this->makeOwner();

        $this->actingAs($owner)->post(route('app.machinery.letters.store'), $this->basePayload());
        $letter = CompanyLetter::latest('id')->first();

        $withoutAttachment = $this->actingAs($owner)->get(route('app.machinery.letters.pdf', $letter));
        $withoutAttachment->assertOk();
        $pagesBefore = $this->pdfPageCount($withoutAttachment->getContent());

        $this->actingAs($owner)->post(route('app.machinery.letters.attachments.store', $letter), [
            'file' => UploadedFile::fake()->createWithContent('client-po.pdf', self::MINIMAL_PDF),
        ])->assertSessionDoesntHaveErrors();

        $withAttachment = $this->actingAs($owner)->get(route('app.machinery.letters.pdf', $letter));
        $withAttachment->assertOk();

        $this->assertSame($pagesBefore + 1, $this->pdfPageCount($withAttachment->getContent()));
    }

    public function test_a_non_pdf_attachment_is_not_merged_but_still_downloads_fine(): void
    {
        Storage::fake('public');
        [$company, $owner] = $this->makeOwner();

        $this->actingAs($owner)->post(route('app.machinery.letters.store'), $this->basePayload());
        $letter = CompanyLetter::latest('id')->first();

        $this->actingAs($owner)->post(route('app.machinery.letters.attachments.store', $letter), [
            'file' => UploadedFile::fake()->image('signed-copy.jpg'),
        ])->assertSessionDoesntHaveErrors();

        $response = $this->actingAs($owner)->get(route('app.machinery.letters.pdf', $letter));
        $response->assertOk();
    }
}
