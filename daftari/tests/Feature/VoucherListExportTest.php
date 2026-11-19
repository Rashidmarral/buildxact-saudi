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
 * "there is no option to download the report on receipt voucher and
 * payment vouchers to download all the records on show page" — both
 * index pages already had a "Download combined PDF" button, but it only
 * appeared once a specific client/supplier was filtered in
 * (summaryPdf()'s own abort_unless 404'd otherwise) — there was no way to
 * export the whole (unfiltered, or filtered by date only) list at all.
 * CSV export is new; the PDF export now works with no party filter too.
 */
class VoucherListExportTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): array
    {
        $company = Company::create(['name' => 'Export Co.', 'slug' => 'export-co-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $bankAccount = BankAccount::create(['company_id' => $company->id, 'name' => 'Main Bank', 'type' => 'bank', 'currency' => 'SAR']);

        return [$owner, $bankAccount];
    }

    public function test_payment_vouchers_csv_export_includes_every_filtered_row(): void
    {
        [$owner, $bankAccount] = $this->makeOwner();
        PaymentVoucher::create(['company_id' => $owner->company_id, 'bank_account_id' => $bankAccount->id, 'voucher_number' => 'PV-00001', 'date' => '2026-06-01', 'party_type' => 'manual', 'payee_name' => 'Supplier A', 'amount' => 500, 'method' => 'bank_transfer', 'status' => 'issued']);
        PaymentVoucher::create(['company_id' => $owner->company_id, 'bank_account_id' => $bankAccount->id, 'voucher_number' => 'PV-00002', 'date' => '2026-06-05', 'party_type' => 'manual', 'payee_name' => 'Supplier B', 'amount' => 750, 'method' => 'cash', 'status' => 'issued']);

        $response = $this->actingAs($owner)->get(route('app.payment-vouchers.index', ['export' => 'csv']));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();
        $this->assertStringContainsString('PV-00001', $content);
        $this->assertStringContainsString('PV-00002', $content);
        $this->assertStringContainsString('Supplier A', $content);
        $this->assertStringContainsString('750.00', $content);
    }

    public function test_receipt_vouchers_csv_export_includes_every_filtered_row(): void
    {
        [$owner, $bankAccount] = $this->makeOwner();
        ReceiptVoucher::create(['company_id' => $owner->company_id, 'bank_account_id' => $bankAccount->id, 'voucher_number' => 'RV-00001', 'date' => '2026-06-01', 'payer_name' => 'Client A', 'amount' => 1000, 'method' => 'bank_transfer', 'status' => 'issued']);

        $response = $this->actingAs($owner)->get(route('app.receipt-vouchers.index', ['export' => 'csv']));

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('RV-00001', $content);
        $this->assertStringContainsString('Client A', $content);
        $this->assertStringContainsString('1000.00', $content);
    }

    public function test_payment_voucher_combined_pdf_works_without_any_party_filter(): void
    {
        [$owner, $bankAccount] = $this->makeOwner();
        PaymentVoucher::create(['company_id' => $owner->company_id, 'bank_account_id' => $bankAccount->id, 'voucher_number' => 'PV-00001', 'date' => '2026-06-01', 'party_type' => 'manual', 'payee_name' => 'Supplier A', 'amount' => 500, 'method' => 'bank_transfer', 'status' => 'issued']);

        $response = $this->actingAs($owner)->get(route('app.payment-vouchers.summary-pdf'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_receipt_voucher_combined_pdf_works_without_any_party_filter(): void
    {
        [$owner, $bankAccount] = $this->makeOwner();
        ReceiptVoucher::create(['company_id' => $owner->company_id, 'bank_account_id' => $bankAccount->id, 'voucher_number' => 'RV-00001', 'date' => '2026-06-01', 'payer_name' => 'Client A', 'amount' => 1000, 'method' => 'bank_transfer', 'status' => 'issued']);

        $response = $this->actingAs($owner)->get(route('app.receipt-vouchers.summary-pdf'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }
}
