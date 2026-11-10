<?php

namespace Tests\Feature;

use App\Console\Commands\SendMonthlyFinancialStatements;
use App\Mail\MonthlyFinancialStatementsMail;
use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * "add more... Auto-emailed monthly financial statements" — the last
 * item of Batch D. An opted-in company gets a Balance Sheet + Income
 * Statement PDF emailed to every user with report access once per
 * calendar month; an opted-out company, or one with no such user, is
 * skipped; running the command twice in the same month sends once.
 */
class MonthlyFinancialStatementsEmailTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompanyAndOwner(bool $enabled = true): array
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        $company->update(['financial_statements_auto_email_enabled' => $enabled]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active', 'email' => 'owner@example.com']);

        return [$company, $owner];
    }

    public function test_an_opted_in_company_gets_the_statements_emailed_with_two_pdf_attachments(): void
    {
        Mail::fake();
        [$company, $owner] = $this->makeCompanyAndOwner();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Al Jeul Construction']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-0001',
            'issue_date' => now()->subMonthNoOverflow()->startOfMonth()->addDays(5), 'status' => 'sent', 'currency' => 'SAR',
            'subtotal' => 5000, 'vat_total' => 750, 'total' => 5750, 'amount_paid' => 0,
        ]);
        app(LedgerPostingService::class)->postInvoiceIssued($invoice->fresh());

        Artisan::call(SendMonthlyFinancialStatements::class);

        Mail::assertQueued(MonthlyFinancialStatementsMail::class, function ($mail) use ($company, $owner) {
            return $mail->hasTo($owner->email) && $mail->company->id === $company->id && count($mail->attachments()) === 2;
        });

        $this->assertSame(now()->subMonthNoOverflow()->format('Y-m'), $company->fresh()->financial_statements_last_sent_period);
    }

    public function test_a_company_with_the_setting_off_is_skipped(): void
    {
        Mail::fake();
        $this->makeCompanyAndOwner(enabled: false);

        Artisan::call(SendMonthlyFinancialStatements::class);

        Mail::assertNothingQueued();
    }

    public function test_running_twice_in_the_same_month_only_sends_once(): void
    {
        Mail::fake();
        $this->makeCompanyAndOwner();

        Artisan::call(SendMonthlyFinancialStatements::class);
        Artisan::call(SendMonthlyFinancialStatements::class);

        Mail::assertQueuedCount(1);
    }

    public function test_a_company_with_no_reports_permission_user_is_skipped_without_crashing(): void
    {
        Mail::fake();
        $company = Company::create(['name' => 'No Viewers Co.', 'slug' => 'nvc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        $company->update(['financial_statements_auto_email_enabled' => true]);

        Artisan::call(SendMonthlyFinancialStatements::class);

        Mail::assertNothingQueued();
        $this->assertNull($company->fresh()->financial_statements_last_sent_period);
    }
}
