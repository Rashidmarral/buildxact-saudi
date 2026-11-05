<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Bill;
use App\Models\Client;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\Invoice;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Go-live gap analysis finding: credit notes, debit notes, and purchase
 * returns against a foreign-currency invoice/bill posted their raw
 * document-currency amounts straight into the base-currency ledger with
 * no conversion at all — unlike invoices, bills, and payments, which all
 * already convert through their own exchange_rate. A USD credit note
 * against a company with SAR as its base currency would book the raw
 * USD figures as if they were SAR.
 */
class CreditDebitNoteFxPostingTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $company = Company::create(['name' => 'FX Co.', 'slug' => 'fx-'.uniqid(), 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return $company;
    }

    public function test_a_credit_note_against_a_foreign_currency_invoice_posts_in_base_currency(): void
    {
        $company = $this->makeCompany();
        $client = Client::create(['company_id' => $company->id, 'name' => 'USD Client']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-FX-1',
            'issue_date' => now(), 'status' => 'sent', 'currency' => 'USD', 'exchange_rate' => 3.75,
        ]);
        $creditNote = CreditNote::create([
            'company_id' => $company->id, 'invoice_id' => $invoice->id, 'client_id' => $client->id,
            'credit_note_number' => 'CN-FX-1', 'issue_date' => now(), 'status' => 'issued',
            'currency' => 'USD', 'exchange_rate' => 3.75,
            'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
        ]);

        $entry = app(LedgerPostingService::class)->postCreditNote($creditNote);

        $this->assertNotNull($entry);
        // 100 USD subtotal * 3.75 = 375 SAR; 15 USD VAT * 3.75 = 56.25 SAR;
        // 115 USD total * 3.75 = 431.25 SAR — every line converted, not
        // the raw USD figures.
        $totalDebits = (float) $entry->lines()->sum('debit');
        $totalCredits = (float) $entry->lines()->sum('credit');
        $this->assertSame(431.25, $totalDebits);
        $this->assertSame(431.25, $totalCredits);
    }

    public function test_a_debit_note_against_a_foreign_currency_invoice_posts_in_base_currency(): void
    {
        $company = $this->makeCompany();
        $client = Client::create(['company_id' => $company->id, 'name' => 'USD Client']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-FX-2',
            'issue_date' => now(), 'status' => 'sent', 'currency' => 'USD', 'exchange_rate' => 3.75,
        ]);
        $debitNote = DebitNote::create([
            'company_id' => $company->id, 'invoice_id' => $invoice->id, 'client_id' => $client->id,
            'debit_note_number' => 'DN-FX-1', 'issue_date' => now(), 'status' => 'issued',
            'currency' => 'USD', 'exchange_rate' => 3.75,
            'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
        ]);

        $entry = app(LedgerPostingService::class)->postDebitNote($debitNote);

        $this->assertNotNull($entry);
        $totalDebits = (float) $entry->lines()->sum('debit');
        $this->assertSame(431.25, $totalDebits);
    }

    public function test_a_purchase_return_against_a_foreign_currency_bill_posts_in_base_currency(): void
    {
        $company = $this->makeCompany();
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'USD Supplier']);
        $bill = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-FX-1',
            'bill_date' => now(), 'due_date' => now()->addDays(30), 'status' => 'received',
            'currency' => 'USD', 'exchange_rate' => 3.75,
        ]);
        $purchaseReturn = PurchaseReturn::create([
            'company_id' => $company->id, 'bill_id' => $bill->id, 'supplier_id' => $supplier->id,
            'return_number' => 'PR-FX-1', 'issue_date' => now(), 'status' => 'issued',
            'currency' => 'USD', 'exchange_rate' => 3.75,
            'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
        ]);

        $entry = app(LedgerPostingService::class)->postPurchaseReturn($purchaseReturn);

        $this->assertNotNull($entry);
        $totalDebits = (float) $entry->lines()->sum('debit');
        $this->assertSame(431.25, $totalDebits);
    }
}
