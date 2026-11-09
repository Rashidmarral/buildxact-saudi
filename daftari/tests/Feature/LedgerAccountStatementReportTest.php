<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Company;
use App\Models\User;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "add more reports... Account Statement" filtered by transaction type
 * and user — a statement of one OR MORE ledger accounts at once,
 * unlike the single-account Ledger (JournalController::ledger()).
 */
class LedgerAccountStatementReportTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompanyAndOwner(): array
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        return [$company, $owner];
    }

    public function test_selecting_two_accounts_shows_both_as_separate_sections(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $bank = Account::where('company_id', $company->id)->where('code', '1100')->first();
        $capital = Account::where('company_id', $company->id)->where('code', '3000')->first();

        app(LedgerPostingService::class)->post($company, 'manual', 9101, 'Owner contribution', now(), [
            ['account_id' => $bank->id, 'debit' => 6000],
            ['account_id' => $capital->id, 'credit' => 6000],
        ]);

        $page = $this->actingAs($owner)->get(route('app.reports.ledger-account-statement', ['account_ids' => [$bank->id, $capital->id]]));
        $page->assertOk()->assertSee($bank->label())->assertSee($capital->label())->assertSee('6,000.00');

        $csv = $this->actingAs($owner)->get(route('app.reports.ledger-account-statement', ['account_ids' => [$bank->id, $capital->id], 'export' => 'csv']));
        $csv->assertOk();
        $csvBody = str_replace("\xEF\xBB\xBF", '', $csv->streamedContent());
        $this->assertStringContainsString('6000.00', $csvBody);

        $pdf = $this->actingAs($owner)->get(route('app.reports.ledger-account-statement', ['account_ids' => [$bank->id, $capital->id], 'export' => 'pdf']));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_a_source_type_filter_narrows_the_lines_shown(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $bank = Account::where('company_id', $company->id)->where('code', '1100')->first();
        $capital = Account::where('company_id', $company->id)->where('code', '3000')->first();
        $ledger = app(LedgerPostingService::class);

        $manualEntry = $ledger->post($company, 'manual', 9201, 'Owner contribution manual', now(), [
            ['account_id' => $bank->id, 'debit' => 1000],
            ['account_id' => $capital->id, 'credit' => 1000],
        ]);
        $otherEntry = $ledger->post($company, 'other_source', 9202, 'Owner contribution other', now(), [
            ['account_id' => $bank->id, 'debit' => 500],
            ['account_id' => $capital->id, 'credit' => 500],
        ]);

        $page = $this->actingAs($owner)->get(route('app.reports.ledger-account-statement', ['account_ids' => [$bank->id], 'source_type' => 'manual']));
        $page->assertOk()->assertSee($manualEntry->entry_number)->assertDontSee($otherEntry->entry_number);
    }

    public function test_no_accounts_selected_shows_an_empty_prompt(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();

        $page = $this->actingAs($owner)->get(route('app.reports.ledger-account-statement'));
        $page->assertOk()->assertSee(__('Select one or more accounts above to see their statement.'));
    }
}
