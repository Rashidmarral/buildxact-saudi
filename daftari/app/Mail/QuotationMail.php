<?php

namespace App\Mail;

use App\Models\Quotation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QuotationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Quotation $quotation,
        public readonly string $pdfBinary,
    ) {}

    public function envelope(): Envelope
    {
        $company = $this->quotation->company;
        $typeLabel = $this->quotation->type === 'proforma' ? __('Proforma Invoice') : __('Quotation');

        return new Envelope(
            from: new Address(config('mail.from.address'), $company->name),
            subject: __(':type :number from :company', ['type' => $typeLabel, 'number' => $this->quotation->quotation_number, 'company' => $company->name]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.quotation',
            with: [
                'quotation' => $this->quotation,
                'company' => $this->quotation->company,
                'client' => $this->quotation->client,
                'typeLabel' => $this->quotation->type === 'proforma' ? __('Proforma Invoice') : __('Quotation'),
            ],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfBinary, $this->quotation->quotation_number.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
