<?php

namespace Tests\Feature\Machinery;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\BankAccount;
use App\Models\Client;
use App\Models\CompanyLetter;
use App\Models\Company;
use App\Models\CompanyOverride;
use App\Models\Expense;
use App\Models\FixedAsset;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\MachineryAsset;
use App\Models\MachineryProjectDeployment;
use App\Models\MachineryRentalContract;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\GenericNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;

/**
 * Machinery & Equipment: a paid module (like Coffee Shop, Project Cash
 * Flow before it) that reuses the existing Fixed Asset register for a
 * machine's cost/depreciation/disposal (see FixedAssetLifecycleService)
 * and the existing Invoice pipeline for rental/sale revenue, rather than
 * building parallel accounting for either.
 */
class MachineryModuleTest extends \Tests\TestCase
{
    use RefreshDatabase;

    private function makeCompany(bool $withModule): Company
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000,
            'is_active' => true, 'has_machinery_equipment' => $withModule,
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

    // ------------------------------------------------------------------
    // Module gating
    // ------------------------------------------------------------------

    public function test_a_company_without_the_module_is_blocked_from_every_machinery_route(): void
    {
        $company = $this->makeCompany(withModule: false);
        $owner = $this->makeOwner($company);

        $this->actingAs($owner)->get(route('app.machinery.assets.index'))->assertRedirect(route('app.dashboard'));
        $this->actingAs($owner)->get(route('app.machinery.rental-contracts.index'))->assertRedirect(route('app.dashboard'));
        $this->actingAs($owner)->get(route('app.machinery.deployments.index'))->assertRedirect(route('app.dashboard'));
        $this->actingAs($owner)->get(route('app.machinery.letters.index'))->assertRedirect(route('app.dashboard'));
    }

    public function test_a_company_whose_plan_includes_the_module_can_access_it(): void
    {
        $company = $this->makeCompany(withModule: true);
        $owner = $this->makeOwner($company);

        $this->actingAs($owner)->get(route('app.machinery.assets.index'))->assertOk();
    }

    public function test_an_admin_override_installs_the_module_for_one_company_without_a_plan_change(): void
    {
        $company = $this->makeCompany(withModule: false);
        $owner = $this->makeOwner($company);

        CompanyOverride::create(['company_id' => $company->id, 'type' => 'feature', 'key' => 'machinery_equipment', 'value' => '1']);

        $this->actingAs($owner)->get(route('app.machinery.assets.index'))->assertOk();
    }

    // ------------------------------------------------------------------
    // Asset acquisition — reuses FixedAssetLifecycleService
    // ------------------------------------------------------------------

    public function test_registering_a_machine_creates_a_fixed_asset_and_posts_its_acquisition(): void
    {
        $company = $this->makeCompany(withModule: true);
        $owner = $this->makeOwner($company);
        $bank = BankAccount::create(['company_id' => $company->id, 'name' => 'SNB Current Account', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true]);

        $response = $this->actingAs($owner)->post(route('app.machinery.assets.store'), [
            'name' => 'Asphalt Paver', 'category' => 'Asphalt Paver', 'make' => 'Vögele', 'model' => 'Super 1800',
            'acquisition_date' => now()->toDateString(), 'acquisition_cost' => 250000, 'useful_life_years' => 8,
            'bank_account_id' => $bank->id,
        ]);

        $asset = MachineryAsset::latest('id')->first();
        $response->assertRedirect(route('app.machinery.assets.show', $asset));

        $this->assertNotNull($asset);
        $this->assertSame('Asphalt Paver', $asset->name);
        $this->assertStringStartsWith('EQ-', $asset->asset_code);
        $this->assertSame('available', $asset->status);

        $fixedAsset = $asset->fixedAsset;
        $this->assertNotNull($fixedAsset);
        $this->assertEqualsWithDelta(250000, (float) $fixedAsset->acquisition_cost, 0.01);

        $entry = JournalEntry::where('source_type', 'fixed_asset')->where('source_id', $fixedAsset->id)->first();
        $this->assertNotNull($entry);
        $this->assertEqualsWithDelta((float) $entry->lines()->sum('debit'), (float) $entry->lines()->sum('credit'), 0.01);
    }

    public function test_selling_a_machine_disposes_its_fixed_asset_and_can_raise_a_draft_invoice(): void
    {
        $company = $this->makeCompany(withModule: true);
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Buyer Co.']);

        $fixedAsset = FixedAsset::create([
            'company_id' => $company->id, 'asset_code' => 'AST-100001', 'name' => 'Roller',
            'acquisition_date' => now()->subYear()->toDateString(), 'acquisition_cost' => 100000,
            'useful_life_years' => 5, 'accumulated_depreciation' => 20000, 'status' => 'active',
        ]);
        $asset = MachineryAsset::create([
            'company_id' => $company->id, 'fixed_asset_id' => $fixedAsset->id, 'asset_code' => 'EQ-00001',
            'name' => 'Roller', 'status' => 'available',
        ]);

        $response = $this->actingAs($owner)->post(route('app.machinery.assets.sell', $asset), [
            'sold_at' => now()->toDateString(), 'sale_price' => 90000, 'client_id' => $client->id, 'also_invoice' => '1',
        ]);

        $response->assertRedirect(route('app.machinery.assets.show', $asset));
        $asset->refresh();
        $this->assertSame('sold', $asset->status);
        $fixedAsset->refresh();
        $this->assertSame('disposed', $fixedAsset->status);

        $disposalEntry = JournalEntry::where('source_type', 'fixed_asset_disposal')->where('source_id', $fixedAsset->id)->first();
        $this->assertNotNull($disposalEntry);

        $invoice = Invoice::where('machinery_asset_id', $asset->id)->first();
        $this->assertNotNull($invoice);
        $this->assertSame('draft', $invoice->status);
        $this->assertSame($client->id, $invoice->client_id);
        $this->assertEqualsWithDelta(90000, (float) $invoice->items->first()->unit_price, 0.01);
    }

    public function test_disposal_is_refused_with_a_clear_error_when_the_fixed_assets_mapping_is_missing(): void
    {
        $company = $this->makeCompany(withModule: true);
        $owner = $this->makeOwner($company);

        AccountMapping::withoutGlobalScope('company')->where('company_id', $company->id)->where('key', 'FIXED_ASSETS_DEFAULT')->delete();
        Account::withoutGlobalScope('company')->where('company_id', $company->id)->where('code', '1500')->delete();

        $fixedAsset = FixedAsset::create([
            'company_id' => $company->id, 'asset_code' => 'AST-100002', 'name' => 'Excavator',
            'acquisition_date' => now()->toDateString(), 'acquisition_cost' => 50000, 'useful_life_years' => 5, 'status' => 'active',
        ]);
        $asset = MachineryAsset::create([
            'company_id' => $company->id, 'fixed_asset_id' => $fixedAsset->id, 'asset_code' => 'EQ-00002',
            'name' => 'Excavator', 'status' => 'available',
        ]);

        $response = $this->actingAs($owner)->post(route('app.machinery.assets.sell', $asset), [
            'sold_at' => now()->toDateString(), 'sale_price' => 30000,
        ]);

        $response->assertSessionHasErrors('disposal');
        $asset->refresh();
        $this->assertSame('available', $asset->status);
    }

    // ------------------------------------------------------------------
    // Rental
    // ------------------------------------------------------------------

    public function test_renting_out_a_machine_marks_it_unavailable_and_generating_an_invoice_tags_it(): void
    {
        $company = $this->makeCompany(withModule: true);
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Renter Co.']);
        $asset = MachineryAsset::create(['company_id' => $company->id, 'asset_code' => 'EQ-00003', 'name' => 'Compactor', 'status' => 'available']);

        $storeResponse = $this->actingAs($owner)->post(route('app.machinery.rental-contracts.store'), [
            'machinery_asset_id' => $asset->id, 'client_id' => $client->id,
            'start_date' => now()->toDateString(), 'rate' => 500, 'rate_type' => 'daily', 'fuel_responsibility' => 'owner',
        ]);

        $contract = MachineryRentalContract::latest('id')->first();
        $storeResponse->assertRedirect(route('app.machinery.rental-contracts.show', $contract));
        $asset->refresh();
        $this->assertSame('rented_out', $asset->status);
        $this->assertStringStartsWith('RC-', $contract->contract_number);

        $invoiceResponse = $this->actingAs($owner)->post(route('app.machinery.rental-contracts.generate-invoice', $contract), [
            'period_start' => now()->toDateString(), 'period_end' => now()->addDays(4)->toDateString(), 'units' => 5,
        ]);

        $invoice = Invoice::where('machinery_asset_id', $asset->id)->first();
        $invoiceResponse->assertRedirect(route('app.invoices.show', $invoice));
        $this->assertNotNull($invoice);
        $this->assertSame('draft', $invoice->status);
        $this->assertEqualsWithDelta(2500, (float) $invoice->items->first()->unit_price, 0.01);

        $endResponse = $this->actingAs($owner)->post(route('app.machinery.rental-contracts.end', $contract), [
            'end_date' => now()->toDateString(),
        ]);
        $endResponse->assertRedirect(route('app.machinery.rental-contracts.show', $contract));
        $asset->refresh();
        $this->assertSame('available', $asset->status);
    }

    // ------------------------------------------------------------------
    // Deployment + the exact double-counting trap already fixed once for
    // Project Cash Flow — a notional internal_daily_rate must never feed
    // the project's real cash figures, only actual tagged Expenses do.
    // ------------------------------------------------------------------

    public function test_deploying_a_machine_on_a_project_and_tagging_an_expense_flows_through_project_cash_flow(): void
    {
        $company = $this->makeCompany(withModule: true);
        $owner = $this->makeOwner($company);
        $cash = BankAccount::create(['company_id' => $company->id, 'name' => 'Petty Cash', 'type' => 'cash', 'currency' => 'SAR', 'is_active' => true]);
        $project = Project::create(['company_id' => $company->id, 'code' => 'PRJ-1', 'name' => 'Jamum Site', 'status' => 'active']);
        $asset = MachineryAsset::create(['company_id' => $company->id, 'asset_code' => 'EQ-00004', 'name' => 'Paver', 'status' => 'available']);

        $deployResponse = $this->actingAs($owner)->post(route('app.machinery.deployments.store'), [
            'machinery_asset_id' => $asset->id, 'project_id' => $project->id,
            'start_date' => now()->toDateString(), 'internal_daily_rate' => 1000,
        ]);

        $deployment = MachineryProjectDeployment::latest('id')->first();
        $deployResponse->assertRedirect(route('app.machinery.deployments.index'));
        $asset->refresh();
        $this->assertSame('deployed', $asset->status);

        $expense = Expense::create([
            'company_id' => $company->id, 'bank_account_id' => $cash->id, 'project_id' => $project->id,
            'machinery_asset_id' => $asset->id, 'vendor_name' => 'Fuel Station', 'amount' => 800, 'gross_amount' => 800,
            'vat_amount' => 0, 'tax_category' => 'zero_rated', 'expense_date' => now()->toDateString(), 'status' => 'approved',
        ]);

        // The real cash cost to the project is the tagged Expense (800),
        // never the deployment's own notional internal_daily_rate (1000)
        // — confirms the double-counting trap from Project Cash Flow's
        // own fix doesn't reappear here for machinery deployments.
        $this->assertEqualsWithDelta(800, $asset->fresh()->totalRunningCost(), 0.01);
        $this->assertEqualsWithDelta(-800, $project->fresh()->netCashPosition(), 0.01);

        $endResponse = $this->actingAs($owner)->post(route('app.machinery.deployments.end', $deployment), ['end_date' => now()->toDateString()]);
        $endResponse->assertRedirect(route('app.machinery.deployments.index'));
        $this->assertSame('available', $asset->fresh()->status);
    }

    // ------------------------------------------------------------------
    // Letters & Agreements
    // ------------------------------------------------------------------

    public function test_a_letter_can_be_generated_from_a_preset_and_downloaded_as_a_pdf(): void
    {
        $company = $this->makeCompany(withModule: true);
        $owner = $this->makeOwner($company);
        $asset = MachineryAsset::create(['company_id' => $company->id, 'asset_code' => 'EQ-00005', 'name' => 'Grader', 'status' => 'available']);

        $createResponse = $this->actingAs($owner)->get(route('app.machinery.letters.create', ['document_type' => 'machinery_sale_agreement', 'machinery_asset_id' => $asset->id]));
        $createResponse->assertOk()->assertSee(__('Machinery Sale Agreement'));

        $storeResponse = $this->actingAs($owner)->post(route('app.machinery.letters.store'), [
            'machinery_asset_id' => $asset->id, 'document_type' => 'machinery_sale_agreement',
            'title' => 'Machinery Sale Agreement', 'letter_date' => now()->toDateString(),
            'party_a_role' => 'Seller', 'party_b_role' => 'Buyer', 'party_b_name' => 'Buyer Co.',
            'language_mode' => 'bilingual',
            'content' => [
                5 => ['text_en' => 'Sample clause.', 'text_ar' => 'بند تجريبي.', 'is_heading' => '1'],
                9 => ['text_en' => 'Second clause.', 'text_ar' => 'بند ثانٍ.'],
            ],
        ]);

        $letter = CompanyLetter::latest('id')->first();
        $storeResponse->assertRedirect(route('app.machinery.letters.show', $letter));
        $this->assertStringStartsWith('LTR-', $letter->reference_number);

        // Gapped submitted indices (5, 9) must still store as a clean,
        // sequential 0..n-1 array — not a gapped one that would encode as
        // a JSON object instead of an array.
        $this->assertSame([0, 1], array_keys($letter->content));
        $this->assertTrue($letter->content[0]['is_heading']);
        $this->assertFalse($letter->content[1]['is_heading']);

        foreach (['bilingual_side_by_side', 'bilingual_stacked'] as $layout) {
            $template = $company->invoiceTemplates()->updateOrCreate(
                ['document_type' => 'letter'],
                ['name' => 'Letter template', 'layout' => $layout, 'language_mode' => 'bilingual', 'is_default' => true]
            );

            $pdfResponse = $this->actingAs($owner)->get(route('app.machinery.letters.pdf', $letter));
            $pdfResponse->assertOk();
            $this->assertSame('application/pdf', $pdfResponse->headers->get('Content-Type'));
        }

        $letter->update(['language_mode' => 'arabic_only']);
        $this->actingAs($owner)->get(route('app.machinery.letters.pdf', $letter))->assertOk();

        $letter->update(['language_mode' => 'english_only']);
        $this->actingAs($owner)->get(route('app.machinery.letters.pdf', $letter))->assertOk();
    }

    // ------------------------------------------------------------------
    // Letter attachments — the scanned, signed copy. The relation and
    // eager-load already existed; this exercises the upload/remove
    // endpoints added to close that gap.
    // ------------------------------------------------------------------

    public function test_a_signed_copy_can_be_attached_to_and_removed_from_a_letter(): void
    {
        $company = $this->makeCompany(withModule: true);
        $owner = $this->makeOwner($company);
        $letter = CompanyLetter::create([
            'company_id' => $company->id, 'document_type' => 'custom', 'reference_number' => 'LTR-00001',
            'title' => 'Letter', 'letter_date' => now()->toDateString(), 'party_a_role' => 'Company',
            'party_b_role' => 'Client', 'party_b_name' => 'Someone', 'language_mode' => 'bilingual',
            'content' => [['text_en' => 'Body.', 'text_ar' => 'نص.', 'is_heading' => false]],
        ]);

        $this->actingAs($owner)->post(route('app.machinery.letters.attachments.store', $letter), [
            'file' => UploadedFile::fake()->image('signed-copy.jpg'),
        ])->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('attachments', [
            'attachable_type' => CompanyLetter::class, 'attachable_id' => $letter->id, 'original_name' => 'signed-copy.jpg',
        ]);

        $this->actingAs($owner)->get(route('app.machinery.letters.show', $letter))
            ->assertOk()->assertSee('signed-copy.jpg');

        $attachment = $letter->attachments()->first();
        $this->actingAs($owner)->delete(route('app.machinery.letters.attachments.destroy', [$letter, $attachment]))
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
    }

    // ------------------------------------------------------------------
    // Expiry reminders — a machine's registration/insurance lapsing
    // silently was a real gap: the columns existed but nothing watched
    // them. Mirrors CheckLowStock's notify-once/clear-on-resolution shape.
    // ------------------------------------------------------------------

    public function test_a_machine_with_registration_expiring_soon_notifies_users_once(): void
    {
        Notification::fake();
        $company = $this->makeCompany(withModule: true);
        $owner = $this->makeOwner($company);
        $asset = MachineryAsset::create([
            'company_id' => $company->id, 'asset_code' => 'EQ-00010', 'name' => 'Roller',
            'status' => 'available', 'registration_expiry_date' => now()->addDays(10)->toDateString(),
        ]);

        Artisan::call('machinery:send-expiry-reminders');

        Notification::assertSentTo($owner, GenericNotification::class, function (GenericNotification $notification) use ($asset) {
            return str_contains($notification->body, $asset->asset_code);
        });
        $this->assertNotNull($asset->fresh()->registration_reminder_sent_at);

        // Re-running the command the same day must not re-notify.
        Artisan::call('machinery:send-expiry-reminders');
        Notification::assertSentToTimes($owner, GenericNotification::class, 1);
    }

    public function test_renewing_a_machines_registration_clears_the_reminder_guard(): void
    {
        $company = $this->makeCompany(withModule: true);
        $this->makeOwner($company);
        $asset = MachineryAsset::create([
            'company_id' => $company->id, 'asset_code' => 'EQ-00011', 'name' => 'Roller',
            'status' => 'available', 'registration_expiry_date' => now()->addDays(10)->toDateString(),
            'registration_reminder_sent_at' => now()->subDay(),
        ]);

        // Renewed to a date well outside the reminder window — the next
        // cycle's own reminder must not be silenced by the old guard.
        $asset->update(['registration_expiry_date' => now()->addYear()->toDateString()]);

        Artisan::call('machinery:send-expiry-reminders');

        $this->assertNull($asset->fresh()->registration_reminder_sent_at);
    }

    // ------------------------------------------------------------------
    // Utilization — days rented + deployed over days owned.
    // ------------------------------------------------------------------

    public function test_utilization_percent_reflects_rental_and_deployment_days_against_days_owned(): void
    {
        $company = $this->makeCompany(withModule: true);
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Renter Co.']);
        $project = Project::create(['company_id' => $company->id, 'code' => 'PRJ-2', 'name' => 'Site', 'status' => 'active']);

        $fixedAsset = FixedAsset::create([
            'company_id' => $company->id, 'asset_code' => 'AST-100010', 'name' => 'Paver',
            'acquisition_date' => now()->subDays(19)->toDateString(), 'acquisition_cost' => 100000,
            'useful_life_years' => 5, 'status' => 'active',
        ]);
        $asset = MachineryAsset::create([
            'company_id' => $company->id, 'fixed_asset_id' => $fixedAsset->id, 'asset_code' => 'EQ-00012',
            'name' => 'Paver', 'status' => 'available',
        ]);

        MachineryRentalContract::create([
            'company_id' => $company->id, 'machinery_asset_id' => $asset->id, 'client_id' => $client->id,
            'contract_number' => 'RC-TEST-1', 'start_date' => now()->subDays(9)->toDateString(),
            'end_date' => now()->subDays(5)->toDateString(), 'rate' => 500, 'rate_type' => 'daily', 'status' => 'completed',
        ]);
        MachineryProjectDeployment::create([
            'company_id' => $company->id, 'machinery_asset_id' => $asset->id, 'project_id' => $project->id,
            'start_date' => now()->subDays(4)->toDateString(), 'end_date' => now()->toDateString(), 'status' => 'completed',
        ]);

        // 20 days owned (19 days ago + today), 5 rented days + 5 deployed
        // days = 10 active days out of 20 owned = 50%.
        $this->assertEqualsWithDelta(50.0, $asset->fresh()->load('rentalContracts', 'deployments')->utilizationPercent(), 0.5);

        $this->actingAs($owner)->get(route('app.machinery.assets.show', $asset))->assertOk()->assertSee('%', false);
    }

    // ------------------------------------------------------------------
    // Global search — machinery and letters were previously invisible to
    // the app-wide search, unlike every other record type.
    // ------------------------------------------------------------------

    public function test_global_search_finds_machinery_and_letters_only_when_the_module_is_enabled(): void
    {
        $withModule = $this->makeCompany(withModule: true);
        $ownerWith = $this->makeOwner($withModule);
        $asset = MachineryAsset::create(['company_id' => $withModule->id, 'asset_code' => 'EQ-SEARCH1', 'name' => 'Findable Paver', 'status' => 'available']);
        $letter = CompanyLetter::create([
            'company_id' => $withModule->id, 'document_type' => 'custom', 'reference_number' => 'LTR-SEARCH1',
            'title' => 'Findable Letter', 'letter_date' => now()->toDateString(), 'party_a_role' => 'Company',
            'party_b_role' => 'Client', 'party_b_name' => 'Someone', 'language_mode' => 'bilingual',
            'content' => [['text_en' => 'Body.', 'text_ar' => 'نص.', 'is_heading' => false]],
        ]);

        $response = $this->actingAs($ownerWith)->get(route('app.search', ['q' => 'Findable']));
        $response->assertOk();
        $labels = collect($response->json('groups'))->pluck('label');
        $this->assertTrue($labels->contains(__('Machinery & Equipment')));
        $this->assertTrue($labels->contains(__('Letters & Agreements')));

        $withoutModule = $this->makeCompany(withModule: false);
        $ownerWithout = $this->makeOwner($withoutModule);
        MachineryAsset::create(['company_id' => $withoutModule->id, 'asset_code' => 'EQ-SEARCH2', 'name' => 'Findable Grader', 'status' => 'available']);

        $response = $this->actingAs($ownerWithout)->get(route('app.search', ['q' => 'Findable']));
        $response->assertOk();
        $labels = collect($response->json('groups'))->pluck('label');
        $this->assertFalse($labels->contains(__('Machinery & Equipment')));
    }
}
