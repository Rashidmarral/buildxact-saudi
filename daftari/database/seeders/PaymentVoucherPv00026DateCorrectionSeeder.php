<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\PaymentVoucher;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Master Report, Section 12: PV-00026 (yard rental, SAR 13,000.00, paid
 * to Abdullah bin Yousef bin Ali Al-Qasimi) is dated 2025-08-01 — a
 * full year before every other real record, which all start around
 * September 2026. You confirmed the real date is 2026-08-01 (the same
 * month and day, just the year corrected).
 *
 * This updates the voucher's date and reposts its journal entry at the
 * corrected date — a date change moves which accounting period the
 * entry falls in, so the existing entry is deleted and re-posted
 * rather than edited in place, the same way every other repost in this
 * engagement works. The amount, payee and GL accounts are all
 * unchanged — only the date moves.
 *
 * Run it yourself, after taking a database backup:
 *   php artisan db:seed --class="Database\\Seeders\\PaymentVoucherPv00026DateCorrectionSeeder"
 *
 * Idempotent: skips once the voucher's date is already 2026-08-01.
 */
class PaymentVoucherPv00026DateCorrectionSeeder extends Seeder
{
    private const VOUCHER_NUMBER = 'PV-00026';

    private const CORRECTED_DATE = '2026-08-01';

    public function run(): void
    {
        $company = Company::where('vat_number', '314526094900003')->first();

        if (! $company) {
            $this->command?->warn('Dynamic Core Contracting Company not found — run RealCompanySeeder first.');

            return;
        }

        $voucher = PaymentVoucher::where('company_id', $company->id)
            ->where('voucher_number', self::VOUCHER_NUMBER)
            ->first();

        if (! $voucher) {
            $this->command?->error(self::VOUCHER_NUMBER.' was not found — nothing changed.');

            return;
        }

        if ($voucher->date->toDateString() === self::CORRECTED_DATE) {
            $this->command?->info(self::VOUCHER_NUMBER.' is already dated '.self::CORRECTED_DATE.' — skipping.');

            return;
        }

        $ledger = app(LedgerPostingService::class);

        DB::transaction(function () use ($voucher, $ledger, $company) {
            $ledger->deletePosting($company, 'payment_voucher', $voucher->id);
            $voucher->update(['date' => self::CORRECTED_DATE]);
            $ledger->postPaymentVoucher($voucher->fresh(['bankAccount', 'counterAccount', 'bill', 'expense']));
        });

        $this->command?->info(self::VOUCHER_NUMBER.' re-dated to '.self::CORRECTED_DATE.' and reposted.');
    }
}
