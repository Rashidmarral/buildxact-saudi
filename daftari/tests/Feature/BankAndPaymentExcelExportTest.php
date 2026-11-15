<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\Company;
use App\Models\Item;
use App\Models\PaymentMode;
use App\Models\Plan;
use App\Models\PosRegister;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Excel export coverage for the bank/payment-side reports: Bank
 * Reconciliation Summary (Difference = Statement − Book, a per-row
 * formula), Payment Mode Statement (a running-total formula chain,
 * same convention as Account Statement), Uncleared Bank Items, Payment
 * Mode Balances, Bank/Cash Account Statement (reuses
 * AccountStatementExcelExporter), and Ledger Account Statement (reuses
 * GeneralLedgerExcelExporter — both already covered by their own
 * dedicated test suites, so only a smoke check is needed here).
 */
class BankAndPaymentExcelExportTest extends TestCase
{
    use RefreshDatabase;

    private function loadWorkbook(string $bytes): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $path = tempnam(sys_get_temp_dir(), 'bp-xlsx-test-').'.xlsx';
        file_put_contents($path, $bytes);
        $spreadsheet = IOFactory::load($path);
        unlink($path);

        return $spreadsheet;
    }

    public function test_bank_reconciliation_summarys_difference_column_is_a_formula_that_computes_correctly(): void
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $bankGlAccount = Account::where('company_id', $company->id)->where('code', '1100')->first();
        $bankAccount = BankAccount::create([
            'company_id' => $company->id, 'name' => 'Main Bank', 'type' => 'bank', 'currency' => 'SAR',
            'is_active' => true, 'account_id' => $bankGlAccount->id,
        ]);
        $capital = Account::where('company_id', $company->id)->where('code', '3000')->first();

        app(LedgerPostingService::class)->post($company, 'manual', 5001, 'Owner contribution', now()->subDays(5), [
            ['account_id' => $bankGlAccount->id, 'debit' => 20000],
            ['account_id' => $capital->id, 'credit' => 20000],
        ]);

        BankReconciliation::create([
            'company_id' => $company->id, 'bank_account_id' => $bankAccount->id,
            'statement_date' => now()->toDateString(), 'statement_ending_balance' => 20500, 'status' => 'completed',
        ]);

        $response = $this->actingAs($owner)->get(route('app.reports.bank-reconciliation-summary', ['export' => 'excel']));
        $response->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $sheet = $this->loadWorkbook($response->getContent())->getActiveSheet();

        $dataRow = null;
        foreach (range(1, $sheet->getHighestRow()) as $r) {
            if ($sheet->getCell('A'.$r)->getValue() === 'Main Bank') {
                $dataRow = $r;
            }
        }
        $this->assertNotNull($dataRow);

        $cell = $sheet->getCell('E'.$dataRow);
        $this->assertStringStartsWith('=', (string) $cell->getValue());
        $this->assertEqualsWithDelta(500.0, (float) $cell->getCalculatedValue(), 0.01);
    }

    public function test_payment_mode_statements_running_total_is_a_formula_chain_that_computes_correctly(): void
    {
        $plan = Plan::create(['name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000, 'is_active' => true, 'has_pos' => true]);
        $company = Company::create(['name' => 'Acme Retail', 'slug' => 'acme-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        Subscription::create([
            'company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
        ]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        PaymentMode::seedDefaults($company->id);

        $warehouse = Warehouse::firstOrCreate(['company_id' => $company->id, 'name' => 'Main Store']);
        $register = PosRegister::firstOrCreate(['company_id' => $company->id, 'warehouse_id' => $warehouse->id, 'name' => 'Register 1'], ['is_active' => true]);
        if (! $register->openShift()) {
            $this->actingAs($owner)->post(route('app.pos.shift.open'), ['register_id' => $register->id, 'opening_cash' => 100]);
        }

        foreach ([10, 15] as $amount) {
            $item = Item::create(['company_id' => $company->id, 'name' => 'Item '.uniqid(), 'unit_price' => $amount, 'vat_rate' => 0, 'is_active' => true, 'track_inventory' => false]);
            $this->actingAs($owner)->postJson(route('app.pos.checkout'), [
                'register_id' => $register->id,
                'lines' => [['item_id' => $item->id, 'quantity' => 1, 'unit_price' => $amount]],
                'payments' => [['method' => 'cash', 'amount' => $amount]],
            ])->assertRedirect();
        }

        $cashMode = PaymentMode::where('company_id', $company->id)->where('code', 'cash')->first();
        $response = $this->actingAs($owner)->get(route('app.reports.payment-mode-statement', ['payment_mode_id' => $cashMode->id, 'export' => 'excel']));
        $response->assertOk();

        $sheet = $this->loadWorkbook($response->getContent())->getActiveSheet();

        $rows = [];
        foreach (range(1, $sheet->getHighestRow()) as $r) {
            $f = $sheet->getCell('F'.$r)->getValue();
            if (is_string($f) && str_starts_with($f, '=')) {
                $rows[] = $r;
            }
        }
        $this->assertCount(2, $rows, 'Expected exactly 2 cash sale rows with a running-total formula.');

        // Re-fetch a fresh Cell object immediately before each
        // getCalculatedValue() call — PhpSpreadsheet's calculation
        // cache can go stale if a Cell reference is held across
        // multiple calls instead of re-read from the sheet each time.
        $this->assertStringStartsWith('=', (string) $sheet->getCell('F'.$rows[0])->getValue());
        $this->assertStringStartsWith('=', (string) $sheet->getCell('F'.$rows[1])->getValue());
        $this->assertEqualsWithDelta(10.0, (float) $sheet->getCell('F'.$rows[0])->getCalculatedValue(), 0.01);
        $this->assertEqualsWithDelta(25.0, (float) $sheet->getCell('F'.$rows[1])->getCalculatedValue(), 0.01);
    }

    public function test_bank_account_statement_excel_export_computes_the_correct_closing_balance(): void
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $bankAccount = BankAccount::create(['company_id' => $company->id, 'name' => 'Main Bank', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true]);
        $glAccount = $bankAccount->glAccount();
        $capital = Account::where('company_id', $company->id)->where('code', '3000')->first();
        $ledger = app(LedgerPostingService::class);

        $ledger->post($company, 'manual', 7001, 'Owner contribution', now()->subMonthsNoOverflow(2), [
            ['account_id' => $glAccount->id, 'debit' => 10000],
            ['account_id' => $capital->id, 'credit' => 10000],
        ]);
        $ledger->post($company, 'manual', 7002, 'Owner contribution', now(), [
            ['account_id' => $glAccount->id, 'debit' => 2500],
            ['account_id' => $capital->id, 'credit' => 2500],
        ]);

        $response = $this->actingAs($owner)->get(route('app.reports.bank-account-statement', ['bank_account_id' => $bankAccount->id, 'export' => 'excel']));
        $response->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $sheet = $this->loadWorkbook($response->getContent())->getActiveSheet();

        $closingRow = null;
        foreach (range(1, $sheet->getHighestRow()) as $r) {
            if ($sheet->getCell('A'.$r)->getValue() === __('Closing balance')) {
                $closingRow = $r;
            }
        }
        $this->assertNotNull($closingRow);
        $this->assertEqualsWithDelta(12500.0, (float) $sheet->getCell('E'.$closingRow)->getCalculatedValue(), 0.01);
    }

    public function test_ledger_account_statement_excel_export_downloads_with_both_selected_accounts(): void
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $bank = Account::where('company_id', $company->id)->where('code', '1100')->first();
        $capital = Account::where('company_id', $company->id)->where('code', '3000')->first();

        app(LedgerPostingService::class)->post($company, 'manual', 9101, 'Owner contribution', now(), [
            ['account_id' => $bank->id, 'debit' => 6000],
            ['account_id' => $capital->id, 'credit' => 6000],
        ]);

        $response = $this->actingAs($owner)->get(route('app.reports.ledger-account-statement', ['account_ids' => [$bank->id, $capital->id], 'export' => 'excel']));
        $response->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $sheet = $this->loadWorkbook($response->getContent())->getActiveSheet();
        $titles = [];
        foreach (range(1, $sheet->getHighestRow()) as $r) {
            $titles[] = (string) $sheet->getCell('A'.$r)->getValue();
        }
        $this->assertTrue(collect($titles)->contains(fn ($t) => str_contains($t, '1100')));
        $this->assertTrue(collect($titles)->contains(fn ($t) => str_contains($t, '3000')));
    }

    public function test_uncleared_bank_items_and_payment_mode_balances_excel_exports_download_correctly(): void
    {
        $company = Company::create(['name' => 'Uncleared Co.', 'slug' => 'uncleared-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $bankAccount = BankAccount::create(['company_id' => $company->id, 'name' => 'Main Bank', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true]);

        \App\Models\ReceiptVoucher::create([
            'company_id' => $company->id, 'bank_account_id' => $bankAccount->id, 'voucher_number' => 'RV-1',
            'date' => now()->subDays(2)->toDateString(), 'payer_name' => 'Client A', 'amount' => 1500, 'status' => 'issued',
        ]);

        $uncleared = $this->actingAs($owner)->get(route('app.reports.uncleared-bank-items', ['bank_account_id' => $bankAccount->id, 'export' => 'excel']));
        $uncleared->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $sheet = $this->loadWorkbook($uncleared->getContent())->getActiveSheet();
        $total = null;
        foreach (range(1, $sheet->getHighestRow()) as $r) {
            if ($sheet->getCell('A'.$r)->getValue() === __('Total')) {
                $total = $r;
            }
        }
        $this->assertNotNull($total);
        $this->assertEqualsWithDelta(1500.0, (float) $sheet->getCell('C'.$total)->getCalculatedValue(), 0.01);

        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        PaymentMode::seedDefaults($company->id);
        $balances = $this->actingAs($owner)->get(route('app.reports.payment-mode-balances', ['export' => 'excel']));
        $balances->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}
