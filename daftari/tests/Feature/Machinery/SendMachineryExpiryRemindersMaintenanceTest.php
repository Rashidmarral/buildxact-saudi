<?php

namespace Tests\Feature\Machinery;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Company;
use App\Models\MachineryAsset;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\GenericNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * "build both those next" — next_maintenance_due_date rides the same
 * notify-once-then-clear-on-renewal reminder command that already
 * covers registration/insurance expiry (SendMachineryExpiryReminders),
 * just with a third field.
 */
class SendMachineryExpiryRemindersMaintenanceTest extends TestCase
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

    public function test_it_notifies_once_when_maintenance_is_due_within_the_window(): void
    {
        Notification::fake();
        $company = $this->makeCompany();
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $asset = MachineryAsset::create([
            'company_id' => $company->id, 'asset_code' => 'EQ-00001', 'name' => 'Steel Roller', 'status' => 'available',
            'next_maintenance_due_date' => now()->addDays(10),
        ]);

        $this->artisan('machinery:send-expiry-reminders')->assertSuccessful();

        Notification::assertSentTo($owner, GenericNotification::class, fn ($n) => str_contains($n->toArray($owner)['body'], 'EQ-00001'));
        $this->assertNotNull($asset->fresh()->maintenance_reminder_sent_at);
    }

    public function test_it_does_not_notify_again_until_the_guard_clears(): void
    {
        Notification::fake();
        $company = $this->makeCompany();
        User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        MachineryAsset::create([
            'company_id' => $company->id, 'asset_code' => 'EQ-00001', 'name' => 'Steel Roller', 'status' => 'available',
            'next_maintenance_due_date' => now()->addDays(10),
        ]);

        $this->artisan('machinery:send-expiry-reminders');
        Notification::fake();
        $this->artisan('machinery:send-expiry-reminders');

        Notification::assertNothingSent();
    }

    public function test_renewing_past_the_window_clears_the_guard_and_reminds_again_later(): void
    {
        Notification::fake();
        $company = $this->makeCompany();
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $asset = MachineryAsset::create([
            'company_id' => $company->id, 'asset_code' => 'EQ-00001', 'name' => 'Steel Roller', 'status' => 'available',
            'next_maintenance_due_date' => now()->addDays(5), 'maintenance_reminder_sent_at' => now(),
        ]);

        // Pushed well past the 30-day window — the stale guard should clear, but nothing sends yet.
        $asset->update(['next_maintenance_due_date' => now()->addDays(90)]);
        $this->artisan('machinery:send-expiry-reminders');

        Notification::assertNothingSent();
        $this->assertNull($asset->fresh()->maintenance_reminder_sent_at);
    }

    public function test_an_asset_with_no_maintenance_date_is_left_alone(): void
    {
        Notification::fake();
        $company = $this->makeCompany();
        User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        MachineryAsset::create(['company_id' => $company->id, 'asset_code' => 'EQ-00001', 'name' => 'Steel Roller', 'status' => 'available']);

        $this->artisan('machinery:send-expiry-reminders')->assertSuccessful();

        Notification::assertNothingSent();
    }
}
