<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Bill;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\PurchaseOrder;
use App\Models\Quotation;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Security audit finding M-11: storageUsedBytes() was tracked and shown
 * on billing/usage pages, but nothing actually stopped a company from
 * uploading past its plan's storage cap — every attachment/document
 * upload endpoint accepted the file regardless.
 */
class StorageQuotaEnforcedOnUploadTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(?int $maxStorageMb): Company
    {
        $plan = Plan::create([
            'name' => 'Test Plan', 'slug' => 'test-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000,
            'is_active' => true, 'max_storage_mb' => $maxStorageMb,
            'has_quotations' => true, 'has_purchase_orders' => true,
        ]);
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Subscription::create([
            'company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
        ]);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['company_id' => $company->id, 'role' => 'owner', 'status' => 'active']);
    }

    /** Simulates a company already sitting at its full storage quota. */
    private function fillQuota(Company $company, int $mb): void
    {
        Attachment::create([
            'company_id' => $company->id, 'attachable_type' => Company::class, 'attachable_id' => $company->id,
            'original_name' => 'existing.pdf', 'path' => 'existing.pdf', 'size' => $mb * 1048576, 'mime_type' => 'application/pdf',
        ]);
    }

    public function test_an_invoice_attachment_is_rejected_once_the_company_is_at_its_storage_cap(): void
    {
        $company = $this->makeCompany(1);
        $this->fillQuota($company, 1);
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client A']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-0001', 'type' => 'standard', 'status' => 'draft',
            'issue_date' => now(), 'subtotal' => 100, 'vat_total' => 15, 'total' => 115, 'currency' => 'SAR',
        ]);

        $response = $this->actingAs($owner)->post(route('app.invoices.attachments.store', $invoice), [
            'file' => UploadedFile::fake()->create('doc.pdf', 100),
        ]);

        $response->assertSessionHasErrors('file');
        $this->assertSame(1, Attachment::where('company_id', $company->id)->count());
    }

    public function test_a_bill_attachment_still_works_under_the_storage_cap(): void
    {
        $company = $this->makeCompany(1000);
        $owner = $this->makeOwner($company);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Supplier A']);
        $bill = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-0001',
            'bill_date' => now(), 'status' => 'draft', 'currency' => 'SAR',
        ]);

        $response = $this->actingAs($owner)->post(route('app.bills.attachments.store', $bill), [
            'file' => UploadedFile::fake()->create('doc.pdf', 100),
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertSame(1, Attachment::where('company_id', $company->id)->count());
    }

    public function test_a_purchase_order_attachment_is_rejected_once_the_company_is_at_its_storage_cap(): void
    {
        $company = $this->makeCompany(1);
        $this->fillQuota($company, 1);
        $owner = $this->makeOwner($company);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Supplier A']);
        $purchaseOrder = PurchaseOrder::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'po_number' => 'PO-0001',
            'order_date' => now(), 'status' => 'draft', 'currency' => 'SAR',
        ]);

        $response = $this->actingAs($owner)->post(route('app.purchase-orders.attachments.store', $purchaseOrder), [
            'file' => UploadedFile::fake()->create('doc.pdf', 100),
        ]);

        $response->assertSessionHasErrors('file');
    }

    public function test_a_quotation_attachment_is_rejected_once_the_company_is_at_its_storage_cap(): void
    {
        $company = $this->makeCompany(1);
        $this->fillQuota($company, 1);
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client A']);
        $quotation = Quotation::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'quotation_number' => 'QUO-0001', 'status' => 'draft',
            'issue_date' => now(), 'subtotal' => 100, 'vat_total' => 15, 'total' => 115, 'currency' => 'SAR',
        ]);

        $response = $this->actingAs($owner)->post(route('app.quotations.attachments.store', $quotation), [
            'file' => UploadedFile::fake()->create('doc.pdf', 100),
        ]);

        $response->assertSessionHasErrors('file');
    }

    public function test_a_company_document_upload_is_rejected_once_the_company_is_at_its_storage_cap(): void
    {
        $company = $this->makeCompany(1);
        $this->fillQuota($company, 1);
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->post(route('app.settings.documents.store'), [
            'document_type' => 'cr', 'file' => UploadedFile::fake()->create('cr.pdf', 100),
        ]);

        $response->assertSessionHasErrors('file');
        $this->assertSame(1, Attachment::where('company_id', $company->id)->count());
    }

    public function test_a_company_with_no_storage_limit_is_never_blocked(): void
    {
        $company = $this->makeCompany(null);
        $this->fillQuota($company, 100000);
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->post(route('app.settings.documents.store'), [
            'document_type' => 'cr', 'file' => UploadedFile::fake()->create('cr.pdf', 100),
        ]);

        $response->assertSessionDoesntHaveErrors();
    }
}
