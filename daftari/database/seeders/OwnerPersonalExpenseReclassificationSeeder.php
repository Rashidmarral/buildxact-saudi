<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Company;
use App\Models\Expense;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * "how seperate the expenses what should i do... what should be
 * recorded in the app please set the complete accounts etc" — the real
 * audit found 13 real expense rows under the "Home" / "Home Food"
 * categories (coffee, shawarma, home groceries, home fuel/water —
 * SAR 1,248.00 combined) booked as ordinary Operating Expenses. These
 * are personal, not business, spending: they are not deductible and
 * they overstate real company expenses / understate real profit, which
 * is exactly what a Zakat/tax audit would flag.
 *
 * This does NOT delete them — the cash genuinely left a company account
 * and that movement is real, so the bank/cash side of every one of
 * these journal entries stays exactly as it was. It only moves the
 * OTHER side of each entry from Operating Expenses (account 5100) to
 * the new Owner's Drawings account (3100, an equity account — see
 * Account::systemAccounts()) by setting Expense.account_id, which
 * LedgerPostingService::postExpense() already uses to override its
 * default account when one is set. Owner's Drawings reduces what the
 * owner has invested in the company — it is the correct place for
 * personal spending that happens to flow through a company account,
 * instead of silently inflating Operating Expenses.
 *
 * Going forward: don't record personal/household spending as an
 * Expense at all. If cash must move through the company for it, record
 * it the same way (an Expense with its account_id set to Owner's
 * Drawings, 3100), or ask for a quick way to do this directly from the
 * expense form.
 *
 * Run it yourself, after taking a database backup:
 *   php artisan db:seed --class="Database\\Seeders\\OwnerPersonalExpenseReclassificationSeeder"
 *
 * Idempotent: skips any row whose account_id is already set to Owner's
 * Drawings.
 */
class OwnerPersonalExpenseReclassificationSeeder extends Seeder
{
    /**
     * Matched by category + description + amount + date — these rows
     * have no stable "number" of their own, unlike every other real
     * document seeded elsewhere in this engagement.
     */
    private const ROWS = [
        ['category' => 'Home', 'description' => 'Home food and water Khazan at Nawariya', 'amount' => 400.00, 'date' => '2026-09-30'],
        ['category' => 'Home', 'description' => 'Online transfer to Rashid account for daily food and fuel expenses', 'amount' => 300.00, 'date' => '2026-10-08'],
        ['category' => 'Home', 'description' => 'This amount spend on home expenses Jamum', 'amount' => 123.00, 'date' => '2026-09-30'],
        ['category' => 'Home', 'description' => "Paid net cash for Gas refill Minaraten hosh: 40 and Yaris fuel: 20 \r\nalso take employee to leave at car rental place", 'amount' => 60.00, 'date' => '2026-10-13'],
        ['category' => 'Home Food', 'description' => 'local food expense', 'amount' => 30.00, 'date' => '2026-10-13'],
        ['category' => 'Home Food', 'description' => 'Morning breakfast, Evening Shawarma 5 PCS, bottle, juices', 'amount' => 95.00, 'date' => '2026-10-14'],
        ['category' => 'Home Food', 'description' => 'Murgi chawal with chicken and drink refrence Khalid shb at jamum', 'amount' => 40.00, 'date' => '2026-10-15'],
        ['category' => 'Home', 'description' => 'Night shwarma ,burger, juices and bottle', 'amount' => 40.00, 'date' => '2026-10-16'],
        ['category' => 'Home', 'description' => 'Shawarma, burger, juice and bottle', 'amount' => 40.00, 'date' => '2026-10-15'],
        ['category' => 'Home', 'description' => 'Chicken and sabzi', 'amount' => 30.00, 'date' => '2026-10-16'],
        ['category' => 'Home', 'description' => 'Coffee', 'amount' => 20.00, 'date' => '2026-10-16'],
        ['category' => 'Home Food', 'description' => 'Home sabzi', 'amount' => 20.00, 'date' => '2026-10-18'],
        ['category' => 'Home', 'description' => 'Burger, bottle and juice', 'amount' => 50.00, 'date' => '2026-10-19'],
    ];

    public function run(): void
    {
        $company = Company::where('vat_number', '314526094900003')->first();

        if (! $company) {
            $this->command?->warn('Dynamic Core Contracting Company not found — run RealCompanySeeder first.');

            return;
        }

        // Idempotent against every other system account this company
        // already has — only the new Owner's Drawings row gets created.
        Account::seedSystemAccounts($company->id);
        $drawings = Account::where('company_id', $company->id)->where('code', '3100')->first();

        if (! $drawings) {
            $this->command?->error("Owner's Drawings account (3100) could not be created — aborting.");

            return;
        }

        $ledger = app(LedgerPostingService::class);
        $reclassified = 0;
        $skipped = 0;

        DB::transaction(function () use ($company, $drawings, $ledger, &$reclassified, &$skipped) {
            foreach (self::ROWS as $row) {
                $expense = Expense::where('company_id', $company->id)
                    ->whereHas('category', fn ($q) => $q->where('name', $row['category']))
                    ->where('description', $row['description'])
                    ->where('amount', $row['amount'])
                    ->whereDate('expense_date', $row['date'])
                    ->first();

                if (! $expense) {
                    $this->command?->warn("Expense not found (category={$row['category']}, amount={$row['amount']}, date={$row['date']}) — skipping, nothing changed for it.");

                    continue;
                }

                if ((int) $expense->account_id === $drawings->id) {
                    $skipped++;

                    continue;
                }

                $ledger->deletePosting($company, 'expense', $expense->id);
                $expense->update(['account_id' => $drawings->id]);
                $ledger->postExpense($expense->fresh(['account', 'bankAccount']));
                $reclassified++;
            }
        });

        $this->command?->info("Reclassified {$reclassified} personal expense(s) to Owner's Drawings (".$skipped.' already done).');
    }
}
