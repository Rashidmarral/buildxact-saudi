<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Project;
use App\Models\ProjectChangeOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "first build the project module same as it is as the hamly erp with
 * complete details as they have with proper functionality" — Phase 4:
 * Change Orders (adjust the EFFECTIVE contract value once approved,
 * without overwriting the original signed contract_value) and Site
 * Diary (daily log: weather/manpower/work done/materials/issues).
 */
class ProjectChangeOrderSiteDiaryTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        return Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid()]);
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    private function makeProject(Company $company, float $contractValue = 500000): Project
    {
        return Project::create(['company_id' => $company->id, 'code' => 'PRJ-000001', 'name' => 'Test Project', 'status' => 'active', 'contract_value' => $contractValue]);
    }

    public function test_a_change_order_can_be_added_and_is_numbered(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $project = $this->makeProject($company);

        $this->actingAs($owner)->post(route('app.projects.change-orders.store', $project), [
            'title' => 'Add drainage works', 'amount' => 25000, 'days_impact' => 5,
        ])->assertSessionDoesntHaveErrors();

        $changeOrder = ProjectChangeOrder::first();
        $this->assertSame('CO-00001', $changeOrder->change_order_number);
        $this->assertSame('draft', $changeOrder->status);
    }

    public function test_approving_a_change_order_adjusts_the_effective_contract_value_without_changing_the_original(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $project = $this->makeProject($company, 500000);
        $changeOrder = $project->changeOrders()->create(['change_order_number' => 'CO-00001', 'title' => 'Add drainage works', 'amount' => 25000, 'status' => 'draft']);

        $this->actingAs($owner)->post(route('app.projects.change-orders.approve', [$project, $changeOrder]))
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(500000.0, (float) $project->fresh()->contract_value);
        $this->assertSame(525000.0, $project->fresh()->effectiveContractValue());
    }

    public function test_a_rejected_change_order_never_affects_the_effective_contract_value(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $project = $this->makeProject($company, 500000);
        $changeOrder = $project->changeOrders()->create(['change_order_number' => 'CO-00001', 'title' => 'Scope reduction', 'amount' => -50000, 'status' => 'draft']);

        $this->actingAs($owner)->post(route('app.projects.change-orders.reject', [$project, $changeOrder]))
            ->assertSessionDoesntHaveErrors();

        $this->assertSame('rejected', $changeOrder->fresh()->status);
        $this->assertSame(500000.0, $project->fresh()->effectiveContractValue());
    }

    public function test_a_draft_change_order_can_be_deleted_but_an_approved_one_cannot(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $project = $this->makeProject($company);
        $draft = $project->changeOrders()->create(['change_order_number' => 'CO-00001', 'title' => 'Draft', 'amount' => 1000, 'status' => 'draft']);
        $approved = $project->changeOrders()->create(['change_order_number' => 'CO-00002', 'title' => 'Approved', 'amount' => 1000, 'status' => 'approved']);

        $this->actingAs($owner)->delete(route('app.projects.change-orders.destroy', [$project, $draft]))
            ->assertSessionDoesntHaveErrors();
        $this->assertDatabaseMissing('project_change_orders', ['id' => $draft->id]);

        $this->actingAs($owner)->delete(route('app.projects.change-orders.destroy', [$project, $approved]))
            ->assertNotFound();
        $this->assertDatabaseHas('project_change_orders', ['id' => $approved->id]);
    }

    public function test_a_site_diary_entry_can_be_added_and_removed(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $project = $this->makeProject($company);

        $this->actingAs($owner)->post(route('app.projects.site-diary.store', $project), [
            'entry_date' => now()->toDateString(), 'weather' => 'Clear', 'manpower_count' => 12,
            'work_done' => 'Subbase laid on section 2', 'issues_delays' => 'Late delivery of asphalt',
        ])->assertSessionDoesntHaveErrors();

        $entry = $project->siteDiaryEntries()->first();
        $this->assertSame('Clear', $entry->weather);
        $this->assertSame(12, $entry->manpower_count);

        $this->actingAs($owner)->delete(route('app.projects.site-diary.destroy', [$project, $entry]))
            ->assertSessionDoesntHaveErrors();
        $this->assertDatabaseMissing('project_site_diary_entries', ['id' => $entry->id]);
    }

    public function test_the_show_page_renders_the_new_tabs_and_effective_contract_value(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $project = $this->makeProject($company, 500000);
        $project->changeOrders()->create(['change_order_number' => 'CO-00001', 'title' => 'Add drainage works', 'amount' => 25000, 'status' => 'approved']);
        $project->siteDiaryEntries()->create(['entry_date' => now(), 'work_done' => 'Subbase laid on section 2']);

        $response = $this->actingAs($owner)->get(route('app.projects.show', $project));

        $response->assertOk()
            ->assertSee('Add drainage works')
            ->assertSee('Subbase laid on section 2')
            ->assertSee(\App\Support\Money::format(525000), false);
    }
}
