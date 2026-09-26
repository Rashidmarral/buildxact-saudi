<?php

namespace App\Mail;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Notifies the sales team when a new Lead lands, so an inbound prospect
 * from /contact, /get-started, or an industry landing page never sits
 * unseen in the CRM — the same gap ContactFormMail's own docblock
 * describes ("every lead silently vanished") but for the pipeline itself.
 */
class NewLeadMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 4;

    public array $backoff = [30, 120, 600];

    public function __construct(public readonly Lead $lead) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('New lead: :name', ['name' => $this->lead->name]),
            replyTo: [new Address($this->lead->email, $this->lead->name)],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.new-lead',
            with: ['lead' => $this->lead],
        );
    }
}
