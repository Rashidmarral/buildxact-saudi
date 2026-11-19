<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Company;
use App\Models\PaymentVoucher;
use App\Models\ReceiptVoucher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "add reports for bank accounts like custody accounts cash bank using
 * beautiful and modern layouts so i can show the owner each account
 * reports" — a running-balance statement (and branded PDF) per account
 * already existed, but only inside the separately-gated Project Cash
 * Flow module: a company without that module/permission had no
 * statement for a plain bank/cash/custody account at all. This covers
 * the new, ungated BankAccountReportController, reachable from the
 * ordinary cash_banks permission every company already has.
 */
class BankAccountReportTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): array
    {
        $company = Company::create(['name' => 'Reports Co.', 'slug' => 'reports-'.uniqid(), 'currency' => 'SAR']);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        return [$company, $owner];
    }

    public function test_the_index_page_groups_accounts_into_bank_cash_and_custody(): void
    {
        [$company, $owner] = $this->makeOwner();
        BankAccount::create(['company_id' => $company->id, 'name' => 'SNB Current', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true, 'opening_balance' => 1000]);
        BankAccount::create(['company_id' => $company->id, 'name' => 'Petty Cash', 'type' => 'cash', 'currency' => 'SAR', 'is_active' => true, 'opening_balance' => 200]);
        BankAccount::create(['company_id' => $company->id, 'name' => 'Khalid Float', 'type' => 'cash', 'currency' => 'SAR', 'is_active' => true, 'is_personal' => true, 'personal_owner_name' => 'Khalid', 'opening_balance' => 500]);

        $response = $this->actingAs($owner)->get(route('app.account-reports.index'));

        $response->assertOk();
        $response->assertSee('SNB Current');
        $response->assertSee('Petty Cash');
        $response->assertSee('Khalid Float');
        $response->assertSee(\App\Support\Money::format(1700));
    }

    public function test_the_per_account_statement_shows_opening_and_running_balance(): void
    {
        [$company, $owner] = $this->makeOwner();
        $account = BankAccount::create(['company_id' => $company->id, 'name' => 'SNB Current', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true, 'opening_balance' => 1000]);

        ReceiptVoucher::create(['company_id' => $company->id, 'bank_account_id' => $account->id, 'party_type' => 'customer', 'voucher_number' => 'RV-1', 'date' => now()->toDateString(), 'payer_name' => 'A Client', 'amount' => 500, 'method' => 'bank_transfer', 'status' => 'issued']);
        PaymentVoucher::create(['company_id' => $company->id, 'bank_account_id' => $account->id, 'party_type' => 'supplier', 'voucher_number' => 'PV-1', 'date' => now()->toDateString(), 'payee_name' => 'A Supplier', 'amount' => 300, 'method' => 'cash', 'status' => 'issued']);

        $response = $this->actingAs($owner)->get(route('app.account-reports.show', $account));

        $response->assertOk();
        // Opening 1000 + received 500 - paid 300 = 1200.
        $response->assertSee(\App\Support\Money::format(1200));
        $response->assertSee('RV-1');
        $response->assertSee('PV-1');
    }

    public function test_the_statement_pdf_renders(): void
    {
        [$company, $owner] = $this->makeOwner();
        $account = BankAccount::create(['company_id' => $company->id, 'name' => 'SNB Current', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true, 'opening_balance' => 1000]);
        ReceiptVoucher::create(['company_id' => $company->id, 'bank_account_id' => $account->id, 'party_type' => 'customer', 'voucher_number' => 'RV-1', 'date' => now()->toDateString(), 'payer_name' => 'A Client', 'amount' => 500, 'method' => 'bank_transfer', 'status' => 'issued']);

        $response = $this->actingAs($owner)->get(route('app.account-reports.pdf', $account));

        $response->assertOk();
        $this->assertStringStartsWith('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_a_company_cannot_view_another_companys_account_report(): void
    {
        [$companyA] = $this->makeOwner();
        [, $ownerB] = $this->makeOwner();
        $accountA = BankAccount::create(['company_id' => $companyA->id, 'name' => 'SNB Current', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true]);

        $this->actingAs($ownerB)->get(route('app.account-reports.show', $accountA))->assertNotFound();
    }

    public function test_the_cash_custody_index_links_to_the_statement_without_the_project_cash_flow_module(): void
    {
        [$company, $owner] = $this->makeOwner();
        $account = BankAccount::create(['company_id' => $company->id, 'name' => 'Khalid Float', 'type' => 'cash', 'currency' => 'SAR', 'is_active' => true, 'is_personal' => true, 'personal_owner_name' => 'Khalid']);

        $response = $this->actingAs($owner)->get(route('app.cash-custody.index'));

        $response->assertOk();
        $response->assertSee(route('app.account-reports.show', $account), false);
    }
}
