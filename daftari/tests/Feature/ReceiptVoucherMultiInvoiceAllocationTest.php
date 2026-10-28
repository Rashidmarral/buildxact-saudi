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
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "i need to show the exact amount i have received and below shows the
 * remaining to add it or settle it like i have received 157000
 * payment how can i show it seperate like client ask i have not sent
 * the payment this way and also there is one transfer refrence how
 * can i deal with it" — splitting one real transfer into several
 * Receipt Vouchers correctly settled each invoice but broke the
 * client-facing statement: it no longer showed the one real amount
 * they actually sent, or its one real bank reference. A single voucher
 * can now allocate its one real amount across several invoices.
 */
class ReceiptVoucherMultiInvoiceAllocationTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    private function makeInvoice(Company $company, Client $client, string $number, float $total): Invoice
    {
        return Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => $number,
            'status' => 'sent', 'issue_date' => now(), 'currency' => 'SAR',
            'subtotal' => round($total / 1.15, 2), 'vat_total' => round($total - $total / 1.15, 2), 'total' => $total,
        ]);
    }

    public function test_one_voucher_can_settle_two_invoices_at_once(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $account = BankAccount::create(['company_id' => $company->id, 'name' => 'Operating Account', 'type' => 'bank', 'is_active' => true]);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Sada Al Jeul Construction']);
        $inv3 = $this->makeInvoice($company, $client, 'INV-00003', 230000);
        // Already partially paid, 72,477.89 remaining — a real
        // InvoicePayment row, not just the column, since
        // settleInvoiceAndPost() recomputes amount_paid from the sum of
        // actual payment rows every time it runs.
        $inv3->invoicePayments()->create(['amount' => 157522.11, 'paid_at' => now()->subWeek()]);
        $inv3->update(['amount_paid' => 157522.11]);
        $inv5 = $this->makeInvoice($company, $client, 'INV-2026-00005', 134020.43);

        $response = $this->actingAs($owner)->post(route('app.receipt-vouchers.store'), [
            'party_type' => 'customer', 'client_id' => $client->id, 'payer_name' => $client->name,
            'bank_account_id' => $account->id, 'date' => now()->toDateString(), 'amount' => 157000,
            'method' => 'bank_transfer', 'reference' => 'FT262999XYZQ',
            'allocations' => [
                ['invoice_id' => $inv3->id, 'amount' => 72477.89],
                ['invoice_id' => $inv5->id, 'amount' => 84522.11],
            ],
        ]);

        $response->assertRedirect();
        $voucher = ReceiptVoucher::first();
        $this->assertEqualsWithDelta(157000, (float) $voucher->amount, 0.01);
        $this->assertNull($voucher->invoice_id);
        $this->assertSame(2, $voucher->allocations()->count());

        $inv3->refresh();
        $inv5->refresh();
        $this->assertSame('paid', $inv3->status);
        $this->assertEqualsWithDelta(0.0, $inv3->balanceDue(), 0.01);
        $this->assertSame('partially_paid', $inv5->status);
        $this->assertEqualsWithDelta(84522.11, (float) $inv5->amount_paid, 0.01);

        // Exactly one journal entry for the whole voucher amount — not one per invoice.
        $entries = JournalEntry::where('source_type', 'receipt_voucher')->where('source_id', $voucher->id)->get();
        $this->assertCount(1, $entries);
        $this->assertEqualsWithDelta(157000, (float) $entries->first()->lines()->where('debit', '>', 0)->sum('debit'), 0.01);

        $this->assertSame(2, InvoicePayment::whereIn('id', ReceiptVoucherAllocation::pluck('invoice_payment_id'))->count());
    }

    public function test_allocations_exceeding_the_voucher_amount_are_rejected(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $account = BankAccount::create(['company_id' => $company->id, 'name' => 'Operating Account', 'type' => 'bank', 'is_active' => true]);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Sada Al Jeul Construction']);
        $inv1 = $this->makeInvoice($company, $client, 'INV-A', 100000);
        $inv2 = $this->makeInvoice($company, $client, 'INV-B', 100000);

        $response = $this->actingAs($owner)->post(route('app.receipt-vouchers.store'), [
            'party_type' => 'customer', 'client_id' => $client->id, 'payer_name' => $client->name,
            'bank_account_id' => $account->id, 'date' => now()->toDateString(), 'amount' => 50000,
            'method' => 'bank_transfer',
            'allocations' => [
                ['invoice_id' => $inv1->id, 'amount' => 30000],
                ['invoice_id' => $inv2->id, 'amount' => 30000],
            ],
        ]);

        $response->assertSessionHasErrors('allocations');
        $this->assertSame(0, ReceiptVoucher::count());
    }

    public function test_an_allocation_to_another_clients_invoice_is_rejected(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $account = BankAccount::create(['company_id' => $company->id, 'name' => 'Operating Account', 'type' => 'bank', 'is_active' => true]);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Sada Al Jeul Construction']);
        $otherClient = Client::create(['company_id' => $company->id, 'name' => 'Another Client']);
        $foreignInvoice = $this->makeInvoice($company, $otherClient, 'INV-OTHER', 50000);

        $response = $this->actingAs($owner)->post(route('app.receipt-vouchers.store'), [
            'party_type' => 'customer', 'client_id' => $client->id, 'payer_name' => $client->name,
            'bank_account_id' => $account->id, 'date' => now()->toDateString(), 'amount' => 10000,
            'method' => 'bank_transfer',
            'allocations' => [['invoice_id' => $foreignInvoice->id, 'amount' => 10000]],
        ]);

        $response->assertSessionHasErrors('allocations');
        $this->assertSame(0, ReceiptVoucherAllocation::count());
    }

    public function test_the_account_statement_shows_one_combined_payment_line_matching_the_real_transfer(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $account = BankAccount::create(['company_id' => $company->id, 'name' => 'Operating Account', 'type' => 'bank', 'is_active' => true]);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Sada Al Jeul Construction']);
        $inv3 = $this->makeInvoice($company, $client, 'INV-00003', 230000);
        $inv3->invoicePayments()->create(['amount' => 157522.11, 'paid_at' => now()->subWeek()]);
        $inv3->update(['amount_paid' => 157522.11]);
        $inv5 = $this->makeInvoice($company, $client, 'INV-2026-00005', 134020.43);

        $this->actingAs($owner)->post(route('app.receipt-vouchers.store'), [
            'party_type' => 'customer', 'client_id' => $client->id, 'payer_name' => $client->name,
            'bank_account_id' => $account->id, 'date' => now()->toDateString(), 'amount' => 157000,
            'method' => 'bank_transfer', 'reference' => 'FT262999XYZQ',
            'allocations' => [
                ['invoice_id' => $inv3->id, 'amount' => 72477.89],
                ['invoice_id' => $inv5->id, 'amount' => 84522.11],
            ],
        ]);
        $voucher = ReceiptVoucher::first();

        // The CSV export is the precise, structured way to check this —
        // the on-screen page can legitimately print "72,477.89" too, as
        // the running BALANCE after the invoice's own debit line posts
        // (nothing to do with how the payment itself is split).
        $response = $this->actingAs($owner)->get(route('app.reports.account-statement', ['type' => 'customer', 'client_id' => $client->id, 'export' => 'csv']));

        $response->assertOk();
        $rows = array_map('str_getcsv', explode("\n", trim($response->streamedContent())));
        $paymentRows = array_filter($rows, fn ($row) => isset($row[3]) && $row[3] === '157000.00');

        // The one real transfer shows as exactly one credit line, for its
        // exact real amount — never split into 72,477.89 / 84,522.11.
        $this->assertCount(1, $paymentRows);
        $description = array_values($paymentRows)[0][1];
        $this->assertStringContainsString('FT262999XYZQ', $description);
        $this->assertStringContainsString($voucher->voucher_number, $description);
        $this->assertStringContainsString('INV-00003', $description);
        $this->assertStringContainsString('INV-2026-00005', $description);
        $this->assertEmpty(array_filter($rows, fn ($row) => isset($row[3]) && in_array($row[3], ['72477.89', '84522.11'], true)));
    }

    public function test_voiding_the_voucher_reverses_both_invoices(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $account = BankAccount::create(['company_id' => $company->id, 'name' => 'Operating Account', 'type' => 'bank', 'is_active' => true]);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Sada Al Jeul Construction']);
        $inv1 = $this->makeInvoice($company, $client, 'INV-A', 100000);
        $inv2 = $this->makeInvoice($company, $client, 'INV-B', 100000);

        $this->actingAs($owner)->post(route('app.receipt-vouchers.store'), [
            'party_type' => 'customer', 'client_id' => $client->id, 'payer_name' => $client->name,
            'bank_account_id' => $account->id, 'date' => now()->toDateString(), 'amount' => 150000,
            'method' => 'bank_transfer',
            'allocations' => [
                ['invoice_id' => $inv1->id, 'amount' => 100000],
                ['invoice_id' => $inv2->id, 'amount' => 50000],
            ],
        ]);
        $voucher = ReceiptVoucher::first();

        $this->actingAs($owner)->post(route('app.receipt-vouchers.void', $voucher))->assertRedirect();

        $this->assertSame('sent', $inv1->fresh()->status);
        $this->assertSame('sent', $inv2->fresh()->status);
        $this->assertEqualsWithDelta(0.0, (float) $inv1->fresh()->amount_paid, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $inv2->fresh()->amount_paid, 0.01);
        // void() reverses the posting (an offsetting entry), it doesn't
        // delete the original — same convention as every other voucher.
        $this->assertSame(1, JournalEntry::where('source_type', 'receipt_voucher_reversal')->where('source_id', $voucher->id)->count());
    }
}
