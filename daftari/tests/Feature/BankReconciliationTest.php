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
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Audit finding: nothing let a company confirm its books agree with the
 * bank — no statement import, no matching against vouchers. A
 * reconciliation session imports a CSV statement, auto-matches it against
 * unreconciled receipt/payment vouchers by amount and date, and supports
 * manual match/unmatch before being marked complete.
 */
class BankReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): array
    {
        $company = Company::create(['name' => 'Reconcile Co.', 'slug' => 'reconcile-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $bankAccount = BankAccount::create(['company_id' => $company->id, 'name' => 'Main Bank', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true]);

        return [$owner, $bankAccount];
    }

    public function test_starting_a_reconciliation_creates_an_in_progress_record(): void
    {
        [$owner, $bankAccount] = $this->makeOwner();

        $response = $this->actingAs($owner)->post(route('app.bank-reconciliations.store', $bankAccount), [
            'statement_date' => '2026-06-30', 'statement_ending_balance' => 5000,
        ]);

        $reconciliation = BankReconciliation::first();
        $response->assertRedirect(route('app.bank-reconciliations.show', $reconciliation));
        $this->assertSame('in_progress', $reconciliation->status);
        $this->assertSame($bankAccount->id, $reconciliation->bank_account_id);
    }

    public function test_importing_a_csv_statement_auto_matches_a_receipt_voucher_by_amount_and_date(): void
    {
        [$owner, $bankAccount] = $this->makeOwner();
        ReceiptVoucher::create([
            'company_id' => $owner->company_id, 'bank_account_id' => $bankAccount->id, 'voucher_number' => 'RV-1',
            'date' => '2026-06-10', 'payer_name' => 'Client A', 'amount' => 1000, 'status' => 'issued',
        ]);

        $reconciliation = BankReconciliation::create([
            'company_id' => $owner->company_id, 'bank_account_id' => $bankAccount->id,
            'statement_date' => '2026-06-30', 'statement_ending_balance' => 1000, 'status' => 'in_progress',
        ]);

        $csv = "date,description,reference,amount\n2026-06-11,Deposit from Client A,,1000\n";
        $file = UploadedFile::fake()->createWithContent('statement.csv', $csv);

        $this->actingAs($owner)->post(route('app.bank-reconciliations.import', $reconciliation), ['file' => $file])
            ->assertSessionDoesntHaveErrors();

        $line = BankStatementLine::first();
        $this->assertNotNull($line);
        $this->assertSame('receipt_voucher', $line->matched_type);
        $this->assertSame(1000.0, (float) $line->amount);
    }

    public function test_a_statement_line_outside_the_matching_window_stays_unmatched(): void
    {
        [$owner, $bankAccount] = $this->makeOwner();
        ReceiptVoucher::create([
            'company_id' => $owner->company_id, 'bank_account_id' => $bankAccount->id, 'voucher_number' => 'RV-2',
            'date' => '2026-01-01', 'payer_name' => 'Client B', 'amount' => 500, 'status' => 'issued',
        ]);

        $reconciliation = BankReconciliation::create([
            'company_id' => $owner->company_id, 'bank_account_id' => $bankAccount->id,
            'statement_date' => '2026-06-30', 'statement_ending_balance' => 500, 'status' => 'in_progress',
        ]);

        $csv = "date,description,amount\n2026-06-15,Unrelated deposit,500\n";
        $file = UploadedFile::fake()->createWithContent('statement.csv', $csv);

        $this->actingAs($owner)->post(route('app.bank-reconciliations.import', $reconciliation), ['file' => $file]);

        $line = BankStatementLine::first();
        $this->assertNull($line->matched_type);
    }

    public function test_manual_match_and_unmatch(): void
    {
        [$owner, $bankAccount] = $this->makeOwner();
        $payment = PaymentVoucher::create([
            'company_id' => $owner->company_id, 'bank_account_id' => $bankAccount->id, 'party_type' => 'manual',
            'voucher_number' => 'PV-1', 'date' => '2026-06-05', 'payee_name' => 'Supplier X', 'amount' => 200, 'status' => 'issued',
        ]);

        $reconciliation = BankReconciliation::create([
            'company_id' => $owner->company_id, 'bank_account_id' => $bankAccount->id,
            'statement_date' => '2026-06-30', 'statement_ending_balance' => -200, 'status' => 'in_progress',
        ]);
        $line = $reconciliation->lines()->create(['date' => '2026-06-05', 'description' => 'Withdrawal', 'amount' => -200, 'company_id' => $owner->company_id]);

        $this->actingAs($owner)->post(route('app.bank-statement-lines.match', $line), [
            'matched_type' => 'payment_voucher', 'matched_id' => $payment->id,
        ])->assertSessionDoesntHaveErrors();

        $this->assertSame('payment_voucher', $line->fresh()->matched_type);

        $this->actingAs($owner)->post(route('app.bank-statement-lines.unmatch', $line))->assertSessionDoesntHaveErrors();
        $this->assertNull($line->fresh()->matched_type);
    }

    public function test_a_voucher_already_matched_in_one_reconciliation_is_not_offered_as_a_candidate_again(): void
    {
        [$owner, $bankAccount] = $this->makeOwner();
        $receipt = ReceiptVoucher::create([
            'company_id' => $owner->company_id, 'bank_account_id' => $bankAccount->id, 'voucher_number' => 'RV-3',
            'date' => '2026-06-01', 'payer_name' => 'Client C', 'amount' => 300, 'status' => 'issued',
        ]);

        $first = BankReconciliation::create([
            'company_id' => $owner->company_id, 'bank_account_id' => $bankAccount->id,
            'statement_date' => '2026-06-15', 'statement_ending_balance' => 300, 'status' => 'in_progress',
        ]);
        $first->lines()->create(['date' => '2026-06-01', 'description' => 'Deposit', 'amount' => 300, 'matched_type' => 'receipt_voucher', 'matched_id' => $receipt->id, 'company_id' => $owner->company_id]);

        $second = BankReconciliation::create([
            'company_id' => $owner->company_id, 'bank_account_id' => $bankAccount->id,
            'statement_date' => '2026-06-30', 'statement_ending_balance' => 0, 'status' => 'in_progress',
        ]);

        $response = $this->actingAs($owner)->get(route('app.bank-reconciliations.show', $second));

        $response->assertOk();
        $response->assertDontSee('RV-3');
    }

    public function test_completing_a_reconciliation_marks_it_completed(): void
    {
        [$owner, $bankAccount] = $this->makeOwner();
        $reconciliation = BankReconciliation::create([
            'company_id' => $owner->company_id, 'bank_account_id' => $bankAccount->id,
            'statement_date' => '2026-06-30', 'statement_ending_balance' => 0, 'status' => 'in_progress',
        ]);

        $this->actingAs($owner)->post(route('app.bank-reconciliations.complete', $reconciliation))
            ->assertSessionDoesntHaveErrors();

        $this->assertSame('completed', $reconciliation->fresh()->status);
        $this->assertNotNull($reconciliation->fresh()->completed_at);
    }
}
