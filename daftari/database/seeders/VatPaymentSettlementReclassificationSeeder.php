<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Company;
use App\Models\Expense;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Master Report, Section 11 #11: the real SAR 3,113.41 "Paid Company
 * vat" expense (category VAT, dated 2026-10-06) is a settlement of VAT
 * already owed to ZATCA, not a new business cost — recording it as an
 * ordinary Operating Expense overstates expenses and understates the
 * VAT Output liability by the same amount. Your rule 14: a liability
 * settlement is never a new cost.
 *
 * The bank side is untouched (the cash really did leave the bank
 * account on that date) — only the other side of the entry moves, from
 * Operating Expenses to VAT Output (code 2100), the same account
 * every real sales invoice credits when it charges VAT. Because VAT
 * Output is a liability (credit-normal), debiting it here correctly
 * reduces the balance ZATCA is still owed, exactly as if this were the
 * VAT remittance itself.
 *
 * Run it yourself, after taking a database backup:
 *   php artisan db:seed --class="Database\\Seeders\\VatPaymentSettlementReclassificationSeeder"
 *
 * Idempotent: skips if the expense's account_id is already VAT Output.
 */
class VatPaymentSettlementReclassificationSeeder extends Seeder
{
    private const CATEGORY = 'VAT';

    private const DESCRIPTION = 'Paid Company vat';

    private const AMOUNT = 3113.41;

    private const DATE = '2026-10-06';

    public function run(): void
    {
        $company = Company::where('vat_number', '314526094900003')->first();

        if (! $company) {
            $this->command?->warn('Dynamic Core Contracting Company not found — run RealCompanySeeder first.');

            return;
        }

        $vatOutput = Account::where('company_id', $company->id)->where('code', '2100')->first();

        if (! $vatOutput) {
            $this->command?->error('VAT Output account (2100) not found — run Account::seedSystemAccounts() first.');

            return;
        }

        $expense = Expense::where('company_id', $company->id)
            ->whereHas('category', fn ($q) => $q->where('name', self::CATEGORY))
            ->where('description', self::DESCRIPTION)
            ->where('amount', self::AMOUNT)
            ->whereDate('expense_date', self::DATE)
            ->first();

        if (! $expense) {
            $this->command?->error('The real "Paid Company vat" expense was not found — nothing changed.');

            return;
        }

        if ((int) $expense->account_id === $vatOutput->id) {
            $this->command?->info('Already settled against VAT Output — skipping.');

            return;
        }

        $ledger = app(LedgerPostingService::class);

        DB::transaction(function () use ($expense, $vatOutput, $ledger, $company) {
            $ledger->deletePosting($company, 'expense', $expense->id);
            $expense->update(['account_id' => $vatOutput->id]);
            $ledger->postExpense($expense->fresh(['account', 'bankAccount']));
        });

        $this->command?->info('Settled SAR '.number_format(self::AMOUNT, 2).' VAT payment against VAT Output instead of Operating Expenses.');
    }
}
