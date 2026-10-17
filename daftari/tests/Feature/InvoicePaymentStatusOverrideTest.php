<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "user have option to select invoice status paid or pending due
 * something and it will show in header invoice paid or pending" —
 * payment_status_override lets a user pick the Paid/Pending/Partially
 * paid/Overdue label shown on the invoice header and the printed/
 * client-facing document directly, without needing to record a real
 * payment first. Deliberately display-only: Invoice::displayedPaymentStatus()
 * is the one thing that reads it — balanceDue(), the real status column,
 * ledger postings, credit/debit note eligibility, and ZATCA sync guards
 * all keep using the real data untouched, and recording/editing a real
 * payment silently clears the override so real data always wins once
 * it's actually entered.
 */
class InvoicePaymentStatusOverrideTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): array
    {
        $company = Company::create(['name' => 'Override Co.', 'slug' => 'override-'.uniqid(), 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        return [$company, $owner];
    }

    private function makeInvoice(Company $company, string $status = 'sent', float $amountPaid = 0): Invoice
    {
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co.']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-'.uniqid(),
            'status' => $status, 'issue_date' => now(), 'due_date' => now()->addDays(30),
            'subtotal' => 1000, 'vat_total' => 150, 'total' => 1150, 'amount_paid' => $amountPaid, 'currency' => 'SAR',
        ]);
        InvoiceItem::create(['invoice_id' => $invoice->id, 'description' => 'Work', 'quantity' => 1, 'unit_price' => 1000, 'vat_rate' => 15, 'vat_amount' => 150, 'line_total' => 1150]);

        return $invoice->fresh();
    }

    public function test_an_owner_can_set_the_displayed_status_to_paid_without_recording_a_payment(): void
    {
        [$company, $owner] = $this->makeOwner();
        $invoice = $this->makeInvoice($company);

        $this->actingAs($owner)->post(route('app.invoices.payment-status', $invoice), ['payment_status_override' => 'paid'])
            ->assertSessionDoesntHaveErrors();

        $invoice->refresh();
        $this->assertSame('paid', $invoice->payment_status_override);
        $this->assertSame('paid', $invoice->displayedPaymentStatus());
        // Purely cosmetic — the real status, amount_paid and balance due
        // are completely untouched.
        $this->assertSame('sent', $invoice->status);
        $this->assertSame(0.0, (float) $invoice->amount_paid);
        $this->assertEqualsWithDelta(1150.0, $invoice->balanceDue(), 0.01);
    }

    public function test_setting_the_override_back_to_automatic_clears_it(): void
    {
        [$company, $owner] = $this->makeOwner();
        $invoice = $this->makeInvoice($company);
        $invoice->update(['payment_status_override' => 'overdue']);

        $this->actingAs($owner)->post(route('app.invoices.payment-status', $invoice), ['payment_status_override' => ''])
            ->assertSessionDoesntHaveErrors();

        $this->assertNull($invoice->fresh()->payment_status_override);
    }

    public function test_an_invalid_override_value_is_rejected(): void
    {
        [$company, $owner] = $this->makeOwner();
        $invoice = $this->makeInvoice($company);

        $this->actingAs($owner)->post(route('app.invoices.payment-status', $invoice), ['payment_status_override' => 'bogus'])
            ->assertSessionHasErrors('payment_status_override');
    }

    public function test_the_override_is_rejected_for_a_draft_invoice(): void
    {
        [$company, $owner] = $this->makeOwner();
        $invoice = $this->makeInvoice($company, status: 'draft');

        $this->actingAs($owner)->post(route('app.invoices.payment-status', $invoice), ['payment_status_override' => 'paid'])
            ->assertNotFound();
    }

    public function test_the_override_is_rejected_for_a_cancelled_invoice(): void
    {
        [$company, $owner] = $this->makeOwner();
        $invoice = $this->makeInvoice($company, status: 'cancelled');

        $this->actingAs($owner)->post(route('app.invoices.payment-status', $invoice), ['payment_status_override' => 'paid'])
            ->assertNotFound();
    }

    /**
     * A stale override from before this invoice was cancelled must never
     * resurrect itself as the displayed label — displayedPaymentStatus()
     * only honors an override while the real status is still one a
     * payment label makes sense for.
     */
    public function test_a_cancelled_invoices_displayed_status_ignores_any_leftover_override(): void
    {
        [$company] = $this->makeOwner();
        $invoice = $this->makeInvoice($company, status: 'sent');
        $invoice->update(['payment_status_override' => 'paid']);

        $invoice->update(['status' => 'cancelled']);

        $this->assertSame('cancelled', $invoice->fresh()->displayedPaymentStatus());
    }

    public function test_recording_a_real_payment_clears_a_stale_override(): void
    {
        [$company, $owner] = $this->makeOwner();
        $invoice = $this->makeInvoice($company);
        $invoice->update(['payment_status_override' => 'overdue']);

        $this->actingAs($owner)->post(route('app.invoices.payments.store', $invoice), [
            'amount' => 1150, 'paid_at' => now()->toDateString(), 'method' => 'cash',
        ])->assertSessionDoesntHaveErrors();

        $invoice->refresh();
        $this->assertNull($invoice->payment_status_override);
        $this->assertSame('paid', $invoice->displayedPaymentStatus());
    }

    public function test_the_header_badge_reflects_the_override_on_the_show_page(): void
    {
        [$company, $owner] = $this->makeOwner();
        $invoice = $this->makeInvoice($company, status: 'sent');
        $invoice->update(['payment_status_override' => 'overdue']);

        $this->actingAs($owner)->get(route('app.invoices.show', $invoice))
            ->assertOk()
            ->assertSee(__('Overdue'));
    }

    public function test_the_public_invoice_page_shows_the_overridden_status_badge(): void
    {
        [$company] = $this->makeOwner();
        $invoice = $this->makeInvoice($company, status: 'sent');
        $invoice->update(['payment_status_override' => 'pending']);

        $response = $this->get(route('public.invoices.show', [$invoice->id, $invoice->public_token]));

        $response->assertOk();
        $response->assertSee('Pending');
    }

    public function test_the_downloaded_pdf_shows_the_overridden_status_badge(): void
    {
        [$company] = $this->makeOwner();
        $invoice = $this->makeInvoice($company, status: 'sent');
        $invoice->update(['payment_status_override' => 'paid']);

        $html = view('documents.print.pdf', $invoice->fresh()->pdfData() + ['embed' => fn () => null])->render();

        $this->assertStringContainsString('Paid', $html);
        $this->assertStringContainsString('مدفوعة بالكامل', $html);
    }
}
