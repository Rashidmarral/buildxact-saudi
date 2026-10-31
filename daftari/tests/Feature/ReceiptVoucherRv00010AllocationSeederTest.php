<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\BankAccount;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\JournalEntry;
use App\Models\ReceiptVoucher;
use App\Models\ReceiptVoucherAllocation;
use Database\Seeders\ReceiptVoucherRv00010AllocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Master Report, Section 12: RV-00010 should be split across the two
 * invoices it actually funded — traceability only, no GL change and no
 * new InvoicePayment (both already exist).
 */
class ReceiptVoucherRv00010AllocationSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedFixture(): array
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting Company', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR', 'vat_number' => '314526094900003']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        $bank = BankAccount::create(['company_id' => $company->id, 'name' => 'SNB — Operating Account', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true]);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Sada Al Jeul Construction Establishment']);

        $inv1 = Invoice::create(['company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-00003', 'issue_date' => '2026-10-21', 'status' => 'paid', 'currency' => 'SAR', 'subtotal' => 200000, 'vat_total' => 30000, 'total' => 230000.00, 'amount_paid' => 230000.00]);
        $inv2 = Invoice::create(['company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-2026-00005', 'issue_date' => '2026-10-10', 'status' => 'partially_paid', 'currency' => 'SAR', 'subtotal' => 116539.50, 'vat_total' => 17480.93, 'total' => 134020.43, 'amount_paid' => 110569.06]);

        $payment1 = InvoicePayment::create(['invoice_id' => $inv1->id, 'amount' => 72489.39, 'paid_at' => '2026-10-20', 'method' => 'bank_transfer', 'reference' => 'FT26293FQMQP']);
        $payment2 = InvoicePayment::create(['invoice_id' => $inv2->id, 'amount' => 85021.22, 'paid_at' => '2026-10-20', 'method' => 'bank_transfer', 'reference' => 'FT26293FQMQP']);

        $voucher = ReceiptVoucher::create([
            'company_id' => $company->id, 'bank_account_id' => $bank->id, 'party_type' => 'customer', 'client_id' => $client->id,
            'voucher_number' => 'RV-00010', 'date' => '2026-10-20', 'payer_name' => 'Sada Al Jeul Construction Establishment',
            'amount' => 157510.61, 'method' => 'bank_transfer', 'reference' => 'FT26293FQMQP', 'status' => 'issued',
        ]);

        return [$company, $voucher, $inv1, $inv2, $payment1, $payment2];
    }

    public function test_it_does_nothing_when_the_company_does_not_exist(): void
    {
        (new ReceiptVoucherRv00010AllocationSeeder)->run();

        $this->assertSame(0, ReceiptVoucherAllocation::count());
    }

    public function test_it_links_the_voucher_to_both_invoice_payments_without_a_gl_change(): void
    {
        [$company, $voucher, $inv1, $inv2, $payment1, $payment2] = $this->seedFixture();
        $journalCountBefore = JournalEntry::count();
        $paid1Before = (float) $inv1->fresh()->amount_paid;
        $paid2Before = (float) $inv2->fresh()->amount_paid;

        (new ReceiptVoucherRv00010AllocationSeeder)->run();

        $this->assertSame(2, ReceiptVoucherAllocation::where('receipt_voucher_id', $voucher->id)->count());

        $allocation1 = ReceiptVoucherAllocation::where('receipt_voucher_id', $voucher->id)->where('invoice_id', $inv1->id)->first();
        $this->assertNotNull($allocation1);
        $this->assertSame($payment1->id, $allocation1->invoice_payment_id);
        $this->assertEqualsWithDelta(72489.39, (float) $allocation1->amount, 0.01);

        $allocation2 = ReceiptVoucherAllocation::where('receipt_voucher_id', $voucher->id)->where('invoice_id', $inv2->id)->first();
        $this->assertNotNull($allocation2);
        $this->assertSame($payment2->id, $allocation2->invoice_payment_id);
        $this->assertEqualsWithDelta(85021.22, (float) $allocation2->amount, 0.01);

        $this->assertSame($journalCountBefore, JournalEntry::count(), 'must never post a journal entry — the voucher is already posted');
        $this->assertSame(2, InvoicePayment::count(), 'must never create a new invoice payment — both already exist');
        $this->assertEqualsWithDelta($paid1Before, (float) $inv1->fresh()->amount_paid, 0.01);
        $this->assertEqualsWithDelta($paid2Before, (float) $inv2->fresh()->amount_paid, 0.01);
    }

    public function test_it_errors_cleanly_when_a_matching_payment_is_missing(): void
    {
        [$company, $voucher, , , , $payment2] = $this->seedFixture();
        $payment2->delete();

        (new ReceiptVoucherRv00010AllocationSeeder)->run();

        $this->assertSame(0, ReceiptVoucherAllocation::count());
    }

    public function test_reseeding_does_not_duplicate_the_allocations(): void
    {
        [$company, $voucher] = $this->seedFixture();

        (new ReceiptVoucherRv00010AllocationSeeder)->run();
        (new ReceiptVoucherRv00010AllocationSeeder)->run();

        $this->assertSame(2, ReceiptVoucherAllocation::where('receipt_voucher_id', $voucher->id)->count());
    }
}
