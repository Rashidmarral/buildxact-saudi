<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\BankTransfer;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Master Report, Section 11 #12: the real SAR 500.00 transfer dated
 * 2026-10-19, notes "For home expenses", moves money from Petty Cash
 * (company cash) to "Rashid Cash" — a bank account flagged
 * is_personal=1, the owner's own cash custody, not a company asset.
 *
 * It was recorded as an ordinary BankTransfer, but
 * LedgerPostingService::postBankTransfer() never posts anything for a
 * cash-to-cash movement (same account type on both sides — see its own
 * docblock), so today this SAR 500.00 leaving the company is invisible
 * to the GL entirely: it isn't an expense, isn't a drawing, isn't
 * anything. The money is real and it really left for personal use, so
 * this posts the one journal entry the transfer itself never got: Dr
 * Owner's Drawings (3100) / Cr Cash on Hand (DEFAULT_CASH) — the same
 * treatment as every other personal item already reclassified (see
 * OwnerPersonalExpenseReclassificationSeeder), posted directly under
 * source_type 'bank_transfer' against this transfer's own id, since a
 * BankTransfer carries no account_id of its own to override.
 *
 * Run it yourself, after taking a database backup:
 *   php artisan db:seed --class="Database\\Seeders\\HomeExpenseBankTransferReclassificationSeeder"
 *
 * Idempotent: skips if a journal entry already exists for this transfer.
 */
class HomeExpenseBankTransferReclassificationSeeder extends Seeder
{
    private const AMOUNT = 500.00;

    private const NOTES = 'For home expenses';

    private const DATE = '2026-10-19';

    public function run(): void
    {
        $company = Company::where('vat_number', '314526094900003')->first();

        if (! $company) {
            $this->command?->warn('Dynamic Core Contracting Company not found — run RealCompanySeeder first.');

            return;
        }

        $transfer = BankTransfer::where('company_id', $company->id)
            ->where('amount', self::AMOUNT)
            ->where('notes', self::NOTES)
            ->whereDate('date', self::DATE)
            ->first();

        if (! $transfer) {
            $this->command?->error('The real "For home expenses" SAR 500.00 transfer was not found — nothing changed.');

            return;
        }

        $ledger = app(LedgerPostingService::class);

        if (JournalEntry::where('company_id', $company->id)
            ->where('source_type', 'bank_transfer')
            ->where('source_id', $transfer->id)
            ->exists()) {
            $this->command?->info('Already posted as a drawing — skipping.');

            return;
        }

        $drawings = Account::where('company_id', $company->id)->where('code', '3100')->first();
        $cash = AccountMapping::resolve($company->id, 'DEFAULT_CASH');

        if (! $drawings || ! $cash) {
            $this->command?->error("Owner's Drawings (3100) or the default cash account mapping could not be resolved — aborting.");

            return;
        }

        DB::transaction(function () use ($ledger, $company, $transfer, $drawings, $cash) {
            $ledger->post($company, 'bank_transfer', $transfer->id, "Owner's drawing: {$transfer->notes}", $transfer->date, [
                ['account_id' => $drawings->id, 'debit' => (float) $transfer->amount],
                ['account_id' => $cash->id, 'credit' => (float) $transfer->amount],
            ]);
        });

        $this->command?->info('Posted SAR '.number_format(self::AMOUNT, 2)." to Owner's Drawings for the ".self::DATE.' home-expense transfer.');
    }
}
