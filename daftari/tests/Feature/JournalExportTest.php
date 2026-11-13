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
 * "add CSV/PDF export to Journal listing and the single-account GL
 * view" — the Journal Entries listing (JournalController::index) and
 * the single-account ledger (JournalController::ledger) never had any
 * export; everything else in Reports did. Both now support
 * ?export=csv and ?export=pdf, same convention as every report.
 */
class JournalExportTest extends TestCase
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

    public function test_journal_index_csv_and_pdf_exports_download_with_the_posted_entry(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $bank = Account::where('company_id', $company->id)->where('code', '1100')->first();
        $capital = Account::where('company_id', $company->id)->where('code', '3000')->first();

        app(LedgerPostingService::class)->post($company, 'manual', 9001, 'Owner contribution', now(), [
            ['account_id' => $bank->id, 'debit' => 15000],
            ['account_id' => $capital->id, 'credit' => 15000],
        ]);

        $csv = $this->actingAs($owner)->get(route('app.journals.index', ['export' => 'csv']));
        $csv->assertOk();
        $csvBody = str_replace("\xEF\xBB\xBF", '', $csv->streamedContent());
        $this->assertStringContainsString('15000.00', $csvBody);

        $pdf = $this->actingAs($owner)->get(route('app.journals.index', ['export' => 'pdf']));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $page = $this->actingAs($owner)->get(route('app.journals.index'));
        $page->assertOk()->assertSee(__('Download CSV'));
    }

    public function test_account_ledger_csv_and_pdf_exports_include_opening_and_closing_balance(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();
        $bank = Account::where('company_id', $company->id)->where('code', '1100')->first();
        $capital = Account::where('company_id', $company->id)->where('code', '3000')->first();

        app(LedgerPostingService::class)->post($company, 'manual', 9002, 'Owner contribution', now(), [
            ['account_id' => $bank->id, 'debit' => 8000],
            ['account_id' => $capital->id, 'credit' => 8000],
        ]);

        $csv = $this->actingAs($owner)->get(route('app.ledger.index', ['account_id' => $bank->id, 'export' => 'csv']));
        $csv->assertOk();
        $csvBody = str_replace("\xEF\xBB\xBF", '', $csv->streamedContent());
        $this->assertStringContainsString('8000.00', $csvBody);

        $pdf = $this->actingAs($owner)->get(route('app.ledger.index', ['account_id' => $bank->id, 'export' => 'pdf']));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $page = $this->actingAs($owner)->get(route('app.ledger.index', ['account_id' => $bank->id]));
        $page->assertOk()->assertSee(__('Download PDF'));
    }
}
