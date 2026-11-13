<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Company;
use App\Models\InvoiceTemplate;
use App\Models\User;
use App\Models\VatReturnPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "fix it for all remaining" — every simple report PDF (VAT, trial
 * balance, balance sheet, income statement, sales, expenses, WHT
 * return, cash flow, customer/supplier reports, aging, VAT return
 * filings) plus four more PDFs outside the Reports menu that share the
 * exact same documents.print.partials.report.header/footer-banner
 * partials (cash custody, cash trackers, Kafeel payments, owner
 * reimbursements) now accept a company's own Invoice Templates design
 * — see InvoiceTemplateController::TYPES and
 * documents/print/partials/report/header.blade.php. These confirm the
 * new document_type keys are usable and that each of the
 * less-commonly-tested controllers still renders with a template
 * configured — AccountStatementTemplateTest already covers the shared
 * header/footer mechanism itself in detail.
 */
class AllReportTemplatesTest extends TestCase
{
    use RefreshDatabase;

    private const NEW_TYPES = [
        'vat_return', 'vat_return_filing', 'trial_balance', 'balance_sheet', 'income_statement',
        'sales_report', 'expense_report', 'wht_return', 'cash_flow_statement',
        'customer_report', 'supplier_report', 'aging_report',
        'cash_custody_report', 'cash_tracker_report', 'kafeel_payments_statement', 'owner_reimbursements_report',
    ];

    private function makeCompany(): Company
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        \App\Models\Account::seedSystemAccounts($company->id);
        \App\Models\AccountMapping::seedDefaults($company->id);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    public function test_every_new_document_type_is_selectable_in_the_template_editor(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);

        foreach (self::NEW_TYPES as $type) {
            $response = $this->actingAs($owner)->post(route('app.invoice-templates.store'), [
                'name' => 'Design for '.$type, 'document_types' => [$type],
            ]);

            $response->assertRedirect();
            $this->assertDatabaseHas('invoice_templates', ['company_id' => $company->id, 'document_type' => $type]);
        }
    }

    public function test_vat_return_filing_pdf_still_renders_with_a_configured_letterhead(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Filing letterhead', 'document_type' => 'vat_return_filing',
            'layout' => 'custom_letterhead', 'is_default' => true, 'letterhead_path' => 'letterheads/fake.png',
        ]);
        $period = VatReturnPeriod::create([
            'company_id' => $company->id, 'period_start' => now()->subMonth(), 'period_end' => now(),
            'output_tax' => 100, 'input_tax_purchases' => 20, 'expense_tax' => 0, 'net_recoverable_input_tax' => 20,
            'credit_brought_forward' => 0, 'amount_payable' => 80, 'credit_carried_forward' => 0, 'created_by' => $owner->id,
        ]);

        $response = $this->actingAs($owner)->get(route('app.reports.vat-returns.pdf', $period));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_cash_custody_report_pdf_still_renders_with_a_configured_footer(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        BankAccount::create(['company_id' => $company->id, 'name' => 'Khalid', 'type' => 'cash', 'is_personal' => true, 'personal_owner_name' => 'Khalid', 'is_active' => true]);
        InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Custody footer', 'document_type' => 'cash_custody_report',
            'layout' => 'minimal', 'is_default' => true, 'footer_path' => 'footers/fake.png',
        ]);

        $response = $this->actingAs($owner)->get(route('app.cash-custody.pdf'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_owner_reimbursements_pdf_still_renders_with_a_configured_footer(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        \App\Models\Expense::create(['company_id' => $company->id, 'paid_by_owner' => true, 'expense_date' => now(), 'vendor_name' => 'Vendor', 'description' => 'desc', 'amount' => 100, 'vat_amount' => 15, 'status' => 'approved']);
        InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Reimbursements footer', 'document_type' => 'owner_reimbursements_report',
            'layout' => 'minimal', 'is_default' => true, 'footer_path' => 'footers/fake.png',
        ]);

        $response = $this->actingAs($owner)->get(route('app.owner-reimbursements.pdf'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }
}
