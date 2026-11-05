<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Database\Seeder;

/**
 * Found while answering "how much net profit do i have" — invoice
 * INV-00003 (id 14, the real SAR 230,000.00 invoice to Sada Al Jeul,
 * issued 2026-10-21) was edited AFTER its ledger entry was first
 * created: the invoice itself now shows subtotal SAR 200,000.00 / VAT
 * SAR 30,000.00 / total SAR 230,000.00, but its only journal entry
 * (JE-00117, dated 2026-10-13 — the invoice's date before the edit)
 * still only posts SAR 136,965.75 / SAR 20,544.86 / SAR 157,510.61 —
 * the amount from BEFORE the edit.
 *
 * This isn't just a wrong date: it understates real recognized revenue
 * by SAR 63,034.25, real output VAT by SAR 9,455.14, and real
 * Accounts Receivable by SAR 72,489.39 — a real gap in the Income
 * Statement and in what the VAT return would report as output VAT
 * collected, not merely a cosmetic one.
 *
 * Fixes it the same way Company Audit's own "repost" action does
 * (CompanyAuditController::repost()) — delete the stale entry and let
 * LedgerPostingService::postInvoiceIssued() post the invoice's current,
 * correct figures, which also fixes the date (it always posts on
 * invoice->issue_date).
 *
 * Run it yourself, after taking a database backup:
 *   php artisan db:seed --class="Database\\Seeders\\Inv00003LedgerCorrectionSeeder"
 *
 * Idempotent: does nothing once the posted Sales Revenue line already
 * matches the invoice's own subtotal.
 */
class Inv00003LedgerCorrectionSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('vat_number', '314526094900003')->first();

        if (! $company) {
            $this->command?->warn('Dynamic Core Contracting Company not found — run RealCompanySeeder first.');

            return;
        }

        $invoice = Invoice::where('company_id', $company->id)->where('invoice_number', 'INV-00003')->first();

        if (! $invoice) {
            $this->command?->error('INV-00003 was not found — nothing to correct.');

            return;
        }

        $ledger = app(LedgerPostingService::class);

        $existing = JournalEntry::where('company_id', $company->id)
            ->where('source_type', 'invoice')->where('source_id', $invoice->id)
            ->with('lines')
            ->first();

        $alreadyCorrect = $existing && abs((float) $existing->lines->sum(fn ($l) => (float) $l->credit - (float) $l->debit)
            - ((float) $invoice->subtotal + (float) $invoice->vat_total)) < 0.02
            && $existing->entry_date->format('Y-m-d') === $invoice->issue_date->format('Y-m-d');

        if ($alreadyCorrect) {
            $this->command?->info('INV-00003 ledger entry already matches the invoice — skipping.');

            return;
        }

        $ledger->deletePosting($company, 'invoice', $invoice->id);
        $entry = $ledger->postInvoiceIssued($invoice->fresh());

        if (! $entry) {
            $this->command?->error('Reposting INV-00003 produced nothing — check the invoice is still issued/not voided.');

            return;
        }

        $this->command?->info("INV-00003 reposted as entry {$entry->entry_number} on {$invoice->issue_date->format('Y-m-d')} — SAR ".number_format((float) $invoice->subtotal, 2).' revenue, SAR '.number_format((float) $invoice->vat_total, 2).' output VAT.');
    }
}
