<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\Project;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Two things fixed here: (1) every already-paginated list used a fixed
 * page size with no way for a user to change it — resolvePerPage() +
 * partials/pagination.blade.php add a validated ?per_page= selector,
 * retrofitted onto the highest-traffic lists (Invoices, Bills, Clients,
 * Suppliers, Items, Quotations); (2) Projects and the Inventory stock
 * report had no pagination at all — ->get()'d every row unconditionally.
 */
class PaginationTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwnerWithClient(): array
    {
        $company = Company::create(['name' => 'Page Co.', 'slug' => 'page-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client']);

        return [$owner, $client];
    }

    public function test_invoices_index_honours_a_valid_per_page_value(): void
    {
        [$owner, $client] = $this->makeOwnerWithClient();
        for ($i = 0; $i < 5; $i++) {
            Invoice::create([
                'company_id' => $owner->company_id, 'client_id' => $client->id, 'invoice_number' => "INV-$i",
                'issue_date' => now(), 'due_date' => now()->addDays(30), 'status' => 'draft', 'currency' => 'SAR',
            ]);
        }

        $response = $this->actingAs($owner)->get(route('app.invoices.index', ['per_page' => 10]));

        $response->assertOk();
        $response->assertViewHas('invoices', fn ($paginator) => $paginator->perPage() === 10);
    }

    public function test_invoices_index_falls_back_to_the_default_for_an_out_of_range_per_page_value(): void
    {
        [$owner] = $this->makeOwnerWithClient();

        $response = $this->actingAs($owner)->get(route('app.invoices.index', ['per_page' => 9999]));

        $response->assertOk();
        $response->assertViewHas('invoices', fn ($paginator) => $paginator->perPage() === 20);
    }

    public function test_projects_index_is_now_paginated(): void
    {
        $company = Company::create(['name' => 'Project Co.', 'slug' => 'project-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        for ($i = 0; $i < 3; $i++) {
            Project::create(['company_id' => $company->id, 'name' => "Project $i", 'code' => "PROJ_$i", 'status' => 'active']);
        }

        $response = $this->actingAs($owner)->get(route('app.projects.index', ['per_page' => 10]));

        $response->assertOk();
        $response->assertViewHas('projects', fn ($paginator) => method_exists($paginator, 'perPage') && $paginator->perPage() === 10);
    }

    public function test_inventory_stock_is_now_paginated_and_still_sorted_by_item_name(): void
    {
        $company = Company::create(['name' => 'Stock Co.', 'slug' => 'stock-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $warehouse = Warehouse::create(['company_id' => $company->id, 'name' => 'Main']);
        $zeta = Item::create(['company_id' => $company->id, 'name' => 'Zeta Widget', 'track_inventory' => true]);
        $alpha = Item::create(['company_id' => $company->id, 'name' => 'Alpha Widget', 'track_inventory' => true]);
        ItemStock::create(['item_id' => $zeta->id, 'warehouse_id' => $warehouse->id, 'quantity' => 5]);
        ItemStock::create(['item_id' => $alpha->id, 'warehouse_id' => $warehouse->id, 'quantity' => 5]);

        $response = $this->actingAs($owner)->get(route('app.inventory.stock', ['per_page' => 10]));

        $response->assertOk();
        $response->assertViewHas('stocks', function ($paginator) use ($alpha, $zeta) {
            $names = collect($paginator->items())->map(fn ($s) => $s->item->name)->values()->all();

            return $paginator->perPage() === 10 && $names === ['Alpha Widget', 'Zeta Widget'];
        });
    }
}
