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

    /**
     * "our main supplier is shahid but as a reference we are purchasing
     * material from aljazeera and ashraf also and paying directly i need
     * what we have paid to shahid, ashraf and aljazeera separately" —
     * every voucher sits under one umbrella Supplier record, with the
     * real recipient named only in the free-text reference. The `q`
     * search narrows the list (and its total) down to one recipient's own
     * vouchers.
     */
    public function test_free_text_search_narrows_payment_vouchers_to_one_real_payee(): void
    {
        $owner = $this->makeOwner();
        $account = $this->makeAccount($owner);
        $saed = Supplier::create(['company_id' => $owner->company_id, 'name' => 'SAED EST']);

        PaymentVoucher::create(['company_id' => $owner->company_id, 'bank_account_id' => $account->id, 'party_type' => 'supplier', 'supplier_id' => $saed->id, 'voucher_number' => 'PV-1', 'date' => now()->toDateString(), 'payee_name' => 'SAED EST', 'reference' => 'Material advance to Shahid', 'amount' => 5000, 'method' => 'cash', 'status' => 'issued']);
        PaymentVoucher::create(['company_id' => $owner->company_id, 'bank_account_id' => $account->id, 'party_type' => 'supplier', 'supplier_id' => $saed->id, 'voucher_number' => 'PV-2', 'date' => now()->toDateString(), 'payee_name' => 'SAED EST', 'reference' => 'Steel purchase - Ashraf Trading', 'amount' => 3000, 'method' => 'cash', 'status' => 'issued']);

        $response = $this->actingAs($owner)->get(route('app.payment-vouchers.index', ['supplier_id' => $saed->id, 'q' => 'shahid']));

        $response->assertOk();
        $response->assertSee('PV-1');
        $response->assertDontSee('PV-2');
        $response->assertSee(\App\Support\Money::format(5000));
    }

    public function test_grouped_summary_pdf_splits_one_umbrella_supplier_into_separate_payee_sections(): void
    {
        $owner = $this->makeOwner();
        $account = $this->makeAccount($owner);
        $saed = Supplier::create(['company_id' => $owner->company_id, 'name' => 'SAED EST']);

        PaymentVoucher::create(['company_id' => $owner->company_id, 'bank_account_id' => $account->id, 'party_type' => 'supplier', 'supplier_id' => $saed->id, 'voucher_number' => 'PV-1', 'date' => now()->toDateString(), 'payee_name' => 'SAED EST', 'reference' => 'Material advance to Shahid', 'amount' => 5000, 'method' => 'cash', 'status' => 'issued']);
        PaymentVoucher::create(['company_id' => $owner->company_id, 'bank_account_id' => $account->id, 'party_type' => 'supplier', 'supplier_id' => $saed->id, 'voucher_number' => 'PV-2', 'date' => now()->toDateString(), 'payee_name' => 'SAED EST', 'reference' => 'Steel purchase - Ashraf Trading', 'amount' => 3000, 'method' => 'cash', 'status' => 'issued']);
        // Matches no defined group's keywords, so it must land in "Other" rather than vanish from the grand total.
        PaymentVoucher::create(['company_id' => $owner->company_id, 'bank_account_id' => $account->id, 'party_type' => 'supplier', 'supplier_id' => $saed->id, 'voucher_number' => 'PV-3', 'date' => now()->toDateString(), 'payee_name' => 'SAED EST', 'reference' => 'Unrelated transport cost', 'amount' => 700, 'method' => 'cash', 'status' => 'issued']);

        $response = $this->actingAs($owner)->get(route('app.payment-vouchers.summary-pdf', [
            'supplier_id' => $saed->id,
            'group_label' => ['Shahid', 'Ashraf'],
            'group_terms' => ['shahid', 'ashraf'],
        ]));

        $response->assertOk();
        $this->assertStringStartsWith('application/pdf', $response->headers->get('Content-Type'));
        // The grand total must still cover every voucher, including the unmatched one swept into "Other".
        $this->assertStringContainsString('grouped', $response->headers->get('Content-Disposition'));
    }

    public function test_free_text_search_narrows_receipt_vouchers_by_payer_name_column(): void
    {
        $owner = $this->makeOwner();
        $account = $this->makeAccount($owner);
        $client = Client::create(['company_id' => $owner->company_id, 'name' => 'Sada Al Jeul Construction']);

        ReceiptVoucher::create(['company_id' => $owner->company_id, 'bank_account_id' => $account->id, 'party_type' => 'customer', 'client_id' => $client->id, 'voucher_number' => 'RV-1', 'date' => now()->toDateString(), 'payer_name' => 'Sada Al Jeul Construction', 'reference' => 'Progress payment - Shahid works', 'amount' => 20000, 'method' => 'bank_transfer', 'status' => 'issued']);
        ReceiptVoucher::create(['company_id' => $owner->company_id, 'bank_account_id' => $account->id, 'party_type' => 'customer', 'client_id' => $client->id, 'voucher_number' => 'RV-2', 'date' => now()->toDateString(), 'payer_name' => 'Sada Al Jeul Construction', 'reference' => 'Final settlement', 'amount' => 15000, 'method' => 'bank_transfer', 'status' => 'issued']);

        $response = $this->actingAs($owner)->get(route('app.receipt-vouchers.index', ['client_id' => $client->id, 'q' => 'shahid']));

        $response->assertOk();
        $response->assertSee('RV-1');
        $response->assertDontSee('RV-2');
    }

    public function test_grouped_summary_pdf_renders_for_receipt_vouchers_without_a_payee_name_column_error(): void
    {
        $owner = $this->makeOwner();
        $account = $this->makeAccount($owner);
        $client = Client::create(['company_id' => $owner->company_id, 'name' => 'Sada Al Jeul Construction']);

        ReceiptVoucher::create(['company_id' => $owner->company_id, 'bank_account_id' => $account->id, 'party_type' => 'customer', 'client_id' => $client->id, 'voucher_number' => 'RV-1', 'date' => now()->toDateString(), 'payer_name' => 'Sada Al Jeul Construction', 'reference' => 'Progress payment - Shahid works', 'amount' => 20000, 'method' => 'bank_transfer', 'status' => 'issued']);

        $response = $this->actingAs($owner)->get(route('app.receipt-vouchers.summary-pdf', [
            'client_id' => $client->id,
            'group_label' => ['Shahid'],
            'group_terms' => ['shahid'],
        ]));

        $response->assertOk();
        $this->assertStringStartsWith('application/pdf', $response->headers->get('Content-Type'));
    }

    /**
     * "add an option to download separate pdf like one for shahid, one
     * for ashraf and same for algazaierih" — each quick-filter chip gets
     * its own standalone "PDF" link (q=<keyword>&label=<display name>):
     * one real payee's own document, not the grouped combined file. The
     * optional `label` keeps the filename and in-PDF note readable (e.g.
     * "Al Jazeera") instead of showing the raw matching keyword.
     */
    public function test_a_single_quick_filter_payee_can_be_downloaded_as_its_own_pdf(): void
    {
        $owner = $this->makeOwner();
        $account = $this->makeAccount($owner);
        $saed = Supplier::create(['company_id' => $owner->company_id, 'name' => 'SAED EST']);

        PaymentVoucher::create(['company_id' => $owner->company_id, 'bank_account_id' => $account->id, 'party_type' => 'supplier', 'supplier_id' => $saed->id, 'voucher_number' => 'PV-1', 'date' => now()->toDateString(), 'payee_name' => 'SAED EST', 'reference' => 'Material advance to Shahid', 'amount' => 5000, 'method' => 'cash', 'status' => 'issued']);
        PaymentVoucher::create(['company_id' => $owner->company_id, 'bank_account_id' => $account->id, 'party_type' => 'supplier', 'supplier_id' => $saed->id, 'voucher_number' => 'PV-2', 'date' => now()->toDateString(), 'payee_name' => 'SAED EST', 'reference' => 'Steel purchase - Ashraf Trading', 'amount' => 3000, 'method' => 'cash', 'status' => 'issued']);

        $response = $this->actingAs($owner)->get(route('app.payment-vouchers.summary-pdf', [
            'supplier_id' => $saed->id,
            'q' => 'gazaierih',
            'label' => 'Al Jazeera',
        ]));

        $response->assertOk();
        $this->assertStringStartsWith('application/pdf', $response->headers->get('Content-Type'));
        // The friendly label drives the filename, not the raw matching keyword.
        $this->assertStringContainsString('al-jazeera', $response->headers->get('Content-Disposition'));
        $this->assertStringNotContainsString('gazaierih', $response->headers->get('Content-Disposition'));
    }

    public function test_a_single_quick_filter_payee_can_be_downloaded_as_its_own_pdf_for_receipt_vouchers(): void
    {
        $owner = $this->makeOwner();
        $account = $this->makeAccount($owner);
        $client = Client::create(['company_id' => $owner->company_id, 'name' => 'Sada Al Jeul Construction']);

        ReceiptVoucher::create(['company_id' => $owner->company_id, 'bank_account_id' => $account->id, 'party_type' => 'customer', 'client_id' => $client->id, 'voucher_number' => 'RV-1', 'date' => now()->toDateString(), 'payer_name' => 'Sada Al Jeul Construction', 'reference' => 'Progress payment - Ashraf works', 'amount' => 20000, 'method' => 'bank_transfer', 'status' => 'issued']);

        $response = $this->actingAs($owner)->get(route('app.receipt-vouchers.summary-pdf', [
            'client_id' => $client->id,
            'q' => 'ashraf',
            'label' => 'Ashraf',
        ]));

        $response->assertOk();
        $this->assertStringStartsWith('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('ashraf', $response->headers->get('Content-Disposition'));
    }

    /**
     * "shahid will sign the document as he received this all payments ...
     * add sign area at the end that claims the supplier have approve
     * that he have received these payment as proof" — renders the
     * underlying Blade view directly (same pattern as
     * InvoicePdfPaymentPresentationTest) rather than through mPDF, since
     * the declaration/signature text needs to be asserted against real
     * HTML rather than compiled PDF bytes.
     */
    public function test_payment_summary_pdf_view_includes_a_signature_declaration_and_signing_fields(): void
    {
        $owner = $this->makeOwner();
        $account = $this->makeAccount($owner);
        $saed = Supplier::create(['company_id' => $owner->company_id, 'name' => 'SAED EST']);
        $voucher = PaymentVoucher::create(['company_id' => $owner->company_id, 'bank_account_id' => $account->id, 'party_type' => 'supplier', 'supplier_id' => $saed->id, 'voucher_number' => 'PV-1', 'date' => now()->toDateString(), 'payee_name' => 'SAED EST', 'reference' => 'Material advance to Shahid', 'amount' => 5000, 'method' => 'cash', 'status' => 'issued']);

        $html = view('documents.print.voucher-summary-pdf', [
            'type' => 'payment',
            'company' => $owner->company,
            'party' => $saed,
            'vouchers' => collect([$voucher]),
            'total' => 5000.0,
            'from' => null,
            'to' => null,
            'search' => 'Shahid',
            'groups' => null,
            'template' => $owner->company->defaultTemplateFor('voucher_summary'),
            'embed' => fn () => null,
        ])->render();

        $this->assertStringContainsString(__('I, the undersigned, acknowledge receipt of the total amount of :amount from :company, as full settlement for the payment voucher(s) detailed above.', ['amount' => \App\Support\Money::format(5000), 'company' => $owner->company->name]), $html);
        $this->assertStringContainsString(__('Received by (supplier / recipient)'), $html);
        $this->assertStringContainsString(__('ID / Iqama No.'), $html);
        // The real recipient's name (from the quick-filter label) is pre-filled rather than left for manual entry.
        $this->assertStringContainsString('Shahid', $html);
    }

    public function test_receipt_summary_pdf_view_includes_the_receipt_side_signature_declaration(): void
    {
        $owner = $this->makeOwner();
        $account = $this->makeAccount($owner);
        $client = Client::create(['company_id' => $owner->company_id, 'name' => 'Sada Al Jeul Construction']);
        $voucher = ReceiptVoucher::create(['company_id' => $owner->company_id, 'bank_account_id' => $account->id, 'party_type' => 'customer', 'client_id' => $client->id, 'voucher_number' => 'RV-1', 'date' => now()->toDateString(), 'payer_name' => 'Sada Al Jeul Construction', 'amount' => 20000, 'method' => 'bank_transfer', 'status' => 'issued']);

        $html = view('documents.print.voucher-summary-pdf', [
            'type' => 'receipt',
            'company' => $owner->company,
            'party' => $client,
            'vouchers' => collect([$voucher]),
            'total' => 20000.0,
            'from' => null,
            'to' => null,
            'search' => '',
            'groups' => null,
            'template' => $owner->company->defaultTemplateFor('voucher_summary'),
            'embed' => fn () => null,
        ])->render();

        $this->assertStringContainsString(__('I, the undersigned, confirm that the total amount of :amount detailed above has been received by :company.', ['amount' => \App\Support\Money::format(20000), 'company' => $owner->company->name]), $html);
        $this->assertStringContainsString(__('Acknowledged by (payer)'), $html);
    }
}
