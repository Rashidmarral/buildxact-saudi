<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Coupon;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "please use pagination for the whole app on company side and super
 * admin add option to show results 10,20,30 etc" — extends the
 * resolvePerPage()/partials/pagination.blade.php pattern already proven
 * on the highest-traffic lists (see PaginationTest) to the rest of the
 * company-side controllers and to the Super Admin side, which had no
 * per-page selector infrastructure of its own at all. This covers a
 * representative sample from each side, plus the one page with more than
 * one independent paginator on it (the ZATCA compliance logs), which
 * needed its own ?xxx_per_page= key per table instead of all three
 * colliding on one ?per_page=.
 */
class PaginationRolloutTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): User
    {
        $company = Company::create(['name' => 'Rollout Co.', 'slug' => 'rollout-'.uniqid(), 'status' => 'active']);

        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'company_id' => null]);
    }

    public function test_expenses_index_honours_a_valid_per_page_value(): void
    {
        $owner = $this->makeOwner();
        for ($i = 0; $i < 3; $i++) {
            Expense::create([
                'company_id' => $owner->company_id, 'vendor_name' => "Vendor $i", 'amount' => 100,
                'gross_amount' => 100, 'vat_amount' => 0, 'tax_category' => 'zero_rated', 'expense_date' => now()->toDateString(),
            ]);
        }

        $response = $this->actingAs($owner)->get(route('app.expenses.index', ['per_page' => 10]));

        $response->assertOk();
        $response->assertViewHas('expenses', fn ($paginator) => $paginator->perPage() === 10);
    }

    public function test_expenses_index_falls_back_to_the_default_for_an_out_of_range_per_page_value(): void
    {
        $owner = $this->makeOwner();

        $response = $this->actingAs($owner)->get(route('app.expenses.index', ['per_page' => 9999]));

        $response->assertOk();
        $response->assertViewHas('expenses', fn ($paginator) => $paginator->perPage() === 20);
    }

    public function test_bank_transfers_index_shows_the_per_page_selector(): void
    {
        $owner = $this->makeOwner();

        $response = $this->actingAs($owner)->get(route('app.bank-transfers.index'));

        $response->assertOk();
        $response->assertSee(__('Per page'));
    }

    public function test_admin_companies_index_honours_a_valid_per_page_value(): void
    {
        $admin = $this->makeAdmin();
        for ($i = 0; $i < 3; $i++) {
            Company::create(['name' => "Admin Co $i", 'slug' => 'admin-co-'.uniqid(), 'status' => 'active']);
        }

        $response = $this->actingAs($admin)->get(route('admin.companies.index', ['per_page' => 10]));

        $response->assertOk();
        $response->assertViewHas('companies', fn ($paginator) => $paginator->perPage() === 10);
    }

    public function test_admin_coupons_index_falls_back_to_the_default_for_an_out_of_range_per_page_value(): void
    {
        $admin = $this->makeAdmin();
        Coupon::create(['code' => 'SAVE10', 'discount_type' => 'percentage', 'percentage' => 10, 'is_active' => true]);

        $response = $this->actingAs($admin)->get(route('admin.coupons.index', ['per_page' => 9999]));

        $response->assertOk();
        $response->assertViewHas('coupons', fn ($paginator) => $paginator->perPage() === 20);
    }

    public function test_admin_tickets_index_shows_the_per_page_selector(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('admin.tickets.index'));

        $response->assertOk();
        $response->assertSee(__('Per page'));
    }

    /**
     * The ZATCA compliance logs page has three independent paginated
     * tables (invoices/credit notes/debit notes) that don't share one
     * sortable identity — each must honour its OWN ?xxx_per_page= key
     * without the three colliding on a single ?per_page=.
     */
    public function test_admin_zatca_logs_gives_each_of_its_three_tables_an_independent_per_page(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('admin.zatca.logs', [
            'invoice_per_page' => 10, 'credit_note_per_page' => 25, 'debit_note_per_page' => 50,
        ]));

        $response->assertOk();
        $response->assertViewHas('invoiceLogs', fn ($paginator) => $paginator->perPage() === 10);
        $response->assertViewHas('creditNoteLogs', fn ($paginator) => $paginator->perPage() === 25);
        $response->assertViewHas('debitNoteLogs', fn ($paginator) => $paginator->perPage() === 50);
    }
}
