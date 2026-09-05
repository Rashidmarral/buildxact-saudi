<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Invoice $invoice,
        public readonly string $pdfBinary,
    ) {}

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
            Attachment::fromData(fn () => $this->pdfBinary, $this->invoice->invoice_number.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
