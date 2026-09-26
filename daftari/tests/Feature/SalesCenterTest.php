<?php

namespace Tests\Feature;

use App\Models\AdminRole;
use App\Models\Lead;
use App\Models\SalesTarget;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The 90-Day Sales Plan + Sales Tools the operator asked for directly:
 * "how i can get sales give me client hunting as well". Weekly targets
 * (SalesTarget) are admin-editable; every "actual" figure is computed
 * live from Lead rather than stored, so the two can never drift.
 */
class SalesCenterTest extends TestCase
{
    use RefreshDatabase;

    private function makeSuperAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'company_id' => null]);
    }

    private function seedPlan(): void
    {
        for ($week = 1; $week <= SalesTarget::WEEKS; $week++) {
            SalesTarget::create([
                'week_number' => $week,
                'new_leads_target' => 10 + $week,
                'demos_target' => 2 + $week,
                'won_target' => $week,
            ]);
        }
    }

    public function test_a_super_admin_can_view_the_90_day_plan_page(): void
    {
        $this->seedPlan();

        $response = $this->actingAs($this->makeSuperAdmin())->get(route('admin.sales-center.plan'));

        $response->assertOk();
        $response->assertSee('Week 1');
        $response->assertSee('Week 13');
    }

    public function test_a_super_admin_can_view_the_sales_tools_page(): void
    {
        $response = $this->actingAs($this->makeSuperAdmin())->get(route('admin.sales-center.tools'));

        $response->assertOk();
        $response->assertSee('WhatsApp opening message');
        $response->assertSee('Objection-handling cheat sheet');
    }

    public function test_a_staff_without_the_permission_cannot_reach_the_sales_center(): void
    {
        $staff = User::factory()->create(['role' => 'admin_staff', 'company_id' => null]);

        $this->actingAs($staff)->get(route('admin.sales-center.plan'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.sales-center.tools'))->assertForbidden();
    }

    public function test_a_staff_granted_the_sales_center_permission_can_reach_the_plan_page(): void
    {
        $this->seedPlan();
        $role = AdminRole::create(['name' => 'Sales', 'slug' => 'sales-'.uniqid(), 'permissions' => ['sales_center']]);
        $staff = User::factory()->create(['role' => 'admin_staff', 'company_id' => null]);
        $staff->adminRoles()->attach($role);

        $this->actingAs($staff)->get(route('admin.sales-center.plan'))->assertOk();
    }

    public function test_updating_targets_persists_the_new_weekly_goals(): void
    {
        $this->seedPlan();

        $payload = [];
        foreach (range(1, SalesTarget::WEEKS) as $week) {
            $payload[$week] = ['new_leads_target' => 50, 'demos_target' => 15, 'won_target' => 8];
        }

        $this->actingAs($this->makeSuperAdmin())
            ->post(route('admin.sales-center.targets.update'), ['targets' => $payload])
            ->assertRedirect();

        $this->assertSame(50, SalesTarget::where('week_number', 1)->value('new_leads_target'));
        $this->assertSame(8, SalesTarget::where('week_number', 13)->value('won_target'));
    }

    public function test_restarting_the_plan_updates_the_start_date_setting(): void
    {
        $this->actingAs($this->makeSuperAdmin())
            ->post(route('admin.sales-center.restart'), ['start_date' => '2026-01-05'])
            ->assertRedirect();

        $this->assertSame('2026-01-05', Setting::get('sales_plan_start_date'));
    }

    public function test_the_plan_page_seeds_a_start_date_automatically_on_first_view(): void
    {
        $this->assertNull(Setting::get('sales_plan_start_date'));

        $this->actingAs($this->makeSuperAdmin())->get(route('admin.sales-center.plan'))->assertOk();

        $this->assertNotNull(Setting::get('sales_plan_start_date'));
    }

    public function test_week_one_actual_new_leads_reflects_leads_created_in_that_window(): void
    {
        $this->seedPlan();
        Setting::set('sales_plan_start_date', now()->toDateString());

        Lead::capture(['name' => 'Ali', 'email' => 'ali@example.com'], 'manual', notify: false);
        Lead::capture(['name' => 'Sara', 'email' => 'sara@example.com'], 'manual', notify: false);

        $response = $this->actingAs($this->makeSuperAdmin())->get(route('admin.sales-center.plan'));

        $response->assertOk();
        $weekOne = collect($response->viewData('rows'))->firstWhere(fn ($row) => $row['target']->week_number === 1);

        // Both leads were captured "now", inside week 1's window.
        $this->assertSame(2, $weekOne['new_leads_actual']);
    }
}
