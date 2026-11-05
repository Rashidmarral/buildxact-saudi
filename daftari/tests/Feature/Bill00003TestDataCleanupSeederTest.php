<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Supplier;
use App\Services\Accounting\LedgerPostingService;
use Database\Seeders\Bill00003TestDataCleanupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "we have not issue the final bill this was in testing phase...we are
 * adding the fake invoices at all in our system" — BILL-00003 (East
 * West Establishment, SAR 57,960.00) was confirmed test data, still
 * sitting 'posted' and unpaid in the real books. This voids it the
 * same way BillController::void() does, reversing its journal entry
 * rather than deleting the document.
 */
class Bill00003TestDataCleanupSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedPostedBill(): array
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting Company', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR', 'vat_number' => '314526094900003']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'East West Establishment', 'cr_number' => '7054146241']);
        $bill = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-00003',
            'status' => 'draft', 'bill_date' => '2026-09-22', 'currency' => 'SAR', 'exchange_rate' => 1,
        ]);
        $item = new BillItem(['bill_id' => $bill->id, 'description' => 'Supply of sub base course material with transportation', 'quantity' => 120, 'unit_price' => 420, 'vat_rate' => 15, 'sort_order' => 0]);
        $item->recalculate();
        $item->save();
        $bill->recalculateTotals();
        $bill->update(['status' => 'posted']);
        app(LedgerPostingService::class)->postBillPosted($bill->fresh('items.item'));

        return [$company, $bill->fresh()];
    }

    public function test_it_errors_cleanly_when_the_bill_does_not_exist(): void
    {
        Company::create(['name' => 'Dynamic Core Contracting Company', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR', 'vat_number' => '314526094900003']);

        (new Bill00003TestDataCleanupSeeder)->run();

        $this->assertSame(0, Bill::count());
    }

    public function test_it_voids_the_bill_and_reverses_its_journal_entry(): void
    {
        [$company, $bill] = $this->seedPostedBill();
        $this->assertEqualsWithDelta(57960.00, (float) $bill->total, 0.01);

        (new Bill00003TestDataCleanupSeeder)->run();

        $this->assertSame('void', $bill->fresh()->status);
        $this->assertSame(1, JournalEntry::where('company_id', $company->id)->where('source_type', 'bill_reversal')->where('source_id', $bill->id)->count());

        $opex = Account::where('company_id', $company->id)->where('code', '5100')->first();
        $vatInput = Account::where('company_id', $company->id)->where('code', '1300')->first();

        $netOpex = (float) JournalEntryLine::whereHas('journalEntry', fn ($q) => $q->where('company_id', $company->id)->whereIn('source_type', ['bill', 'bill_reversal']))
            ->where('account_id', $opex->id)
            ->selectRaw('COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) as net')->value('net');
        $netVatInput = (float) JournalEntryLine::whereHas('journalEntry', fn ($q) => $q->where('company_id', $company->id)->whereIn('source_type', ['bill', 'bill_reversal']))
            ->where('account_id', $vatInput->id)
            ->selectRaw('COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) as net')->value('net');

        $this->assertEqualsWithDelta(0.00, $netOpex, 0.01, 'the reversal must fully cancel the fake expense');
        $this->assertEqualsWithDelta(0.00, $netVatInput, 0.01, 'the reversal must fully cancel the fake input VAT');
    }

    public function test_reseeding_does_not_reverse_it_twice(): void
    {
        [$company, $bill] = $this->seedPostedBill();

        (new Bill00003TestDataCleanupSeeder)->run();
        (new Bill00003TestDataCleanupSeeder)->run();

        $this->assertSame(1, JournalEntry::where('company_id', $company->id)->where('source_type', 'bill_reversal')->where('source_id', $bill->id)->count());
    }
}
