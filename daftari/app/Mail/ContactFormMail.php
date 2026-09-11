<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Go-live audit finding: the public /contact form on the marketing site
 * validated its input and showed a "we'll get back to you" success
 * message, but never actually sent the message anywhere — every lead
 * silently vanished. Now delivered to Settings → Email → support_email
 * (the same address already surfaced to customers as the support
 * contact), with the sender's own address set as reply-to so a human can
 * just hit reply.
 */
class ContactFormMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 4;

    public array $backoff = [30, 120, 600];

    public function __construct(
        public readonly string $fromName,
        public readonly string $fromEmail,
        public readonly string $messageBody,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('New contact form message from :name', ['name' => $this->fromName]),
            replyTo: [new Address($this->fromEmail, $this->fromName)],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.contact-form',
            with: [
                'fromName' => $this->fromName,
                'fromEmail' => $this->fromEmail,
                'messageBody' => $this->messageBody,
            ],
        );
    }
}
