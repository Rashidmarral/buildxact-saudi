<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Client;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\ZatcaInvoiceLog;
use App\Services\Accounting\LedgerIntegrityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * LedgerIntegrityService is the super-admin "Ledger cleanup" tool: it
 * finds journal entries whose source document was hard-deleted outside
 * the app's own void/cancel flow (always a manual/ad-hoc action, since
 * LedgerPostingService itself only ever reverses, never deletes — see
 * that class's own docblock), and lets an admin purge exactly those
 * entries. It must never flag — or touch — a journal entry whose source
 * document still exists, and must refuse outright once the company has
 * any ZATCA activity on file, however small.
 */
class LedgerIntegrityServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(array $overrides = []): Company
    {
        return Company::create(array_merge([
            'name' => 'Test Co', 'slug' => 'test-co-'.uniqid(), 'status' => 'active',
        ], $overrides));
    }

    private function makeAccount(Company $company, string $code = '1100'): Account
    {
        return Account::create([
            'company_id' => $company->id, 'code' => $code, 'name' => 'Accounts Receivable',
            'type' => 'asset', 'normal_balance' => 'debit',
        ]);
    }

    /**
     * Writes a journal entry with one debit and one credit line directly
     * (bypassing LedgerPostingService, which isn't the thing under test
     * here) so source_type/source_id can be set freely.
     */
    private function makeEntry(Company $company, Account $account, string $sourceType, int $sourceId, float $amount, string $description = 'Test entry'): JournalEntry
    {
        $entry = JournalEntry::create([
            'company_id' => $company->id, 'entry_number' => 'JE-'.uniqid(), 'entry_date' => now()->toDateString(),
            'source_type' => $sourceType, 'source_id' => $sourceId, 'description' => $description,
        ]);

        $entry->lines()->create(['account_id' => $account->id, 'debit' => $amount, 'credit' => 0]);
        $entry->lines()->create(['account_id' => $account->id, 'debit' => 0, 'credit' => $amount]);

        return $entry;
    }

    private function makeInvoice(Company $company): Invoice
    {
        $client = Client::create(['company_id' => $company->id, 'name' => 'Test Client']);

        return Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-'.uniqid(),
            'type' => 'standard', 'status' => 'draft', 'issue_date' => now()->toDateString(), 'currency' => 'SAR',
        ]);
    }

    // ---------------------------------------------------------------
    // scanOrphans()
    // ---------------------------------------------------------------

    public function test_scan_ignores_entries_whose_source_document_still_exists(): void
    {
        $company = $this->makeCompany();
        $account = $this->makeAccount($company);
        $invoice = $this->makeInvoice($company);

        $this->makeEntry($company, $account, 'invoice', $invoice->id, 115);

        $result = app(LedgerIntegrityService::class)->scanOrphans($company);

        $this->assertCount(0, $result);
    }

    public function test_scan_ignores_a_soft_deleted_document_it_is_not_an_orphan(): void
    {
        $company = $this->makeCompany();
        $account = $this->makeAccount($company);
        $invoice = $this->makeInvoice($company);

        $this->makeEntry($company, $account, 'invoice', $invoice->id, 115);

        $invoice->delete(); // Invoice uses SoftDeletes — still "exists" for this purpose.

        $result = app(LedgerIntegrityService::class)->scanOrphans($company);

        $this->assertCount(0, $result);
    }

    public function test_scan_finds_an_entry_whose_soft_deletable_document_was_force_deleted(): void
    {
        $company = $this->makeCompany();
        $account = $this->makeAccount($company);
        $invoice = $this->makeInvoice($company);

        $this->makeEntry($company, $account, 'invoice', $invoice->id, 115);

        $invoiceId = $invoice->id;
        $invoice->forceDelete();

        $result = app(LedgerIntegrityService::class)->scanOrphans($company);

        $this->assertCount(1, $result);
        $this->assertSame($invoiceId, $result->first()['source_id']);
        $this->assertSame(Invoice::class, $result->first()['model']);
    }

    /**
     * Regression test for the bug this service had before being fixed:
     * calling withTrashed() unconditionally on a model that doesn't use
     * SoftDeletes (CreditNote is one of 15 of the 22 mapped models that
     * don't) throws "call to undefined method" — scanOrphans() must
     * branch on whether the mapped model actually has the trait.
     */
    public function test_scan_finds_an_orphan_for_a_model_without_soft_deletes(): void
    {
        $company = $this->makeCompany();
        $account = $this->makeAccount($company);
        $invoice = $this->makeInvoice($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'CN Client']);
        $creditNote = CreditNote::create([
            'company_id' => $company->id, 'invoice_id' => $invoice->id, 'client_id' => $client->id,
            'credit_note_number' => 'CN-'.uniqid(), 'issue_date' => now()->toDateString(),
        ]);

        $this->makeEntry($company, $account, 'credit_note', $creditNote->id, 11.50);

        $creditNoteId = $creditNote->id;
        $creditNote->delete(); // CreditNote has no SoftDeletes — this is a hard delete.

        $result = app(LedgerIntegrityService::class)->scanOrphans($company);

        $this->assertCount(1, $result);
        $this->assertSame($creditNoteId, $result->first()['source_id']);
        $this->assertSame(CreditNote::class, $result->first()['model']);
    }

    public function test_scan_groups_an_original_and_its_reversal_into_one_group(): void
    {
        $company = $this->makeCompany();
        $account = $this->makeAccount($company);
        $invoice = $this->makeInvoice($company);

        $this->makeEntry($company, $account, 'invoice', $invoice->id, 115, 'Invoice INV-00001');
        $this->makeEntry($company, $account, 'invoice_reversal', $invoice->id, 115, 'Reversal — invoice INV-00001 removed');

        $invoice->forceDelete();

        $result = app(LedgerIntegrityService::class)->scanOrphans($company);

        $this->assertCount(1, $result);
        $group = $result->first();
        $this->assertCount(2, $group['entries']);
        // Each entry here carries its own debit+credit line of 115 (see
        // makeEntry()), so the group's gross total is both entries' lines
        // combined: 230 each side — what matters is debit == credit.
        $this->assertSame(230.0, $group['total_debit']);
        $this->assertSame(230.0, $group['total_credit']);
    }

    public function test_scan_ignores_unmapped_source_types(): void
    {
        $company = $this->makeCompany();
        $account = $this->makeAccount($company);

        $this->makeEntry($company, $account, 'manual', 999, 50);

        $result = app(LedgerIntegrityService::class)->scanOrphans($company);

        $this->assertCount(0, $result);
    }

    public function test_scan_is_scoped_to_the_given_company(): void
    {
        $companyA = $this->makeCompany();
        $companyB = $this->makeCompany();
        $accountA = $this->makeAccount($companyA);
        $invoiceB = $this->makeInvoice($companyB);

        // An entry under company A referencing an id that happens to
        // match a real, undeleted invoice belonging to company B must
        // never be treated as "exists" cross-tenant.
        $this->makeEntry($companyA, $accountA, 'invoice', $invoiceB->id, 50);

        $result = app(LedgerIntegrityService::class)->scanOrphans($companyA);

        $this->assertCount(1, $result);
    }

    // ---------------------------------------------------------------
    // purgeOrphans()
    // ---------------------------------------------------------------

    public function test_purge_removes_orphaned_entries_and_their_lines(): void
    {
        $company = $this->makeCompany();
        $account = $this->makeAccount($company);
        $invoice = $this->makeInvoice($company);

        $entry = $this->makeEntry($company, $account, 'invoice', $invoice->id, 115);
        $invoice->forceDelete();

        $result = app(LedgerIntegrityService::class)->purgeOrphans($company);

        $this->assertSame(1, $result['entries_removed']);
        $this->assertSame(115.0, $result['gross_amount']);
        $this->assertDatabaseMissing('journal_entries', ['id' => $entry->id]);
        $this->assertDatabaseCount('journal_entry_lines', 0);
    }

    public function test_purge_never_touches_a_still_existing_documents_postings(): void
    {
        $company = $this->makeCompany();
        $account = $this->makeAccount($company);
        $liveInvoice = $this->makeInvoice($company);
        $deadInvoice = $this->makeInvoice($company);

        $liveEntry = $this->makeEntry($company, $account, 'invoice', $liveInvoice->id, 200);
        $this->makeEntry($company, $account, 'invoice', $deadInvoice->id, 50);
        $deadInvoice->forceDelete();

        $result = app(LedgerIntegrityService::class)->purgeOrphans($company);

        $this->assertSame(1, $result['entries_removed']);
        $this->assertDatabaseHas('journal_entries', ['id' => $liveEntry->id]);
    }

    public function test_purge_throws_and_changes_nothing_once_the_company_has_zatca_activity(): void
    {
        $company = $this->makeCompany();
        $account = $this->makeAccount($company);
        $invoice = $this->makeInvoice($company);

        $entry = $this->makeEntry($company, $account, 'invoice', $invoice->id, 115);
        $invoice->forceDelete();

        $zatcaInvoice = $this->makeInvoice($company);
        ZatcaInvoiceLog::create([
            'company_id' => $company->id, 'invoice_id' => $zatcaInvoice->id, 'environment' => 'simulation',
            'invoice_type' => 'b2b', 'direction' => 'clearance', 'status' => 'cleared',
            'request_uuid' => (string) \Illuminate\Support\Str::uuid(),
        ]);

        $this->expectException(RuntimeException::class);

        try {
            app(LedgerIntegrityService::class)->purgeOrphans($company);
        } finally {
            $this->assertDatabaseHas('journal_entries', ['id' => $entry->id]);
        }
    }

    public function test_purge_is_a_no_op_when_there_is_nothing_orphaned(): void
    {
        $company = $this->makeCompany();

        $result = app(LedgerIntegrityService::class)->purgeOrphans($company);

        $this->assertSame(0, $result['entries_removed']);
        $this->assertSame(0.0, $result['gross_amount']);
    }
}
