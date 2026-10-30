<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Services\Accounting\LedgerPostingService;
use Database\Seeders\Inv00003LedgerCorrectionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "how much net profit do i have" — while answering that, found
 * INV-00003's ledger entry was stale: posted at its pre-edit amount
 * (SAR 157,510.61) while the real invoice now reads SAR 230,000.00.
 * This fixes the gap, not just the date.
 */
class Inv00003LedgerCorrectionSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedStaleInvoice(): array
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting Company', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR', 'vat_number' => '314526094900003']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        $client = Client::create(['company_id' => $company->id, 'name' => 'Sada Al Jeul Construction Establishment', 'type' => 'company']);

        // Posted first at the pre-edit (30% milestone) amount...
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-00003',
            'type' => 'standard', 'status' => 'paid', 'issue_date' => '2026-10-13', 'due_date' => '2026-10-13',
            'subtotal' => 136965.75, 'vat_total' => 20544.86, 'total' => 157510.61, 'amount_paid' => 157510.61,
            'currency' => 'SAR', 'exchange_rate' => 1,
        ]);
        app(LedgerPostingService::class)->postInvoiceIssued($invoice->fresh());

        // ...then edited to its real, final quantities/amount, WITHOUT reposting.
        $invoice->update([
            'issue_date' => '2026-10-21', 'due_date' => '2026-10-21',
            'subtotal' => 200000.00, 'vat_total' => 30000.00, 'total' => 230000.00, 'amount_paid' => 230000.00,
        ]);

        return [$company, $invoice];
    }

    public function test_it_errors_cleanly_when_the_invoice_does_not_exist(): void
    {
        Company::create(['name' => 'Dynamic Core Contracting Company', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR', 'vat_number' => '314526094900003']);

        (new Inv00003LedgerCorrectionSeeder)->run();

        $this->assertSame(0, JournalEntry::where('source_type', 'invoice')->count());
    }

    public function test_it_reposts_the_stale_entry_at_the_invoices_real_current_amount(): void
    {
        [$company, $invoice] = $this->seedStaleInvoice();

        (new Inv00003LedgerCorrectionSeeder)->run();

        $this->assertSame(1, JournalEntry::where('company_id', $company->id)->where('source_type', 'invoice')->where('source_id', $invoice->id)->count());
        $entry = JournalEntry::where('company_id', $company->id)->where('source_type', 'invoice')->where('source_id', $invoice->id)->first();

        $this->assertSame('2026-10-21', $entry->entry_date->format('Y-m-d'));

        $revenue = Account::where('company_id', $company->id)->where('code', '4000')->first();
        $vatOutput = Account::where('company_id', $company->id)->where('code', '2100')->first();
        $ar = Account::where('company_id', $company->id)->where('code', '1200')->first();

        $this->assertEqualsWithDelta(200000.00, (float) JournalEntryLine::where('journal_entry_id', $entry->id)->where('account_id', $revenue->id)->sum('credit'), 0.01);
        $this->assertEqualsWithDelta(30000.00, (float) JournalEntryLine::where('journal_entry_id', $entry->id)->where('account_id', $vatOutput->id)->sum('credit'), 0.01);
        $this->assertEqualsWithDelta(230000.00, (float) JournalEntryLine::where('journal_entry_id', $entry->id)->where('account_id', $ar->id)->sum('debit'), 0.01);
    }

    public function test_reseeding_does_not_duplicate_or_change_it_again(): void
    {
        [$company, $invoice] = $this->seedStaleInvoice();

        (new Inv00003LedgerCorrectionSeeder)->run();
        (new Inv00003LedgerCorrectionSeeder)->run();

        $this->assertSame(1, JournalEntry::where('company_id', $company->id)->where('source_type', 'invoice')->where('source_id', $invoice->id)->count());
    }
}
