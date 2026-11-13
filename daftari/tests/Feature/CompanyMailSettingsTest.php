<?php

namespace Tests\Feature;

use App\Mail\CompanySmtpTestMail;
use App\Mail\InvoiceMail;
use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyMailConfig;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * "please login and verify each and everything" (Hamly ERP) — Hamly lets
 * each business configure its own outgoing mail server instead of every
 * tenant sharing one platform mailer. See CompanyMailService for exactly
 * which emails this applies to (a company talking to its own customer)
 * versus which stay on the platform mailer (team invites, Daftari's own
 * subscription billing).
 */
class CompanyMailSettingsTest extends TestCase
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

    public function test_an_owner_can_save_smtp_settings_and_the_password_is_encrypted(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->post(route('app.settings.mail.update'), [
            'is_enabled' => '1',
            'smtp_host' => 'smtp.dynamiccore.sa',
            'smtp_port' => '587',
            'smtp_encryption' => 'tls',
            'smtp_username' => 'billing@dynamiccore.sa',
            'smtp_password' => 'super-secret',
            'from_address' => 'billing@dynamiccore.sa',
            'from_name' => 'Dynamic Core Contracting',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $config = CompanyMailConfig::where('company_id', $company->id)->first();
        $this->assertTrue($config->is_enabled);
        $this->assertSame('smtp.dynamiccore.sa', $config->smtp_host);
        $this->assertSame('super-secret', $config->smtp_password);

        $rawColumn = \Illuminate\Support\Facades\DB::table('company_mail_configs')->where('company_id', $company->id)->value('smtp_password');
        $this->assertNotSame('super-secret', $rawColumn);
    }

    public function test_leaving_the_password_field_blank_keeps_the_existing_password(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        CompanyMailConfig::create(['company_id' => $company->id, 'smtp_host' => 'smtp.old.com', 'smtp_password' => 'original-secret', 'is_enabled' => true]);

        $this->actingAs($owner)->post(route('app.settings.mail.update'), [
            'is_enabled' => '1',
            'smtp_host' => 'smtp.new.com',
            'smtp_encryption' => 'tls',
            'from_address' => 'billing@dynamiccore.sa',
        ]);

        $this->assertSame('original-secret', CompanyMailConfig::where('company_id', $company->id)->first()->smtp_password);
    }

    public function test_sending_a_test_email_requires_a_saved_host_first(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->post(route('app.settings.mail.test'), ['test_email' => 'owner@example.com']);

        $response->assertSessionHasErrors('mail_test');
    }

    public function test_sending_a_test_email_dispatches_mail_through_the_saved_config(): void
    {
        Mail::fake();
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        CompanyMailConfig::create(['company_id' => $company->id, 'smtp_host' => 'smtp.dynamiccore.sa', 'from_address' => 'billing@dynamiccore.sa', 'is_enabled' => true]);

        $response = $this->actingAs($owner)->post(route('app.settings.mail.test'), ['test_email' => 'owner@example.com']);

        $response->assertSessionDoesntHaveErrors();
        Mail::assertSent(CompanySmtpTestMail::class, fn ($mail) => $mail->hasTo('owner@example.com'));
    }

    public function test_emailing_an_invoice_applies_the_companys_own_smtp_config(): void
    {
        Mail::fake();
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        CompanyMailConfig::create(['company_id' => $company->id, 'smtp_host' => 'smtp.dynamiccore.sa', 'smtp_port' => 2525, 'from_address' => 'billing@dynamiccore.sa', 'from_name' => 'Dynamic Core', 'is_enabled' => true]);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Test Client', 'email' => 'client@example.com']);
        $invoice = Invoice::create(['company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-00001', 'issue_date' => now(), 'status' => 'sent', 'currency' => 'SAR', 'subtotal' => 100, 'vat_total' => 15, 'total' => 115]);

        $this->actingAs($owner)->post(route('app.invoices.email', $invoice))->assertSessionDoesntHaveErrors();

        $this->assertSame('smtp.dynamiccore.sa', config('mail.mailers.smtp.host'));
        $this->assertSame(2525, config('mail.mailers.smtp.port'));
        $this->assertSame('billing@dynamiccore.sa', config('mail.from.address'));
        // InvoiceMail implements ShouldQueue, so send() queues it rather
        // than dispatching synchronously — assertQueued, not assertSent.
        Mail::assertQueued(InvoiceMail::class);
    }

    public function test_emailing_an_invoice_leaves_the_default_mailer_untouched_when_not_configured(): void
    {
        Mail::fake();
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Test Client', 'email' => 'client@example.com']);
        $invoice = Invoice::create(['company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-00001', 'issue_date' => now(), 'status' => 'sent', 'currency' => 'SAR', 'subtotal' => 100, 'vat_total' => 15, 'total' => 115]);
        $defaultHost = config('mail.mailers.smtp.host');

        $this->actingAs($owner)->post(route('app.invoices.email', $invoice))->assertSessionDoesntHaveErrors();

        $this->assertSame($defaultHost, config('mail.mailers.smtp.host'));
        Mail::assertQueued(InvoiceMail::class);
    }
}
