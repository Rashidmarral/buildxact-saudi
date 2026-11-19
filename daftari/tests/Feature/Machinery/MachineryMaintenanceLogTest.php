<?php

namespace Tests\Feature\Machinery;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Expense;
use App\Models\MachineryAsset;
use App\Models\MachineryMaintenanceLog;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "build both those next" (maintenance scheduling + trip logs) —
 * logging a service event records history and, when a next due date
 * is given, schedules MachineryAsset.next_maintenance_due_date, which
 * SendMachineryExpiryReminders reminds against 30 days ahead.
 */
class MachineryMaintenanceLogTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000,
            'is_active' => true, 'has_machinery_equipment' => true,
        ]);
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        Subscription::create([
            'company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
        ]);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    private function makeAsset(Company $company): MachineryAsset
    {
        return MachineryAsset::create(['company_id' => $company->id, 'asset_code' => 'EQ-00001', 'name' => 'Steel Roller', 'status' => 'available']);
    }

    public function test_logging_maintenance_schedules_the_next_due_date_on_the_asset(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $asset = $this->makeAsset($company);

        $response = $this->actingAs($owner)->post(route('app.machinery.maintenance.store'), [
            'machinery_asset_id' => $asset->id,
            'service_type' => 'Oil change',
            'performed_at' => now()->toDateString(),
            'next_due_date' => now()->addMonths(3)->toDateString(),
            'notes' => 'Routine service.',
        ]);

        $response->assertRedirect(route('app.machinery.maintenance.index'));
        $log = MachineryMaintenanceLog::where('machinery_asset_id', $asset->id)->first();
        $this->assertNotNull($log);
        $this->assertSame('Oil change', $log->service_type);

        $asset->refresh();
        $this->assertSame(now()->addMonths(3)->toDateString(), $asset->next_maintenance_due_date->toDateString());
    }

    public function test_logging_without_a_next_due_date_clears_any_existing_schedule(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $asset = $this->makeAsset($company);
        $asset->update(['next_maintenance_due_date' => now()->addWeek(), 'maintenance_reminder_sent_at' => now()]);

        $this->actingAs($owner)->post(route('app.machinery.maintenance.store'), [
            'machinery_asset_id' => $asset->id,
            'service_type' => 'General service',
            'performed_at' => now()->toDateString(),
        ])->assertRedirect(route('app.machinery.maintenance.index'));

        $asset->refresh();
        $this->assertNull($asset->next_maintenance_due_date);
        $this->assertNull($asset->maintenance_reminder_sent_at, 'the stale guard is cleared along with the date');
    }

    public function test_a_new_next_due_date_resets_the_reminder_guard(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $asset = $this->makeAsset($company);
        $asset->update(['next_maintenance_due_date' => now()->addDays(5), 'maintenance_reminder_sent_at' => now()]);

        $this->actingAs($owner)->post(route('app.machinery.maintenance.store'), [
            'machinery_asset_id' => $asset->id,
            'service_type' => 'Tyre replacement',
            'performed_at' => now()->toDateString(),
            'next_due_date' => now()->addMonths(6)->toDateString(),
        ])->assertRedirect(route('app.machinery.maintenance.index'));

        $asset->refresh();
        $this->assertNull($asset->maintenance_reminder_sent_at);
    }

    public function test_a_maintenance_log_can_link_a_real_expense(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $asset = $this->makeAsset($company);
        $bank = BankAccount::create(['company_id' => $company->id, 'name' => 'Petty Cash', 'type' => 'cash', 'currency' => 'SAR', 'is_active' => true]);
        $expense = Expense::create([
            'company_id' => $company->id, 'machinery_asset_id' => $asset->id, 'bank_account_id' => $bank->id,
            'description' => 'Oil change parts', 'amount' => 200, 'gross_amount' => 200, 'vat_amount' => 0,
            'tax_category' => 'zero_rated', 'status' => 'approved', 'expense_date' => now()->toDateString(),
        ]);

        $this->actingAs($owner)->post(route('app.machinery.maintenance.store'), [
            'machinery_asset_id' => $asset->id,
            'service_type' => 'Oil change',
            'performed_at' => now()->toDateString(),
            'expense_id' => $expense->id,
        ])->assertRedirect(route('app.machinery.maintenance.index'));

        $log = MachineryMaintenanceLog::where('machinery_asset_id', $asset->id)->first();
        $this->assertSame($expense->id, $log->expense_id);
    }

    public function test_the_index_page_shows_overdue_and_history(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $asset = $this->makeAsset($company);
        $asset->update(['next_maintenance_due_date' => now()->subDay()]);
        MachineryMaintenanceLog::create(['company_id' => $company->id, 'machinery_asset_id' => $asset->id, 'service_type' => 'Oil change', 'performed_at' => now()->subMonth()]);

        $response = $this->actingAs($owner)->get(route('app.machinery.maintenance.index'));

        $response->assertOk();
        $response->assertSee('Overdue');
        $response->assertSee('Oil change');
    }
}
