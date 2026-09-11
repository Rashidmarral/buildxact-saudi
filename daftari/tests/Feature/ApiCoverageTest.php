<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\BillPayment;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Audit finding MEDIUM-20: the public API (routes/api.php) only ever
 * covered Clients/Items/Invoices — a token holder had no way to read
 * Bills, invoice/bill Payments, Journal Entries, or the core financial
 * reports (Trial Balance/Balance Sheet/Income Statement), even though the
 * web panel exposes all of that. New read-only v1 endpoints mirror the
 * same company-scoping and permission gates their web-panel equivalents
 * already use. The Trial Balance/Balance Sheet/Income Statement JSON
 * endpoints share FinancialReportService with the web report pages, so
 * this doubles as a regression check that the extraction of that service
 * out of ReportController didn't change any figures.
 */
class ApiCoverageTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): User
    {
        $company = Company::create(['name' => 'API Co.', 'slug' => 'api-'.uniqid()]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    public function test_bills_index_and_show_return_only_the_callers_own_company(): void
    {
        $owner = $this->makeOwner();
        $otherOwner = $this->makeOwner();

        $supplier = Supplier::create(['company_id' => $owner->company_id, 'name' => 'ACME Supplies']);
        $bill = Bill::create([
            'company_id' => $owner->company_id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-1',
            'status' => 'draft', 'bill_date' => now()->toDateString(), 'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
        ]);
        BillItem::create(['bill_id' => $bill->id, 'description' => 'Supplies', 'quantity' => 1, 'unit_price' => 100, 'vat_rate' => 15, 'vat_amount' => 15, 'line_total' => 115]);

        $otherSupplier = Supplier::create(['company_id' => $otherOwner->company_id, 'name' => 'Other Supplies']);
        $otherBill = Bill::create([
            'company_id' => $otherOwner->company_id, 'supplier_id' => $otherSupplier->id, 'bill_number' => 'BILL-9',
            'status' => 'draft', 'bill_date' => now()->toDateString(), 'subtotal' => 50, 'vat_total' => 0, 'total' => 50,
        ]);

        Sanctum::actingAs($owner, ['read']);

        $index = $this->getJson(route('api.v1.bills.index'));
        $index->assertOk();
        $index->assertJsonCount(1, 'data');
        $index->assertJsonPath('data.0.bill_number', 'BILL-1');

        $show = $this->getJson(route('api.v1.bills.show', $bill));
        $show->assertOk()->assertJsonPath('bill_number', 'BILL-1')->assertJsonPath('items.0.description', 'Supplies');

        $this->getJson(route('api.v1.bills.show', $otherBill))->assertNotFound();
    }

    public function test_invoice_and_bill_payments_are_listed_nested_under_their_parent(): void
    {
        $owner = $this->makeOwner();
        $client = Client::create(['company_id' => $owner->company_id, 'client_code' => 'C-1', 'type' => 'company', 'name' => 'A Client']);
        $invoice = Invoice::create([
            'company_id' => $owner->company_id, 'client_id' => $client->id, 'invoice_number' => 'INV-1', 'type' => 'standard',
            'issue_date' => now(), 'status' => 'sent', 'currency' => 'SAR', 'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
        ]);
        InvoicePayment::create(['invoice_id' => $invoice->id, 'amount' => 50, 'paid_at' => now(), 'method' => 'bank_transfer']);

        $supplier = Supplier::create(['company_id' => $owner->company_id, 'name' => 'A Supplier']);
        $bill = Bill::create([
            'company_id' => $owner->company_id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-1',
            'status' => 'posted', 'bill_date' => now()->toDateString(), 'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
        ]);
        BillPayment::create(['bill_id' => $bill->id, 'amount' => 40, 'paid_at' => now(), 'method' => 'bank_transfer']);

        Sanctum::actingAs($owner, ['read']);

        $invoicePayments = $this->getJson(route('api.v1.invoices.payments.index', $invoice));
        $invoicePayments->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.amount', 50);

        $billPayments = $this->getJson(route('api.v1.bills.payments.index', $bill));
        $billPayments->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.amount', 40);
    }

    private function postEntry(User $owner, float $amount): JournalEntry
    {
        $cash = Account::where('company_id', $owner->company_id)->where('type', 'asset')->first();
        $revenue = Account::where('company_id', $owner->company_id)->where('type', 'revenue')->first();

        $entry = JournalEntry::create([
            'company_id' => $owner->company_id, 'entry_number' => 'JE-'.uniqid(), 'entry_date' => now()->toDateString(),
            'source_type' => 'manual', 'source_id' => 0, 'description' => 'Cash sale',
        ]);
        JournalEntryLine::create(['journal_entry_id' => $entry->id, 'account_id' => $cash->id, 'debit' => $amount, 'credit' => 0]);
        JournalEntryLine::create(['journal_entry_id' => $entry->id, 'account_id' => $revenue->id, 'debit' => 0, 'credit' => $amount]);

        return $entry;
    }

    public function test_journal_entries_index_and_show_are_scoped_and_show_lines(): void
    {
        $owner = $this->makeOwner();
        $entry = $this->postEntry($owner, 1000);

        Sanctum::actingAs($owner, ['read']);

        $index = $this->getJson(route('api.v1.journal-entries.index'));
        $index->assertOk()->assertJsonCount(1, 'data');

        $show = $this->getJson(route('api.v1.journal-entries.show', $entry));
        $show->assertOk()->assertJsonCount(2, 'lines');
    }

    public function test_trial_balance_balance_sheet_and_income_statement_endpoints_return_the_same_figures_as_the_ledger(): void
    {
        $owner = $this->makeOwner();
        $this->postEntry($owner, 1000);

        Sanctum::actingAs($owner, ['read']);

        $trialBalance = $this->getJson(route('api.v1.reports.trial-balance'));
        $trialBalance->assertOk();
        $this->assertEquals(1000, $trialBalance->json('total_debit'));
        $this->assertEquals(1000, $trialBalance->json('total_credit'));

        $balanceSheet = $this->getJson(route('api.v1.reports.balance-sheet'));
        $balanceSheet->assertOk()->assertJsonPath('balanced', true);

        $incomeStatement = $this->getJson(route('api.v1.reports.income-statement'));
        $incomeStatement->assertOk();
        $this->assertEquals(1000, $incomeStatement->json('net_sales'));
    }

    public function test_a_read_only_token_cannot_reach_endpoints_without_the_matching_permission(): void
    {
        $owner = $this->makeOwner();
        $staff = User::factory()->create(['role' => 'staff', 'company_id' => $owner->company_id, 'status' => 'active']);

        Sanctum::actingAs($staff, ['read']);

        $this->getJson(route('api.v1.bills.index'))->assertForbidden();
        $this->getJson(route('api.v1.journal-entries.index'))->assertForbidden();
        $this->getJson(route('api.v1.reports.trial-balance'))->assertForbidden();
    }
}
