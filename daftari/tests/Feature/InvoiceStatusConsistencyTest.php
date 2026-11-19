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
 * "on invoice list page paid invoice status coming as sent" — the
 * invoice show page used to let a user hand-pick a purely cosmetic
 * Paid/Pending/Partially paid/Overdue label (payment_status_override)
 * that only ever showed on that one page, never on the invoice list
 * (which always showed the real status column). Picking "Paid" there
 * made the invoice look settled on its own page while the list kept
 * showing "Sent" for the exact same invoice — confusing, and the
 * report that prompted removing the selector entirely. Both pages now
 * only ever read the real status column, so they can never disagree.
 */
class InvoiceStatusConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): array
    {
        $company = Company::create(['name' => 'Status Co.', 'slug' => 'status-'.uniqid(), 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        return [$company, $owner];
    }

    private function makeInvoice(Company $company, string $status = 'sent'): Invoice
    {
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co.']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-'.uniqid(),
            'status' => $status, 'issue_date' => now(), 'due_date' => now()->addDays(30),
            'subtotal' => 1000, 'vat_total' => 150, 'total' => 1150, 'amount_paid' => $status === 'paid' ? 1150 : 0, 'currency' => 'SAR',
        ]);
        InvoiceItem::create(['invoice_id' => $invoice->id, 'description' => 'Work', 'quantity' => 1, 'unit_price' => 1000, 'vat_rate' => 15, 'vat_amount' => 150, 'line_total' => 1150]);

        return $invoice->fresh();
    }

    public function test_a_paid_invoices_badge_matches_on_both_the_list_and_the_show_page(): void
    {
        [$company, $owner] = $this->makeOwner();
        $invoice = $this->makeInvoice($company, status: 'paid');

        $list = $this->actingAs($owner)->get(route('app.invoices.index'));
        $show = $this->actingAs($owner)->get(route('app.invoices.show', $invoice));

        $list->assertOk()->assertSee(__('Paid'));
        $show->assertOk()->assertSee(__('Paid'));
    }

    public function test_the_invoice_show_page_no_longer_offers_a_payment_status_selector(): void
    {
        [$company, $owner] = $this->makeOwner();
        $invoice = $this->makeInvoice($company, status: 'paid');

        $response = $this->actingAs($owner)->get(route('app.invoices.show', $invoice));

        $response->assertOk();
        $response->assertDontSee('name="payment_status_override"', false);
    }

    public function test_the_retired_payment_status_route_no_longer_exists(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('app.invoices.payment-status'));
    }
}
