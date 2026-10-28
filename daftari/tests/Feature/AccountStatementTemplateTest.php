<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Client;
use App\Models\Company;
use App\Models\InvoiceTemplate;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "can you please make the different design layout for account statement
 * to client or supplier like we have added custom header or footer for
 * our contract documents same we can apply header footer to our account
 * statement as well" — the free Reports > Account Statement PDF (covers
 * both customers and suppliers) never read from Invoice Templates at
 * all, unlike letter-pdf.blade.php/client-statement-pdf.blade.php, which
 * already support a custom letterhead header and/or footer banner image
 * for their own document types. Adds 'account_statement' as a template
 * document_type and wires the same mPDF repeating-header/footer
 * mechanism into it.
 */
class AccountStatementTemplateTest extends TestCase
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

    public function test_a_custom_letterhead_is_registered_as_a_repeating_page_header(): void
    {
        $company = $this->makeCompany();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Sada Al Jeul Construction']);

        $html = view('user.reports.pdf.account-statement', [
            'company' => $company, 'type' => 'customer', 'party' => $client,
            'period' => ['from' => now()->subMonth(), 'to' => now()],
            'lines' => collect(), 'openingBalance' => 0.0, 'closingBalance' => 0.0,
            'template' => new InvoiceTemplate(['layout' => 'custom_letterhead', 'letterhead_path' => 'letterheads/fake.png']),
            'embed' => fn ($path) => $path ? 'data:image/png;base64,fake' : null,
        ])->render();

        $this->assertStringContainsString('<htmlpageheader name="report-letterhead">', $html);
        $this->assertStringContainsString('<sethtmlpageheader name="report-letterhead" page="ALL"', $html);
        // The custom letterhead replaces the standard navy header band
        // entirely — printing both would duplicate the company name/title.
        $this->assertStringNotContainsString('ACCOUNT STATEMENT', $html);
    }

    public function test_a_footer_banner_is_registered_as_a_repeating_page_footer_on_any_layout(): void
    {
        $company = $this->makeCompany();
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Sada Al Jeul Construction']);

        $html = view('user.reports.pdf.account-statement', [
            'company' => $company, 'type' => 'supplier', 'party' => $supplier,
            'period' => ['from' => now()->subMonth(), 'to' => now()],
            'lines' => collect(), 'openingBalance' => 0.0, 'closingBalance' => 0.0,
            // 'minimal', not custom_letterhead — proves the footer banner
            // isn't tied to the letterhead layout choice.
            'template' => new InvoiceTemplate(['layout' => 'minimal', 'footer_path' => 'footers/fake.png']),
            'embed' => fn ($path) => $path ? 'data:image/png;base64,fake' : null,
        ])->render();

        $this->assertStringContainsString('<htmlpagefooter name="report-footer">', $html);
        $this->assertStringContainsString('<sethtmlpagefooter name="report-footer" page="ALL"', $html);
        // The standard navy header band is unaffected by a footer-only template.
        $this->assertStringContainsString('ACCOUNT STATEMENT', $html);
    }

    public function test_the_standard_header_still_renders_with_no_template_configured(): void
    {
        $company = $this->makeCompany();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Sada Al Jeul Construction']);

        $html = view('user.reports.pdf.account-statement', [
            'company' => $company, 'type' => 'customer', 'party' => $client,
            'period' => ['from' => now()->subMonth(), 'to' => now()],
            'lines' => collect(), 'openingBalance' => 0.0, 'closingBalance' => 0.0,
            'template' => null,
            'embed' => fn ($path) => null,
        ])->render();

        $this->assertStringContainsString('ACCOUNT STATEMENT', $html);
        $this->assertStringNotContainsString('<htmlpageheader', $html);
        $this->assertStringNotContainsString('<htmlpagefooter', $html);
    }

    public function test_a_default_account_statement_template_is_picked_up_end_to_end(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Sada Al Jeul Construction']);
        InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Statement letterhead', 'document_type' => 'account_statement',
            'layout' => 'custom_letterhead', 'is_default' => true, 'letterhead_path' => 'letterheads/fake.png',
        ]);

        $response = $this->actingAs($owner)->get(route('app.reports.account-statement', [
            'type' => 'customer', 'client_id' => $client->id, 'export' => 'pdf',
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_the_account_statement_document_type_is_selectable_in_the_template_editor(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->post(route('app.invoice-templates.store'), [
            'name' => 'Statement design', 'document_types' => ['account_statement'], 'layout' => 'minimal',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('invoice_templates', ['company_id' => $company->id, 'document_type' => 'account_statement']);
    }
}
