<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\Employee;
use App\Models\Project;
use App\Models\TaxRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * "first build the project module same as it is as the hamly erp with
 * complete details as they have with proper functionality" — Phase 0:
 * extends Project with Hamly's full field set (type, business location,
 * project manager, billing type, default VAT rate, contract value,
 * retention terms, advance recovery, cost centre, site address) and adds
 * the Documents tab (attachments) that was a confirmed real gap.
 */
class ProjectHamlyFieldsTest extends TestCase
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

    public function test_a_project_can_be_created_with_the_full_hamly_field_set(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Sada Al Jeul Construction Est.']);
        $branch = Branch::create(['company_id' => $company->id, 'name' => 'Riyadh Branch']);
        $manager = Employee::create(['company_id' => $company->id, 'employee_number' => 'EMP-'.uniqid(), 'full_name' => 'Ahmed Al-Otaibi', 'hire_date' => now()->subYear()->toDateString(), 'status' => 'active']);
        $costCenter = CostCenter::create(['company_id' => $company->id, 'code' => 'CC-1', 'name' => 'Road Works', 'is_active' => true]);
        $taxRate = TaxRate::create(['company_id' => $company->id, 'name' => 'Standard', 'rate' => 15, 'type' => 'standard', 'is_active' => true]);

        $response = $this->actingAs($owner)->post(route('app.projects.store'), [
            'name' => 'Al-Jumum Square Road Works',
            'type' => 'construction_contract',
            'status' => 'active',
            'client_id' => $client->id,
            'business_location_id' => $branch->id,
            'project_manager_id' => $manager->id,
            'billing_type' => 'progress_retention',
            'default_tax_rate_id' => $taxRate->id,
            'cost_center_id' => $costCenter->id,
            'contract_value' => 500000,
            'retention_percent' => 10,
            'retention_calculated_on' => 'excluding_vat',
            'advance_recovery_percent' => 20,
            'site_address' => 'Al-Jumum Square',
            'city' => 'Makkah',
            'description' => 'Resurfacing and drainage works.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('projects', [
            'company_id' => $company->id, 'name' => 'Al-Jumum Square Road Works',
            'type' => 'construction_contract', 'business_location_id' => $branch->id,
            'project_manager_id' => $manager->id, 'billing_type' => 'progress_retention',
            'default_tax_rate_id' => $taxRate->id, 'cost_center_id' => $costCenter->id,
            'contract_value' => 500000, 'retention_percent' => 10, 'advance_recovery_percent' => 20,
            'site_address' => 'Al-Jumum Square', 'city' => 'Makkah',
        ]);
    }

    public function test_the_database_defaults_type_and_billing_type_when_a_project_is_created_outside_the_form(): void
    {
        $company = $this->makeCompany();

        $project = Project::create(['company_id' => $company->id, 'code' => 'PRJ-000001', 'name' => 'Simple Job', 'status' => 'active']);

        $this->assertSame('construction_contract', $project->fresh()->type);
        $this->assertSame('milestones', $project->fresh()->billing_type);
        $this->assertSame('excluding_vat', $project->fresh()->retention_calculated_on);
    }

    public function test_the_create_form_preselects_construction_contract_and_milestone_billing(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->get(route('app.projects.create'));

        $response->assertOk();
        $response->assertSee('value="construction_contract" selected', false);
        $response->assertSee('value="milestones" selected', false);
    }

    public function test_the_show_page_renders_project_details_and_billing_info(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $manager = Employee::create(['company_id' => $company->id, 'employee_number' => 'EMP-'.uniqid(), 'full_name' => 'Ahmed Al-Otaibi', 'hire_date' => now()->subYear()->toDateString(), 'status' => 'active']);
        $project = Project::create([
            'company_id' => $company->id, 'code' => 'PRJ-000001', 'name' => 'Test Project', 'status' => 'active',
            'type' => 'maintenance_amc', 'project_manager_id' => $manager->id, 'billing_type' => 'recurring_amc',
            'contract_value' => 120000, 'retention_percent' => 5, 'created_by' => $owner->id,
        ]);

        $response = $this->actingAs($owner)->get(route('app.projects.show', $project));

        $response->assertOk()
            ->assertSee('Ahmed Al-Otaibi')
            ->assertSee('Maintenance (AMC)')
            ->assertSee('Recurring (AMC)');
    }

    public function test_a_file_can_be_attached_to_and_removed_from_a_project(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $project = Project::create(['company_id' => $company->id, 'code' => 'PRJ-000001', 'name' => 'Test Project', 'status' => 'active', 'created_by' => $owner->id]);

        $this->actingAs($owner)->post(route('app.projects.attachments.store', $project), [
            'file' => UploadedFile::fake()->image('site-plan.jpg'),
        ])->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('attachments', [
            'attachable_type' => Project::class, 'attachable_id' => $project->id, 'original_name' => 'site-plan.jpg',
        ]);

        $attachment = $project->attachments()->first();
        $this->actingAs($owner)->delete(route('app.projects.attachments.destroy', [$project, $attachment]))
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
    }

    public function test_a_company_cannot_remove_another_companys_project_attachment(): void
    {
        $companyA = $this->makeCompany();
        $companyB = $this->makeCompany();
        $ownerA = $this->makeOwner($companyA);
        $projectB = Project::create(['company_id' => $companyB->id, 'code' => 'PRJ-000001', 'name' => 'Other Co Project', 'status' => 'active']);
        $this->actingAs($this->makeOwner($companyB))->post(route('app.projects.attachments.store', $projectB), [
            'file' => UploadedFile::fake()->image('file.jpg'),
        ]);
        $attachmentB = $projectB->attachments()->first();

        $this->actingAs($ownerA)->delete(route('app.projects.attachments.destroy', [$projectB, $attachmentB]))
            ->assertNotFound();
    }
}
