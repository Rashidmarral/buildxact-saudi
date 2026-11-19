<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Quotation;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Audit finding: invoice/bill/quotation/purchase-order attachment uploads
 * validated only file|max:10240 — no mimes: rule — then served the
 * original file back from the public disk as-is. A crafted .html/.svg
 * upload could execute in a browser when opened from the attachment link.
 */
class AttachmentUploadSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): User
    {
        $company = Company::create(['name' => 'Attach Co.', 'slug' => 'attach-'.uniqid()]);

        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    public function test_invoice_attachment_rejects_disallowed_file_type(): void
    {
        $owner = $this->makeOwner();
        $client = Client::create(['company_id' => $owner->company_id, 'name' => 'Client']);
        $invoice = Invoice::create([
            'company_id' => $owner->company_id, 'client_id' => $client->id, 'invoice_number' => 'INV-1',
            'issue_date' => now(), 'due_date' => now()->addDays(30), 'status' => 'draft',
            'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
        ]);

        $response = $this->actingAs($owner)->post(route('app.invoices.attachments.store', $invoice), [
            'file' => UploadedFile::fake()->create('payload.html', 10, 'text/html'),
        ]);

        $response->assertSessionHasErrors('file');
        $this->assertSame(0, $invoice->attachments()->count());
    }

    public function test_invoice_attachment_accepts_an_allowed_file_type(): void
    {
        $owner = $this->makeOwner();
        $client = Client::create(['company_id' => $owner->company_id, 'name' => 'Client']);
        $invoice = Invoice::create([
            'company_id' => $owner->company_id, 'client_id' => $client->id, 'invoice_number' => 'INV-2',
            'issue_date' => now(), 'due_date' => now()->addDays(30), 'status' => 'draft',
            'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
        ]);

        $response = $this->actingAs($owner)->post(route('app.invoices.attachments.store', $invoice), [
            'file' => UploadedFile::fake()->create('receipt.pdf', 10, 'application/pdf'),
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertSame(1, $invoice->attachments()->count());
    }

    public function test_bill_attachment_rejects_disallowed_file_type(): void
    {
        $owner = $this->makeOwner();
        $supplier = Supplier::create(['company_id' => $owner->company_id, 'name' => 'Supplier']);
        $bill = Bill::create([
            'company_id' => $owner->company_id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-1',
            'status' => 'draft', 'bill_date' => now()->toDateString(),
            'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
        ]);

        $response = $this->actingAs($owner)->post(route('app.bills.attachments.store', $bill), [
            'file' => UploadedFile::fake()->create('payload.svg', 10, 'image/svg+xml'),
        ]);

        $response->assertSessionHasErrors('file');
        $this->assertSame(0, $bill->attachments()->count());
    }

    public function test_quotation_attachment_rejects_disallowed_file_type(): void
    {
        $owner = $this->makeOwner();
        $client = Client::create(['company_id' => $owner->company_id, 'name' => 'Client']);
        $quotation = Quotation::create([
            'company_id' => $owner->company_id, 'client_id' => $client->id, 'quotation_number' => 'QUO-1',
            'issue_date' => now(), 'status' => 'draft',
            'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
        ]);

        $response = $this->actingAs($owner)->post(route('app.quotations.attachments.store', $quotation), [
            'file' => UploadedFile::fake()->create('payload.exe', 10, 'application/x-msdownload'),
        ]);

        $response->assertSessionHasErrors('file');
        $this->assertSame(0, $quotation->attachments()->count());
    }

    public function test_purchase_order_attachment_rejects_disallowed_file_type(): void
    {
        $owner = $this->makeOwner();
        $supplier = Supplier::create(['company_id' => $owner->company_id, 'name' => 'Supplier']);
        $purchaseOrder = PurchaseOrder::create([
            'company_id' => $owner->company_id, 'supplier_id' => $supplier->id, 'po_number' => 'PO-1',
            'order_date' => now()->toDateString(), 'status' => 'draft',
            'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
        ]);

        $response = $this->actingAs($owner)->post(route('app.purchase-orders.attachments.store', $purchaseOrder), [
            'file' => UploadedFile::fake()->create('payload.php', 10, 'application/x-httpd-php'),
        ]);

        $response->assertSessionHasErrors('file');
        $this->assertSame(0, $purchaseOrder->attachments()->count());
    }
}
