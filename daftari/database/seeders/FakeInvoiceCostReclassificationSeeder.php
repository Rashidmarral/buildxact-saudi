<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Company;
use App\Models\Expense;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Master Report, Section 12: you confirmed expense id 22 ("Eng Khalid
 * paid net cash for invoice issuing from East West Establishment", SAR
 * 1,800.00, category VAT, dated 2026-09-30) is the real cost of
 * acquiring BILL-00003 — the fake invoice voided by
 * Bill00003TestDataCleanupSeeder.
 *
 * The SAR 1,800.00 cash really did leave the company, so this is NOT
 * voided like the fake bill itself — it stays a real recorded loss.
 * What changes is only where it sits: out of Operating Expenses (which
 * would make it look like a legitimate deductible business cost for
 * VAT/Zakat purposes) and into Non-Deductible Expenses (5900), a
 * system account added specifically for real cash outflows that can
 * never be claimed as a deductible cost. The cash/bank side of the
 * entry is untouched.
 *
 * Run it yourself, after taking a database backup:
 *   php artisan db:seed --class="Database\\Seeders\\FakeInvoiceCostReclassificationSeeder"
 *
 * Idempotent: skips if the expense's account_id is already Non-Deductible Expenses.
 */
class FakeInvoiceCostReclassificationSeeder extends Seeder
{
    private const CATEGORY = 'VAT';

    private const DESCRIPTION = 'Eng Khalid paid net cash for invoice issuing from East West Establishment';

    private const AMOUNT = 1800.00;

    private const DATE = '2026-09-30';

    public function run(): void
    {
        $company = Company::where('vat_number', '314526094900003')->first();

        if (! $company) {
            $this->command?->warn('Dynamic Core Contracting Company not found — run RealCompanySeeder first.');

            return;
        }

        // Idempotent against every other system account this company
        // already has — only creates the new 5900 row if missing.
        Account::seedSystemAccounts($company->id);
        $nonDeductible = Account::where('company_id', $company->id)->where('code', '5900')->first();

        if (! $nonDeductible) {
            $this->command?->error('Non-Deductible Expenses account (5900) could not be created — aborting.');

            return;
        }

        $expense = Expense::where('company_id', $company->id)
            ->whereHas('category', fn ($q) => $q->where('name', self::CATEGORY))
            ->where('description', self::DESCRIPTION)
            ->where('amount', self::AMOUNT)
            ->whereDate('expense_date', self::DATE)
            ->first();

        if (! $expense) {
            $this->command?->error('The real "invoice issuing" cost (expense id 22) was not found — nothing changed.');

            return;
        }

        if ((int) $expense->account_id === $nonDeductible->id) {
            $this->command?->info('Already reclassified — skipping.');

            return;
        }

        $ledger = app(LedgerPostingService::class);

        DB::transaction(function () use ($expense, $nonDeductible, $ledger, $company) {
            $ledger->deletePosting($company, 'expense', $expense->id);
            $expense->update(['account_id' => $nonDeductible->id]);
            $ledger->postExpense($expense->fresh(['account', 'bankAccount']));
        });

        $this->command?->info('Reclassified SAR '.number_format(self::AMOUNT, 2).' (cost of the fake invoice) to Non-Deductible Expenses.');
    }
}
