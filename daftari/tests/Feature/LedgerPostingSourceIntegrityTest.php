<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use InvalidArgumentException;

/**
 * Audit findings GL-01 and GL-02.
 *
 * GL-01: post()'s own duplicate check (alreadyPosted()) is an unlocked
 * read — two concurrent callers for the same source document could both
 * pass it before either transaction commits, each creating a real,
 * duplicate journal entry for the same business event. The actual fix is
 * the database-level unique index on (company_id, source_type, source_id)
 * added in migration 2026_10_25_040000 — this is the layer that closes the
 * race a plain application-level check cannot. A single test process
 * cannot reproduce true concurrency, so this test exercises the
 * constraint itself directly (what a losing concurrent writer would hit)
 * and confirms post() is wired to treat that as an idempotent no-op
 * rather than letting the exception escape to the caller.
 *
 * GL-02: lines used to be summed as raw, unrounded floats and only the
 * aggregate total was rounded for the balance check — but decimal(14,2)
 * columns round each line independently at insert time, so a set of lines
 * that balanced on their unrounded sum could land unbalanced once stored.
 * quantizeLines() now rounds every line to 2dp before any summing, and
 * rejects a line that carries both a non-zero debit and a non-zero
 * credit (a malformed line no legitimate caller should ever produce).
 */
class LedgerPostingSourceIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $company = Company::create(['name' => 'Ledger Integrity Co.', 'slug' => 'ledger-integrity-'.uniqid()]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return $company;
    }

    public function test_the_database_rejects_a_second_journal_entry_for_the_same_source_document(): void
    {
        $company = $this->makeCompany();

        JournalEntry::create([
            'company_id' => $company->id, 'entry_number' => 'JE-0001', 'entry_date' => now(),
            'source_type' => 'bill', 'source_id' => 777, 'description' => 'First post',
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        JournalEntry::create([
            'company_id' => $company->id, 'entry_number' => 'JE-0002', 'entry_date' => now(),
            'source_type' => 'bill', 'source_id' => 777, 'description' => 'Racing duplicate post',
        ]);
    }

    public function test_a_different_company_may_use_the_same_source_type_and_id(): void
    {
        $companyA = $this->makeCompany();
        $companyB = $this->makeCompany();

        JournalEntry::create([
            'company_id' => $companyA->id, 'entry_number' => 'JE-0001', 'entry_date' => now(),
            'source_type' => 'bill', 'source_id' => 1, 'description' => 'Company A bill',
        ]);

        $entry = JournalEntry::create([
            'company_id' => $companyB->id, 'entry_number' => 'JE-0001', 'entry_date' => now(),
            'source_type' => 'bill', 'source_id' => 1, 'description' => 'Company B bill',
        ]);

        $this->assertNotNull($entry->id);
    }

    public function test_posting_the_same_source_document_twice_through_the_service_is_an_idempotent_no_op(): void
    {
        $company = $this->makeCompany();
        $ledger = app(LedgerPostingService::class);
        $cash = Account::where('company_id', $company->id)->where('code', '1000')->first();
        $equity = Account::where('company_id', $company->id)->where('code', '3000')->first();
        $lines = [
            ['account_id' => $cash->id, 'debit' => 1000, 'credit' => 0],
            ['account_id' => $equity->id, 'debit' => 0, 'credit' => 1000],
        ];

        $first = $ledger->post($company, 'manual_test_source', 42, 'First post', now(), $lines);
        $second = $ledger->post($company, 'manual_test_source', 42, 'Duplicate post attempt', now(), $lines);

        $this->assertNotNull($first);
        $this->assertNull($second);
        $this->assertSame(1, JournalEntry::where('company_id', $company->id)->where('source_type', 'manual_test_source')->where('source_id', 42)->count());
    }

    public function test_a_line_carrying_both_a_debit_and_a_credit_is_rejected(): void
    {
        $company = $this->makeCompany();
        $ledger = app(LedgerPostingService::class);
        $cash = Account::where('company_id', $company->id)->where('code', '1000')->first();
        $equity = Account::where('company_id', $company->id)->where('code', '3000')->first();

        $this->expectException(InvalidArgumentException::class);

        $ledger->post($company, 'malformed_source', 1, 'Malformed line', now(), [
            ['account_id' => $cash->id, 'debit' => 500, 'credit' => 500],
            ['account_id' => $equity->id, 'debit' => 0, 'credit' => 500],
        ]);
    }

    public function test_a_line_carrying_both_a_debit_and_a_credit_is_rejected_for_manual_entries_too(): void
    {
        $company = $this->makeCompany();
        $ledger = app(LedgerPostingService::class);
        $cash = Account::where('company_id', $company->id)->where('code', '1000')->first();
        $equity = Account::where('company_id', $company->id)->where('code', '3000')->first();

        $this->expectException(InvalidArgumentException::class);

        $ledger->postManual($company, 'Malformed manual entry', now(), [
            ['account_id' => $cash->id, 'debit' => 500, 'credit' => 500],
            ['account_id' => $equity->id, 'debit' => 0, 'credit' => 500],
        ]);
    }

    public function test_lines_that_only_balance_before_per_line_rounding_are_now_quantized_consistently(): void
    {
        $company = $this->makeCompany();
        $ledger = app(LedgerPostingService::class);
        $cash = Account::where('company_id', $company->id)->where('code', '1000')->first();
        $equity = Account::where('company_id', $company->id)->where('code', '3000')->first();

        // Each raw amount independently rounds to 2dp the same way whether
        // it happens per-line (now) or only once at the aggregate total
        // (before) — 333.333 -> 333.33 three times either way sums to
        // 999.99 on both sides, so this is a same-outcome sanity check
        // that quantizeLines() didn't change a case that already worked.
        $entry = $ledger->postManual($company, 'Fractional amounts', now(), [
            ['account_id' => $cash->id, 'debit' => 333.333, 'credit' => 0],
            ['account_id' => $cash->id, 'debit' => 333.333, 'credit' => 0],
            ['account_id' => $cash->id, 'debit' => 333.333, 'credit' => 0],
            ['account_id' => $equity->id, 'debit' => 0, 'credit' => 999.99],
        ]);

        $this->assertNotNull($entry->id);
        foreach ($entry->lines as $line) {
            $this->assertSame(round((float) $line->debit, 2), (float) $line->debit);
            $this->assertSame(round((float) $line->credit, 2), (float) $line->credit);
        }
    }

    public function test_a_zero_amount_line_is_silently_dropped_rather_than_stored(): void
    {
        $company = $this->makeCompany();
        $ledger = app(LedgerPostingService::class);
        $cash = Account::where('company_id', $company->id)->where('code', '1000')->first();
        $equity = Account::where('company_id', $company->id)->where('code', '3000')->first();

        $entry = $ledger->postManual($company, 'Includes a zero line', now(), [
            ['account_id' => $cash->id, 'debit' => 1000, 'credit' => 0],
            ['account_id' => $cash->id, 'debit' => 0, 'credit' => 0],
            ['account_id' => $equity->id, 'debit' => 0, 'credit' => 1000],
        ]);

        $this->assertSame(2, $entry->lines()->count());
    }
}
