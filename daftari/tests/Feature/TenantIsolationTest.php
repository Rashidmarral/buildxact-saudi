<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Client;
use App\Models\Company;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Module 01 (Super Admin Foundation) fixes: a platform admin (company_id
 * always null) previously had nothing stopping them from browsing into the
 * company panel at /app/*, where BelongsToCompany's global scope silently
 * no-ops for a null company_id — showing every tenant's data unscoped
 * instead of a 403 or an empty list. EnsureUserBelongsToCompany closes that
 * at the route level; these tests lock in both directions plus the
 * underlying cross-tenant scoping guarantee it depends on.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_is_redirected_away_from_the_company_panel(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'company_id' => null]);

        $response = $this->actingAs($admin)->get('/app');

        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_admin_staff_is_redirected_away_from_the_company_panel(): void
    {
        $admin = User::factory()->create(['role' => 'admin_staff', 'company_id' => null]);

        $response = $this->actingAs($admin)->get('/app');

        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_company_owner_can_still_reach_the_company_panel(): void
    {
        $company = Company::create(['name' => 'Test Co', 'slug' => 'test-co-'.uniqid(), 'status' => 'active']);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $response = $this->actingAs($owner)->get('/app');

        $response->assertOk();
    }

    public function test_a_company_user_cannot_view_another_companys_client_by_id(): void
    {
        $companyA = Company::create(['name' => 'Company A', 'slug' => 'company-a-'.uniqid(), 'status' => 'active']);
        $companyB = Company::create(['name' => 'Company B', 'slug' => 'company-b-'.uniqid(), 'status' => 'active']);

        $ownerA = User::factory()->create(['role' => 'owner', 'company_id' => $companyA->id, 'status' => 'active']);
        $clientB = Client::create(['company_id' => $companyB->id, 'name' => 'Client of Company B']);

        $response = $this->actingAs($ownerA)->get("/app/clients/{$clientB->id}/edit");

        $response->assertNotFound();
    }

    public function test_a_company_user_cannot_view_another_companys_bill_by_id(): void
    {
        $companyA = Company::create(['name' => 'Company A', 'slug' => 'company-a-'.uniqid(), 'status' => 'active']);
        $companyB = Company::create(['name' => 'Company B', 'slug' => 'company-b-'.uniqid(), 'status' => 'active']);

        $ownerA = User::factory()->create(['role' => 'owner', 'company_id' => $companyA->id, 'status' => 'active']);
        $supplierB = Supplier::create(['company_id' => $companyB->id, 'name' => 'Supplier of Company B']);
        $billB = Bill::create([
            'company_id' => $companyB->id,
            'supplier_id' => $supplierB->id,
            'bill_number' => 'BILL-'.uniqid(),
            'status' => 'draft',
            'bill_date' => now()->toDateString(),
            'subtotal' => 100,
            'vat_total' => 15,
            'total' => 115,
        ]);

        $response = $this->actingAs($ownerA)->get(route('app.bills.show', $billB));

        $response->assertNotFound();
    }

    public function test_a_company_user_cannot_view_another_companys_expense_by_id(): void
    {
        $companyA = Company::create(['name' => 'Company A', 'slug' => 'company-a-'.uniqid(), 'status' => 'active']);
        $companyB = Company::create(['name' => 'Company B', 'slug' => 'company-b-'.uniqid(), 'status' => 'active']);

        $ownerA = User::factory()->create(['role' => 'owner', 'company_id' => $companyA->id, 'status' => 'active']);
        $expenseB = Expense::create([
            'company_id' => $companyB->id,
            'vendor_name' => 'Vendor of Company B',
            'description' => 'Some expense',
            'amount' => 100,
            'gross_amount' => 115,
            'vat_amount' => 15,
            'expense_date' => now()->toDateString(),
            'status' => 'approved',
        ]);

        $response = $this->actingAs($ownerA)->get(route('app.expenses.edit', $expenseB));

        $response->assertNotFound();
    }

    public function test_a_company_users_invoice_list_never_shows_another_companys_invoices(): void
    {
        $companyA = Company::create(['name' => 'Company A', 'slug' => 'company-a-'.uniqid(), 'status' => 'active']);
        $companyB = Company::create(['name' => 'Company B', 'slug' => 'company-b-'.uniqid(), 'status' => 'active']);

        $ownerA = User::factory()->create(['role' => 'owner', 'company_id' => $companyA->id, 'status' => 'active']);
        $clientA = Client::create(['company_id' => $companyA->id, 'name' => 'Client of Company A']);
        $clientB = Client::create(['company_id' => $companyB->id, 'name' => 'Client of Company B']);

        $invoiceA = Invoice::create([
            'company_id' => $companyA->id, 'client_id' => $clientA->id,
            'invoice_number' => 'A-VISIBLE-'.uniqid(), 'type' => 'standard', 'status' => 'draft',
            'issue_date' => now()->toDateString(), 'currency' => 'SAR',
        ]);
        Invoice::create([
            'company_id' => $companyB->id, 'client_id' => $clientB->id,
            'invoice_number' => 'B-HIDDEN-'.uniqid(), 'type' => 'standard', 'status' => 'draft',
            'issue_date' => now()->toDateString(), 'currency' => 'SAR',
        ]);

        $response = $this->actingAs($ownerA)->get(route('app.invoices.index'));

        $response->assertOk()
            ->assertSee($invoiceA->invoice_number)
            ->assertDontSee('B-HIDDEN');
    }
}
