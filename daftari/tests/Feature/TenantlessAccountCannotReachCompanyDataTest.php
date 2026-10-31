<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Third-party audit finding SEC-03 (High): BelongsToCompany's global scope
 * only constrained a query when the authenticated user HAD a company_id —
 * a tenantless platform account (super_admin, admin_staff, partner all
 * share the same `users` table/guard and can have company_id = null) fell
 * through that check entirely, leaving every tenant-scoped model
 * completely UNSCOPED for them, with no intent behind it at all — not even
 * for the two legitimate admin roles.
 *
 * Fixed at the trait: `partner` (and any future tenantless role) now sees
 * zero rows from a tenant-scoped model by default — the secure default.
 * `super_admin`/`admin_staff` are trusted to see every company's data
 * through ordinary queries, not just explicit withoutGlobalScopes() calls:
 * the entire /admin/* route group is already gated to exactly these two
 * roles before any controller runs, and a first attempt at denying ALL
 * tenantless accounts by default broke dozens of existing, intentional
 * admin features (listing pages, relation loads, route-bound lookups)
 * across the whole admin panel — proving those two roles were always
 * meant to see every company's data this way.
 */
class TenantlessAccountCannotReachCompanyDataTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompanyWithAttachment(): array
    {
        $company = Company::create(['name' => 'Victim Co.', 'slug' => 'victim-co-'.uniqid()]);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Supplier']);
        $file = UploadedFile::fake()->create('bank-statement.pdf', 10, 'application/pdf');
        $path = $file->store('bills-attachments', 'public');
        $attachment = Attachment::create([
            'company_id' => $company->id, 'attachable_type' => Supplier::class, 'attachable_id' => $supplier->id,
            'original_name' => 'bank-statement.pdf', 'path' => $path, 'size' => $file->getSize(), 'mime_type' => 'application/pdf',
        ]);

        return [$company, $attachment];
    }

    /**
     * FileServeController's own explicit `where('company_id', ...)` check
     * (defense in depth, independent of the global scope) still denies
     * even super_admin/admin_staff here — the admin panel has no
     * legitimate reason to serve a tenant's private attachment through
     * this specific unauthenticated-adjacent route, and nothing in this
     * fix changes that.
     */
    public function test_a_super_admin_with_no_company_cannot_download_a_companys_attachment(): void
    {
        [, $attachment] = $this->makeCompanyWithAttachment();
        $superAdmin = User::factory()->create(['company_id' => null, 'role' => 'super_admin', 'status' => 'active']);

        $this->actingAs($superAdmin)->get('/files/'.$attachment->path)->assertNotFound();
    }

    public function test_an_admin_staff_account_with_no_company_cannot_download_a_companys_attachment(): void
    {
        [, $attachment] = $this->makeCompanyWithAttachment();
        $adminStaff = User::factory()->create(['company_id' => null, 'role' => 'admin_staff', 'status' => 'active']);

        $this->actingAs($adminStaff)->get('/files/'.$attachment->path)->assertNotFound();
    }

    public function test_a_partner_account_with_no_company_cannot_download_a_companys_attachment(): void
    {
        [, $attachment] = $this->makeCompanyWithAttachment();
        $partner = User::factory()->create(['company_id' => null, 'role' => 'partner', 'status' => 'active']);

        $this->actingAs($partner)->get('/files/'.$attachment->path)->assertNotFound();
    }

    public function test_a_suspended_company_user_cannot_download_their_own_companys_attachment(): void
    {
        [$company, $attachment] = $this->makeCompanyWithAttachment();
        $suspendedUser = User::factory()->create(['company_id' => $company->id, 'role' => 'staff', 'status' => 'suspended']);

        $this->actingAs($suspendedUser)->get('/files/'.$attachment->path)->assertNotFound();
    }

    public function test_an_active_company_user_can_still_download_their_own_companys_attachment(): void
    {
        [$company, $attachment] = $this->makeCompanyWithAttachment();
        $owner = User::factory()->create(['company_id' => $company->id, 'role' => 'owner', 'status' => 'active']);

        $this->actingAs($owner)->get('/files/'.$attachment->path)->assertOk();
    }

    public function test_a_partner_account_sees_zero_rows_from_a_tenant_scoped_model_by_default(): void
    {
        $company = Company::create(['name' => 'Some Co.', 'slug' => 'some-co-'.uniqid()]);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client']);
        Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-TENANTLESS-TEST', 'type' => 'standard',
            'status' => 'draft', 'issue_date' => now()->toDateString(), 'currency' => $company->currency,
            'subtotal' => 100, 'vat_total' => 0, 'total' => 100,
        ]);
        $partner = User::factory()->create(['company_id' => null, 'role' => 'partner', 'status' => 'active']);

        $this->actingAs($partner);
        $this->assertSame(0, Invoice::count(), 'A partner account must see zero rows from a tenant-scoped model, not every company\'s rows.');
    }

    public function test_a_super_admin_sees_every_companys_rows_from_a_tenant_scoped_model_by_default(): void
    {
        $companyA = Company::create(['name' => 'A Co.', 'slug' => 'a-co-'.uniqid()]);
        $companyB = Company::create(['name' => 'B Co.', 'slug' => 'b-co-'.uniqid()]);
        $clientA = Client::create(['company_id' => $companyA->id, 'name' => 'Client A']);
        $clientB = Client::create(['company_id' => $companyB->id, 'name' => 'Client B']);
        Invoice::create([
            'company_id' => $companyA->id, 'client_id' => $clientA->id, 'invoice_number' => 'INV-A', 'type' => 'standard', 'status' => 'draft',
            'issue_date' => now()->toDateString(), 'currency' => $companyA->currency, 'subtotal' => 100, 'vat_total' => 0, 'total' => 100,
        ]);
        Invoice::create([
            'company_id' => $companyB->id, 'client_id' => $clientB->id, 'invoice_number' => 'INV-B', 'type' => 'standard', 'status' => 'draft',
            'issue_date' => now()->toDateString(), 'currency' => $companyB->currency, 'subtotal' => 100, 'vat_total' => 0, 'total' => 100,
        ]);
        $superAdmin = User::factory()->create(['company_id' => null, 'role' => 'super_admin', 'status' => 'active']);
        $adminStaff = User::factory()->create(['company_id' => null, 'role' => 'admin_staff', 'status' => 'active']);

        $this->actingAs($superAdmin);
        $this->assertSame(2, Invoice::count());

        $this->actingAs($adminStaff);
        $this->assertSame(2, Invoice::count());
    }

    public function test_an_unauthenticated_guest_can_still_reach_a_public_favicon(): void
    {
        $file = UploadedFile::fake()->image('favicon.png');
        $path = $file->store('favicons', 'public');

        $this->get('/files/'.$path)->assertOk();
    }

    public function test_a_super_admin_can_implicitly_bind_a_route_model_across_companies(): void
    {
        $company = Company::create(['name' => 'Bound Co.', 'slug' => 'bound-co-'.uniqid()]);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-BOUND', 'type' => 'standard',
            'status' => 'draft', 'issue_date' => now()->toDateString(), 'currency' => $company->currency,
            'subtotal' => 100, 'vat_total' => 0, 'total' => 100,
        ]);
        $superAdmin = User::factory()->create(['company_id' => null, 'role' => 'super_admin', 'status' => 'active']);

        $this->actingAs($superAdmin);
        $bound = (new Invoice)->resolveRouteBinding($invoice->id);

        $this->assertNotNull($bound, 'A super_admin must be able to implicitly bind a route model belonging to any company.');
        $this->assertSame($invoice->id, $bound->id);
    }

    public function test_a_partner_account_cannot_implicitly_bind_a_route_model_across_companies(): void
    {
        $company = Company::create(['name' => 'Bound Co.', 'slug' => 'bound-co-'.uniqid()]);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-BOUND', 'type' => 'standard',
            'status' => 'draft', 'issue_date' => now()->toDateString(), 'currency' => $company->currency,
            'subtotal' => 100, 'vat_total' => 0, 'total' => 100,
        ]);
        $partner = User::factory()->create(['company_id' => null, 'role' => 'partner', 'status' => 'active']);

        $this->actingAs($partner);
        $bound = (new Invoice)->resolveRouteBinding($invoice->id);

        $this->assertNull($bound, 'A partner account must not be able to implicitly bind a route model belonging to any company.');
    }
}
