<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * "please remove my added categories because i have create them
 * randomly i don't know the accounts... remove the old fake
 * categories" — the last step of the category overhaul, run after
 * ExpenseCategoryCodingSeeder (renames the real categories and adds
 * the operator's requested ones) and ExpenseCategoryCorrectionSeeder
 * (moves every real expense onto the final list). By the time this
 * runs, "Bill", "Fuel", "Management" and "Operating expense" should
 * have no real expenses left pointing at them — along with anything
 * else: a category the operator created by hand through the Expense
 * Categories page while experimenting, with nothing real ever posted
 * to it.
 *
 * Anything with ZERO expenses left and a name outside the final list
 * (the operator's twelve plus Home / Home Food / VAT / Project — the
 * four that have to exist for real, already-corrected transactions
 * with nothing else to go to) is deleted outright. Anything that
 * genuinely still has expenses is left alone and reported instead of
 * silently deleted or silently keeping bad data — check those by hand.
 *
 * Also resolves the one confirmed accidental duplicate: a second
 * "Rent" category with the same name as the real one. Its expenses
 * (if it has any) are moved into the real "Rent" row first — never
 * silently dropped — then the empty duplicate is deleted.
 *
 * Run it yourself, after taking a database backup, as the last of the
 * three category-overhaul seeders:
 *   php artisan db:seed --class="Database\\Seeders\\RetireLegacyExpenseCategoriesSeeder"
 *
 * Idempotent: a category already gone stays gone; nothing to delete
 * means nothing happens.
 */
class RetireLegacyExpenseCategoriesSeeder extends Seeder
{
    private const FINAL_NAMES = [
        'Bank Charges', 'Marketing & Advertising', 'Miscellaneous', 'Office Supplies',
        'Professional Fees', 'Rent', 'Repairs & Maintenance', 'Salaries & Wages',
        'Telephone & Internet', 'Transportation', 'Travel', 'Utilities',
        'Home', 'Home Food', 'VAT', 'Project',
    ];

    public function run(): void
    {
        $company = Company::where('vat_number', '314526094900003')->first();

        if (! $company) {
            $this->command?->warn('Dynamic Core Contracting Company not found — run RealCompanySeeder first.');

            return;
        }

        $ledger = app(LedgerPostingService::class);
        $deleted = 0;
        $merged = 0;
        $kept = [];

        DB::transaction(function () use ($company, $ledger, &$deleted, &$merged, &$kept) {
            // True duplicates first: more than one row sharing the same
            // final-list name. Keep the oldest (lowest id), fold any
            // expenses on the others into it, delete the rest.
            foreach (self::FINAL_NAMES as $name) {
                $rows = ExpenseCategory::where('company_id', $company->id)
                    ->where('name', $name)
                    ->orderBy('id')
                    ->get();

                if ($rows->count() < 2) {
                    continue;
                }

                $canonical = $rows->first();
                foreach ($rows->slice(1) as $duplicate) {
                    foreach ($duplicate->expenses()->get() as $expense) {
                        $ledger->deletePosting($company, 'expense', $expense->id);
                        $expense->update(['expense_category_id' => $canonical->id]);
                        $ledger->postExpense($expense->fresh(['account', 'category', 'bankAccount']));
                        $merged++;
                    }
                    $duplicate->delete();
                }
            }

            // Then anything outside the final list at all.
            ExpenseCategory::where('company_id', $company->id)
                ->whereNotIn('name', self::FINAL_NAMES)
                ->get()
                ->each(function (ExpenseCategory $category) use (&$deleted, &$kept) {
                    if ($category->expenses()->exists() || $category->children()->exists()) {
                        $kept[] = $category->name;

                        return;
                    }

                    $category->delete();
                    $deleted++;
                });
        });

        $this->command?->info("Removed {$deleted} legacy/random categor(y/ies), merged {$merged} duplicate-category expense(s).");
        if (! empty($kept)) {
            $this->command?->warn('Left in place (still has real expenses or sub-categories, check by hand): '.implode(', ', $kept));
        }
    }
}
