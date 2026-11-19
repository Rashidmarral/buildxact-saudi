<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankStatementLine;
use App\Models\Company;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mpdf\Mpdf;
use Tests\TestCase;

/**
 * Two gaps flagged as missing from Bank Reconciliation: (1) a directly-
 * paid Expense (bank_account_id set, no voucher involved) never showed up
 * as a matchable book transaction, even though it's a real, unreconciled
 * bank movement; (2) statement import only accepted a clean CSV, with no
 * way to import a PDF bank statement at all. BankStatementPdfParserTest
 * covers the parser's own line-reading rules in isolation; this file
 * covers the two gaps end to end.
 */
class BankReconciliationEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): array
    {
        $company = Company::create(['name' => 'Reconcile Co.', 'slug' => 'reconcile-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $bankAccount = BankAccount::create(['company_id' => $company->id, 'name' => 'Main Bank', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true]);

        return [$owner, $bankAccount];
    }

    public function test_a_directly_paid_expense_is_offered_as_a_matchable_candidate(): void
    {
        [$owner, $bankAccount] = $this->makeOwner();
        Expense::create([
            'company_id' => $owner->company_id, 'bank_account_id' => $bankAccount->id, 'vendor_name' => 'Fuel Station',
            'description' => 'Fuel', 'amount' => 200, 'gross_amount' => 230, 'vat_amount' => 30,
            'tax_category' => 'standard_15', 'expense_date' => '2026-06-10', 'status' => 'approved',
        ]);
        $reconciliation = BankReconciliation::create([
            'company_id' => $owner->company_id, 'bank_account_id' => $bankAccount->id,
            'statement_date' => '2026-06-30', 'statement_ending_balance' => -230, 'status' => 'in_progress',
        ]);

        $csv = "date,description,amount\n2026-06-11,Card purchase,-230\n";
        $file = UploadedFile::fake()->createWithContent('statement.csv', $csv);

        $this->actingAs($owner)->post(route('app.bank-reconciliations.import', $reconciliation), ['file' => $file])
            ->assertSessionDoesntHaveErrors();

        $line = BankStatementLine::first();
        $this->assertSame('expense', $line->matched_type);
    }

    public function test_an_owner_paid_expense_is_never_offered_since_it_never_touched_a_bank_account(): void
    {
        [$owner, $bankAccount] = $this->makeOwner();
        Expense::create([
            'company_id' => $owner->company_id, 'paid_by_owner' => true, 'vendor_name' => 'Rashid',
            'description' => 'Fuel', 'amount' => 200, 'gross_amount' => 230, 'vat_amount' => 30,
            'tax_category' => 'standard_15', 'expense_date' => '2026-06-10', 'status' => 'approved',
        ]);
        $reconciliation = BankReconciliation::create([
            'company_id' => $owner->company_id, 'bank_account_id' => $bankAccount->id,
            'statement_date' => '2026-06-30', 'statement_ending_balance' => -230, 'status' => 'in_progress',
        ]);

        $response = $this->actingAs($owner)->get(route('app.bank-reconciliations.show', $reconciliation));

        $response->assertOk();
        $response->assertDontSee('Rashid');
    }

    public function test_uploading_a_pdf_statement_imports_lines_and_auto_matches(): void
    {
        [$owner, $bankAccount] = $this->makeOwner();
        Expense::create([
            'company_id' => $owner->company_id, 'bank_account_id' => $bankAccount->id, 'vendor_name' => 'Fuel Station',
            'description' => 'Fuel', 'amount' => 1000, 'gross_amount' => 1000, 'vat_amount' => 0,
            'tax_category' => 'zero_rated', 'expense_date' => '2026-06-11', 'status' => 'approved',
        ]);
        $reconciliation = BankReconciliation::create([
            'company_id' => $owner->company_id, 'bank_account_id' => $bankAccount->id,
            'statement_date' => '2026-06-30', 'statement_ending_balance' => -1000, 'status' => 'in_progress',
        ]);

        $mpdf = new Mpdf(['tempDir' => storage_path('app/mpdf-tmp')]);
        $mpdf->WriteHTML('<p>2026-06-11 Card purchase Fuel Station 1,000.00 DR 9,000.00</p>');
        $pdfBytes = $mpdf->Output('', 'S');

        $tmpPath = tempnam(sys_get_temp_dir(), 'stmt').'.pdf';
        file_put_contents($tmpPath, $pdfBytes);
        $file = new UploadedFile($tmpPath, 'statement.pdf', 'application/pdf', null, true);

        $response = $this->actingAs($owner)->post(route('app.bank-reconciliations.import', $reconciliation), ['file' => $file]);

        $response->assertSessionDoesntHaveErrors();
        $line = BankStatementLine::first();
        $this->assertNotNull($line, 'No statement line was created from the PDF.');
        $this->assertSame(-1000.0, (float) $line->amount);
        $this->assertSame('expense', $line->matched_type);

        @unlink($tmpPath);
    }

    public function test_an_imported_line_can_be_individually_removed(): void
    {
        [$owner, $bankAccount] = $this->makeOwner();
        $reconciliation = BankReconciliation::create([
            'company_id' => $owner->company_id, 'bank_account_id' => $bankAccount->id,
            'statement_date' => '2026-06-30', 'statement_ending_balance' => 0, 'status' => 'in_progress',
        ]);
        $line = $reconciliation->lines()->create(['date' => '2026-06-05', 'description' => 'Misparsed junk', 'amount' => -99999, 'company_id' => $owner->company_id]);

        $this->actingAs($owner)->delete(route('app.bank-statement-lines.destroy', $line))
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseMissing('bank_statement_lines', ['id' => $line->id]);
    }

    public function test_a_line_cannot_be_removed_from_a_completed_reconciliation(): void
    {
        [$owner, $bankAccount] = $this->makeOwner();
        $reconciliation = BankReconciliation::create([
            'company_id' => $owner->company_id, 'bank_account_id' => $bankAccount->id,
            'statement_date' => '2026-06-30', 'statement_ending_balance' => 0, 'status' => 'completed', 'completed_at' => now(),
        ]);
        $line = $reconciliation->lines()->create(['date' => '2026-06-05', 'description' => 'x', 'amount' => 10, 'company_id' => $owner->company_id]);

        $this->actingAs($owner)->delete(route('app.bank-statement-lines.destroy', $line))->assertStatus(422);
        $this->assertDatabaseHas('bank_statement_lines', ['id' => $line->id]);
    }
}
