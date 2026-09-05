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

    /**
     * Ladder tier this reminder belongs to: 1 = friendly (7+ days overdue),
     * 2 = firm (14+ days), 3 = final notice (30+ days). Drives both the
     * subject line urgency and the wording of the body view.
     */
    public function __construct(
        public readonly Invoice $invoice,
        public readonly int $tier = 1,
    ) {}

    public function envelope(): Envelope
    {
        $company = $this->invoice->company;

        $subjects = [
            1 => __('Payment reminder: invoice :number from :company', ['number' => $this->invoice->invoice_number, 'company' => $company->name]),
            2 => __('Overdue notice: invoice :number from :company', ['number' => $this->invoice->invoice_number, 'company' => $company->name]),
            3 => __('Final notice: invoice :number from :company is seriously overdue', ['number' => $this->invoice->invoice_number, 'company' => $company->name]),
        ];

        return new Envelope(
            from: new Address(config('mail.from.address'), $company->name),
            subject: $subjects[$this->tier] ?? $subjects[1],
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
                'tier' => $this->tier,
                'daysOverdue' => $this->invoice->daysOverdue(),
            ],
        );
    }
}
