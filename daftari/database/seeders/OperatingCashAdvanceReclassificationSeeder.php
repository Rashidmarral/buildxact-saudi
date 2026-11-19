<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Company;
use App\Models\Expense;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Master Report, Section 12: you confirmed the 9 "Operating expense"
 * cash-to-individual rows (SAR 16,350.00 total, 12 real expense rows —
 * three of them grouped under one Section 4 line each for Nasir bhai's
 * MC1 transfers and Sheraz's food/parts top-up) are cash handed to
 * people that is still sitting with them, not yet spent and settled —
 * so it belongs in Due from Employees (Advances), not Operating
 * Expenses.
 *
 * Exactly the same mechanism as
 * OwnerPersonalExpenseReclassificationSeeder: the bank/cash side of
 * each entry is untouched (the cash really did leave), only the other
 * side moves — from Operating Expenses (5100) to Due from Employees
 * (Advances) (1260, an asset — see Account::systemAccounts()) — via
 * Expense.account_id, which LedgerPostingService::postExpense() already
 * uses to override its default account.
 *
 * Going forward: when cash is handed to someone to spend on the
 * project's behalf, record it as an Expense with account_id set to
 * 1260 at the time it's given; settle it later (set employee_id on the
 * real spending Expense once receipts come in) rather than booking the
 * hand-over itself as an Operating Expense.
 *
 * Run it yourself, after taking a database backup:
 *   php artisan db:seed --class="Database\\Seeders\\OperatingCashAdvanceReclassificationSeeder"
 *
 * Idempotent: skips any row whose account_id is already Due from
 * Employees (Advances).
 */
class OperatingCashAdvanceReclassificationSeeder extends Seeder
{
    /**
     * Matched by category + description + amount + date, the same way
     * OwnerPersonalExpenseReclassificationSeeder matches its rows —
     * these have no stable "number" of their own.
     */
    private const ROWS = [
        ['category' => 'Operating expense', 'description' => 'Net Cash paid to sheraz', 'amount' => 500.00, 'date' => '2026-09-28'],
        ['category' => 'Operating expense', 'description' => 'Net cash paid to Khalid shb Saudi', 'amount' => 2000.00, 'date' => '2026-09-29'],
        ['category' => 'Operating expense', 'description' => 'Net cash paid to Rashid', 'amount' => 1000.00, 'date' => '2026-09-30'],
        ['category' => 'Operating expense', 'description' => 'Net cash paid to Eng Khalid Shb', 'amount' => 10000.00, 'date' => '2026-09-29'],
        ['category' => 'Operating expense', 'description' => 'payment cut for salaries', 'amount' => 10.00, 'date' => '2026-10-01'],
        ['category' => 'Operating expense', 'description' => 'Nasir bhai paid to MC1 from personal account', 'amount' => 1000.00, 'date' => '2026-09-30'],
        ['category' => 'Operating expense', 'description' => 'Nasir bhai sent to Mubashir account for MC1 works', 'amount' => 250.00, 'date' => '2026-09-30'],
        ['category' => 'Operating expense', 'description' => 'Nasir bhai online transfer to Azmat Six wala account', 'amount' => 500.00, 'date' => '2026-09-29'],
        ['category' => 'Management', 'description' => 'Nasir bhai withdrawal cash from his account and Paid to Khalid Saudi Cash', 'amount' => 500.00, 'date' => '2026-09-30'],
        ['category' => 'Operating expense', 'description' => 'Paid to Sheraz for food and spare parts balance', 'amount' => 50.00, 'date' => '2026-10-12'],
        ['category' => 'Operating expense', 'description' => 'purchase 2 silicone and bolt keys refrence Yahya', 'amount' => 40.00, 'date' => '2026-10-14'],
        ['category' => 'Operating expense', 'description' => 'this amount paid for lab by khalid shb', 'amount' => 500.00, 'date' => '2026-10-12'],
    ];

    public function run(): void
    {
        $company = Company::where('vat_number', '314526094900003')->first();

        if (! $company) {
            $this->command?->warn('Dynamic Core Contracting Company not found — run RealCompanySeeder first.');

            return;
        }

        // Idempotent against every other system account this company
        // already has — only creates anything missing.
        Account::seedSystemAccounts($company->id);
        $advances = Account::where('company_id', $company->id)->where('code', '1260')->first();

        if (! $advances) {
            $this->command?->error('Due from Employees (Advances) account (1260) could not be created — aborting.');

            return;
        }

        $ledger = app(LedgerPostingService::class);
        $reclassified = 0;
        $skipped = 0;

        DB::transaction(function () use ($company, $advances, $ledger, &$reclassified, &$skipped) {
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

                if ((int) $expense->account_id === $advances->id) {
                    $skipped++;

                    continue;
                }

                $ledger->deletePosting($company, 'expense', $expense->id);
                $expense->update(['account_id' => $advances->id]);
                $ledger->postExpense($expense->fresh(['account', 'bankAccount']));
                $reclassified++;
            }
        });

        $this->command?->info("Reclassified {$reclassified} cash-advance row(s) to Due from Employees (Advances) (".$skipped.' already done).');
    }
}
