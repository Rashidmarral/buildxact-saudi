<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "first build the project module same as it is as the hamly erp with
 * complete details as they have with proper functionality" — Phase 5:
 * Billing. Branches by billing_type (progress from BOQ, time &
 * material from approved timesheets/costs, fixed from the remaining
 * contract balance), plus a billing-type-agnostic advance invoice.
 * Every invoice this creates is a DRAFT — never auto-sent — same
 * practice as every other real financial document in this engagement.
 */
class ProjectBillingTest extends TestCase
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

    private function makeClient(Company $company): Client
    {
        return Client::create(['company_id' => $company->id, 'name' => 'Client Co.']);
    }

    public function test_progress_billing_bills_only_the_unbilled_portion_of_each_boq_item(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = $this->makeClient($company);
        $project = Project::create([
            'company_id' => $company->id, 'code' => 'PRJ-000001', 'name' => 'Test Project', 'status' => 'active',
            'client_id' => $client->id, 'billing_type' => 'milestones',
        ]);
        $boqItem = $project->boqItems()->create(['description' => 'Asphalt resurfacing', 'quantity' => 1000, 'rate' => 50, 'executed_quantity' => 400, 'billed_quantity' => 100]);

        $response = $this->actingAs($owner)->post(route('app.projects.billing.progress', $project));

        $invoice = Invoice::first();
        $response->assertRedirect(route('app.invoices.show', $invoice));
        $this->assertSame('draft', $invoice->status);
        $this->assertSame($client->id, $invoice->client_id);
        $this->assertSame($project->id, $invoice->project_id);
        $this->assertSame(15000.0, (float) $invoice->subtotal);
        $this->assertSame(400.0, (float) $boqItem->fresh()->billed_quantity);
    }

    public function test_progress_retention_billing_withholds_the_projects_retention_percent(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = $this->makeClient($company);
        $project = Project::create([
            'company_id' => $company->id, 'code' => 'PRJ-000001', 'name' => 'Test Project', 'status' => 'active',
            'client_id' => $client->id, 'billing_type' => 'progress_retention', 'retention_percent' => 10,
        ]);
        $project->boqItems()->create(['description' => 'Asphalt', 'quantity' => 1000, 'rate' => 50, 'executed_quantity' => 400, 'billed_quantity' => 0]);

        $this->actingAs($owner)->post(route('app.projects.billing.progress', $project));

        $invoice = Invoice::first();
        $this->assertSame(10.0, (float) $invoice->retention_rate);
        $this->assertSame(2000.0, (float) $invoice->retention_amount);
    }

    public function test_progress_billing_is_blocked_when_nothing_is_outstanding(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = $this->makeClient($company);
        $project = Project::create([
            'company_id' => $company->id, 'code' => 'PRJ-000001', 'name' => 'Test Project', 'status' => 'active',
            'client_id' => $client->id, 'billing_type' => 'milestones',
        ]);
        $project->boqItems()->create(['description' => 'Asphalt', 'quantity' => 1000, 'rate' => 50, 'executed_quantity' => 400, 'billed_quantity' => 400]);

        $this->actingAs($owner)->post(route('app.projects.billing.progress', $project))->assertStatus(422);
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_time_and_material_billing_pulls_in_approved_timesheets_and_billable_costs_and_marks_them_invoiced(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = $this->makeClient($company);
        $project = Project::create([
            'company_id' => $company->id, 'code' => 'PRJ-000001', 'name' => 'Test Project', 'status' => 'active',
            'client_id' => $client->id, 'billing_type' => 'time_and_material',
        ]);
        $employee = Employee::create(['company_id' => $company->id, 'employee_number' => 'EMP-1', 'full_name' => 'Khalid', 'hire_date' => now()->subYear()->toDateString(), 'status' => 'active']);
        $timesheet = $project->timesheets()->create(['employee_id' => $employee->id, 'work_date' => now(), 'hours' => 8, 'bill_rate' => 40, 'billable' => true, 'status' => 'approved']);
        $draftTimesheet = $project->timesheets()->create(['employee_id' => $employee->id, 'work_date' => now(), 'hours' => 8, 'bill_rate' => 40, 'billable' => true, 'status' => 'draft']);
        $cost = $project->projectCosts()->create(['cost_head' => 'equipment', 'cost_date' => now(), 'description' => 'Roller rental', 'amount' => 2500, 'billable' => true]);

        $this->actingAs($owner)->post(route('app.projects.billing.time-and-material', $project))
            ->assertSessionDoesntHaveErrors();

        $invoice = Invoice::first();
        $this->assertNotNull($invoice);
        $this->assertSame(2820.0, (float) $invoice->subtotal);
        $this->assertNotNull($timesheet->fresh()->invoiced_at);
        $this->assertSame($invoice->id, $timesheet->fresh()->invoice_id);
        $this->assertNotNull($cost->fresh()->invoiced_at);
        $this->assertNull($draftTimesheet->fresh()->invoiced_at);
    }

    public function test_time_and_material_billing_never_double_bills_an_already_invoiced_timesheet(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = $this->makeClient($company);
        $project = Project::create([
            'company_id' => $company->id, 'code' => 'PRJ-000001', 'name' => 'Test Project', 'status' => 'active',
            'client_id' => $client->id, 'billing_type' => 'time_and_material',
        ]);
        $employee = Employee::create(['company_id' => $company->id, 'employee_number' => 'EMP-1', 'full_name' => 'Khalid', 'hire_date' => now()->subYear()->toDateString(), 'status' => 'active']);
        $project->timesheets()->create(['employee_id' => $employee->id, 'work_date' => now(), 'hours' => 8, 'bill_rate' => 40, 'billable' => true, 'status' => 'approved']);

        $this->actingAs($owner)->post(route('app.projects.billing.time-and-material', $project));
        $this->actingAs($owner)->post(route('app.projects.billing.time-and-material', $project))->assertStatus(422);

        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_fixed_price_billing_bills_the_remaining_contract_balance_net_of_prior_invoices(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = $this->makeClient($company);
        $project = Project::create([
            'company_id' => $company->id, 'code' => 'PRJ-000001', 'name' => 'Test Project', 'status' => 'active',
            'client_id' => $client->id, 'billing_type' => 'fixed_price', 'contract_value' => 100000,
        ]);
        Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'project_id' => $project->id,
            'invoice_number' => 'INV-0001', 'status' => 'issued', 'issue_date' => now()->toDateString(), 'due_date' => now()->toDateString(), 'total' => 40000,
        ]);

        $this->actingAs($owner)->post(route('app.projects.billing.fixed', $project))->assertSessionDoesntHaveErrors();

        $invoice = Invoice::where('invoice_number', '!=', 'INV-0001')->first();
        $this->assertSame(60000.0, (float) $invoice->subtotal);
    }

    public function test_fixed_price_billing_is_blocked_once_the_full_contract_is_already_billed(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = $this->makeClient($company);
        $project = Project::create([
            'company_id' => $company->id, 'code' => 'PRJ-000001', 'name' => 'Test Project', 'status' => 'active',
            'client_id' => $client->id, 'billing_type' => 'fixed_price', 'contract_value' => 100000,
        ]);
        Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'project_id' => $project->id,
            'invoice_number' => 'INV-0001', 'status' => 'issued', 'issue_date' => now()->toDateString(), 'due_date' => now()->toDateString(), 'total' => 100000,
        ]);

        $this->actingAs($owner)->post(route('app.projects.billing.fixed', $project))->assertStatus(422);
    }

    public function test_an_advance_invoice_can_be_created_for_any_billing_type_except_no_billing(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = $this->makeClient($company);
        $project = Project::create([
            'company_id' => $company->id, 'code' => 'PRJ-000001', 'name' => 'Test Project', 'status' => 'active',
            'client_id' => $client->id, 'billing_type' => 'milestones',
        ]);

        $this->actingAs($owner)->post(route('app.projects.billing.advance', $project), ['amount' => 20000])
            ->assertSessionDoesntHaveErrors();

        $invoice = Invoice::first();
        $this->assertSame(20000.0, (float) $invoice->subtotal);
        $this->assertSame('draft', $invoice->status);
    }

    public function test_billing_is_blocked_without_a_client(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $project = Project::create([
            'company_id' => $company->id, 'code' => 'PRJ-000001', 'name' => 'Test Project', 'status' => 'active',
            'billing_type' => 'fixed_price', 'contract_value' => 50000,
        ]);

        $this->actingAs($owner)->post(route('app.projects.billing.fixed', $project))->assertStatus(422);
    }

    public function test_the_invoices_index_can_be_filtered_to_one_projects_invoices(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = $this->makeClient($company);
        $projectA = Project::create(['company_id' => $company->id, 'code' => 'PRJ-A', 'name' => 'Project A', 'status' => 'active']);
        $projectB = Project::create(['company_id' => $company->id, 'code' => 'PRJ-B', 'name' => 'Project B', 'status' => 'active']);
        Invoice::create(['company_id' => $company->id, 'client_id' => $client->id, 'project_id' => $projectA->id, 'invoice_number' => 'INV-A', 'status' => 'draft', 'issue_date' => now()->toDateString(), 'due_date' => now()->toDateString(), 'total' => 1000]);
        Invoice::create(['company_id' => $company->id, 'client_id' => $client->id, 'project_id' => $projectB->id, 'invoice_number' => 'INV-B', 'status' => 'draft', 'issue_date' => now()->toDateString(), 'due_date' => now()->toDateString(), 'total' => 2000]);

        $response = $this->actingAs($owner)->get(route('app.invoices.index', ['project_id' => $projectA->id]));

        $response->assertOk()->assertSee('INV-A')->assertDontSee('INV-B');
    }
}
