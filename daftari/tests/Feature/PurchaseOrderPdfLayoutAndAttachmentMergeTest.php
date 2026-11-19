<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Company;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Feature request: a user shared a real Purchase Order they'd received
 * from another company as a reference and asked for their own downloaded
 * PO to match its layout (logo/bilingual title/info box header, a
 * greeting+reference block, an items table with totals appended below it,
 * numbered terms, three-column signature blocks, a distribution footer)
 * — and for the download to include, as trailing pages in the same file,
 * "the client quote" and "required approval documents", which map to the
 * PO's own PDF attachments (Daftari has no structured "received
 * quotation" object to render instead).
 *
 * Purchase Orders got their own dedicated PDF template
 * (documents.print.purchase-order-pdf), separate from the shared
 * documents.print.pdf layout system every other document type uses, plus
 * a new MpdfRenderer::mergePdfs() that folds in PDF attachments via
 * mPDF's bundled FPDI support.
 */
class PurchaseOrderPdfLayoutAndAttachmentMergeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A hand-built, minimal-but-valid single-page PDF (no content stream,
     * just an empty page) — small enough to embed as a literal and
     * reliably parseable by mPDF's bundled FPDI PDF parser, verified
     * against pdfinfo during development. Used to simulate a real PDF
     * attachment (a sub-vendor's quotation, an approval document) without
     * needing a binary fixture file in the repo.
     */
    private const MINIMAL_PDF = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]>>endobj\nxref\n0 4\n0000000000 65535 f \n0000000009 00000 n \n0000000052 00000 n \n0000000101 00000 n \ntrailer<</Size 4/Root 1 0 R>>\nstartxref\n164\n%%EOF";

    private function makeOrder(): array
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Saed Est.', 'name_ar' => 'مؤسسة سائد']);
        $project = Project::create(['company_id' => $company->id, 'code' => 'PRJ-1', 'name' => 'Al-Jumum Square Road Works', 'status' => 'active', 'created_by' => $owner->id]);

        $order = PurchaseOrder::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'project_id' => $project->id,
            'po_number' => 'PO-0001', 'status' => 'approved', 'order_date' => now()->toDateString(),
            'notes' => "20% advance on signing.\n30% on completion of Subbase.",
            'subtotal' => 570000, 'vat_total' => 85500, 'total' => 655500,
        ]);
        PurchaseOrderItem::create([
            'purchase_order_id' => $order->id, 'description' => 'Subbase, Base Course & Asphalt',
            'quantity' => 1, 'unit_price' => 570000, 'vat_rate' => 15, 'vat_amount' => 85500, 'line_total' => 655500,
        ]);

        return [$owner, $order];
    }

    private function pdfPageCount(string $bytes): int
    {
        preg_match_all('/\/Type\s*\/Page(?!s)\b/', $bytes, $matches);

        return count($matches[0]);
    }

    public function test_downloading_a_purchase_orders_pdf_succeeds_with_the_dedicated_layout(): void
    {
        [$owner, $order] = $this->makeOrder();

        $response = $this->actingAs($owner)->get(route('app.purchase-orders.pdf', $order));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame(1, $this->pdfPageCount($response->getContent()));
    }

    public function test_a_pdf_attachment_is_merged_into_the_downloaded_purchase_order(): void
    {
        [$owner, $order] = $this->makeOrder();

        $this->actingAs($owner)->post(route('app.purchase-orders.attachments.store', $order), [
            'file' => UploadedFile::fake()->createWithContent('saed-quotation.pdf', self::MINIMAL_PDF),
        ])->assertSessionDoesntHaveErrors();

        $response = $this->actingAs($owner)->get(route('app.purchase-orders.pdf', $order));

        $response->assertOk();
        $this->assertSame(2, $this->pdfPageCount($response->getContent()));
    }

    public function test_a_non_pdf_attachment_is_not_merged_but_stays_listed_and_downloadable(): void
    {
        [$owner, $order] = $this->makeOrder();

        $this->actingAs($owner)->post(route('app.purchase-orders.attachments.store', $order), [
            'file' => UploadedFile::fake()->image('site-photo.jpg'),
        ])->assertSessionDoesntHaveErrors();

        $response = $this->actingAs($owner)->get(route('app.purchase-orders.pdf', $order));

        $response->assertOk();
        $this->assertSame(1, $this->pdfPageCount($response->getContent()));
        $this->assertDatabaseHas('attachments', [
            'attachable_type' => PurchaseOrder::class, 'attachable_id' => $order->id, 'original_name' => 'site-photo.jpg',
        ]);
    }

    public function test_multiple_pdf_attachments_are_all_merged_in_upload_order(): void
    {
        [$owner, $order] = $this->makeOrder();

        $this->actingAs($owner)->post(route('app.purchase-orders.attachments.store', $order), [
            'file' => UploadedFile::fake()->createWithContent('quotation.pdf', self::MINIMAL_PDF),
        ]);
        $this->actingAs($owner)->post(route('app.purchase-orders.attachments.store', $order), [
            'file' => UploadedFile::fake()->createWithContent('approval.pdf', self::MINIMAL_PDF),
        ]);

        $response = $this->actingAs($owner)->get(route('app.purchase-orders.pdf', $order));

        $response->assertOk();
        $this->assertSame(3, $this->pdfPageCount($response->getContent()));
    }

    public function test_a_corrupt_pdf_attachment_is_skipped_without_breaking_the_download(): void
    {
        [$owner, $order] = $this->makeOrder();

        $path = 'po-attachments/corrupt-'.uniqid().'.pdf';
        Storage::disk('public')->put($path, 'this is not a real pdf');
        Attachment::create([
            'company_id' => $order->company_id, 'attachable_type' => PurchaseOrder::class, 'attachable_id' => $order->id,
            'uploaded_by' => $owner->id, 'original_name' => 'corrupt.pdf', 'path' => $path,
            'size' => 22, 'mime_type' => 'application/pdf',
        ]);

        $response = $this->actingAs($owner)->get(route('app.purchase-orders.pdf', $order));

        $response->assertOk();
        $this->assertSame(1, $this->pdfPageCount($response->getContent()));
    }
}
