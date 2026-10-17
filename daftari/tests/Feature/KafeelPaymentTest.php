<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Company;
use App\Models\CompanyOverride;
use App\Models\KafeelPayment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * "i don't want to show this other user have same login details like i
 * have super admin access if i need this i will check this option and
 * it will be visible in the sidebar otherwise it will be hide" — a
 * private per-company payment log (not a sellable module: no plan grants
 * it, see FeatureRegistry's 'kafeel_payments' entry) that only turns on
 * for a company once a Super Admin sets a CompanyOverride for it, the
 * same generic front door ClientStatementTest already proves for a
 * 'gated' key, now exercised against the first 'planned' one.
 */
class KafeelPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000, 'is_active' => true,
        ]);
        $company = Company::create(['name' => 'Zubaidi', 'slug' => 'zubaidi-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
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

    private function installModule(Company $company): void
    {
        CompanyOverride::create(['company_id' => $company->id, 'type' => 'feature', 'key' => 'kafeel_payments', 'value' => '1', 'reason' => 'test']);
    }

    // ------------------------------------------------------------------
    // Module gating — hidden by default, admin-only CompanyOverride
    // ------------------------------------------------------------------

    public function test_a_company_without_an_override_is_blocked_from_every_route(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);

        $this->actingAs($owner)->get(route('app.kafeel-payments.index'))->assertRedirect(route('app.dashboard'));
        $this->actingAs($owner)->get(route('app.kafeel-payments.create'))->assertRedirect(route('app.dashboard'));
    }

    public function test_no_plan_grants_the_module_on_its_own(): void
    {
        $company = $this->makeCompany();

        $this->assertFalse(app(\App\Services\Features\FeatureAccessService::class)->enabled($company, 'kafeel_payments'));
    }

    public function test_a_super_admin_override_installs_it_for_one_company_without_a_plan_change(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $this->installModule($company);

        $this->actingAs($owner)->get(route('app.kafeel-payments.index'))->assertOk();
    }

    public function test_the_nav_link_is_hidden_without_the_override_and_appears_once_installed(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);

        $this->actingAs($owner)->get(route('app.dashboard'))
            ->assertDontSee(route('app.kafeel-payments.index'), false);

        $this->installModule($company);

        $this->actingAs($owner)->get(route('app.dashboard'))
            ->assertSee(route('app.kafeel-payments.index'), false);
    }

    public function test_the_module_is_not_in_the_self_service_marketplace(): void
    {
        $this->assertSame(
            'planned',
            \App\Support\FeatureRegistry::catalog()['kafeel_payments']['type'] ?? null,
        );
    }

    // ------------------------------------------------------------------
    // CRUD + cross-company isolation
    // ------------------------------------------------------------------

    public function test_a_payment_can_be_recorded_edited_and_deleted(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $this->installModule($company);

        $this->actingAs($owner)->post(route('app.kafeel-payments.store'), [
            'payment_date' => '2026-08-10', 'paid_to' => 'Abdul Majeed', 'category' => 'cash_transfer',
            'description' => 'Transfer to Abdul Majeed Account', 'amount' => 500,
        ])->assertRedirect();

        $payment = KafeelPayment::first();
        $this->assertSame('Abdul Majeed', $payment->paid_to);
        $this->assertSame('500.00', (string) $payment->amount);
        $this->assertSame($owner->id, $payment->created_by);

        $this->actingAs($owner)->put(route('app.kafeel-payments.update', $payment), [
            'payment_date' => '2026-08-10', 'paid_to' => 'Abdul Majeed', 'category' => 'cash_transfer',
            'description' => 'Transfer to Abdul Majeed Account', 'amount' => 600,
        ])->assertRedirect();
        $this->assertSame('600.00', (string) $payment->fresh()->amount);

        $this->actingAs($owner)->delete(route('app.kafeel-payments.destroy', $payment))->assertRedirect();
        $this->assertSoftDeleted('kafeel_payments', ['id' => $payment->id]);
    }

    public function test_a_payment_cannot_be_viewed_or_edited_by_another_company(): void
    {
        $companyA = $this->makeCompany();
        $this->installModule($companyA);
        $ownerA = $this->makeOwner($companyA);
        $payment = KafeelPayment::create([
            'company_id' => $companyA->id, 'payment_date' => now()->toDateString(), 'category' => 'other',
            'description' => 'Company A payment', 'amount' => 100, 'created_by' => $ownerA->id,
        ]);

        $companyB = $this->makeCompany();
        $this->installModule($companyB);
        $ownerB = $this->makeOwner($companyB);

        $this->actingAs($ownerB)->get(route('app.kafeel-payments.edit', $payment))->assertNotFound();
    }

    // ------------------------------------------------------------------
    // Attachments
    // ------------------------------------------------------------------

    public function test_a_file_can_be_attached_to_and_removed_from_a_payment(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $this->installModule($company);
        $payment = KafeelPayment::create([
            'company_id' => $company->id, 'payment_date' => now()->toDateString(), 'category' => 'other',
            'description' => 'Fine', 'amount' => 150, 'created_by' => $owner->id,
        ]);

        $this->actingAs($owner)->post(route('app.kafeel-payments.attachments.store', $payment), [
            'file' => UploadedFile::fake()->image('receipt.jpg'),
        ])->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('attachments', [
            'attachable_type' => KafeelPayment::class, 'attachable_id' => $payment->id, 'original_name' => 'receipt.jpg',
        ]);

        $attachment = $payment->attachments()->first();
        $this->actingAs($owner)->delete(route('app.kafeel-payments.attachments.destroy', [$payment, $attachment]))
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
    }

    // ------------------------------------------------------------------
    // CSV import
    // ------------------------------------------------------------------

    public function test_csv_import_creates_rows_and_reports_skipped_ones(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $this->installModule($company);

        $csv = "Date,Description,Amount,Paid To,Category\n"
            .'2025-08-10,License Fee,100,,license_fee'."\n"
            .'2025-08-16,Abdul Majeed,500,Abdul Majeed,cash_transfer'."\n"
            .'not-a-date,Bad row,abc,,other'."\n";
        $file = UploadedFile::fake()->createWithContent('kafeel.csv', $csv);

        $response = $this->actingAs($owner)->post(route('app.kafeel-payments.import'), ['file' => $file]);

        $response->assertRedirect(route('app.kafeel-payments.index'));
        $this->assertSame(2, KafeelPayment::where('company_id', $company->id)->count());
        $this->assertCount(1, session('importSkipped'));
    }

    public function test_csv_import_falls_back_to_other_for_an_unrecognized_category(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $this->installModule($company);

        $csv = "Date,Description,Amount,Category\n".'2025-08-10,Something,50,not_a_real_category'."\n";
        $file = UploadedFile::fake()->createWithContent('kafeel.csv', $csv);

        $this->actingAs($owner)->post(route('app.kafeel-payments.import'), ['file' => $file]);

        $this->assertSame('other', KafeelPayment::first()->category);
    }

    // ------------------------------------------------------------------
    // Exports + reporting
    // ------------------------------------------------------------------

    public function test_csv_and_pdf_exports_respond_with_the_right_content_type(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $this->installModule($company);
        KafeelPayment::create([
            'company_id' => $company->id, 'payment_date' => now()->toDateString(), 'category' => 'fuel',
            'description' => 'Fuel', 'amount' => 50, 'created_by' => $owner->id,
        ]);

        $csvResponse = $this->actingAs($owner)->get(route('app.kafeel-payments.index', ['export' => 'csv']));
        $csvResponse->assertOk();
        $this->assertStringContainsString('text/csv', $csvResponse->headers->get('Content-Type'));

        $pdfResponse = $this->actingAs($owner)->get(route('app.kafeel-payments.index', ['export' => 'pdf']));
        $pdfResponse->assertOk();
        $this->assertStringStartsWith('application/pdf', $pdfResponse->headers->get('Content-Type'));
    }

    public function test_the_index_shows_the_period_total_and_category_breakdown(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $this->installModule($company);
        KafeelPayment::create([
            'company_id' => $company->id, 'payment_date' => now()->toDateString(), 'category' => 'fuel',
            'description' => 'Fuel', 'amount' => 50, 'created_by' => $owner->id,
        ]);
        KafeelPayment::create([
            'company_id' => $company->id, 'payment_date' => now()->toDateString(), 'category' => 'subscription',
            'description' => 'STC Card', 'amount' => 120, 'created_by' => $owner->id,
        ]);

        $response = $this->actingAs($owner)->get(route('app.kafeel-payments.index'));

        $response->assertOk();
        $response->assertSee(\App\Support\Money::format(170));
    }

    public function test_kafeel_payments_are_never_posted_to_the_general_ledger(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $this->installModule($company);

        $this->actingAs($owner)->post(route('app.kafeel-payments.store'), [
            'payment_date' => now()->toDateString(), 'category' => 'cash_transfer',
            'description' => 'Transfer to Kafeel Account', 'amount' => 300,
        ]);

        $this->assertSame(0, \App\Models\JournalEntry::where('company_id', $company->id)->count());
    }
}
