<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A draft invoice's "Record payment" box already had an amount field, a
 * method dropdown (cash/bank_transfer/card/other), and a reference field
 * — but entering a partial payment like "20% of the invoice" required
 * hand-calculating the SAR figure. This adds quick percentage buttons
 * (20/25/50/100%) plus a custom-percent input that fill the amount field;
 * recording the payment itself is unchanged (still one InvoicePayment
 * row against the existing amount field).
 */
class InvoicePaymentPercentageHelperTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['company_id' => $company->id, 'role' => 'owner', 'status' => 'active']);
    }

    public function test_a_draft_invoices_payment_form_shows_percentage_quick_select_and_the_existing_method_and_status_fields(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co.']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-1',
            'status' => 'draft', 'issue_date' => now(), 'due_date' => now()->addDays(30),
            'subtotal' => 625000, 'vat_total' => 93750, 'total' => 718750, 'currency' => 'SAR',
        ]);

        $response = $this->actingAs($owner)->get(route('app.invoices.show', $invoice));

        $response->assertOk()
            ->assertSee(__('Custom %'))
            ->assertSee('id="payment-amount"', false)
            ->assertSee('setPaymentPercent', false)
            // Already existed before this change — a payment can be
            // recorded on a draft invoice straight away, no need to
            // "send" it first, and the method/status machinery is here.
            ->assertSee(__('Cash'))
            ->assertSee(__('Bank transfer'))
            ->assertSee(__('Record payment'));
    }

    public function test_recording_a_partial_payment_still_sets_the_invoice_to_partially_paid(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co.']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-2',
            'status' => 'draft', 'issue_date' => now(), 'due_date' => now()->addDays(30),
            'subtotal' => 625000, 'vat_total' => 93750, 'total' => 718750, 'currency' => 'SAR',
        ]);

        // 20% of 718,750 = 143,750 — exactly the pre-computed value the
        // "20%" quick-select button would have filled in.
        $response = $this->actingAs($owner)->post(route('app.invoices.payments.store', $invoice), [
            'amount' => 143750, 'paid_at' => now()->toDateString(), 'method' => 'bank_transfer',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $invoice->refresh();
        $this->assertSame('partially_paid', $invoice->status);
        $this->assertEqualsWithDelta(143750, (float) $invoice->amount_paid, 0.01);
        $this->assertEqualsWithDelta(575000, $invoice->balanceDue(), 0.01);
    }
}
