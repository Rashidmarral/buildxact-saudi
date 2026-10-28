<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\BankAccount;
use App\Models\Client;
use App\Models\Company;
use App\Models\InvoiceTemplate;
use App\Models\MachineryAsset;
use App\Models\PaymentVoucher;
use App\Models\Plan;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\ReceiptVoucher;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "please check other issues as well same as for pdf in complete app" —
 * the same bug found and fixed in letter-pdf.blade.php (a plain inline
 * <img> for a letterhead/footer image just flows once wherever the
 * content happens to end, instead of repeating on every page — losing
 * the header past page 1, and the footer landing mid-page or spilling
 * onto its own trailing page) turned out to exist, unfixed, in every
 * other PDF template that offers a letterhead/footer image:
 * documents.print.pdf (shared by invoices, quotations, bills, credit
 * notes, debit notes, prepayment invoices, and purchase returns),
 * project-cash-flow-pdf, voucher-pdf, voucher-summary-pdf,
 * machinery-statement-pdf, client-statement-pdf, and
 * bank-account-report-pdf. Reproduced directly against a real 20-line
 * quotation with a custom letterhead before fixing it (confirmed: the
 * header never repeated past page 1, and the footer spilled onto its
 * own third page). All were converted to mPDF's own repeating
 * <htmlpageheader>/<htmlpagefooter>, with MpdfRenderer::
 * resolvePageMargins() sizing the page margins to fit from page 1
 * (not just page 2+) — see its own docblock for why that part matters
 * independently of which view is rendering.
 */
class PdfHeaderFooterRepeatsAcrossDocumentTypesTest extends TestCase
{
    use RefreshDatabase;

    private function countPdfPages(string $pdf): int
    {
        preg_match_all('/\/Type\s*\/Page(?!s)/', $pdf, $matches);

        return count($matches[0]);
    }

    public function test_pdf_blade_custom_letterhead_header_is_registered_as_a_repeating_page_header(): void
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'pdf-hdr-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        $client = Client::create(['company_id' => $company->id, 'name' => 'A Client']);
        $quotation = Quotation::create(['company_id' => $company->id, 'client_id' => $client->id, 'quotation_number' => 'QTN-00001', 'type' => 'quotation', 'status' => 'issued', 'issue_date' => now(), 'currency' => 'SAR', 'subtotal' => 100, 'vat_total' => 15, 'total' => 115]);
        QuotationItem::create(['quotation_id' => $quotation->id, 'description' => 'Item', 'quantity' => 1, 'unit_price' => 100, 'vat_rate' => 15, 'vat_amount' => 15, 'line_total' => 115]);

        $data = $quotation->pdfData();
        $data['template']?->delete();
        $data['template'] = new InvoiceTemplate(['layout' => 'custom_letterhead', 'letterhead_path' => 'letterheads/fake.png']);

        $html = view('documents.print.pdf', $data + ['embed' => fn ($path) => $path ? 'data:image/png;base64,fake' : null])->render();

        $this->assertStringContainsString('<htmlpageheader name="letterhead">', $html);
        $this->assertStringContainsString('<sethtmlpageheader name="letterhead" page="ALL"', $html);
    }

    public function test_pdf_blade_footer_is_registered_as_a_repeating_page_footer_on_every_layout(): void
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'pdf-ftr-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        $client = Client::create(['company_id' => $company->id, 'name' => 'A Client']);
        $quotation = Quotation::create(['company_id' => $company->id, 'client_id' => $client->id, 'quotation_number' => 'QTN-00001', 'type' => 'quotation', 'status' => 'issued', 'issue_date' => now(), 'currency' => 'SAR', 'subtotal' => 100, 'vat_total' => 15, 'total' => 115]);
        QuotationItem::create(['quotation_id' => $quotation->id, 'description' => 'Item', 'quantity' => 1, 'unit_price' => 100, 'vat_rate' => 15, 'vat_amount' => 15, 'line_total' => 115]);

        $data = $quotation->pdfData();
        $data['template']?->delete();
        // 'minimal' — not custom_letterhead — proves the footer repeats
        // on every layout, not just the one with a full-width header.
        $data['template'] = new InvoiceTemplate(['layout' => 'minimal', 'footer_path' => 'footers/fake.png']);

        $html = view('documents.print.pdf', $data + ['embed' => fn ($path) => $path ? 'data:image/png;base64,fake' : null])->render();

        $this->assertStringContainsString('<htmlpagefooter name="footer-banner">', $html);
        $this->assertStringContainsString('<sethtmlpagefooter name="footer-banner" page="ALL"', $html);
    }

    public function test_project_cash_flow_pdf_footer_is_registered_as_a_repeating_page_footer(): void
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'pcf-ftr-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);

        $html = view('documents.print.project-cash-flow-pdf', [
            'title' => 'Project Cash Flow Statement',
            'subject' => 'Al-Jumum Yards',
            'company' => $company,
            'template' => new InvoiceTemplate(['footer_path' => 'footers/fake.png']),
            'period' => ['from' => now()->subMonth(), 'to' => now()],
            'summary' => app(\App\Services\BankAccountLedgerService::class)->summarize(collect()),
            'ledger' => app(\App\Services\BankAccountLedgerService::class)->build(projectId: null, bankAccountId: null),
            'embed' => fn ($path) => $path ? 'data:image/png;base64,fake' : null,
        ])->render();

        $this->assertStringContainsString('<htmlpagefooter name="footer-banner">', $html);
        $this->assertStringContainsString('<sethtmlpagefooter name="footer-banner" page="ALL"', $html);
    }

    public function test_voucher_pdf_footer_is_registered_as_a_repeating_page_footer(): void
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'vch-ftr-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        $account = BankAccount::create(['company_id' => $company->id, 'name' => 'Main', 'bank_name' => 'A Bank', 'type' => 'bank', 'is_active' => true]);
        $voucher = PaymentVoucher::create(['company_id' => $company->id, 'bank_account_id' => $account->id, 'party_type' => 'manual', 'voucher_number' => 'PV-1', 'date' => now()->toDateString(), 'payee_name' => 'A Payee', 'amount' => 100, 'method' => 'cash', 'status' => 'issued']);
        $voucher->loadMissing('bankAccount', 'counterAccount');

        $html = view('documents.print.voucher-pdf', [
            'voucher' => $voucher,
            'type' => 'payment',
            'company' => $company,
            'template' => new InvoiceTemplate(['footer_path' => 'footers/fake.png']),
            'embed' => fn ($path) => $path ? 'data:image/png;base64,fake' : null,
        ])->render();

        $this->assertStringContainsString('<htmlpagefooter name="footer-banner">', $html);
        $this->assertStringContainsString('<sethtmlpagefooter name="footer-banner" page="ALL"', $html);
    }

    /**
     * "i have already assign letter head template to receipt voucher
     * but it's not applied to pdf" — voucher-pdf.blade.php embedded
     * $template->footer_path but never checked for the 'custom_letterhead'
     * layout at all, so a company's own uploaded header image could
     * never replace the standard company-info block on either voucher
     * type, even though every other document type with the same
     * layout option (invoices, letters) already honored it.
     */
    public function test_voucher_pdf_custom_letterhead_header_is_registered_as_a_repeating_page_header(): void
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'vch-hdr-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        $account = BankAccount::create(['company_id' => $company->id, 'name' => 'Main', 'bank_name' => 'A Bank', 'type' => 'bank', 'is_active' => true]);
        $voucher = ReceiptVoucher::create(['company_id' => $company->id, 'bank_account_id' => $account->id, 'party_type' => 'manual', 'voucher_number' => 'RV-1', 'date' => now()->toDateString(), 'payer_name' => 'A Payer', 'amount' => 100, 'method' => 'cash', 'status' => 'issued']);
        $voucher->loadMissing('bankAccount', 'counterAccount');

        $html = view('documents.print.voucher-pdf', [
            'voucher' => $voucher,
            'type' => 'receipt',
            'company' => $company,
            'template' => new InvoiceTemplate(['layout' => 'custom_letterhead', 'letterhead_path' => 'letterheads/fake.png']),
            'embed' => fn ($path) => $path ? 'data:image/png;base64,fake' : null,
        ])->render();

        $this->assertStringContainsString('<htmlpageheader name="voucher-letterhead">', $html);
        $this->assertStringContainsString('<sethtmlpageheader name="voucher-letterhead" page="ALL"', $html);
        // The letterhead image replaces the standard company-info block
        // entirely — printing both would duplicate the company's own
        // name/logo/address.
        $this->assertStringNotContainsString('class="company-name"', $html);
    }

    public function test_voucher_summary_pdf_footer_is_registered_as_a_repeating_page_footer(): void
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'vsm-ftr-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        $html = view('documents.print.voucher-summary-pdf', [
            'type' => 'payment',
            'company' => $company,
            'party' => null,
            'vouchers' => collect(),
            'total' => 0.0,
            'from' => null,
            'to' => null,
            'search' => 'A Payee',
            'groups' => null,
            'template' => new InvoiceTemplate(['footer_path' => 'footers/fake.png']),
            'embed' => fn ($path) => $path ? 'data:image/png;base64,fake' : null,
        ])->render();

        $this->assertStringContainsString('<htmlpagefooter name="footer-banner">', $html);
        $this->assertStringContainsString('<sethtmlpagefooter name="footer-banner" page="ALL"', $html);
    }

    public function test_client_statement_pdf_still_renders_fine_with_a_configured_footer(): void
    {
        $plan = Plan::create(['name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000, 'is_active' => true, 'has_client_statements' => true]);
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'cst-ftr-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'A Client']);
        InvoiceTemplate::create(['company_id' => $company->id, 'document_type' => 'all', 'name' => 'Default', 'layout' => 'minimal', 'is_default' => true, 'footer_path' => 'footers/fake.png']);

        $response = $this->actingAs($owner)->get(route('app.client-statements.pdf', $client));

        $response->assertOk();
    }

    public function test_bank_account_report_pdf_still_renders_fine_with_a_configured_footer(): void
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'bar-ftr-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $account = BankAccount::create(['company_id' => $company->id, 'name' => 'SNB Current', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true, 'opening_balance' => 1000]);
        ReceiptVoucher::create(['company_id' => $company->id, 'bank_account_id' => $account->id, 'party_type' => 'customer', 'voucher_number' => 'RV-1', 'date' => now()->toDateString(), 'payer_name' => 'A Client', 'amount' => 500, 'method' => 'bank_transfer', 'status' => 'issued']);
        InvoiceTemplate::create(['company_id' => $company->id, 'document_type' => 'all', 'name' => 'Default', 'layout' => 'minimal', 'is_default' => true, 'footer_path' => 'footers/fake.png']);

        $response = $this->actingAs($owner)->get(route('app.account-reports.pdf', $account));

        $response->assertOk();
    }

    public function test_machinery_statement_pdf_still_renders_fine_with_a_configured_footer(): void
    {
        $plan = Plan::create(['name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000, 'is_active' => true, 'has_machinery_equipment' => true]);
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'mst-ftr-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $asset = MachineryAsset::create(['company_id' => $company->id, 'asset_code' => 'EQ-00001', 'name' => 'Compactor', 'status' => 'available']);
        InvoiceTemplate::create(['company_id' => $company->id, 'document_type' => 'all', 'name' => 'Default', 'layout' => 'minimal', 'is_default' => true, 'footer_path' => 'footers/fake.png']);

        $response = $this->actingAs($owner)->get(route('app.machinery.assets.statement.pdf', $asset));

        $response->assertOk();
    }

    public function test_subcontract_receiving_report_custom_letterhead_header_is_registered_as_a_repeating_page_header(): void
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'sub-hdr-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        $project = \App\Models\Project::create(['company_id' => $company->id, 'code' => 'PROJ-1', 'name' => 'Jumum Yard Project', 'status' => 'active']);
        $supplier = \App\Models\Supplier::create(['company_id' => $company->id, 'name' => 'Al Fahad Subcontracting']);
        $subcontract = \App\Models\Subcontract::create(['company_id' => $company->id, 'project_id' => $project->id, 'supplier_id' => $supplier->id, 'contract_value' => 100000, 'retention_percent' => 5, 'status' => 'active']);

        $html = view('user.subcontracts.pdf.receiving-report', [
            'company' => $company,
            'subcontract' => $subcontract,
            'template' => new InvoiceTemplate(['layout' => 'custom_letterhead', 'letterhead_path' => 'letterheads/fake.png']),
            'embed' => fn ($path) => $path ? 'data:image/png;base64,fake' : null,
        ])->render();

        $this->assertStringContainsString('<htmlpageheader name="report-letterhead">', $html);
        $this->assertStringContainsString('<sethtmlpageheader name="report-letterhead" page="ALL"', $html);
        $this->assertStringNotContainsString('Receiving Payments Report', $html);
    }

    public function test_subcontract_receiving_report_default_template_is_picked_up_end_to_end(): void
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'sub-tpl-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $project = \App\Models\Project::create(['company_id' => $company->id, 'code' => 'PROJ-1', 'name' => 'Jumum Yard Project', 'status' => 'active']);
        $supplier = \App\Models\Supplier::create(['company_id' => $company->id, 'name' => 'Al Fahad Subcontracting']);
        $subcontract = \App\Models\Subcontract::create(['company_id' => $company->id, 'project_id' => $project->id, 'supplier_id' => $supplier->id, 'contract_value' => 100000, 'retention_percent' => 5, 'status' => 'active']);
        InvoiceTemplate::create(['company_id' => $company->id, 'document_type' => 'subcontractor_receiving_report', 'name' => 'Letterhead', 'layout' => 'custom_letterhead', 'is_default' => true, 'letterhead_path' => 'letterheads/fake.png']);

        $response = $this->actingAs($owner)->get(route('app.subcontracts.report.pdf', $subcontract));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }
}
