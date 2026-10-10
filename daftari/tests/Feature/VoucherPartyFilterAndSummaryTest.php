<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\BankAccount;
use App\Models\Client;
use App\Models\Company;
use App\Models\PaymentVoucher;
use App\Models\ReceiptVoucher;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "i have issue 7 to 8 payment voucher there is no option to get total
 * amount paid and also there is option to get single voucher of all
 * payments" — a subcontractor paid across several separate vouchers had
 * no way to see the running total, or hand them one consolidated
 * document. Adds supplier/client/date filters plus an issued-only total
 * to both voucher indexes, and a "Download combined PDF" bundling
 * exactly the filtered vouchers into one branded statement.
 */
class VoucherPartyFilterAndSummaryTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): User
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    private function makeAccount(User $owner): BankAccount
    {
        return BankAccount::create(['company_id' => $owner->company_id, 'name' => 'SNB Current Account', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true]);
    }

    public function test_filtering_payment_vouchers_by_supplier_shows_only_their_vouchers_and_the_right_total(): void
    {
        $owner = $this->makeOwner();
        $account = $this->makeAccount($owner);
        $saed = Supplier::create(['company_id' => $owner->company_id, 'name' => 'SAED EST']);
        $other = Supplier::create(['company_id' => $owner->company_id, 'name' => 'Other Supplier']);

        PaymentVoucher::create(['company_id' => $owner->company_id, 'bank_account_id' => $account->id, 'party_type' => 'supplier', 'supplier_id' => $saed->id, 'voucher_number' => 'PV-1', 'date' => now()->toDateString(), 'payee_name' => 'SAED EST', 'amount' => 5000, 'method' => 'bank_transfer', 'status' => 'issued']);
        PaymentVoucher::create(['company_id' => $owner->company_id, 'bank_account_id' => $account->id, 'party_type' => 'supplier', 'supplier_id' => $saed->id, 'voucher_number' => 'PV-2', 'date' => now()->toDateString(), 'payee_name' => 'SAED EST', 'amount' => 3000, 'method' => 'cash', 'status' => 'issued']);
        // A voided voucher for the same supplier must not count toward the total.
        PaymentVoucher::create(['company_id' => $owner->company_id, 'bank_account_id' => $account->id, 'party_type' => 'supplier', 'supplier_id' => $saed->id, 'voucher_number' => 'PV-3', 'date' => now()->toDateString(), 'payee_name' => 'SAED EST', 'amount' => 999, 'method' => 'cash', 'status' => 'void']);
        PaymentVoucher::create(['company_id' => $owner->company_id, 'bank_account_id' => $account->id, 'party_type' => 'supplier', 'supplier_id' => $other->id, 'voucher_number' => 'PV-4', 'date' => now()->toDateString(), 'payee_name' => 'Other Supplier', 'amount' => 10000, 'method' => 'cash', 'status' => 'issued']);

        $response = $this->actingAs($owner)->get(route('app.payment-vouchers.index', ['supplier_id' => $saed->id]));

        $response->assertOk();
        $response->assertSee('PV-1');
        $response->assertSee('PV-2');
        $response->assertDontSee('PV-4');
        // Total paid (filtered) = 5000 + 3000 = 8000, excluding the voided 999 and the other supplier's 10000.
        $response->assertSee(\App\Support\Money::format(8000));
        $response->assertSee(route('app.payment-vouchers.summary-pdf', ['supplier_id' => $saed->id]), false);
    }

    public function test_the_combined_pdf_downloads_with_only_the_filtered_vouchers(): void
    {
        $owner = $this->makeOwner();
        $account = $this->makeAccount($owner);
        $saed = Supplier::create(['company_id' => $owner->company_id, 'name' => 'SAED EST']);
        PaymentVoucher::create(['company_id' => $owner->company_id, 'bank_account_id' => $account->id, 'party_type' => 'supplier', 'supplier_id' => $saed->id, 'voucher_number' => 'PV-1', 'date' => now()->toDateString(), 'payee_name' => 'SAED EST', 'amount' => 5000, 'method' => 'bank_transfer', 'status' => 'issued']);

        $response = $this->actingAs($owner)->get(route('app.payment-vouchers.summary-pdf', ['supplier_id' => $saed->id]));

        $response->assertOk();
        $this->assertStringStartsWith('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_the_combined_pdf_requires_a_party_filter(): void
    {
        $owner = $this->makeOwner();

        $this->actingAs($owner)->get(route('app.payment-vouchers.summary-pdf'))->assertNotFound();
    }

    public function test_filtering_receipt_vouchers_by_client_shows_only_their_vouchers_and_the_right_total(): void
    {
        $owner = $this->makeOwner();
        $account = $this->makeAccount($owner);
        $client = Client::create(['company_id' => $owner->company_id, 'name' => 'Sada Al Jeul Construction']);
        $otherClient = Client::create(['company_id' => $owner->company_id, 'name' => 'Other Client']);

        ReceiptVoucher::create(['company_id' => $owner->company_id, 'bank_account_id' => $account->id, 'party_type' => 'customer', 'client_id' => $client->id, 'voucher_number' => 'RV-1', 'date' => now()->toDateString(), 'payer_name' => 'Sada Al Jeul Construction', 'amount' => 20000, 'method' => 'bank_transfer', 'status' => 'issued']);
        ReceiptVoucher::create(['company_id' => $owner->company_id, 'bank_account_id' => $account->id, 'party_type' => 'customer', 'client_id' => $client->id, 'voucher_number' => 'RV-2', 'date' => now()->toDateString(), 'payer_name' => 'Sada Al Jeul Construction', 'amount' => 15000, 'method' => 'bank_transfer', 'status' => 'issued']);
        ReceiptVoucher::create(['company_id' => $owner->company_id, 'bank_account_id' => $account->id, 'party_type' => 'customer', 'client_id' => $otherClient->id, 'voucher_number' => 'RV-3', 'date' => now()->toDateString(), 'payer_name' => 'Other Client', 'amount' => 1000, 'method' => 'cash', 'status' => 'issued']);

        $response = $this->actingAs($owner)->get(route('app.receipt-vouchers.index', ['client_id' => $client->id]));

        $response->assertOk();
        $response->assertSee('RV-1');
        $response->assertSee('RV-2');
        $response->assertDontSee('RV-3');
        $response->assertSee(\App\Support\Money::format(35000));
    }

    public function test_date_range_filter_narrows_the_total(): void
    {
        $owner = $this->makeOwner();
        $account = $this->makeAccount($owner);
        $saed = Supplier::create(['company_id' => $owner->company_id, 'name' => 'SAED EST']);
        PaymentVoucher::create(['company_id' => $owner->company_id, 'bank_account_id' => $account->id, 'party_type' => 'supplier', 'supplier_id' => $saed->id, 'voucher_number' => 'PV-OLD', 'date' => '2026-01-01', 'payee_name' => 'SAED EST', 'amount' => 1000, 'method' => 'cash', 'status' => 'issued']);
        PaymentVoucher::create(['company_id' => $owner->company_id, 'bank_account_id' => $account->id, 'party_type' => 'supplier', 'supplier_id' => $saed->id, 'voucher_number' => 'PV-NEW', 'date' => now()->toDateString(), 'payee_name' => 'SAED EST', 'amount' => 2000, 'method' => 'cash', 'status' => 'issued']);

        $response = $this->actingAs($owner)->get(route('app.payment-vouchers.index', ['supplier_id' => $saed->id, 'from' => now()->startOfMonth()->toDateString()]));

        $response->assertOk();
        $response->assertDontSee('PV-OLD');
        $response->assertSee('PV-NEW');
        $response->assertSee(\App\Support\Money::format(2000));
    }
}
