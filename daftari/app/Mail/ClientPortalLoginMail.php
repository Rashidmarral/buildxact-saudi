<?php

namespace App\Mail;

use App\Models\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClientPortalLoginMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 4;

    public array $backoff = [30, 120, 600];

    public function __construct(
        public readonly Client $client,
        public readonly string $loginUrl,
    ) {}

    public function envelope(): Envelope
    {
        $company = $this->client->company;

        return new Envelope(
            from: new Address(config('mail.from.address'), $company->name),
            subject: __('Your sign-in link for :company', ['company' => $company->name]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.client-portal-login',
            with: [
                'client' => $this->client,
                'company' => $this->client->company,
                'loginUrl' => $this->loginUrl,
            ],
        );
    }
}
