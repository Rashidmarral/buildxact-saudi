<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\JournalEntry;
use App\Models\MachineryAsset;
use App\Models\MachineryProjectDeployment;
use App\Models\Project;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "yes please if required gl posting please do for all" — equipment,
 * labour, manual project costs, and material issues now post real
 * journal entries. Equipment/labour/manual costs are an internal
 * allocation (Dr COGS / Cr Internal Cost Recovery — net zero on the
 * company's P&L, since the real expense is already posted elsewhere:
 * machinery depreciation, payroll). Material issues are genuine stock
 * consumption (Dr COGS / Cr Inventory Asset), the only one of the four
 * with no other GL representation already on the books.
 */
class ProjectCostGlPostingTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid()]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    private function makeProject(Company $company): Project
    {
        return Project::create(['company_id' => $company->id, 'code' => 'PRJ-000001', 'name' => 'Test Project', 'status' => 'active']);
    }

    public function test_a_manual_cost_posts_dr_cogs_cr_internal_cost_recovery(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $project = $this->makeProject($company);

        $this->actingAs($owner)->post(route('app.projects.costs.store', $project), [
            'cost_head' => 'equipment', 'cost_date' => now()->toDateString(), 'description' => 'Roller rental', 'amount' => 2500,
        ]);

        $entry = JournalEntry::where('source_type', 'project_cost')->first();
        $this->assertNotNull($entry);
        $cogs = Account::where('company_id', $company->id)->where('code', '5000')->first();
        $recovery = Account::where('company_id', $company->id)->where('code', '5950')->first();
        $this->assertEqualsWithDelta(2500, $entry->lines->where('account_id', $cogs->id)->sum('debit'), 0.01);
        $this->assertEqualsWithDelta(2500, $entry->lines->where('account_id', $recovery->id)->sum('credit'), 0.01);
        $totalDebit = $entry->lines->sum('debit');
        $totalCredit = $entry->lines->sum('credit');
        $this->assertEqualsWithDelta($totalDebit, $totalCredit, 0.01);
    }

    public function test_removing_a_manual_cost_reverses_its_journal_entry(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $project = $this->makeProject($company);
        $this->actingAs($owner)->post(route('app.projects.costs.store', $project), [
            'cost_head' => 'other', 'cost_date' => now()->toDateString(), 'description' => 'Misc', 'amount' => 100,
        ]);
        $cost = $project->projectCosts()->first();

        $this->actingAs($owner)->delete(route('app.projects.costs.destroy', [$project, $cost]));

        $this->assertDatabaseMissing('journal_entries', ['source_type' => 'project_cost', 'source_id' => $cost->id]);
    }

    public function test_an_approved_timesheet_posts_dr_cogs_cr_internal_cost_recovery(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $project = $this->makeProject($company);
        $employee = Employee::create(['company_id' => $company->id, 'employee_number' => 'EMP-1', 'full_name' => 'Khalid', 'hire_date' => now()->subYear()->toDateString(), 'status' => 'active']);
        $this->actingAs($owner)->post(route('app.projects.timesheets.store', $project), [
            'employee_id' => $employee->id, 'work_date' => now()->toDateString(), 'hours' => 8, 'cost_rate' => 20,
        ]);
        $timesheet = $project->timesheets()->first();

        $response = $this->actingAs($owner)->post(route('app.projects.timesheets.approve', [$project, $timesheet]));

        $this->assertNotNull($timesheet->fresh()->project_cost_id, 'status: '.$response->getStatusCode());
        $entry = JournalEntry::where('source_type', 'project_cost')->first();
        $this->assertNotNull($entry);
        $this->assertEqualsWithDelta(160, $entry->lines->sum('debit'), 0.01);
    }

    public function test_removing_an_approved_timesheet_reverses_its_journal_entry(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $project = $this->makeProject($company);
        $employee = Employee::create(['company_id' => $company->id, 'employee_number' => 'EMP-1', 'full_name' => 'Khalid', 'hire_date' => now()->subYear()->toDateString(), 'status' => 'active']);
        $this->actingAs($owner)->post(route('app.projects.timesheets.store', $project), [
            'employee_id' => $employee->id, 'work_date' => now()->toDateString(), 'hours' => 8, 'cost_rate' => 20,
        ]);
        $timesheet = $project->timesheets()->first();
        $this->actingAs($owner)->post(route('app.projects.timesheets.approve', [$project, $timesheet]));
        $projectCostId = $timesheet->fresh()->projectCost->id;

        $this->actingAs($owner)->delete(route('app.projects.timesheets.destroy', [$project, $timesheet]));

        $this->assertDatabaseMissing('journal_entries', ['source_type' => 'project_cost', 'source_id' => $projectCostId]);
    }

    public function test_a_completed_machinery_deployment_posts_dr_cogs_cr_internal_cost_recovery(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $project = $this->makeProject($company);
        $machine = MachineryAsset::create(['company_id' => $company->id, 'asset_code' => 'EQ-1', 'name' => 'Roller', 'status' => 'deployed']);
        $deployment = MachineryProjectDeployment::create([
            'company_id' => $company->id, 'machinery_asset_id' => $machine->id, 'project_id' => $project->id,
            'start_date' => now()->subDays(4)->toDateString(), 'internal_daily_rate' => 500,
            'status' => 'active', 'created_by' => $owner->id,
        ]);

        $this->actingAs($owner)->post(route('app.machinery.deployments.end', $deployment), ['end_date' => now()->toDateString()]);

        $entry = JournalEntry::where('source_type', 'project_cost')->first();
        $this->assertNotNull($entry);
        $this->assertEqualsWithDelta(2500, $entry->lines->sum('debit'), 0.01);
    }

    public function test_a_material_issue_posts_a_real_dr_cogs_cr_inventory_entry(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $project = $this->makeProject($company);
        $item = Item::create(['company_id' => $company->id, 'name' => 'Cement bags', 'is_active' => true, 'purchase_price' => 20]);
        $warehouse = Warehouse::create(['company_id' => $company->id, 'name' => 'Main yard']);
        ItemStock::create(['item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => 100]);

        $this->actingAs($owner)->post(route('app.projects.material-issues.store', $project), [
            'item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => 30, 'issued_at' => now()->toDateString(),
        ]);

        $issue = $project->materialIssues()->first();
        $entry = JournalEntry::where('source_type', 'project_material_issue')->where('source_id', $issue->id)->first();
        $this->assertNotNull($entry);
        $cogs = Account::where('company_id', $company->id)->where('code', '5000')->first();
        $inventory = Account::where('company_id', $company->id)->where('code', '1400')->first();
        $this->assertEqualsWithDelta(600, $entry->lines->where('account_id', $cogs->id)->sum('debit'), 0.01);
        $this->assertEqualsWithDelta(600, $entry->lines->where('account_id', $inventory->id)->sum('credit'), 0.01);
        // No project_cost allocation entry should ALSO exist for the same
        // event — that would double-post the same economic transaction.
        $this->assertDatabaseMissing('journal_entries', ['source_type' => 'project_cost', 'source_id' => $issue->project_cost_id]);
    }

    public function test_removing_a_material_issue_reverses_its_journal_entry(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $project = $this->makeProject($company);
        $item = Item::create(['company_id' => $company->id, 'name' => 'Cement bags', 'is_active' => true, 'purchase_price' => 20]);
        $warehouse = Warehouse::create(['company_id' => $company->id, 'name' => 'Main yard']);
        ItemStock::create(['item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => 100]);
        $this->actingAs($owner)->post(route('app.projects.material-issues.store', $project), [
            'item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => 30, 'issued_at' => now()->toDateString(),
        ]);
        $issue = $project->materialIssues()->first();

        $this->actingAs($owner)->delete(route('app.projects.material-issues.destroy', [$project, $issue]));

        $this->assertDatabaseMissing('journal_entries', ['source_type' => 'project_material_issue', 'source_id' => $issue->id]);
    }

    public function test_a_material_issue_with_no_warehouse_posts_no_gl_entry(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $project = $this->makeProject($company);
        $item = Item::create(['company_id' => $company->id, 'name' => 'Cement bags', 'is_active' => true, 'purchase_price' => 20]);

        $this->actingAs($owner)->post(route('app.projects.material-issues.store', $project), [
            'item_id' => $item->id, 'quantity' => 30, 'issued_at' => now()->toDateString(),
        ])->assertSessionDoesntHaveErrors();

        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_every_project_cost_allocation_nets_zero_against_company_wide_net_income(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $project = $this->makeProject($company);

        $this->actingAs($owner)->post(route('app.projects.costs.store', $project), [
            'cost_head' => 'labour', 'cost_date' => now()->toDateString(), 'description' => 'Allocation test', 'amount' => 999,
        ]);

        $cogs = Account::where('company_id', $company->id)->where('code', '5000')->first();
        $recovery = Account::where('company_id', $company->id)->where('code', '5950')->first();
        // Both sides sit in the expense section of the P&L: a debit to
        // COGS and an equal credit to a contra-expense account cancel out
        // to a zero net change in total expenses company-wide.
        $this->assertEqualsWithDelta(999, $cogs->journalEntryLines()->sum('debit'), 0.01);
        $this->assertEqualsWithDelta(999, $recovery->journalEntryLines()->sum('credit'), 0.01);
    }
}
