<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\BankAccount;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceTemplate;
use App\Models\PaymentVoucher;
use App\Models\ReceiptVoucher;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Feature request: the Payment/Receipt Voucher print design was a plain
 * key-value list — this rebuilds it to match the classic bilingual
 * "سند صرف" / Payment Voucher form Saudi companies print for bank/cheque
 * clearance (amount spelled out in words, a cash/cheque checkbox pair,
 * bilingual field labels), plus lets each company upload its own footer
 * banner image (mirroring the existing letterhead upload) and auto-fills
 * a voucher's party fields from the selected customer/supplier record.
 */
class VoucherRedesignTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $company = Company::create(['name' => 'Voucher Design Co.', 'slug' => 'voucher-design-'.uniqid()]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    private function makeBankAccount(Company $company, string $bankName = 'Al Rajhi Bank'): BankAccount
    {
        return BankAccount::create([
            'company_id' => $company->id, 'name' => 'Main Account', 'bank_name' => $bankName,
            'type' => 'bank', 'is_active' => true,
        ]);
    }

    public function test_a_cash_payment_voucher_shows_the_amount_spelled_out_and_the_cash_checkbox(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $account = $this->makeBankAccount($company);

        $response = $this->actingAs($owner)->post(route('app.payment-vouchers.store'), [
            'party_type' => 'manual',
            'payee_name' => 'Steel Supplies Co.',
            'party_name_ar' => 'شركة توريد الحديد',
            'bank_account_id' => $account->id,
            'date' => now()->toDateString(),
            'amount' => 1500,
            'method' => 'cash',
        ]);

        $voucher = PaymentVoucher::first();
        $this->assertNotNull($voucher);

        $show = $this->actingAs($owner)->get(route('app.payment-vouchers.show', $voucher));

        $show->assertOk();
        $show->assertSee('Steel Supplies Co.');
        $show->assertSee('شركة توريد الحديد');
        $show->assertSee('One Thousand Five Hundred Saudi Riyals Only');
        $show->assertSee('ألف وخمسمائة ريال سعودي فقط لا غير');
        $show->assertSee(__('Accountant'));
        $show->assertSee(__('Receiver'));
    }

    public function test_a_cheque_payment_voucher_shows_the_cheque_number_and_bank(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $account = $this->makeBankAccount($company, 'Saudi National Bank');

        $this->actingAs($owner)->post(route('app.payment-vouchers.store'), [
            'party_type' => 'manual',
            'payee_name' => 'Cheque Recipient',
            'bank_account_id' => $account->id,
            'date' => now()->toDateString(),
            'amount' => 800,
            'method' => 'cheque',
            'reference' => 'CHQ-000123',
        ]);

        $voucher = PaymentVoucher::first();
        $show = $this->actingAs($owner)->get(route('app.payment-vouchers.show', $voucher));

        $show->assertOk();
        $show->assertSee('CHQ-000123');
        $show->assertSee('Saudi National Bank');
    }

    public function test_the_payment_voucher_pdf_downloads_with_the_new_layout(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $account = $this->makeBankAccount($company);

        $this->actingAs($owner)->post(route('app.payment-vouchers.store'), [
            'party_type' => 'manual', 'payee_name' => 'PDF Test Payee',
            'bank_account_id' => $account->id, 'date' => now()->toDateString(),
            'amount' => 250, 'method' => 'cash',
        ]);

        $voucher = PaymentVoucher::first();

        $response = $this->actingAs($owner)->get(route('app.payment-vouchers.pdf', $voucher));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_the_create_form_exposes_supplier_details_for_autofill(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        Supplier::create([
            'company_id' => $company->id, 'name' => 'Autofill Supplier', 'name_ar' => 'مورد التعبئة',
            'vat_number' => '300000000000003', 'phone' => '0500000000', 'email' => 'supplier@example.com',
        ]);

        $response = $this->actingAs($owner)->get(route('app.payment-vouchers.create'));

        $response->assertOk();
        $response->assertSee('300000000000003');
        $response->assertSee('Autofill Supplier');
    }

    public function test_a_company_can_upload_and_remove_a_footer_image_on_a_template(): void
    {
        Storage::fake('public');

        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $template = InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Default', 'document_type' => 'all',
            'layout' => 'bilingual_classic', 'is_default' => true,
        ]);

        $response = $this->actingAs($owner)->put(route('app.invoice-templates.update', $template), [
            'name' => 'Default', 'document_type' => 'all', 'accent_color' => '#0f766e',
            'layout' => 'bilingual_classic', 'language_mode' => 'bilingual', 'table_direction' => 'ltr',
            'footer' => UploadedFile::fake()->image('footer.png', 1600, 200),
        ]);

        $response->assertRedirect();
        $template->refresh();
        $this->assertNotNull($template->footer_path);
        Storage::disk('public')->assertExists($template->footer_path);

        $removal = $this->actingAs($owner)->put(route('app.invoice-templates.update', $template), [
            'name' => 'Default', 'document_type' => 'all', 'accent_color' => '#0f766e',
            'layout' => 'bilingual_classic', 'language_mode' => 'bilingual', 'table_direction' => 'ltr',
            'remove_footer' => '1',
        ]);

        $removal->assertRedirect();
        $template->refresh();
        $this->assertNull($template->footer_path);
    }

    public function test_a_bill_payment_voucher_defaults_its_purpose_line_from_the_bill_and_its_items(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $account = $this->makeBankAccount($company);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Bill Supplier']);
        $bill = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-9001',
            'status' => 'posted', 'bill_date' => now()->toDateString(), 'currency' => $company->currency,
            'subtotal' => 400, 'vat_total' => 0, 'total' => 400,
        ]);
        BillItem::create([
            'bill_id' => $bill->id, 'description' => 'Cement bags', 'quantity' => 10,
            'unit_price' => 40, 'vat_rate' => 0, 'vat_amount' => 0, 'line_total' => 400,
        ]);

        $this->actingAs($owner)->post(route('app.payment-vouchers.store'), [
            'party_type' => 'supplier', 'supplier_id' => $supplier->id, 'bill_id' => $bill->id,
            'payee_name' => $supplier->name, 'bank_account_id' => $account->id,
            'date' => now()->toDateString(), 'amount' => 400, 'method' => 'bank_transfer',
        ]);

        $voucher = PaymentVoucher::first();
        $show = $this->actingAs($owner)->get(route('app.payment-vouchers.show', $voucher));

        $show->assertOk();
        $show->assertSee('BILL-9001');
        $show->assertSee('Cement bags');
    }

    public function test_a_receipt_voucher_defaults_its_purpose_line_from_the_invoice_and_its_items(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $account = $this->makeBankAccount($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Invoice Client']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-7001',
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(),
            'currency' => $company->currency, 'subtotal' => 500, 'vat_total' => 0, 'total' => 500,
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id, 'description' => 'Consulting hours', 'quantity' => 5,
            'unit_price' => 100, 'vat_rate' => 0, 'vat_amount' => 0, 'line_total' => 500,
        ]);

        $this->actingAs($owner)->post(route('app.receipt-vouchers.store'), [
            'party_type' => 'customer', 'client_id' => $client->id, 'invoice_id' => $invoice->id,
            'payer_name' => $client->name, 'bank_account_id' => $account->id,
            'date' => now()->toDateString(), 'amount' => 500, 'method' => 'bank_transfer',
        ]);

        $voucher = ReceiptVoucher::first();
        $show = $this->actingAs($owner)->get(route('app.receipt-vouchers.show', $voucher));

        $show->assertOk();
        $show->assertSee('INV-7001');
        $show->assertSee('Consulting hours');
    }

    public function test_selecting_an_invoice_returns_its_total_date_and_line_items(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Details Client']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-8001',
            'type' => 'standard', 'status' => 'sent', 'issue_date' => '2026-01-15',
            'currency' => $company->currency, 'subtotal' => 1000, 'vat_total' => 150, 'total' => 1150,
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id, 'description' => 'Steel Beam', 'quantity' => 2,
            'unit_price' => 500, 'vat_rate' => 15, 'vat_amount' => 150, 'line_total' => 1150,
        ]);

        $response = $this->actingAs($owner)->get('/app/clients/'.$client->id.'/outstanding-invoices');

        $response->assertOk();
        $response->assertJsonFragment([
            'invoice_number' => 'INV-8001',
            'date' => '2026-01-15',
            'total' => '1,150.00',
            'balance' => '1,150.00',
        ]);
        $response->assertJsonFragment(['description' => 'Steel Beam', 'quantity' => '2']);
    }

    public function test_selecting_a_bill_returns_its_total_date_and_line_items(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Details Supplier']);
        $bill = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-8001',
            'status' => 'posted', 'bill_date' => '2026-02-10', 'currency' => $company->currency,
            'subtotal' => 300, 'vat_total' => 0, 'total' => 300,
        ]);
        BillItem::create([
            'bill_id' => $bill->id, 'description' => 'Office chairs', 'quantity' => 3,
            'unit_price' => 100, 'vat_rate' => 0, 'vat_amount' => 0, 'line_total' => 300,
        ]);

        $response = $this->actingAs($owner)->get('/app/suppliers/'.$supplier->id.'/outstanding-bills');

        $response->assertOk();
        $response->assertJsonFragment([
            'bill_number' => 'BILL-8001',
            'date' => '2026-02-10',
            'total' => '300.00',
            'balance' => '300.00',
        ]);
        $response->assertJsonFragment(['description' => 'Office chairs', 'quantity' => '3']);
    }
}
