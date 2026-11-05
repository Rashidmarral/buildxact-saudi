<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PartnerInviteMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 4;

    public array $backoff = [30, 120, 600];

    public function __construct(
        public readonly User $member,
        public readonly string $acceptUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Your :app partner application was approved', ['app' => config('app.name')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.partner-invite',
            with: [
                'member' => $this->member,
                'acceptUrl' => $this->acceptUrl,
            ],
        );
    }
}
