<?php

namespace Tests\Feature\RepairShop;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyOverride;
use App\Models\Item;
use App\Models\JournalEntry;
use App\Models\Plan;
use App\Models\PosSale;
use App\Models\RepairJob;
use App\Models\RepairJobItem;
use App\Models\SmsConfig;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Repair Shop: the first vertical module out of the roadmap after
 * Restaurant/POS — an intake ticket (device or vehicle), parts/labor
 * lines, a customer-approval gate before real repair work starts, and
 * checkout that reuses PosSaleService exactly like Restaurant does (same
 * GL posting, payment recording and receipt-template a retail POS sale
 * gets). Wired up as a Module 07 'gated' feature (see FeatureRegistry),
 * installable per company through the existing Modules marketplace —
 * no new admin infrastructure.
 */
class RepairShopModuleTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(bool $withRepairShop): Company
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000,
            'is_active' => true, 'has_repair_shop' => $withRepairShop,
        ]);

        $company = Company::create(['name' => 'Saed Auto Care', 'slug' => 'saed-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
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

    // ------------------------------------------------------------------
    // Module gating
    // ------------------------------------------------------------------

    public function test_a_company_without_the_repair_shop_plan_feature_or_override_is_blocked(): void
    {
        $company = $this->makeCompany(withRepairShop: false);
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->get(route('app.repair-jobs.index'));

        $response->assertRedirect(route('app.dashboard'));
        $response->assertSessionHasErrors('feature');
    }

    public function test_a_company_whose_plan_includes_repair_shop_can_access_it(): void
    {
        $company = $this->makeCompany(withRepairShop: true);
        $owner = $this->makeOwner($company);

        $this->actingAs($owner)->get(route('app.repair-jobs.index'))->assertOk();
    }

    public function test_an_admin_override_installs_the_module_for_one_company_without_a_plan_change(): void
    {
        $company = $this->makeCompany(withRepairShop: false);
        $owner = $this->makeOwner($company);
        CompanyOverride::create(['company_id' => $company->id, 'type' => 'feature', 'key' => 'repair_shop', 'value' => '1']);

        $this->actingAs($owner)->get(route('app.repair-jobs.index'))->assertOk();
    }

    // ------------------------------------------------------------------
    // Job lifecycle
    // ------------------------------------------------------------------

    public function test_a_job_can_be_opened_for_a_walk_in_or_a_client(): void
    {
        $company = $this->makeCompany(withRepairShop: true);
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Waleed']);

        $this->actingAs($owner)->post(route('app.repair-jobs.store'), [
            'item_description' => 'iPhone 13 Pro', 'brand' => 'Apple', 'model' => 'iPhone 13 Pro',
            'issue_description' => 'Cracked screen',
        ])->assertRedirect();
        $walkIn = RepairJob::first();
        $this->assertNull($walkIn->client_id);
        $this->assertSame('iPhone 13 Pro', $walkIn->item_description);
        $this->assertSame('received', $walkIn->status);

        $this->actingAs($owner)->post(route('app.repair-jobs.store'), [
            'client_id' => $client->id, 'item_description' => 'Toyota Camry 2019', 'year' => '2019',
        ])->assertRedirect();
        $withClient = RepairJob::where('client_id', $client->id)->firstOrFail();
        $this->assertSame('Toyota Camry 2019', $withClient->item_description);
    }

    public function test_a_repair_job_runs_end_to_end_from_intake_to_paid_pos_sale(): void
    {
        $company = $this->makeCompany(withRepairShop: true);
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Waleed', 'mobile' => '0501234567']);
        $part = Item::create(['company_id' => $company->id, 'name' => 'Screen Assembly', 'unit_price' => 200, 'vat_rate' => 15, 'is_active' => true]);
        $labor = Item::create(['company_id' => $company->id, 'name' => 'Screen Replacement Labor', 'unit_price' => 50, 'vat_rate' => 15, 'is_active' => true, 'item_type' => 'service']);

        $this->actingAs($owner)->post(route('app.repair-jobs.store'), [
            'client_id' => $client->id, 'item_description' => 'iPhone 13 Pro', 'issue_description' => 'Cracked screen',
        ]);
        $job = RepairJob::first();

        $this->actingAs($owner)->postJson(route('app.repair-jobs.items.store', $job), [
            'lines' => [
                ['item_id' => $part->id, 'quantity' => 1, 'core_exchange_credit' => 20],
                ['item_id' => $labor->id, 'quantity' => 1],
            ],
        ])->assertRedirect();

        $job->refresh();
        $this->assertSame(2, $job->items()->count());

        // Can't jump to in_repair before the estimate is approved.
        $blocked = $this->actingAs($owner)->post(route('app.repair-jobs.status', $job), ['status' => 'in_repair']);
        $blocked->assertSessionHasErrors('job');
        $this->assertSame('received', $job->fresh()->status);

        $this->actingAs($owner)->post(route('app.repair-jobs.approve', $job))->assertRedirect();
        $this->assertTrue($job->fresh()->isApproved());

        $this->actingAs($owner)->post(route('app.repair-jobs.status', $job), ['status' => 'in_repair'])->assertRedirect();
        $this->assertSame('in_repair', $job->fresh()->status);

        // The show page's displayed total is what the checkout panel below
        // auto-fills as "pay in full" — it must equal what
        // PosSaleService actually charges (VAT computed net of the core
        // exchange credit, not on the full line price), or a real cashier
        // clicking "Cash" then "Complete checkout" without hand-editing the
        // amount gets a rejected "payments don't add up" error.
        $this->actingAs($owner)->get(route('app.repair-jobs.show', $job))
            ->assertOk()->assertSee('Screen Assembly')->assertSee('264.50');

        // (200 - 20 core credit) * 1.15 + 50 * 1.15 = 264.50
        $response = $this->actingAs($owner)->postJson(route('app.repair-jobs.checkout', $job), [
            'payments' => [['method' => 'cash', 'amount' => 264.50]],
        ]);
        $response->assertRedirect();

        $job->refresh();
        $this->assertSame('collected', $job->status);
        $this->assertNotNull($job->pos_sale_id);
        $this->assertNotNull($job->completed_at);

        $sale = PosSale::find($job->pos_sale_id);
        $this->assertSame('completed', $sale->status);
        $this->assertSame(264.50, (float) $sale->total);
        $this->assertSame($client->id, $sale->client_id);

        $entry = JournalEntry::where('source_type', 'pos_sale')->where('source_id', $sale->id)->with('lines')->first();
        $this->assertNotNull($entry);
        $this->assertEqualsWithDelta((float) $entry->lines->sum('debit'), (float) $entry->lines->sum('credit'), 0.01);

        $this->actingAs($owner)->get(route('app.repair-jobs.show', $job))->assertOk()->assertSee(__('View receipt'));
    }

    /**
     * Security audit finding: RepairJobService::checkout() used to check
     * isOpen() on the PHP object it was handed, before opening its DB
     * transaction — exactly the shape two concurrent requests (a
     * double-click, or two open tabs) would each have: both load the job
     * while it's still 'in_repair', both pass the isOpen() check, both
     * create a PosSale. Reproduces that shape with two independent, stale
     * RepairJob instances (see DocumentNumberingRaceTest for the same
     * pattern) and asserts the fix — a lockForUpdate() re-fetch as the
     * first thing inside the transaction — makes the second call see the
     * first call's 'collected' status instead of also checking out.
     */
    public function test_two_stale_job_instances_cannot_both_check_out_the_same_job(): void
    {
        $company = $this->makeCompany(withRepairShop: true);
        $owner = $this->makeOwner($company);
        $this->actingAs($owner);
        $part = Item::create(['company_id' => $company->id, 'name' => 'Battery', 'unit_price' => 100, 'vat_rate' => 15, 'is_active' => true]);

        $job = RepairJob::create([
            'company_id' => $company->id, 'job_number' => $company->nextRepairJobNumber(),
            'item_description' => 'Laptop', 'status' => 'in_repair', 'created_by' => $owner->id,
        ]);
        RepairJobItem::create([
            'company_id' => $company->id, 'repair_job_id' => $job->id, 'item_id' => $part->id,
            'description' => $part->name, 'quantity' => 1, 'unit_price' => 100, 'vat_rate' => 15,
        ]);

        // Two independent instances of the same row, as two concurrent
        // checkout requests would each have — both still holding
        // status='in_repair' as it was at load time.
        $jobA = RepairJob::find($job->id);
        $jobB = RepairJob::find($job->id);

        $service = app(\App\Services\RepairShop\RepairJobService::class);
        $ledger = app(\App\Services\Accounting\LedgerPostingService::class);
        $payments = [['method' => 'cash', 'amount' => 115.0]];

        $saleA = $service->checkout($jobA, $payments, $ledger);
        $this->assertSame('completed', $saleA->status);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('This job is already closed.');
        $service->checkout($jobB, $payments, $ledger);
    }

    public function test_approving_an_estimate_requires_at_least_one_line(): void
    {
        $company = $this->makeCompany(withRepairShop: true);
        $owner = $this->makeOwner($company);
        $this->actingAs($owner)->post(route('app.repair-jobs.store'), ['item_description' => 'Washing Machine']);
        $job = RepairJob::first();

        $this->actingAs($owner)->post(route('app.repair-jobs.approve', $job))->assertSessionHasErrors('job');
        $this->assertFalse($job->fresh()->isApproved());
    }

    public function test_checkout_is_rejected_with_no_items_on_the_job(): void
    {
        $company = $this->makeCompany(withRepairShop: true);
        $owner = $this->makeOwner($company);
        $this->actingAs($owner)->post(route('app.repair-jobs.store'), ['item_description' => 'Washing Machine']);
        $job = RepairJob::first();

        $response = $this->actingAs($owner)->postJson(route('app.repair-jobs.checkout', $job), [
            'payments' => [['method' => 'cash', 'amount' => 10]],
        ]);

        $response->assertSessionHasErrors('checkout');
        $this->assertSame(0, PosSale::count());
    }

    public function test_a_job_can_be_cancelled(): void
    {
        $company = $this->makeCompany(withRepairShop: true);
        $owner = $this->makeOwner($company);
        $this->actingAs($owner)->post(route('app.repair-jobs.store'), ['item_description' => 'Laptop']);
        $job = RepairJob::first();

        $this->actingAs($owner)->post(route('app.repair-jobs.cancel', $job), ['cancel_reason' => 'Customer changed their mind'])->assertRedirect();

        $job->refresh();
        $this->assertSame('cancelled', $job->status);
        $this->assertSame('Customer changed their mind', $job->cancel_reason);
    }

    public function test_diagnosis_notes_can_be_saved(): void
    {
        $company = $this->makeCompany(withRepairShop: true);
        $owner = $this->makeOwner($company);
        $this->actingAs($owner)->post(route('app.repair-jobs.store'), ['item_description' => 'Laptop']);
        $job = RepairJob::first();

        $this->actingAs($owner)->post(route('app.repair-jobs.diagnosis', $job), [
            'diagnosis_notes' => 'Battery swollen, needs replacement.',
        ])->assertRedirect();

        $this->assertSame('Battery swollen, needs replacement.', $job->fresh()->diagnosis_notes);
    }

    public function test_a_company_cannot_add_another_companys_item_to_its_job(): void
    {
        $companyA = $this->makeCompany(withRepairShop: true);
        $companyB = $this->makeCompany(withRepairShop: true);
        $ownerA = $this->makeOwner($companyA);
        $itemB = Item::create(['company_id' => $companyB->id, 'name' => 'Other Co Part', 'unit_price' => 10, 'vat_rate' => 15, 'is_active' => true]);
        $this->actingAs($ownerA)->post(route('app.repair-jobs.store'), ['item_description' => 'Phone']);
        $job = RepairJob::first();

        $response = $this->actingAs($ownerA)->postJson(route('app.repair-jobs.items.store', $job), [
            'lines' => [['item_id' => $itemB->id, 'quantity' => 1]],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['lines.0.item_id']);
        $this->assertSame(0, RepairJobItem::withoutGlobalScopes()->count());
    }

    // ------------------------------------------------------------------
    // Customer notification
    // ------------------------------------------------------------------

    public function test_notify_sms_sends_a_ready_for_pickup_message(): void
    {
        Http::fake(['https://el.cloud.unifonic.com/*' => Http::response(['success' => true, 'data' => ['MessageID' => 'abc123']], 200)]);
        $company = $this->makeCompany(withRepairShop: true);
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Waleed', 'mobile' => '0501234567']);
        SmsConfig::create(['company_id' => $company->id, 'app_sid' => 'test-sid', 'sender_id' => 'Daftari', 'is_enabled' => true]);
        $this->actingAs($owner)->post(route('app.repair-jobs.store'), ['client_id' => $client->id, 'item_description' => 'iPhone 13 Pro']);
        $job = RepairJob::first();

        $response = $this->actingAs($owner)->post(route('app.repair-jobs.notify-sms', $job));

        $response->assertRedirect();
        Http::assertSent(fn ($request) => str_contains($request->url(), 'unifonic.com') && str_contains($request['Body'] ?? '', $job->job_number));
    }

    public function test_notify_sms_without_a_configured_gateway_404s(): void
    {
        $company = $this->makeCompany(withRepairShop: true);
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Waleed', 'mobile' => '0501234567']);
        $this->actingAs($owner)->post(route('app.repair-jobs.store'), ['client_id' => $client->id, 'item_description' => 'iPhone 13 Pro']);
        $job = RepairJob::first();

        $this->actingAs($owner)->post(route('app.repair-jobs.notify-sms', $job))->assertNotFound();
    }

    public function test_the_index_page_lists_open_jobs(): void
    {
        $company = $this->makeCompany(withRepairShop: true);
        $owner = $this->makeOwner($company);
        $this->actingAs($owner)->post(route('app.repair-jobs.store'), ['item_description' => 'Washing Machine']);
        $job = RepairJob::first();

        $response = $this->actingAs($owner)->get(route('app.repair-jobs.index'));

        $response->assertOk();
        $response->assertSee($job->job_number);
        $response->assertSee('Washing Machine');
        $response->assertSee(__('Walk-in'));
    }

    // ------------------------------------------------------------------
    // Auto Parts half: compatibility notes on items
    // ------------------------------------------------------------------

    public function test_an_item_can_store_and_show_vehicle_compatibility_notes(): void
    {
        $company = $this->makeCompany(withRepairShop: true);
        $owner = $this->makeOwner($company);

        $this->actingAs($owner)->post(route('app.items.store'), [
            'name' => 'Brake Pad Set', 'item_type' => 'physical', 'unit_price' => 120, 'vat_rate' => 15,
            'compatibility_notes' => 'Toyota Camry 2018–2022, Corolla 2015–2020',
        ])->assertRedirect();

        $item = Item::where('name', 'Brake Pad Set')->firstOrFail();
        $this->assertSame('Toyota Camry 2018–2022, Corolla 2015–2020', $item->compatibility_notes);

        $lookup = $this->actingAs($owner)->getJson(route('app.repair-jobs.item-lookup', ['q' => 'Camry']));
        $lookup->assertOk();
        $lookup->assertJsonFragment(['id' => $item->id]);
    }
}
