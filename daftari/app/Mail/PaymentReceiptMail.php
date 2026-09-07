<?php

namespace App\Mail;

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

    public function __construct(
        public readonly Payment $payment,
        string $pdfBinary,
    ) {
        $this->pdfBase64 = base64_encode($pdfBinary);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('app.name')),
            subject: __('Your :app payment receipt', ['app' => config('app.name')]),
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
            ],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => base64_decode($this->pdfBase64), 'receipt-'.$this->payment->id.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
