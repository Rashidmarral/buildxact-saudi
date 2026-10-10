<?php

namespace Tests\Feature;

use App\Mail\ClientPortalLoginMail;
use App\Mail\InvoiceMail;
use App\Mail\OverdueInvoiceReminderMail;
use App\Mail\PaymentReceiptMail;
use App\Mail\QuotationMail;
use App\Mail\SubscriptionExpiringMail;
use App\Mail\TeamInviteMail;
use App\Mail\WelcomeMail;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Quotation;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Audit "worth doing soon": all 8 transactional Mailables now implement
 * ShouldQueue with retry/backoff (matching the ZATCA/webhook job pattern),
 * so a transient SMTP failure retries instead of silently losing the email.
 *
 * The three Mailables that attach a rendered PDF (InvoiceMail,
 * QuotationMail, PaymentReceiptMail) carried the PDF as a raw binary
 * string. Queuing wraps a mailable's payload through
 * Illuminate\Queue\Queue::createPayload(), which json_encode()s it — and
 * json_encode() fails outright on a string containing invalid-UTF-8 byte
 * sequences, which real PDF bytes reliably contain. This is a real
 * end-to-end regression test (no Mail::fake()), since a faked mailer skips
 * the queue payload serialization entirely and would not have caught it.
 */
class QueuedTransactionalMailTest extends TestCase
{
    use RefreshDatabase;

    private function invalidUtf8Pdf(): string
    {
        // A handful of raw bytes that are not valid UTF-8 on their own —
        // this is what caused Queue::createPayload()'s json_encode() to
        // fail with "Malformed UTF-8 characters" before the base64 fix.
        return "%PDF-1.4\n".random_bytes(64)."\xff\xfe\x80\x81".random_bytes(64);
    }

    public function test_invoice_mail_implements_should_queue_and_actually_queues_without_a_json_encode_error(): void
    {
        $company = Company::create(['name' => 'Mail Co.', 'slug' => 'mail-'.uniqid()]);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client', 'email' => 'client@example.com']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-'.uniqid(),
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(),
            'currency' => $company->currency, 'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
        ]);

        $mail = new InvoiceMail($invoice, $this->invalidUtf8Pdf());
        $this->assertInstanceOf(ShouldQueue::class, $mail);

        // This send() actually round-trips through the real queue payload
        // serializer (QUEUE_CONNECTION=sync in phpunit.xml still calls
        // createPayload()) — it must not throw.
        Mail::to('client@example.com')->send($mail);
        $this->assertTrue(true);
    }

    public function test_quotation_mail_implements_should_queue_and_actually_queues_without_a_json_encode_error(): void
    {
        $company = Company::create(['name' => 'Mail Co.', 'slug' => 'mail-'.uniqid()]);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client', 'email' => 'client@example.com']);
        $quotation = Quotation::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'quotation_number' => 'QUO-'.uniqid(),
            'type' => 'quotation', 'status' => 'draft', 'issue_date' => now()->toDateString(),
            'currency' => $company->currency, 'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
        ]);

        $mail = new QuotationMail($quotation, $this->invalidUtf8Pdf());
        $this->assertInstanceOf(ShouldQueue::class, $mail);

        Mail::to('client@example.com')->send($mail);
        $this->assertTrue(true);
    }

    public function test_payment_receipt_mail_implements_should_queue_and_actually_queues_without_a_json_encode_error(): void
    {
        $company = Company::create(['name' => 'Mail Co.', 'slug' => 'mail-'.uniqid()]);
        $plan = Plan::create(['name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000]);
        $subscription = Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth()]);
        $payment = Payment::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'subscription_id' => $subscription->id, 'amount' => 100, 'currency' => 'SAR', 'status' => 'paid', 'paid_at' => now()]);

        $mail = new PaymentReceiptMail($payment, $this->invalidUtf8Pdf());
        $this->assertInstanceOf(ShouldQueue::class, $mail);

        Mail::to('owner@example.com')->send($mail);
        $this->assertTrue(true);
    }

    public function test_all_eight_transactional_mailables_implement_should_queue(): void
    {
        foreach ([
            InvoiceMail::class, QuotationMail::class, PaymentReceiptMail::class,
            OverdueInvoiceReminderMail::class, SubscriptionExpiringMail::class,
            TeamInviteMail::class, WelcomeMail::class, ClientPortalLoginMail::class,
        ] as $mailable) {
            $this->assertTrue(
                is_subclass_of($mailable, ShouldQueue::class) || in_array(ShouldQueue::class, class_implements($mailable), true),
                "{$mailable} should implement ShouldQueue so a transient SMTP failure retries instead of losing the email."
            );
        }
    }
}
