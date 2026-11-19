<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankStatementLine;
use App\Models\Company;
use App\Models\PaymentVoucher;
use App\Models\ReceiptVoucher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "add more reports... uncleared bank items" — deposits not yet on the
 * bank statement and payments not yet presented, as at a date. Mirrors
 * BankReconciliationController::unmatchedBookTransactions() but as a
 * standalone report independent of any particular reconciliation
 * session.
 */
class UnclearedBankItemsReportTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwnerAndAccount(): array
    {
        $company = Company::create(['name' => 'Uncleared Co.', 'slug' => 'uncleared-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $bankAccount = BankAccount::create(['company_id' => $company->id, 'name' => 'Main Bank', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true]);

        return [$owner, $bankAccount];
    }

    public function test_an_unmatched_receipt_and_payment_both_appear_as_outstanding(): void
    {
        [$owner, $bankAccount] = $this->makeOwnerAndAccount();

        ReceiptVoucher::create([
            'company_id' => $owner->company_id, 'bank_account_id' => $bankAccount->id, 'voucher_number' => 'RV-1',
            'date' => now()->subDays(2)->toDateString(), 'payer_name' => 'Client A', 'amount' => 1500, 'status' => 'issued',
        ]);
        PaymentVoucher::create([
            'company_id' => $owner->company_id, 'bank_account_id' => $bankAccount->id, 'voucher_number' => 'PV-1',
            'date' => now()->subDay()->toDateString(), 'payee_name' => 'Supplier B', 'amount' => 600, 'status' => 'issued',
        ]);

        $page = $this->actingAs($owner)->get(route('app.reports.uncleared-bank-items', ['bank_account_id' => $bankAccount->id]));
        $page->assertOk()->assertSee('Client A')->assertSee('Supplier B');

        $csv = $this->actingAs($owner)->get(route('app.reports.uncleared-bank-items', ['bank_account_id' => $bankAccount->id, 'export' => 'csv']));
        $csv->assertOk();
        $csvBody = str_replace("\xEF\xBB\xBF", '', $csv->streamedContent());
        $this->assertStringContainsString('1500.00', $csvBody);
        $this->assertStringContainsString('-600.00', $csvBody);

        $pdf = $this->actingAs($owner)->get(route('app.reports.uncleared-bank-items', ['bank_account_id' => $bankAccount->id, 'export' => 'pdf']));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_a_receipt_already_matched_to_a_statement_line_drops_off(): void
    {
        [$owner, $bankAccount] = $this->makeOwnerAndAccount();

        $receipt = ReceiptVoucher::create([
            'company_id' => $owner->company_id, 'bank_account_id' => $bankAccount->id, 'voucher_number' => 'RV-2',
            'date' => now()->toDateString(), 'payer_name' => 'Matched Client', 'amount' => 900, 'status' => 'issued',
        ]);

        $reconciliation = BankReconciliation::create([
            'company_id' => $owner->company_id, 'bank_account_id' => $bankAccount->id,
            'statement_date' => now()->toDateString(), 'statement_ending_balance' => 900, 'status' => 'in_progress',
        ]);
        BankStatementLine::create([
            'company_id' => $owner->company_id, 'bank_reconciliation_id' => $reconciliation->id,
            'date' => now()->toDateString(), 'description' => 'Deposit', 'amount' => 900,
            'matched_type' => 'receipt_voucher', 'matched_id' => $receipt->id,
        ]);

        $page = $this->actingAs($owner)->get(route('app.reports.uncleared-bank-items', ['bank_account_id' => $bankAccount->id]));
        $page->assertOk()->assertDontSee('Matched Client');
    }

    public function test_a_transaction_dated_after_the_as_at_date_is_excluded(): void
    {
        [$owner, $bankAccount] = $this->makeOwnerAndAccount();

        ReceiptVoucher::create([
            'company_id' => $owner->company_id, 'bank_account_id' => $bankAccount->id, 'voucher_number' => 'RV-3',
            'date' => now()->addDays(10)->toDateString(), 'payer_name' => 'Future Client', 'amount' => 1200, 'status' => 'issued',
        ]);

        $page = $this->actingAs($owner)->get(route('app.reports.uncleared-bank-items', ['bank_account_id' => $bankAccount->id, 'as_at' => now()->toDateString()]));
        $page->assertOk()->assertDontSee('Future Client');
    }
}
