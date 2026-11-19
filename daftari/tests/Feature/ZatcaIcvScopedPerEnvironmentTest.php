<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\ZatcaEnvironmentCredential;
use App\Services\Zatca\ZatcaSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * ZatcaSyncService::nextIcv() used to pool the mandatory Invoice Counter
 * Value across every ZATCA environment (developer/simulation/
 * production) by counting zatca_invoice_logs/zatca_credit_note_logs/
 * zatca_debit_note_logs rows with no `environment` filter, even though
 * the PIH hash chain (zatca_last_invoice_hash) is already correctly
 * scoped per environment via Company::switchZatcaEnvironment(). ICV now
 * lives as its own persisted, per-environment counter
 * (companies.zatca_icv_counter, stashed/restored on environment switch
 * exactly like the hash chain) that only advances on a CONFIRMED
 * success — see ZatcaOnboardingResetClearsHashChainTest for the other
 * half of this fix (a reset now zeroes both together).
 */
class ZatcaIcvScopedPerEnvironmentTest extends TestCase
{
    use RefreshDatabase;

    private const SIM_CSID = 'SIM-CSID-PLACEHOLDER-BASE64-CERTIFICATE-BYTES';

    private const PROD_CSID = 'PROD-CSID-PLACEHOLDER-BASE64-CERTIFICATE-BYTES-DIFFER-FROM-SIM';

    private function makeOnboardedOwner(): array
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(),
            'price_monthly' => 100, 'price_yearly' => 1000, 'is_active' => true,
            'has_zatca_phase2' => true,
        ]);

        $company = Company::create([
            'name' => 'Env Scoped ICV Co.', 'slug' => 'env-icv-'.uniqid(),
            'zatca_integration_mode' => Company::ZATCA_MODE_PHASE2,
            'zatca_environment' => 'simulation',
            'zatca_sync_b2b' => true, 'zatca_sync_b2c' => true,
            'zatca_onboarding_status' => 'onboarded',
            'zatca_csr' => 'SIM-CSR', 'zatca_private_key' => 'sim-key',
            'zatca_production_csid' => self::SIM_CSID, 'zatca_production_secret' => 'sim-secret',
        ]);

        Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        return [$company, $owner];
    }

    private function makeInvoice(Company $company): Invoice
    {
        $client = Client::create(['company_id' => $company->id, 'name' => 'Test Client', 'vat_number' => '300012345600003']);

        return Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-'.uniqid(),
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(), 'currency' => $company->currency,
            'subtotal' => 100, 'discount_total' => 0, 'vat_total' => 15, 'total' => 115,
        ]);
    }

    public function test_switching_environments_gives_the_new_one_its_own_icv_starting_at_one(): void
    {
        Http::fake(['*' => Http::response(['clearedInvoice' => 'stamp'], 200)]);
        [$company, $owner] = $this->makeOnboardedOwner();
        $sync = app(ZatcaSyncService::class);

        // Two real submissions in Simulation.
        $sync->submit($this->makeInvoice($company));
        $sync->submit($this->makeInvoice($company));
        $this->assertSame(3, $sync->nextIcv($company->fresh()));

        // Switch to Production — a brand-new CSID/identity, onboarded
        // separately — via the real settings update, the same way a
        // company actually would.
        $this->actingAs($owner)->put(route('app.zatca.settings.update'), [
            'zatca_environment' => 'production',
            'zatca_sync_frequency' => 'manual',
            'zatca_sync_b2b' => '1', 'zatca_sync_b2c' => '1',
        ]);
        $company = $company->fresh();
        $company->update(['zatca_onboarding_status' => 'onboarded', 'zatca_production_csid' => self::PROD_CSID, 'zatca_production_secret' => 'prod-secret']);

        // Production has never submitted anything — its ICV starts at 1,
        // not 3 (the old pooled behavior would have continued from
        // Simulation's count here).
        $this->assertSame(1, $sync->nextIcv($company->fresh()));

        $sync->submit($this->makeInvoice($company));
        $this->assertSame(2, $sync->nextIcv($company->fresh()));
    }

    public function test_switching_back_to_an_environment_resumes_its_own_icv_not_the_other_ones(): void
    {
        Http::fake(['*' => Http::response(['clearedInvoice' => 'stamp'], 200)]);
        [$company, $owner] = $this->makeOnboardedOwner();
        $sync = app(ZatcaSyncService::class);

        $sync->submit($this->makeInvoice($company));
        $sync->submit($this->makeInvoice($company));
        // Simulation's next ICV is 3.

        $this->actingAs($owner)->put(route('app.zatca.settings.update'), [
            'zatca_environment' => 'production',
            'zatca_sync_frequency' => 'manual',
            'zatca_sync_b2b' => '1', 'zatca_sync_b2c' => '1',
        ]);
        $company = $company->fresh();
        $company->update(['zatca_onboarding_status' => 'onboarded', 'zatca_production_csid' => self::PROD_CSID, 'zatca_production_secret' => 'prod-secret']);
        $sync->submit($this->makeInvoice($company));
        // Production's next ICV is now 2.

        $this->actingAs($owner)->put(route('app.zatca.settings.update'), [
            'zatca_environment' => 'simulation',
            'zatca_sync_frequency' => 'manual',
            'zatca_sync_b2b' => '1', 'zatca_sync_b2c' => '1',
        ]);

        $this->assertSame(3, $sync->nextIcv($company->fresh()), 'Simulation must resume at its own next ICV (3), unaffected by the single submission made under Production.');

        $slot = ZatcaEnvironmentCredential::where('company_id', $company->id)->where('environment', 'production')->first();
        $this->assertSame(1, $slot->icv_counter, 'Production\'s own counter (1, after its one submission) must stay stashed, not merged into Simulation\'s.');
    }

    public function test_resetting_onboarding_zeroes_the_icv_for_the_active_environment_only(): void
    {
        Http::fake(['*' => Http::response(['clearedInvoice' => 'stamp'], 200)]);
        [$company, $owner] = $this->makeOnboardedOwner();
        $sync = app(ZatcaSyncService::class);

        $sync->submit($this->makeInvoice($company));
        $sync->submit($this->makeInvoice($company));
        $this->assertSame(3, $sync->nextIcv($company->fresh()));

        $this->actingAs($owner)->post(route('app.zatca.reset'));

        $this->assertSame(1, $sync->nextIcv($company->fresh()), 'A reset onboarding (fresh CSR/CSID) must start the next invoice at ICV 1, matching the genesis hash it now also resets to.');
    }

    /**
     * Exercises the backfill migration directly against hand-built
     * historical data: two log rows for the same company under two
     * different environments, each with a real ICV value embedded in
     * its stored xml_payload (as ZatcaXmlGenerator::icvReference()
     * actually writes it) — the shape every pre-existing company's real
     * ZATCA submission history is in. Confirms the migration recovers
     * the correct per-environment starting point for both the
     * currently-active environment (written straight onto the company
     * row) and a different, previously-visited one (written into its
     * zatca_environment_credentials save slot).
     */
    public function test_the_backfill_migration_recovers_the_real_per_environment_icv_from_stored_xml(): void
    {
        [$company] = $this->makeOnboardedOwner();
        // A save slot for 'production' already exists, the way it would
        // for a company that visited it before switching back.
        ZatcaEnvironmentCredential::create(['company_id' => $company->id, 'environment' => 'production']);

        $invoice = $this->makeInvoice($company);

        $xmlFor = fn (int $icv) => '<Invoice><cbc:ID>IGNORED</cbc:ID><cac:AdditionalDocumentReference><cbc:ID>ICV</cbc:ID><cbc:UUID>'.$icv.'</cbc:UUID></cac:AdditionalDocumentReference></Invoice>';

        // Simulation (the company's current environment): three
        // submissions, highest ICV actually used was 3.
        foreach ([1, 2, 3] as $icv) {
            DB::table('zatca_invoice_logs')->insert([
                'company_id' => $company->id, 'invoice_id' => $invoice->id, 'environment' => 'simulation',
                'invoice_type' => 'b2b', 'direction' => 'clearance', 'status' => 'cleared',
                'request_uuid' => (string) \Illuminate\Support\Str::uuid(), 'xml_payload' => $xmlFor($icv),
                'submitted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        // Production, visited earlier and switched away from: one
        // submission at ICV 1.
        DB::table('zatca_invoice_logs')->insert([
            'company_id' => $company->id, 'invoice_id' => $invoice->id, 'environment' => 'production',
            'invoice_type' => 'b2b', 'direction' => 'clearance', 'status' => 'cleared',
            'request_uuid' => (string) \Illuminate\Support\Str::uuid(), 'xml_payload' => $xmlFor(1),
            'submitted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $migrationFile = glob(database_path('migrations/*_backfill_icv_counter_per_environment.php'))[0];
        (require $migrationFile)->up();

        $this->assertSame(3, $company->fresh()->zatca_icv_counter);
        $prodSlot = ZatcaEnvironmentCredential::where('company_id', $company->id)->where('environment', 'production')->first();
        $this->assertSame(1, $prodSlot->icv_counter);
    }
}
