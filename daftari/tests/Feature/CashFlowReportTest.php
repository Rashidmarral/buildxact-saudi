<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Company;
use App\Models\FixedAsset;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\User;
use App\Services\Reports\FinancialReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Commercial audit finding: the "Cash Flow" report was a cash/bank ledger
 * roll-forward (what moved through the cash accounts) rather than a real
 * classified Statement of Cash Flows (why it moved — operating vs
 * investing vs financing), which is what a bank, auditor, or a management
 * team sizing up burn rate actually expects.
 *
 * Builds a scenario by hand (raw JournalEntry/JournalEntryLine rows,
 * exactly like a real posting would leave behind) with one transaction of
 * each kind — cash and credit sales, cash and credit expenses,
 * depreciation, a cash-funded fixed asset purchase, a cash-funded
 * disposal, and an owner equity injection — worked the arithmetic by hand
 * against the actual resulting cash+bank balance change, and asserts the
 * service's classification lands on those exact numbers so a future
 * regression in the operating/investing/financing split fails loudly
 * instead of quietly misclassifying cash movements again.
 */
class CashFlowReportTest extends TestCase
{
    use RefreshDatabase;

    private function postEntry(Company $company, string $date, array $lines): void
    {
        $entry = JournalEntry::create([
            'company_id' => $company->id, 'entry_number' => 'JE-'.uniqid(), 'entry_date' => $date,
            'source_type' => 'manual', 'description' => 'test',
        ]);

        foreach ($lines as [$code, $debit, $credit]) {
            $account = Account::where('company_id', $company->id)->where('code', $code)->firstOrFail();
            JournalEntryLine::create(['journal_entry_id' => $entry->id, 'account_id' => $account->id, 'debit' => $debit, 'credit' => $credit]);
        }
    }

    public function test_the_indirect_method_classifies_every_kind_of_movement_and_reconciles_to_actual_cash(): void
    {
        $company = Company::create(['name' => 'Cash Flow Co.', 'slug' => 'cashflow-'.uniqid()]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        // Cash sale: +500 net income, no working-capital effect.
        $this->postEntry($company, '2026-01-05', [['1000', 500, 0], ['4000', 0, 500]]);
        // Credit sale: +300 net income, AR +300 (ties up 300 of cash).
        $this->postEntry($company, '2026-01-06', [['1200', 300, 0], ['4000', 0, 300]]);
        // Cash expense: -100 net income, no working-capital effect.
        $this->postEntry($company, '2026-01-07', [['5100', 100, 0], ['1000', 0, 100]]);
        // Credit expense: -50 net income, AP +50 (delays 50 of cash out).
        $this->postEntry($company, '2026-01-08', [['5100', 50, 0], ['2000', 0, 50]]);
        // Depreciation: -20 net income, added back (non-cash).
        $this->postEntry($company, '2026-01-09', [['5150', 20, 0], ['1550', 0, 20]]);
        // Fixed asset bought for cash.
        $this->postEntry($company, '2026-01-10', [['1500', 1000, 0], ['1000', 0, 1000]]);
        FixedAsset::create([
            'company_id' => $company->id, 'account_id' => Account::where('company_id', $company->id)->where('code', '1500')->value('id'),
            'name' => 'Delivery Van', 'category' => 'vehicle', 'acquisition_date' => '2026-01-10', 'acquisition_cost' => 1000,
            'salvage_value' => 0, 'useful_life_years' => 5, 'depreciation_method' => 'straight_line', 'status' => 'active',
        ]);
        // A different, older fixed asset disposed of this period for cash.
        $olderAsset = FixedAsset::create([
            'company_id' => $company->id, 'account_id' => Account::where('company_id', $company->id)->where('code', '1500')->value('id'),
            'name' => 'Old Printer', 'category' => 'equipment', 'acquisition_date' => '2025-01-01', 'acquisition_cost' => 200,
            'salvage_value' => 0, 'useful_life_years' => 3, 'depreciation_method' => 'straight_line', 'status' => 'active',
        ]);
        $this->postEntry($company, '2026-01-11', [['1000', 200, 0], ['1500', 0, 200]]);
        $olderAsset->update(['disposed_at' => '2026-01-11', 'disposal_proceeds' => 200, 'status' => 'disposed']);
        // Owner injects capital into the bank account.
        $this->postEntry($company, '2026-01-15', [['1100', 2000, 0], ['3000', 0, 2000]]);

        $data = app(FinancialReportService::class)->cashFlow($company, \Carbon\Carbon::parse('2026-01-01'), \Carbon\Carbon::parse('2026-01-31'));

        $this->assertSame(630.0, $data['netIncome']); // 500 + 300 - 100 - 50 - 20
        $this->assertSame(20.0, $data['depreciation']);
        $this->assertSame(-250.0, $data['workingCapitalTotal']); // AR -300, AP +50
        $this->assertSame(400.0, $data['operatingTotal']); // 630 + 20 - 250

        $this->assertSame(1000.0, $data['acquisitions']);
        $this->assertSame(200.0, $data['disposalProceeds']);
        $this->assertSame(-800.0, $data['investingTotal']); // 200 - 1000

        $this->assertSame(2000.0, $data['financingTotal']);

        $this->assertSame(1600.0, $data['netChange']); // 400 - 800 + 2000
        $this->assertSame(0.0, $data['openingCash']);
        $this->assertSame(1600.0, $data['closingCash']);
        $this->assertSame(1600.0, $data['actualClosingCash']);
        $this->assertTrue($data['reconciled']);

        // The page itself renders these figures, and CSV export works.
        $this->actingAs($owner)->get(route('app.reports.cash-flow', ['period' => 'custom', 'from' => '2026-01-01', 'to' => '2026-01-31']))
            ->assertOk()
            ->assertSee(__('Reconciled to cash & bank'))
            ->assertSee(number_format(400.0, 2))
            ->assertSee(number_format(-800.0, 2))
            ->assertSee(number_format(2000.0, 2));

        $this->actingAs($owner)->get(route('app.reports.cash-flow', ['period' => 'custom', 'from' => '2026-01-01', 'to' => '2026-01-31', 'export' => 'csv']))
            ->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_a_company_with_no_activity_reports_all_zeros_and_reconciles(): void
    {
        $company = Company::create(['name' => 'Quiet Co.', 'slug' => 'quiet-'.uniqid()]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        $data = app(FinancialReportService::class)->cashFlow($company, \Carbon\Carbon::parse('2026-01-01'), \Carbon\Carbon::parse('2026-01-31'));

        $this->assertSame(0.0, $data['netChange']);
        $this->assertTrue($data['reconciled']);
    }

    public function test_the_cash_flow_page_renders_in_arabic(): void
    {
        $company = Company::create(['name' => 'Arabic Cash Flow Co.', 'slug' => 'ar-cashflow-'.uniqid()]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $this->actingAs($owner)->get(route('locale.switch', 'ar'));

        $this->actingAs($owner)->get(route('app.reports.cash-flow'))
            ->assertOk()->assertSee(__('Operating activities'))->assertSee(__('Investing activities'))->assertSee(__('Financing activities'));
    }
}
