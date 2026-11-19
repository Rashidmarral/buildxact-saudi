<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Deliberately NOT ShouldQueue — same reasoning as the platform-level
 * SmtpTestMail: "Send a test email" needs a synchronous answer to "do
 * these SMTP credentials actually work", not "the job was queued".
 */
class CompanySmtpTestMail extends Mailable
{
    public function __construct(
        public readonly string $companyName,
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
            text: 'mail.company-smtp-test',
            with: ['companyName' => $this->companyName],
        );
    }
}
