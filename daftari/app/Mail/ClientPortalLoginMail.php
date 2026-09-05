<?php

namespace App\Mail;

use App\Models\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClientPortalLoginMail extends Mailable
{
    use Queueable, SerializesModels;

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
