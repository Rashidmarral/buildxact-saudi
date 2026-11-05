<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\BillPayment;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every report PDF now renders on the shared, bilingual, branded
 * report-PDF design via MpdfRenderer (replacing the old plain
 * Barryvdh\DomPDF layouts, which also didn't shape Arabic glyphs
 * correctly). VAT, balance sheet, and account statement were rebuilt;
 * trial balance, income statement, sales, expenses, WHT return, cash
 * flow, and aging previously had no PDF export at all (CSV only) —
 * these smoke-test every one of them end to end to catch a Blade
 * error the view tests alone wouldn't (none of them hit the PDF
 * branch).
 */
class ReportPdfExportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_vat_report_pdf_downloads(): void
    {
        $company = Company::create(['name' => 'Smoke Co', 'name_ar' => 'شركة', 'slug' => 'smoke-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client']);
        $invoice = Invoice::create(['company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-1', 'issue_date' => now(), 'status' => 'sent', 'currency' => 'SAR', 'subtotal' => 100, 'vat_total' => 15, 'total' => 115]);

        foreach (['sales', 'purchases', 'expenses'] as $tab) {
            $response = $this->actingAs($owner)->get(route('app.reports.vat', ['export' => 'pdf', 'tab' => $tab]));
            $response->assertOk();
            $response->assertHeader('Content-Type', 'application/pdf');
        }
    }

    public function test_balance_sheet_pdf_downloads(): void
    {
        $company = Company::create(['name' => 'Smoke Co 2', 'name_ar' => 'شركة 2', 'slug' => 'smoke2-'.uniqid()]);
        \App\Models\Account::seedSystemAccounts($company->id);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $response = $this->actingAs($owner)->get(route('app.reports.balance-sheet', ['export' => 'pdf']));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_customer_account_statement_pdf_downloads(): void
    {
        $company = Company::create(['name' => 'Smoke Co 3', 'name_ar' => 'شركة 3', 'slug' => 'smoke3-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client', 'name_ar' => 'عميل']);
        $invoice = Invoice::create(['company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-1', 'issue_date' => now(), 'status' => 'sent', 'currency' => 'SAR', 'subtotal' => 100, 'vat_total' => 15, 'total' => 115]);
        InvoicePayment::create(['invoice_id' => $invoice->id, 'amount' => 50, 'paid_at' => now()]);

        $response = $this->actingAs($owner)->get(route('app.reports.account-statement', ['type' => 'customer', 'client_id' => $client->id, 'export' => 'pdf']));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');

        // Legacy links keep working.
        $response = $this->actingAs($owner)->get(route('app.reports.account-statement', ['type' => 'customer', 'client_id' => $client->id, 'export' => 'pdf-ar']));
        $response->assertOk();
    }

    public function test_supplier_account_statement_pdf_downloads(): void
    {
        $company = Company::create(['name' => 'Smoke Co 4', 'slug' => 'smoke4-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Supplier']);
        $bill = Bill::create(['company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'B1', 'bill_date' => now(), 'status' => 'posted', 'currency' => 'SAR', 'subtotal' => 100, 'vat_total' => 15, 'total' => 115]);
        BillPayment::create(['bill_id' => $bill->id, 'amount' => 100, 'paid_at' => now()]);

        $response = $this->actingAs($owner)->get(route('app.reports.account-statement', ['type' => 'supplier', 'supplier_id' => $supplier->id, 'export' => 'pdf']));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_trial_balance_pdf_downloads(): void
    {
        $company = Company::create(['name' => 'TB Co', 'slug' => 'tb-'.uniqid()]);
        \App\Models\Account::seedSystemAccounts($company->id);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $response = $this->actingAs($owner)->get(route('app.reports.trial-balance', ['export' => 'pdf']));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_income_statement_pdf_downloads(): void
    {
        $company = Company::create(['name' => 'IS Co', 'slug' => 'is-'.uniqid()]);
        \App\Models\Account::seedSystemAccounts($company->id);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $response = $this->actingAs($owner)->get(route('app.reports.income-statement', ['export' => 'pdf']));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_sales_report_pdf_downloads(): void
    {
        $company = Company::create(['name' => 'Sales Co', 'slug' => 'sales-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client']);
        $invoice = Invoice::create(['company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-S1', 'issue_date' => now(), 'status' => 'sent', 'currency' => 'SAR', 'subtotal' => 100, 'vat_total' => 15, 'total' => 115]);
        $invoice->items()->create(['description' => 'Line', 'quantity' => 1, 'unit_price' => 100, 'vat_rate' => 15, 'vat_amount' => 15, 'line_total' => 115, 'sort_order' => 0]);

        $response = $this->actingAs($owner)->get(route('app.reports.sales', ['export' => 'pdf']));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_expense_report_pdf_downloads(): void
    {
        $company = Company::create(['name' => 'Exp Co', 'slug' => 'exp-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        \App\Models\Expense::create(['company_id' => $company->id, 'expense_date' => now(), 'vendor_name' => 'Vendor', 'description' => 'desc', 'amount' => 100, 'vat_amount' => 15, 'status' => 'approved']);

        $response = $this->actingAs($owner)->get(route('app.reports.expenses', ['export' => 'pdf']));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_wht_return_pdf_downloads(): void
    {
        $company = Company::create(['name' => 'Wht Co', 'slug' => 'wht-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $response = $this->actingAs($owner)->get(route('app.reports.wht-return', ['export' => 'pdf']));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_cash_flow_pdf_downloads(): void
    {
        $company = Company::create(['name' => 'Cf Co', 'slug' => 'cf-'.uniqid()]);
        \App\Models\Account::seedSystemAccounts($company->id);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $response = $this->actingAs($owner)->get(route('app.reports.cash-flow', ['export' => 'pdf']));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_aging_pdf_downloads(): void
    {
        $company = Company::create(['name' => 'Aging Co', 'slug' => 'aging-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client']);
        Invoice::create(['company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-AG1', 'issue_date' => now()->subDays(40), 'due_date' => now()->subDays(10), 'status' => 'overdue', 'currency' => 'SAR', 'subtotal' => 100, 'vat_total' => 15, 'total' => 115]);

        $response = $this->actingAs($owner)->get(route('app.reports.aging', ['export' => 'pdf']));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }
}
