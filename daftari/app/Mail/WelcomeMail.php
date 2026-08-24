<?php

namespace App\Mail;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Company $company,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('app.name')),
            subject: __('Welcome to :app, :company!', ['app' => config('app.name'), 'company' => $this->company->name]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.welcome',
            with: [
                'company' => $this->company,
                'trialEndsAt' => $this->company->trial_ends_at,
                'dashboardUrl' => route('app.dashboard'),
            ],
        );
    }
}
