<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Deliberately NOT ShouldQueue — the whole point of Platform Settings →
 * Email → "Send test email" is a synchronous, immediate answer to "do
 * these SMTP credentials actually work?". Queuing it would just report
 * success the moment the job is dispatched, not once mail is actually
 * delivered, defeating the purpose.
 */
class SmtpTestMail extends Mailable
{
    public function __construct(
        public readonly string $platformName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Test email'),
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.smtp-test',
            with: ['platformName' => $this->platformName],
        );
    }
}
