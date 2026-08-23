<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OverdueInvoiceReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Invoice $invoice,
    ) {}

    public function envelope(): Envelope
    {
        $company = $this->invoice->company;

        return new Envelope(
            from: new Address(config('mail.from.address'), $company->name),
            subject: __('Payment reminder: invoice :number from :company', ['number' => $this->invoice->invoice_number, 'company' => $company->name]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.overdue-invoice-reminder',
            with: [
                'invoice' => $this->invoice,
                'company' => $this->invoice->company,
                'client' => $this->invoice->client,
                'daysOverdue' => (int) $this->invoice->due_date->diffInDays(now()),
            ],
        );
    }
}
