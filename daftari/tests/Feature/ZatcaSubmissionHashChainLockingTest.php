<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\ZatcaInvoiceLog;
use App\Services\Zatca\ZatcaSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Security audit finding D-1: ZatcaSyncService::nextIcv() computed the
 * mandatory Invoice Counter Value via an unlocked COUNT(*), and submit()/
 * submitCreditNote()/submitDebitNote() each read Company::
 * zatca_last_invoice_hash, built/signed/submitted the XML, then wrote the
 * new hash back — none of it wrapped in a transaction or row lock.
 * Concurrent submissions for the same company (two queue workers picking
 * up two instant-sync jobs, or an admin-initiated retry racing a queued
 * job) could read the same previous-invoice-hash and compute the same
 * ICV, corrupting the mandatory hash chain.
 *
 * Fixed by ZatcaSyncService::lockedSubmission(), which wraps the entire
 * read-PIH/ICV -> submit -> write-PIH sequence in DB::transaction() with
 * Company::lockForUpdate(), mirroring Company::nextSequenceNumber()'s
 * existing fix for the identical class of race in document numbering.
 *
 * True concurrent-connection locking isn't observable from a single-
 * process PHPUnit run against SQLite (lockForUpdate() is a silent no-op
 * on that driver, and there's only one connection anyway) — these tests
 * instead verify the two properties that would break under the old,
 * unlocked code if a real race occurred: the hash chain must link
 * correctly from one submission to the next, and the ICV must increment
 * without collision across successive submissions for the same company.
 * The locking mechanism itself (DB::transaction + lockForUpdate on the
 * correct row) is verified directly against the source.
 */
class ZatcaSubmissionHashChainLockingTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_CSID = 'MIIDBzCCAe+gAwIBAgIUBSx0rLzK3YZPX+xqWHd5Snjpe5AwDQYJKoZIhvcNAQELBQAwEzERMA8GA1UEAwwIdGVzdC1lZ3MwHhcNMjYwODMwMjAzODAyWhcNMzYwODI3MjAzODAyWjATMREwDwYDVQQDDAh0ZXN0LWVnczCCASIwDQYJKoZIhvcNAQEBBQADggEPADCCAQoCggEBALq2jcY9XpSEhbetKvAAcMAP7Hjp5uJk7eo8luKn5Rgl9QqM/Bwgjuz6xKKASmn6QSaZOk44wdafGJvi/5MQ9fDVO1bCEUWDFVbMDblBxEjBe3N9FsQ33u4x1uZAUndQMaBukxH3+XxW7bGGCfkYJwJaDbSA6HAPF8kOzFNVjQKmyf3vOHa3uajxwMG4XKXqifFFhmn4jgCIhD5Nd6tLvY0dLMjD+MG7EVLPJCf0BGIMbyRJR6KWbz+lcCrO8hAC2UPX9jObTcz/kQQSDXWS8XnKjxyCr+BTVWZNYfIGOz3Y8YMM36IHBsHG/mIT3GXX6KKK4T9MmoGXcV87pV0dQusCAwEAAaNTMFEwHQYDVR0OBBYEFMVsaLNnewpMwUC33hHUv1RhoyBPMB8GA1UdIwQYMBaAFMVsaLNnewpMwUC33hHUv1RhoyBPMA8GA1UdEwEB/wQFMAMBAf8wDQYJKoZIhvcNAQELBQADggEBAKJPnEQOZlEPhevrJxthiJ2qZnemUKvvrdCJ1e5TqWG3+H2q+35dKjPE3QbPCnJtuw9iL54nkby8DiGrHRowJ5BcoxJbFernKLljBxCxRHOAp7M//nDXrYfWwrdDUqd4GE/T0buNrrCLSLEWdMxS1vEh4j/CV8h9wh9EVS7jgo99487iY/PxolzU5+Wjb+bxsgkrySpKhZt3En4A0jq3+3bP5bdFkj1fhmoAYzcIzUZj9ldcwrqesHGkF+SpGxRh6fll6pHZUnP+zqJH3Jqy1Ccer+M/MVmuqKQtwnMSb4yfRn8u9ffmzAAsBXnCSaceZYaYRPh/dubVhFrie20ODXY=';

    private function makeOnboardedCompany(): Company
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(),
            'price_monthly' => 100, 'price_yearly' => 1000, 'is_active' => true,
            'has_zatca_phase2' => true,
        ]);

        $company = Company::create([
            'name' => 'Hash Chain Co.', 'slug' => 'hash-chain-'.uniqid(),
            'zatca_integration_mode' => Company::ZATCA_MODE_PHASE2,
            'zatca_onboarding_status' => 'onboarded',
            'zatca_production_csid' => self::TEST_CSID,
            'zatca_production_secret' => 'secret-value',
        ]);

        Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth()]);

        return $company;
    }

    private function makeInvoice(Company $company): Invoice
    {
        $client = Client::create(['company_id' => $company->id, 'name' => 'Test Client']);

        return Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-'.uniqid(),
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(), 'currency' => $company->currency,
            'subtotal' => 100, 'discount_total' => 0, 'vat_total' => 15, 'total' => 115,
        ]);
    }

    public function test_the_source_wraps_every_submission_in_a_locked_transaction(): void
    {
        $source = file_get_contents(app_path('Services/Zatca/ZatcaSyncService.php'));

        $this->assertStringContainsString('lockForUpdate()', $source);
        $this->assertStringContainsString('DB::transaction(', $source);
        $this->assertMatchesRegularExpression('/lockedSubmission\(\$invoice->company_id/', $source);
        $this->assertMatchesRegularExpression('/lockedSubmission\(\$creditNote->company_id/', $source);
        $this->assertMatchesRegularExpression('/lockedSubmission\(\$debitNote->company_id/', $source);
    }

    public function test_two_sequential_submissions_for_the_same_company_produce_a_correctly_linked_hash_chain_with_no_icv_collision(): void
    {
        Http::fake(['*' => Http::response(['clearedInvoice' => 'stamp'], 200)]);
        $company = $this->makeOnboardedCompany();
        $sync = app(ZatcaSyncService::class);

        $first = $this->makeInvoice($company);
        $firstLog = $sync->submit($first);

        $second = $this->makeInvoice($company);
        $secondLog = $sync->submit($second);

        $this->assertSame('cleared', $firstLog->status);
        $this->assertSame('cleared', $secondLog->status);

        // The chain: the second submission's previous_invoice_hash must
        // equal the first submission's own invoice_hash — exactly what a
        // race would corrupt if two submissions both read the hash before
        // either wrote it back.
        $this->assertSame($firstLog->invoice_hash, $secondLog->previous_invoice_hash);
        $this->assertNotSame($firstLog->invoice_hash, $secondLog->invoice_hash);

        $this->assertSame($secondLog->invoice_hash, $company->fresh()->zatca_last_invoice_hash);
    }

    public function test_the_icv_counter_increments_without_collision_across_successive_submissions(): void
    {
        Http::fake(['*' => Http::response(['clearedInvoice' => 'stamp'], 200)]);
        $company = $this->makeOnboardedCompany();
        $sync = app(ZatcaSyncService::class);

        $this->assertSame(1, $sync->nextIcv($company));

        $sync->submit($this->makeInvoice($company));
        $this->assertSame(2, $sync->nextIcv($company->fresh()));

        $sync->submit($this->makeInvoice($company));
        $this->assertSame(3, $sync->nextIcv($company->fresh()));

        $sync->submit($this->makeInvoice($company));
        // nextIcv() is COUNT(logs)+1 — after 3 successful submissions the
        // next call must report 4, proving each prior submission's log
        // row was counted exactly once (no collision, no double-count).
        $this->assertSame(4, $sync->nextIcv($company->fresh()));
        $this->assertSame(3, ZatcaInvoiceLog::where('company_id', $company->id)->count());
    }

    public function test_a_failed_submission_does_not_advance_the_hash_chain_for_the_next_attempt(): void
    {
        Http::fake(['*' => Http::response(['error' => 'rejected'], 400)]);
        $company = $this->makeOnboardedCompany();
        $sync = app(ZatcaSyncService::class);

        $invoice = $this->makeInvoice($company);
        $log = $sync->submit($invoice);

        $this->assertSame('failed', $log->status);
        $this->assertNull($company->fresh()->zatca_last_invoice_hash, 'A failed submission must not advance the chain — the next attempt should still build against the genesis hash.');
    }

    public function test_submissions_for_two_different_companies_do_not_share_or_interfere_with_each_others_hash_chain(): void
    {
        Http::fake(['*' => Http::response(['clearedInvoice' => 'stamp'], 200)]);
        $companyA = $this->makeOnboardedCompany();
        $companyB = $this->makeOnboardedCompany();
        $sync = app(ZatcaSyncService::class);

        $logA = $sync->submit($this->makeInvoice($companyA));
        $logB = $sync->submit($this->makeInvoice($companyB));

        $this->assertSame(1, $sync->nextIcv($companyA->fresh()) - 1);
        $this->assertSame(1, $sync->nextIcv($companyB->fresh()) - 1);
        $this->assertNotSame($logA->invoice_hash, $logB->invoice_hash);
        $this->assertNotSame($companyA->fresh()->zatca_last_invoice_hash, $companyB->fresh()->zatca_last_invoice_hash);
    }
}
