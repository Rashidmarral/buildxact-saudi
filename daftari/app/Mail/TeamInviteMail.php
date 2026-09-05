<?php

namespace App\Mail;

use App\Http\Controllers\User\TeamController;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TeamInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $member,
        public readonly string $acceptUrl,
    ) {}

    public function envelope(): Envelope
    {
        $company = $this->member->company;

        return new Envelope(
            from: new Address(config('mail.from.address'), $company->name),
            subject: __(':company invited you to join :app', ['company' => $company->name, 'app' => config('app.name')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.team-invite',
            with: [
                'member' => $this->member,
                'company' => $this->member->company,
                'acceptUrl' => $this->acceptUrl,
                'expiresInHours' => TeamController::INVITE_EXPIRY_HOURS,
            ],
        );
    }
}
