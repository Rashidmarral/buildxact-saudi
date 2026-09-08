<?php

namespace Tests\Feature;

use App\Mail\PaymentReceiptMail;
use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\Payments\PaymentSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Real ZATCA-compliant tax invoices for the platform's own subscription
 * billing, requested directly by the operator: "we need to give real
 * zatca invoice as well if someone purchase subscription of our
 * system". Billed through whichever tenant company the operator
 * designates as their own business (Setting 'platform_billing_company_id',
 * set in Admin -> Platform Settings -> Identity) — deliberately never
 * hard-coded to a specific company, since this codebase ships to many
 * buyers who each have their own operator identity. Entirely inert
 * (falls back to the pre-existing plain payment receipt) until that
 * setting is configured, so no existing install's behavior changes by
 * default.
 */
class PlatformSubscriptionInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeBillingCompany(): Company
    {
        $company = Company::create([
            'name' => 'Dynamic Core Contracting Company',
            'slug' => 'billing-co-'.uniqid(),
            'vat_number' => '314526094900003',
            'currency' => 'SAR',
        ]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        TaxRate::seedDefaults($company->id);

        return $company;
    }

    private function makePayingCompanyWithPayment(float $amount = 230.0): Payment
    {
        $payingCompany = Company::create(['name' => 'Acme Trading', 'slug' => 'acme-'.uniqid(), 'vat_number' => '300099998800003']);
        User::factory()->create(['role' => 'owner', 'company_id' => $payingCompany->id, 'status' => 'active']);
        $plan = Plan::create(['name' => 'Growth', 'slug' => 'growth-'.uniqid(), 'price_monthly' => $amount, 'price_yearly' => $amount * 10, 'currency' => 'SAR']);
        $subscription = Subscription::create(['company_id' => $payingCompany->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly']);

        return Payment::create([
            'company_id' => $payingCompany->id,
            'subscription_id' => $subscription->id,
            'plan_id' => $plan->id,
            'amount' => $amount,
            'currency' => 'SAR',
            'status' => 'paid',
            'method' => 'card',
            'reference' => 'TXN-'.uniqid(),
            'paid_at' => now(),
        ]);
    }

    public function test_no_real_invoice_is_created_when_billing_company_is_not_configured(): void
    {
        Mail::fake();
        $payment = $this->makePayingCompanyWithPayment();

        app(PaymentSettlementService::class)->sendSubscriptionReceipt($payment);

        $this->assertSame(0, Invoice::count());
        $this->assertSame(0, Client::count());
        Mail::assertQueued(PaymentReceiptMail::class, fn ($mail) => $mail->invoice === null);
    }

    public function test_a_real_invoice_is_created_sent_paid_and_emailed_when_configured(): void
    {
        Mail::fake();
        $billingCompany = $this->makeBillingCompany();
        Setting::set('platform_billing_company_id', $billingCompany->id);
        $payment = $this->makePayingCompanyWithPayment(230.0);

        app(PaymentSettlementService::class)->sendSubscriptionReceipt($payment);

        $invoice = Invoice::where('company_id', $billingCompany->id)->firstOrFail();
        $this->assertSame('paid', $invoice->status);
        $this->assertSame('standard', $invoice->type);
        $this->assertNotNull($invoice->qr_code);
        $this->assertEqualsWithDelta(230.0, (float) $invoice->total, 0.001);
        $this->assertEqualsWithDelta(230.0, (float) $invoice->amount_paid, 0.001);
        // VAT-inclusive pricing: subtotal + vat_total must reconstruct the
        // exact amount charged, with zero rounding drift.
        $this->assertEqualsWithDelta($invoice->total, round((float) $invoice->subtotal + (float) $invoice->vat_total, 2), 0.001);

        $client = Client::where('company_id', $billingCompany->id)->firstOrFail();
        $this->assertSame($invoice->client_id, $client->id);
        $this->assertSame('Acme Trading', $client->name);

        Mail::assertQueued(PaymentReceiptMail::class, fn ($mail) => $mail->invoice?->id === $invoice->id);
    }

    public function test_the_invoice_is_fully_posted_to_the_billing_companys_own_ledger(): void
    {
        $billingCompany = $this->makeBillingCompany();
        Setting::set('platform_billing_company_id', $billingCompany->id);
        $payment = $this->makePayingCompanyWithPayment(115.0);

        app(PaymentSettlementService::class)->sendSubscriptionReceipt($payment);

        $invoice = Invoice::where('company_id', $billingCompany->id)->firstOrFail();

        $this->assertDatabaseHas('journal_entries', ['company_id' => $billingCompany->id, 'source_type' => 'invoice', 'source_id' => $invoice->id]);
        $this->assertDatabaseHas('journal_entries', ['company_id' => $billingCompany->id, 'source_type' => 'invoice_payment']);

        $issuedEntry = JournalEntry::where('source_type', 'invoice')->where('source_id', $invoice->id)->firstOrFail();
        $totalDebits = $issuedEntry->lines()->sum('debit');
        $totalCredits = $issuedEntry->lines()->sum('credit');
        $this->assertEqualsWithDelta($totalDebits, $totalCredits, 0.01, 'Journal entry for the issued invoice must balance.');
    }

    public function test_a_renewal_payment_from_the_same_company_reuses_the_same_client(): void
    {
        $billingCompany = $this->makeBillingCompany();
        Setting::set('platform_billing_company_id', $billingCompany->id);

        $payingCompany = Company::create(['name' => 'Renewing Co', 'slug' => 'renew-'.uniqid()]);
        $plan = Plan::create(['name' => 'Basic', 'slug' => 'basic-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000, 'currency' => 'SAR']);
        $subscription = Subscription::create(['company_id' => $payingCompany->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly']);

        foreach ([100, 100] as $amount) {
            $payment = Payment::create([
                'company_id' => $payingCompany->id, 'subscription_id' => $subscription->id, 'plan_id' => $plan->id,
                'amount' => $amount, 'currency' => 'SAR', 'status' => 'paid', 'method' => 'card',
                'reference' => 'TXN-'.uniqid(), 'paid_at' => now(),
            ]);
            app(PaymentSettlementService::class)->sendSubscriptionReceipt($payment);
        }

        $this->assertSame(1, Client::where('company_id', $billingCompany->id)->count());
        $this->assertSame(2, Invoice::where('company_id', $billingCompany->id)->count());
    }

    public function test_the_operator_is_never_billed_for_its_own_subscription(): void
    {
        $billingCompany = $this->makeBillingCompany();
        Setting::set('platform_billing_company_id', $billingCompany->id);

        $plan = Plan::create(['name' => 'Self', 'slug' => 'self-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000, 'currency' => 'SAR']);
        $subscription = Subscription::create(['company_id' => $billingCompany->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly']);
        $payment = Payment::create([
            'company_id' => $billingCompany->id, 'subscription_id' => $subscription->id, 'plan_id' => $plan->id,
            'amount' => 100, 'currency' => 'SAR', 'status' => 'paid', 'method' => 'card',
            'reference' => 'TXN-'.uniqid(), 'paid_at' => now(),
        ]);

        app(PaymentSettlementService::class)->sendSubscriptionReceipt($payment);

        $this->assertSame(0, Invoice::count());
    }

    public function test_a_misconfigured_billing_company_id_falls_back_gracefully(): void
    {
        Mail::fake();
        Setting::set('platform_billing_company_id', 999999);
        $payment = $this->makePayingCompanyWithPayment();

        app(PaymentSettlementService::class)->sendSubscriptionReceipt($payment);

        $this->assertSame(0, Invoice::count());
        Mail::assertQueued(PaymentReceiptMail::class, fn ($mail) => $mail->invoice === null);
    }
}
