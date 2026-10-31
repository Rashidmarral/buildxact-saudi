<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Project;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * "i have mixed transaction and added expenses in wrong categories
 * please fix them all" — nine real expenses were sitting under the
 * wrong category, found by actually reading what each one says it
 * was for, not just its label:
 *
 *  - Two "Bill" rows are a workshop repair invoice and an electricity
 *    bill in disguise — "Bill" was never a real category of spending,
 *    just a catch-all someone picked when unsure.
 *  - A "payment cut for salaries" row sat under Operating Expense
 *    instead of Salaries.
 *  - Three of Nasir bhai's transfers, explicitly "for MC1 works" (a
 *    project milestone), sat under Operating Expense instead of
 *    Project — along with a lab test payment of the exact same kind
 *    as three OTHER lab/project payments already correctly under
 *    Project.
 *  - Two mechanical-parts purchases (bolt keys, spare parts) sat under
 *    Operating Expense instead of Maintenance.
 *
 * Every move here is grounded in the expense's own description —
 * nothing is guessed. Ambiguous rows with no specific signal (plain
 * "net cash paid to X" entries) are deliberately left alone rather
 * than reassigned on a guess.
 *
 * Moving a category also moves where the transaction posts in the GL
 * whenever it has no per-transaction account_id override (see
 * ExpenseCategoryCodingSeeder) — every expense here is unoverridden,
 * so each one is reposted to reflect its corrected category's account.
 * The five rows moving into Maintenance or Project (both flagged
 * requires_project) that had no project_id yet are backfilled to the
 * real Jumum Yard project — the only real project, and exactly where
 * this spending actually happened.
 *
 * Run it yourself, after taking a database backup, once
 * ExpenseCategoryCodingSeeder has already run:
 *   php artisan db:seed --class="Database\\Seeders\\ExpenseCategoryCorrectionSeeder"
 *
 * Idempotent: skips any row that's no longer in the category it's
 * being moved FROM (already corrected, or changed by hand since).
 */
class ExpenseCategoryCorrectionSeeder extends Seeder
{
    /**
     * [from category, description, amount, date, to category, backfill project]
     */
    private const MOVES = [
        ['Bill', 'Paid Warsha Bill from company account', 345.00, '2026-09-28', 'Maintenance', false],
        ['Bill', 'Nasir Bhai paid Minaraten Hosh Electric bill from his account', 327.00, '2026-09-30', 'Utilities', false],
        ['Operating expense', 'payment cut for salaries', 10.00, '2026-10-01', 'Salaries', false],
        ['Operating expense', 'Nasir bhai paid to MC1 from personal account', 1000.00, '2026-09-30', 'Project', true],
        ['Operating expense', 'Nasir bhai sent to Mubashir account for MC1 works', 250.00, '2026-09-30', 'Project', true],
        ['Operating expense', 'Nasir bhai online transfer to Azmat Six wala account', 500.00, '2026-09-29', 'Project', true],
        ['Operating expense', 'this amount paid for lab by khalid shb', 500.00, '2026-10-12', 'Project', false],
        ['Operating expense', 'Paid to Sheraz for food and spare parts balance', 50.00, '2026-10-12', 'Maintenance', true],
        ['Operating expense', 'purchase 2 silicone and bolt keys refrence Yahya', 40.00, '2026-10-14', 'Maintenance', true],
    ];

    public function run(): void
    {
        $company = Company::where('vat_number', '314526094900003')->first();

        if (! $company) {
            $this->command?->warn('Dynamic Core Contracting Company not found — run RealCompanySeeder first.');

            return;
        }

        $project = Project::where('company_id', $company->id)->where('code', 'PROJ_00001')->first();
        $ledger = app(LedgerPostingService::class);
        $moved = 0;
        $skipped = 0;

        DB::transaction(function () use ($company, $project, $ledger, &$moved, &$skipped) {
            foreach (self::MOVES as [$fromName, $description, $amount, $date, $toName, $backfillProject]) {
                $expense = Expense::where('company_id', $company->id)
                    ->whereHas('category', fn ($q) => $q->where('name', $fromName))
                    ->where('description', $description)
                    ->where('amount', $amount)
                    ->whereDate('expense_date', $date)
                    ->first();

                if (! $expense) {
                    $skipped++;
                    $this->command?->warn("Not found under \"{$fromName}\" (amount={$amount}, date={$date}) — already moved or changed, skipping.");

                    continue;
                }

                $toCategory = ExpenseCategory::where('company_id', $company->id)->where('name', $toName)->first();

                if (! $toCategory) {
                    $this->command?->error("Target category \"{$toName}\" not found — aborting without writing anything.");

                    return;
                }

                $update = ['expense_category_id' => $toCategory->id];
                if ($backfillProject && ! $expense->project_id && $project) {
                    $update['project_id'] = $project->id;
                }

                $ledger->deletePosting($company, 'expense', $expense->id);
                $expense->update($update);
                $ledger->postExpense($expense->fresh(['account', 'category', 'bankAccount']));
                $moved++;
            }
        });

        $this->command?->info("Recategorized {$moved} expense(s) to their real category ({$skipped} already done or not found).");
    }
}
