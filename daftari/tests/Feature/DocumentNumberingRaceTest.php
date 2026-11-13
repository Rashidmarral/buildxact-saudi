<?php

namespace Tests\Feature;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Commercial audit finding A8: next*Number() methods used to compute the
 * displayed document number from the counter column already loaded on the
 * PHP model, then separately increment() it in the DB. Two requests that
 * both loaded the Company row before either committed (the normal case
 * under real concurrency — each request builds its own Company instance
 * from Auth::user()->company) would both read the same stale counter and
 * hand out the same document number.
 *
 * These tests reproduce that exact shape — two independent Company model
 * instances for the same row, both "loaded" before either mutates it — and
 * assert the fix (a fresh lockForUpdate() read inside the numbering
 * transaction) makes the second call see the first call's increment
 * instead of repeating it.
 */
class DocumentNumberingRaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_stale_company_instances_never_hand_out_the_same_invoice_number(): void
    {
        $company = Company::create(['name' => 'Race Co.', 'slug' => 'race-'.uniqid()]);

        // Two independent instances of the same row, as two concurrent
        // requests would each have — both still holding next_invoice_number
        // as it was at load time.
        $instanceA = Company::find($company->id);
        $instanceB = Company::find($company->id);

        $numberA = $instanceA->nextInvoiceNumber();
        $numberB = $instanceB->nextInvoiceNumber();

        $this->assertNotSame($numberA, $numberB);
        $this->assertSame('INV-00001', $numberA);
        $this->assertSame('INV-00002', $numberB);
        $this->assertSame(3, $company->fresh()->next_invoice_number);
    }

    public function test_two_stale_company_instances_never_hand_out_the_same_journal_number(): void
    {
        $company = Company::create(['name' => 'Race Co 2.', 'slug' => 'race2-'.uniqid()]);

        $instanceA = Company::find($company->id);
        $instanceB = Company::find($company->id);

        $numberA = $instanceA->nextJournalNumber();
        $numberB = $instanceB->nextJournalNumber();

        $this->assertNotSame($numberA, $numberB);
        $this->assertSame(3, $company->fresh()->next_journal_number);
    }
}
