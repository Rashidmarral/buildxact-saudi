<?php

namespace App\Mail;

use App\Mail\Concerns\AppliesTenantMailConfig;
use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "add more... Auto-emailed monthly financial statements" — see
 * SendMonthlyFinancialStatements's own doc block for who gets this
 * and when. Mirrors StatementMail's shape exactly (same base64-stored
 * PDFs for queue-safety, same retry/backoff).
 */
class MonthlyFinancialStatementsMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, AppliesTenantMailConfig;

    public int $tries = 4;

    public array $backoff = [30, 120, 600];

    private readonly string $balanceSheetBase64;

    private readonly string $incomeStatementBase64;

    public function __construct(
        public readonly Company $company,
        public readonly Carbon $periodStart,
        public readonly Carbon $periodEnd,
        string $balanceSheetPdf,
        string $incomeStatementPdf,
    ) {
        $this->balanceSheetBase64 = base64_encode($balanceSheetPdf);
        $this->incomeStatementBase64 = base64_encode($incomeStatementPdf);
    }

    public function envelope(): Envelope
    {
        $this->applyTenantMailConfig($this->company);

        return new Envelope(
            from: new Address(config('mail.from.address'), $this->company->name),
            subject: __('Financial Statements for :period', ['period' => $this->periodStart->format('F Y')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.monthly-financial-statements',
            with: [
                'company' => $this->company,
                'periodStart' => $this->periodStart,
                'periodEnd' => $this->periodEnd,
            ],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => base64_decode($this->balanceSheetBase64), __('Balance Sheet').'.pdf')
                ->withMime('application/pdf'),
            Attachment::fromData(fn () => base64_decode($this->incomeStatementBase64), __('Income Statement').'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
