<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyLetter;
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
 * existing Quotations/Purchase Orders (CompanyLetterLink), and
 * "Download PDF" folds each one's own live-rendered PDF in behind the
 * letter itself via MpdfRenderer::mergePdfs(), the same mechanism
 * PurchaseOrderController::downloadPdf() already uses for its own PDF
 * attachments. Also covers the optional custom letterhead
 * ("letter pad should be optional to attach") and that Letters &
 * Agreements is no longer gated behind the Machinery & Equipment module
 * ("other companies can also upload for there documents").
 */
class CompanyLetterReferenceDocumentsTest extends TestCase
{
    use RefreshDatabase;

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
}
