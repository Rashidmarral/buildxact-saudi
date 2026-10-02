<?php

namespace App\Mail;

use App\Models\Partner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewPartnerApplicationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 4;

    public array $backoff = [30, 120, 600];

    public function __construct(public readonly Partner $partner) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('New partner application: :name', ['name' => $this->partner->name]),
            replyTo: [new Address($this->partner->email, $this->partner->name)],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.new-partner-application',
            with: ['partner' => $this->partner],
        );
    }
}
