<?php

namespace App\Mail;

use App\Models\Client;
use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The document a company actually hands a client to chase a balance —
 * until now, download-only (see ClientStatementController's own
 * docblock); mirrors InvoiceMail's shape exactly (same base64-stored PDF
 * for queue-safety, same retry/backoff).
 */
class StatementMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 4;

    public array $backoff = [30, 120, 600];

    private readonly string $pdfBase64;

    public function __construct(
        public readonly Client $client,
        public readonly Company $company,
        public readonly float $closingBalance,
        string $pdfBinary,
    ) {
        $this->pdfBase64 = base64_encode($pdfBinary);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), $this->company->name),
            subject: __('Statement of Account from :company', ['company' => $this->company->name]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.statement',
            with: [
                'client' => $this->client,
                'company' => $this->company,
                'closingBalance' => $this->closingBalance,
            ],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => base64_decode($this->pdfBase64), __('Statement of Account').'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
