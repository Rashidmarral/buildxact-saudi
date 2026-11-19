<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit finding MEDIUM-16: "Payment-terms presets / installment plans" —
 * creating an invoice had no quick way to pick a standard payment term
 * (Net 15/30/45/60) that auto-fills the due date, and no way to split an
 * invoice's total into a scheduled payment plan a client can see. Covers
 * the server-side pieces: company/client default-terms-days settings, and
 * the installment-schedule store/destroy/allocation logic (the terms-
 * preset dropdown itself is pure client-side JS with no server behavior
 * to test beyond the settings it reads).
 */
class PaymentTermsAndInstallmentsTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): User
    {
        $company = Company::create(['name' => 'Terms Co.', 'slug' => 'terms-'.uniqid()]);

        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    private function makeInvoice(User $owner, float $total = 1000): Invoice
    {
        $client = Client::create(['company_id' => $owner->company_id, 'name' => 'Client A']);

        return Invoice::create([
            'company_id' => $owner->company_id, 'client_id' => $client->id, 'invoice_number' => 'INV-'.uniqid(),
            'type' => 'standard', 'issue_date' => now(), 'status' => 'sent', 'currency' => 'SAR',
            'subtotal' => $total, 'vat_total' => 0, 'total' => $total,
        ]);
    }

    public function test_the_settings_screen_persists_a_default_payment_terms_days(): void
    {
        $owner = $this->makeOwner();

        $response = $this->actingAs($owner)->put(route('app.settings.update'), [
            'name' => $owner->company->name,
            'invoice_prefix' => $owner->company->invoice_prefix,
            'default_payment_terms_days' => 45,
            'primary_customer_type' => 'mixed',
            'negative_number_format' => 'minus',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertSame(45, $owner->company->fresh()->default_payment_terms_days);
    }

    public function test_a_client_can_override_the_company_default_payment_terms(): void
    {
        $owner = $this->makeOwner();
        $client = Client::create(['company_id' => $owner->company_id, 'name' => 'Net 60 Client']);

        $response = $this->actingAs($owner)->put(route('app.clients.update', $client), [
            'client_code' => $client->client_code, 'type' => 'company', 'name' => $client->name, 'payment_terms_days' => 60,
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertSame(60, $client->fresh()->payment_terms_days);
    }

    public function test_storing_a_valid_installment_schedule_that_sums_to_the_total_succeeds(): void
    {
        $owner = $this->makeOwner();
        $invoice = $this->makeInvoice($owner, 1000);

        $response = $this->actingAs($owner)->post(route('app.invoices.installments.store', $invoice), [
            'installments' => [
                ['description' => 'Deposit', 'due_date' => now()->toDateString(), 'amount' => 300],
                ['description' => 'Balance', 'due_date' => now()->addDays(30)->toDateString(), 'amount' => 700],
            ],
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertCount(2, $invoice->fresh()->installments);
    }

    public function test_an_installment_schedule_that_does_not_sum_to_the_total_is_rejected(): void
    {
        $owner = $this->makeOwner();
        $invoice = $this->makeInvoice($owner, 1000);

        $response = $this->actingAs($owner)->post(route('app.invoices.installments.store', $invoice), [
            'installments' => [
                ['description' => 'Deposit', 'due_date' => now()->toDateString(), 'amount' => 300],
                ['description' => 'Balance', 'due_date' => now()->addDays(30)->toDateString(), 'amount' => 600],
            ],
        ]);

        $response->assertSessionHasErrors('installments');
        $this->assertCount(0, $invoice->fresh()->installments);
    }

    public function test_replacing_an_existing_schedule_clears_the_old_rows(): void
    {
        $owner = $this->makeOwner();
        $invoice = $this->makeInvoice($owner, 1000);

        $this->actingAs($owner)->post(route('app.invoices.installments.store', $invoice), [
            'installments' => [
                ['description' => 'Deposit', 'due_date' => now()->toDateString(), 'amount' => 300],
                ['description' => 'Balance', 'due_date' => now()->addDays(30)->toDateString(), 'amount' => 700],
            ],
        ]);

        $this->actingAs($owner)->post(route('app.invoices.installments.store', $invoice), [
            'installments' => [
                ['description' => 'Half', 'due_date' => now()->toDateString(), 'amount' => 500],
                ['description' => 'Other half', 'due_date' => now()->addDays(60)->toDateString(), 'amount' => 500],
            ],
        ]);

        $installments = $invoice->fresh()->installments;
        $this->assertCount(2, $installments);
        $this->assertSame('Half', $installments->first()->description);
    }

    public function test_removing_the_schedule_deletes_all_installments(): void
    {
        $owner = $this->makeOwner();
        $invoice = $this->makeInvoice($owner, 1000);
        $this->actingAs($owner)->post(route('app.invoices.installments.store', $invoice), [
            'installments' => [
                ['description' => 'Deposit', 'due_date' => now()->toDateString(), 'amount' => 300],
                ['description' => 'Balance', 'due_date' => now()->addDays(30)->toDateString(), 'amount' => 700],
            ],
        ]);

        $response = $this->actingAs($owner)->delete(route('app.invoices.installments.destroy', $invoice));

        $response->assertSessionDoesntHaveErrors();
        $this->assertCount(0, $invoice->fresh()->installments);
    }

    public function test_installment_schedule_allocates_payments_oldest_first_and_reports_status(): void
    {
        $owner = $this->makeOwner();
        $invoice = $this->makeInvoice($owner, 1000);
        $this->actingAs($owner)->post(route('app.invoices.installments.store', $invoice), [
            'installments' => [
                ['description' => 'Deposit', 'due_date' => now()->toDateString(), 'amount' => 300],
                ['description' => 'Balance', 'due_date' => now()->addDays(30)->toDateString(), 'amount' => 700],
            ],
        ]);

        $invoice = $invoice->fresh();
        $invoice->invoicePayments()->create(['amount' => 450, 'paid_at' => now(), 'method' => 'cash']);
        $invoice->amount_paid = 450;
        $invoice->save();

        $schedule = $invoice->fresh()->installmentSchedule()->values();

        $this->assertSame('paid', $schedule[0]->status);
        $this->assertEqualsWithDelta(300.0, $schedule[0]->paid_amount, 0.01);
        $this->assertSame('partial', $schedule[1]->status);
        $this->assertEqualsWithDelta(150.0, $schedule[1]->paid_amount, 0.01);
    }
}
