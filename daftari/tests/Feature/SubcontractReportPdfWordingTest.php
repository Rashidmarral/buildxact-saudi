<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Subcontract;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "you have copy the subcontractor pdf details please update it to
 * project manager milestone and 5% retention not coming in pdf as
 * well" — the printed PDF (receiving-report.blade.php) is shared by
 * both Subcontractor Payments and Project Manager Budgets (same
 * Subcontract model, party_type='supplier' vs 'employee'), but was
 * hardcoded with subcontractor-only wording and an always-rendered
 * milestone/retention schedule — printing "Subcontractor", a milestone
 * table that's always empty for a Project Manager Budget (it never
 * syncs milestones), and a retention banner that's always 0% (PM
 * budgets force retention_percent to 0). These tests render the blade
 * view directly (not through mPDF, whose binary output isn't reliably
 * text-searchable) to pin the exact wording each party_type must and
 * must not show.
 */
class SubcontractReportPdfWordingTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): array
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        return [$company, $owner];
    }

    private function renderPdfView(Company $company, Subcontract $subcontract): string
    {
        return view('user.subcontracts.pdf.receiving-report', [
            'company' => $company,
            'subcontract' => $subcontract->fresh(['project', 'supplier', 'employee', 'boqItems', 'milestones.payments', 'works.payments', 'payments.milestone', 'payments.work', 'payments.paymentVoucher', 'reportCategories']),
            'reportCategoryRows' => null,
            'template' => null,
            'embed' => fn ($path) => null,
        ])->render();
    }

    public function test_a_subcontractor_pdf_keeps_its_original_wording(): void
    {
        [$company, $owner] = $this->makeOwner();
        $project = Project::create(['company_id' => $company->id, 'code' => 'PROJ-1', 'name' => 'Jumum Yard Project', 'status' => 'active']);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'SAED EST']);
        $subcontract = Subcontract::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'party_type' => 'supplier', 'supplier_id' => $supplier->id,
            'contract_value' => 373650, 'retention_percent' => 5, 'status' => 'active',
        ]);
        $subcontract->milestones()->create(['sequence' => 1, 'name' => 'Advance', 'percentage' => 20, 'amount' => 74730]);

        $html = $this->renderPdfView($company, $subcontract);

        $this->assertStringContainsString('Receiving Payments Report', $html);
        $this->assertStringContainsString('Subcontractor', $html);
        $this->assertStringContainsString('PAYMENT SCHEDULE', $html);
        $this->assertStringContainsString('Retention withheld', $html);
        $this->assertStringContainsString('Total contract value', $html);
        $this->assertStringNotContainsString('Project manager signature', $html);
    }

    public function test_a_project_manager_budget_pdf_uses_budget_wording_with_no_milestone_or_retention_section(): void
    {
        [$company, $owner] = $this->makeOwner();
        $project = Project::create(['company_id' => $company->id, 'code' => 'PROJ-1', 'name' => 'Jumum Yard Project', 'status' => 'active']);
        $employee = Employee::create(['company_id' => $company->id, 'employee_number' => 'EMP-00001', 'full_name' => 'Shahid', 'hire_date' => now(), 'basic_salary' => 6000]);
        $budget = Subcontract::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'party_type' => 'employee', 'employee_id' => $employee->id,
            'contract_value' => 373650, 'retention_percent' => 0, 'status' => 'active',
        ]);

        $html = $this->renderPdfView($company, $budget);

        $this->assertStringContainsString('Budget Report', $html);
        $this->assertStringContainsString('Project manager', $html);
        $this->assertStringContainsString('Project manager signature', $html);
        $this->assertStringContainsString('Total budget', $html);
        $this->assertStringContainsString('Balance remaining', $html);
        $this->assertStringNotContainsString('PAYMENT SCHEDULE', $html);
        $this->assertStringNotContainsString('Retention withheld', $html);
        $this->assertStringNotContainsString('Subcontractor', $html);
    }

    public function test_a_project_manager_budget_pdf_shows_its_works_breakdown_when_works_exist(): void
    {
        [$company, $owner] = $this->makeOwner();
        $project = Project::create(['company_id' => $company->id, 'code' => 'PROJ-1', 'name' => 'Jumum Yard Project', 'status' => 'active']);
        $employee = Employee::create(['company_id' => $company->id, 'employee_number' => 'EMP-00001', 'full_name' => 'Shahid', 'hire_date' => now(), 'basic_salary' => 6000]);
        $budget = Subcontract::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'party_type' => 'employee', 'employee_id' => $employee->id,
            'contract_value' => 373650, 'retention_percent' => 0, 'status' => 'active',
        ]);
        $budget->works()->create(['name' => 'MC1', 'budget_amount' => 10000, 'sort_order' => 0]);

        $html = $this->renderPdfView($company, $budget);

        $this->assertStringContainsString('WORKS / SUB-PROJECTS', $html);
        $this->assertStringContainsString('MC1', $html);
    }

    public function test_the_project_manager_budget_pdf_route_renders_successfully_over_http(): void
    {
        [$company, $owner] = $this->makeOwner();
        $project = Project::create(['company_id' => $company->id, 'code' => 'PROJ-1', 'name' => 'Jumum Yard Project', 'status' => 'active']);
        $employee = Employee::create(['company_id' => $company->id, 'employee_number' => 'EMP-00001', 'full_name' => 'Shahid', 'hire_date' => now(), 'basic_salary' => 6000]);
        $budget = Subcontract::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'party_type' => 'employee', 'employee_id' => $employee->id,
            'contract_value' => 373650, 'retention_percent' => 0, 'status' => 'active',
        ]);
        $budget->payments()->create(['company_id' => $company->id, 'amount' => 161860, 'paid_at' => now()]);

        $response = $this->actingAs($owner)->get(route('app.project-manager-budgets.report.pdf', $budget));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('budget-report-', $response->headers->get('Content-Disposition'));
    }
}
