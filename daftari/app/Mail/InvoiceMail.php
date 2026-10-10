<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 4;

    public array $backoff = [30, 120, 600];

    /**
     * Stored base64-encoded rather than as raw binary — once this mailable
     * is queued (ShouldQueue), Laravel JSON-encodes the job payload, and
     * json_encode() rejects a string containing invalid-UTF-8 byte
     * sequences, which raw PDF bytes reliably contain.
     */
    private readonly string $pdfBase64;

    public function __construct(
        public readonly Invoice $invoice,
        string $pdfBinary,
    ) {
        $this->pdfBase64 = base64_encode($pdfBinary);
    }

    public function envelope(): Envelope
    {
        $company = $this->invoice->company;

        return new Envelope(
            from: new Address(config('mail.from.address'), $company->name),
            subject: __('Invoice :number from :company', ['number' => $this->invoice->invoice_number, 'company' => $company->name]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.invoice',
            with: [
                'invoice' => $this->invoice,
                'company' => $this->invoice->company,
                'client' => $this->invoice->client,
            ],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => base64_decode($this->pdfBase64), $this->invoice->invoice_number.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
