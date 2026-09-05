<?php

namespace App\Mail;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionExpiringMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Subscription $subscription,
    ) {}

    public function envelope(): Envelope
    {
        $company = $this->subscription->company;

        return new Envelope(
            from: new Address(config('mail.from.address'), config('app.name')),
            subject: $this->subscription->isTrial()
                ? __('Your :company trial is ending soon', ['company' => $company->name])
                : __('Your :company subscription is renewing soon', ['company' => $company->name]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.subscription-expiring',
            with: [
                'subscription' => $this->subscription,
                'company' => $this->subscription->company,
                'plan' => $this->subscription->plan,
                'daysLeft' => (int) now()->diffInDays($this->subscription->current_period_end),
                'billingUrl' => route('app.billing.index'),
            ],
        );
    }
}
