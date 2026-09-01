<?php

namespace Tests\Feature;

use App\Mail\OverdueInvoiceReminderMail;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Audit finding MEDIUM-13: overdue-invoice reminders were a single flat
 * "friendly reminder" repeated every 3 days no matter how overdue an
 * invoice got — the same gentle tone on day 8 as on day 90. This adds a
 * real 3-rung dunning ladder (7 / 14 / 30 days overdue), escalating the
 * tone and only firing once per rung, plus a per-company opt-out.
 */
class InvoiceDunningLadderTest extends TestCase
{
    use RefreshDatabase;

    private function makeInvoice(int $daysOverdue, array $companyOverrides = [], array $invoiceOverrides = []): Invoice
    {
        $company = Company::create(array_merge([
            'name' => 'Dunning Co.', 'slug' => 'dunning-'.uniqid(),
        ], $companyOverrides));
        $client = Client::create(['company_id' => $company->id, 'name' => 'Slow Payer LLC', 'email' => 'slow@example.com']);

        return Invoice::create(array_merge([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-'.uniqid(),
            'type' => 'standard', 'issue_date' => now()->subDays($daysOverdue + 30), 'due_date' => now()->subDays($daysOverdue),
            'status' => 'sent', 'currency' => 'SAR', 'subtotal' => 1000, 'vat_total' => 150, 'total' => 1150,
        ], $invoiceOverrides));
    }

    public function test_an_invoice_seven_days_overdue_gets_the_tier_one_reminder(): void
    {
        Mail::fake();
        $invoice = $this->makeInvoice(7);

        $this->artisan('invoices:send-overdue-reminders')->assertSuccessful();

        Mail::assertSent(OverdueInvoiceReminderMail::class, fn ($mail) => $mail->invoice->is($invoice) && $mail->tier === 1);
        $this->assertSame(1, $invoice->fresh()->last_reminder_tier);
    }

    public function test_an_invoice_thirty_days_overdue_gets_the_final_notice_tier(): void
    {
        Mail::fake();
        $invoice = $this->makeInvoice(30);

        $this->artisan('invoices:send-overdue-reminders')->assertSuccessful();

        Mail::assertSent(OverdueInvoiceReminderMail::class, fn ($mail) => $mail->invoice->is($invoice) && $mail->tier === 3);
        $this->assertSame(3, $invoice->fresh()->last_reminder_tier);
    }

    public function test_re_running_the_command_never_resends_the_same_tier_twice(): void
    {
        Mail::fake();
        $invoice = $this->makeInvoice(8, invoiceOverrides: ['last_reminder_tier' => 1, 'last_reminder_sent_at' => now()->subDay()]);

        $this->artisan('invoices:send-overdue-reminders')->assertSuccessful();

        Mail::assertNotSent(OverdueInvoiceReminderMail::class);
        $this->assertSame(1, $invoice->fresh()->last_reminder_tier);
    }

    public function test_crossing_into_a_higher_tier_escalates_even_if_a_lower_tier_was_already_sent(): void
    {
        Mail::fake();
        $invoice = $this->makeInvoice(15, invoiceOverrides: ['last_reminder_tier' => 1, 'last_reminder_sent_at' => now()->subDays(8)]);

        $this->artisan('invoices:send-overdue-reminders')->assertSuccessful();

        Mail::assertSent(OverdueInvoiceReminderMail::class, fn ($mail) => $mail->tier === 2);
        $this->assertSame(2, $invoice->fresh()->last_reminder_tier);
    }

    public function test_a_company_that_opted_out_of_dunning_never_gets_a_reminder(): void
    {
        Mail::fake();
        $this->makeInvoice(10, companyOverrides: ['invoice_dunning_enabled' => false]);

        $this->artisan('invoices:send-overdue-reminders')->assertSuccessful();

        Mail::assertNotSent(OverdueInvoiceReminderMail::class);
    }

    public function test_an_invoice_not_yet_seven_days_overdue_gets_no_reminder(): void
    {
        Mail::fake();
        $this->makeInvoice(3);

        $this->artisan('invoices:send-overdue-reminders')->assertSuccessful();

        Mail::assertNotSent(OverdueInvoiceReminderMail::class);
    }

    public function test_a_paid_invoice_never_gets_a_reminder_even_if_past_its_due_date(): void
    {
        Mail::fake();
        $this->makeInvoice(10, invoiceOverrides: ['status' => 'paid', 'amount_paid' => 1150]);

        $this->artisan('invoices:send-overdue-reminders')->assertSuccessful();

        Mail::assertNotSent(OverdueInvoiceReminderMail::class);
    }

    public function test_the_settings_screen_persists_the_dunning_opt_out(): void
    {
        $company = Company::create(['name' => 'Toggle Co.', 'slug' => 'toggle-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $response = $this->actingAs($owner)->post(route('app.settings.approvals.dunning'), []);

        $response->assertSessionDoesntHaveErrors();
        $this->assertFalse($company->fresh()->invoice_dunning_enabled);
    }
}
