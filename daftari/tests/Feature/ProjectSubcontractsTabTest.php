<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Project;
use App\Models\Subcontract;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "first build the project module same as it is as the hamly erp with
 * complete details as they have with proper functionality" — Phase 3:
 * the Subcontracts tab. Subcontract/SubcontractBoqItem/SubcontractMilestone
 * already exist and already link to Project (built earlier in this
 * engagement for the Subcontractor Payments module) — this phase is
 * just surfacing them on the project page, not a new model.
 */
class ProjectSubcontractsTabTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_subcontracts_tab_lists_contracts_raised_against_the_project(): void
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $project = Project::create(['company_id' => $company->id, 'code' => 'PRJ-000001', 'name' => 'Test Project', 'status' => 'active']);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Saed Est.']);
        Subcontract::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'supplier_id' => $supplier->id,
            'contract_number' => 'SC-0001', 'title' => 'Road works subcontract', 'contract_value' => 250000,
            'start_date' => now()->toDateString(), 'status' => 'active',
        ]);

        $response = $this->actingAs($owner)->get(route('app.projects.show', $project));

        $response->assertOk()->assertSee('SC-0001')->assertSee('Road works subcontract')->assertSee('Saed Est.');
    }
}
