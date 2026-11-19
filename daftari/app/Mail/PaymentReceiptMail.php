<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentReceiptMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 4;

    public array $backoff = [30, 120, 600];

    /** @see InvoiceMail::$pdfBase64 */
    private readonly string $pdfBase64;

    /**
     * $invoice is the real ZATCA tax invoice PlatformInvoiceService
     * created for this payment, when the operator has configured platform
     * billing (see PaymentSettlementService::sendSubscriptionReceipt()) —
     * null keeps this exactly the plain payment-confirmation email it was
     * before that feature existed.
     */
    public function __construct(
        public readonly Payment $payment,
        string $pdfBinary,
        public readonly ?Invoice $invoice = null,
    ) {
        $this->pdfBase64 = base64_encode($pdfBinary);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('app.name')),
            subject: $this->invoice
                ? __('Your :app tax invoice :number', ['app' => config('app.name'), 'number' => $this->invoice->invoice_number])
                : __('Your :app payment receipt', ['app' => config('app.name')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.payment-receipt',
            with: [
                'payment' => $this->payment,
                'company' => $this->payment->company,
                'plan' => $this->payment->plan,
                'invoice' => $this->invoice,
            ],
        );
    }

    public function attachments(): array
    {
        $filename = $this->invoice ? $this->invoice->invoice_number.'.pdf' : 'receipt-'.$this->payment->id.'.pdf';

        return [
            Attachment::fromData(fn () => base64_decode($this->pdfBase64), $filename)
                ->withMime('application/pdf'),
        ];
    }
}
