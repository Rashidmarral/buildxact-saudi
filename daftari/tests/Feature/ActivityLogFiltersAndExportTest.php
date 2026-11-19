<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "each and every thing should be trackable in reports with more wide
 * options" — the Activity Log page listed every entry with no way to
 * narrow it down and no export, unlike every other list in Reports.
 * Adds user/action/date filters plus a CSV download.
 */
class ActivityLogFiltersAndExportTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompanyAndOwner(): array
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active']);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        return [$company, $owner];
    }

    public function test_filtering_by_user_action_and_date_narrows_the_results(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $other = User::factory()->create(['role' => 'staff', 'company_id' => $company->id, 'status' => 'active']);

        AuditLog::record('budget.create', null, 'Created budget "FY26"', $owner->id, null, null, $company->id);
        AuditLog::record('budget.delete', null, 'Deleted budget "Old"', $owner->id, null, null, $company->id);
        AuditLog::record('budget.create', null, 'Created budget "Other"', $other->id, null, null, $company->id);

        $byUser = $this->actingAs($owner)->get(route('app.activity.index', ['user_id' => $other->id]));
        $byUser->assertOk()->assertSee('Created budget "Other"')->assertDontSee('Created budget "FY26"');

        $byAction = $this->actingAs($owner)->get(route('app.activity.index', ['action' => 'budget.delete']));
        $byAction->assertOk()->assertSee('Deleted budget "Old"')->assertDontSee('Created budget "FY26"');

        $byDate = $this->actingAs($owner)->get(route('app.activity.index', ['from' => now()->addDay()->toDateString()]));
        $byDate->assertOk()->assertDontSee('Created budget "FY26"');
    }

    public function test_csv_export_matches_the_filtered_page(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();

        AuditLog::record('budget.create', null, 'Created budget "FY26"', $owner->id, null, null, $company->id);
        AuditLog::record('budget.delete', null, 'Deleted budget "Old"', $owner->id, null, null, $company->id);

        $csv = $this->actingAs($owner)->get(route('app.activity.index', ['action' => 'budget.delete', 'export' => 'csv']));
        $csv->assertOk();
        $body = str_replace("\xEF\xBB\xBF", '', $csv->streamedContent());
        $this->assertStringContainsString('Deleted budget', $body);
        $this->assertStringNotContainsString('Created budget', $body);

        $page = $this->actingAs($owner)->get(route('app.activity.index'));
        $page->assertOk()->assertSee(__('Download CSV'));
    }
}
