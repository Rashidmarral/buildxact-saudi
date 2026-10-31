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
 * please fix them all" / "remove the old fake categories please set
 * all according to our account system in our app" — every one of the
 * 48 real expenses is checked against its own description and set to
 * its correct category under the operator's final category list (see
 * ExpenseCategoryCodingSeeder): Bank Charges, Marketing & Advertising,
 * Miscellaneous, Office Supplies, Professional Fees, Rent, Repairs &
 * Maintenance, Salaries & Wages, Telephone & Internet, Transportation,
 * Travel, Utilities, plus Home/Home Food/VAT/Project for real spending
 * none of those twelve cover:
 *
 *  - Two "Bill" rows are a workshop repair invoice and an electricity
 *    bill in disguise -> Repairs & Maintenance / Utilities. "Bill" is
 *    retired entirely (see RetireLegacyExpenseCategoriesSeeder).
 *  - A "payment cut for salaries" row -> Salaries & Wages.
 *  - Three of Nasir bhai's transfers, explicitly "for MC1 works" (a
 *    project milestone), plus a lab test payment of the exact same
 *    kind as three OTHER lab/project payments already correctly under
 *    Project -> Project.
 *  - Two mechanical-parts purchases (bolt keys, spare parts) -> Repairs
 *    & Maintenance.
 *  - Both real Fuel rows -> Transportation (Fuel is retired).
 *  - Every "Management" and remaining ambiguous "Operating expense"
 *    row (plain "net cash paid to X" with no specific signal) ->
 *    Miscellaneous — the operator's own requested catch-all, not a
 *    guess at a more specific category. Both "Operating expense" and
 *    "Management" are retired.
 *
 * Every move here is grounded in the expense's own description or the
 * operator's own instruction — nothing is guessed beyond that.
 *
 * On a real run this also found and fixes a second, real problem: the
 * original ExpenseCategoryController::destroy() (before this
 * engagement added a guard) deleted a category with no check for
 * expenses still using it — and expense_category_id is nullOnDelete,
 * so deleting "Utilities" silently set every expense that used it back
 * to uncategorized rather than failing loudly. Covering all 48 real
 * expenses here (not just the ones that were wrong) means this also
 * repairs any expense an accidentally-deleted category left stranded,
 * and every category this seeder targets is firstOrCreate'd — a run
 * never aborts partway through for a missing category again.
 *
 * Moving a category also moves where the transaction posts in the GL
 * whenever it has no per-transaction account_id override (see
 * ExpenseCategoryCodingSeeder) — every expense actually moved here is
 * unoverridden, so each one is reposted to reflect its category's
 * account. The five rows moving into Repairs & Maintenance or Project
 * (both flagged requires_project) that had no project_id yet are
 * backfilled to the real Jumum Yard project — the only real project,
 * and exactly where this spending actually happened.
 *
 * Run it yourself, after taking a database backup, right after
 * ExpenseCategoryCodingSeeder:
 *   php artisan db:seed --class="Database\\Seeders\\ExpenseCategoryCorrectionSeeder"
 *
 * Idempotent: an expense already in its correct category is left
 * completely alone (no repost, no write) — safe to re-run any time.
 */
class ExpenseCategoryCorrectionSeeder extends Seeder
{
    /**
     * [description, amount, date, correct category, backfill project]
     * — every real expense, not just the ones that were wrong.
     */
    private const CORRECT_CATEGORY = [
        ['Printer paper and toner', 449.57, '2026-08-17', 'Office Supplies', false],
        ['Warehouse rent — this month', 20000.00, '2026-08-22', 'Rent', false],
        ['Electricity — warehouse', 1840.00, '2026-08-20', 'Utilities', false],
        ['hi this is our test expense', 260.87, '2026-09-04', 'Miscellaneous', false],
        ['Paid salaries of Nasir bhai and Ishfaq from company account payments', 3800.00, '2026-09-27', 'Salaries & Wages', false],
        ['Change Roller tyre and tube, online transfer through company account', 550.00, '2026-09-29', 'Repairs & Maintenance', false],
        ['Paid Warsha Bill from company account', 345.00, '2026-09-28', 'Repairs & Maintenance', false],
        ['Net Cash paid to sheraz', 500.00, '2026-09-28', 'Miscellaneous', false],
        ['Net cash paid to Khalid shb Saudi', 2000.00, '2026-09-29', 'Miscellaneous', false],
        ['Net cash paid to Rashid', 1000.00, '2026-09-30', 'Miscellaneous', false],
        ['Net cash paid to Eng Khalid Shb', 10000.00, '2026-09-29', 'Miscellaneous', false],
        ['Net cash paid for Roller tyre and tube replace', 1550.00, '2026-09-29', 'Repairs & Maintenance', false],
        ['Net cash paid to Electrician for roller electrical work', 350.00, '2026-09-29', 'Repairs & Maintenance', false],
        ['Net Cash paid to Rasheed for Hydrollic and roller tyre change', 500.00, '2026-09-28', 'Repairs & Maintenance', false],
        ['Home food and water Khazan at Nawariya', 400.00, '2026-09-30', 'Home', false],
        ['Paid Company vat', 3113.41, '2026-10-06', 'VAT', false],
        ['payment cut for salaries', 10.00, '2026-10-01', 'Salaries & Wages', false],
        ['transfer salaries from company current account', 3800.00, '2026-10-01', 'Salaries & Wages', false],
        ['Online transfer to Rashid account for daily food and fuel expenses', 300.00, '2026-10-08', 'Home', false],
        ["Paid remaining vat amount of invoice: 240\r\nBobcat motors repairs: 1200", 1440.00, '2026-10-11', 'Repairs & Maintenance', false],
        ['Net cash paid to Mukhtabir by Khalid Sir', 1000.00, '2026-09-28', 'Project', false],
        ['Eng Khalid paid net cash for invoice issuing from East West Establishment', 1800.00, '2026-09-30', 'VAT', false],
        ['Net cash paid to Shahid by Eng Khalid', 500.00, '2026-10-13', 'Project', false],
        ['Paid net cash to Sahhid for Labortry Test by Eng Khalid', 1800.00, '2026-10-12', 'Project', false],
        ['Online transfer to Arif Bangali account for Bobcat motors payment 1200 and vat amount due 240', 1440.00, '2026-10-12', 'Repairs & Maintenance', false],
        ['Nasir bhai paid to MC1 from personal account', 1000.00, '2026-09-30', 'Project', true],
        ['Nasir bhai sent to Mubashir account for MC1 works', 250.00, '2026-09-30', 'Project', true],
        ['Nasir bhai online transfer to Azmat Six wala account', 500.00, '2026-09-29', 'Project', true],
        ['Nasir bhai withdrawal cash from his account and Paid to Khalid Saudi Cash', 500.00, '2026-09-30', 'Miscellaneous', false],
        ['Nasir Bhai paid Minaraten Hosh Electric bill from his account', 327.00, '2026-09-30', 'Utilities', false],
        ['This amount spend on home expenses Jamum', 123.00, '2026-09-30', 'Home', false],
        ['Yaris fuel to purchase parts from Jeddah and also go to site to give net cash to shahid', 50.00, '2026-10-12', 'Transportation', false],
        ['Paid to Sheraz for food and spare parts balance', 50.00, '2026-10-12', 'Repairs & Maintenance', true],
        ["Paid net cash for Gas refill Minaraten hosh: 40 and Yaris fuel: 20 \r\nalso take employee to leave at car rental place", 60.00, '2026-10-13', 'Home', false],
        ['local food expense', 30.00, '2026-10-13', 'Home Food', false],
        ['purchase 2 silicone and bolt keys refrence Yahya', 40.00, '2026-10-14', 'Repairs & Maintenance', true],
        ['Morning breakfast, Evening Shawarma 5 PCS, bottle, juices', 95.00, '2026-10-14', 'Home Food', false],
        ['Murgi chawal with chicken and drink refrence Khalid shb at jamum', 40.00, '2026-10-15', 'Home Food', false],
        ['Night shwarma ,burger, juices and bottle', 40.00, '2026-10-16', 'Home', false],
        ['Shawarma, burger, juice and bottle', 40.00, '2026-10-15', 'Home', false],
        ['Chicken and sabzi', 30.00, '2026-10-16', 'Home', false],
        ['Coffee', 20.00, '2026-10-16', 'Home', false],
        ['Yaris Fuel for Masim Pump delivery and food', 60.00, '2026-10-17', 'Transportation', false],
        ['Home sabzi', 20.00, '2026-10-18', 'Home Food', false],
        ['Burger, bottle and juice', 50.00, '2026-10-19', 'Home', false],
        ['Roler pump maintenance paid net cash Masam', 600.00, '2026-10-18', 'Repairs & Maintenance', false],
        ['Paid net cash to nasir bhai 500+4600 and ashiq bhai : 3300', 8400.00, '2026-10-19', 'Miscellaneous', false],
        ['this amount paid for lab by khalid shb', 500.00, '2026-10-12', 'Project', false],
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

        // Every target category is guaranteed to exist before any
        // expense is touched — a category missing on this specific
        // database (e.g. accidentally deleted before the destroy()
        // guard existed) is simply recreated, never a reason to abort
        // partway through leaving some expenses fixed and others not.
        $categoryNames = array_unique(array_column(self::CORRECT_CATEGORY, 3));
        $categories = [];
        foreach ($categoryNames as $name) {
            $categories[$name] = ExpenseCategory::where('company_id', $company->id)
                ->where('name', $name)
                ->orderByDesc('is_active')
                ->orderBy('id')
                ->first()
                ?? ExpenseCategory::create(['company_id' => $company->id, 'name' => $name]);
        }

        $moved = 0;
        $alreadyCorrect = 0;
        $notFound = 0;

        DB::transaction(function () use ($company, $project, $ledger, $categories, &$moved, &$alreadyCorrect, &$notFound) {
            foreach (self::CORRECT_CATEGORY as [$description, $amount, $date, $correctName, $backfillProject]) {
                $expense = Expense::where('company_id', $company->id)
                    ->where('description', $description)
                    ->where('amount', $amount)
                    ->whereDate('expense_date', $date)
                    ->first();

                if (! $expense) {
                    $notFound++;

                    continue;
                }

                $correctCategory = $categories[$correctName];

                if ((int) $expense->expense_category_id === $correctCategory->id) {
                    $alreadyCorrect++;

                    continue;
                }

                $update = ['expense_category_id' => $correctCategory->id];
                if ($backfillProject && ! $expense->project_id && $project) {
                    $update['project_id'] = $project->id;
                }

                $ledger->deletePosting($company, 'expense', $expense->id);
                $expense->update($update);
                $ledger->postExpense($expense->fresh(['account', 'category', 'bankAccount']));
                $moved++;
            }
        });

        $this->command?->info("Set {$moved} expense(s) to their correct category ({$alreadyCorrect} already correct, {$notFound} not found).");
    }
}
