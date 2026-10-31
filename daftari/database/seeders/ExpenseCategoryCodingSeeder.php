<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * "please remove my added categories because i have create them
 * randomly i don't know the accounts add these and assign transaction
 * with matching categories remove the old fake categories please set
 * all according to our account system in our app" — replaces the
 * mnemonic codes this seeder used to assign with the operator's own
 * requested list (Bank Charges 6180, Marketing & Advertising 6170,
 * Miscellaneous 6900, Office Supplies 6140, Professional Fees 6190,
 * Rent 6110, Repairs & Maintenance 6150, Salaries & Wages 6100,
 * Telephone & Internet 6130, Transportation 6220, Travel 6160,
 * Utilities 6120 — a standard SME expense chart), renaming the five
 * real categories that already match one of these in meaning (Office
 * supplies, Utilities, Rent, Salaries, Maintenance) rather than
 * creating duplicates, and adding the seven the operator's real data
 * never had a category for yet.
 *
 * Four more categories stay outside that list because real,
 * already-corrected transactions depend on them and none of the 12
 * fit: Home / Home Food (the owner's personal spending, posts to
 * Owner's Drawings 3100), VAT (the real VAT settlement and the
 * fake-invoice cost, posts to VAT Output 2100), and Project (direct
 * project cash payments, posts to 5000 Cost of Goods Sold like
 * Repairs & Maintenance and Transportation do).
 *
 * Grouped under five parents mirroring a standard Saudi SME/Zakat cost
 * breakdown: Direct Project Costs, General & Administrative, Payroll &
 * Benefits, Owner/Personal, Tax & VAT.
 *
 * "set all according to our account system": Salaries & Wages posts to
 * 5350; Repairs & Maintenance and Transportation post to 5000 Cost of
 * Goods Sold (real direct project cost, not generic overhead) via
 * ExpenseCategory.linked_account_id, which
 * LedgerPostingService::postExpense() falls back to whenever a
 * transaction has no explicit account_id override of its own. Every
 * already-posted, un-overridden expense in a category whose linked
 * account changes is reposted so its journal entry reflects the new
 * account — an explicit override on any individual expense (e.g. the
 * owner-drawings or VAT reclassifications already built) always wins
 * and is never touched here.
 *
 * Run this first, then ExpenseCategoryCorrectionSeeder (moves every
 * real transaction to its correct category under these new names),
 * then RetireLegacyExpenseCategoriesSeeder (removes whatever's left
 * over once nothing real points at it anymore):
 *   php artisan db:seed --class="Database\\Seeders\\ExpenseCategoryCodingSeeder"
 *
 * Idempotent: safe to re-run — renames only where the old name is
 * still in use, and an already-correct category is left untouched.
 */
class ExpenseCategoryCodingSeeder extends Seeder
{
    private const GROUPS = [
        'Direct Project Costs' => 'DIRECT',
        'General & Administrative' => 'ADMIN',
        'Payroll & Benefits' => 'PAYROLL',
        'Owner / Personal' => 'OWNER',
        'Tax & VAT' => 'TAX',
    ];

    /**
     * [match name (the real category already in use, or null if this
     * is a brand new one), final name, code, group, linked_account_code
     * (null = leave at the 5100 global default), is_direct_cost,
     * requires_project, vat_eligible]
     */
    private const CATEGORIES = [
        ['Salaries', 'Salaries & Wages', '6100', 'Payroll & Benefits', '5350', false, false, false],
        ['Rent', 'Rent', '6110', 'General & Administrative', null, false, false, true],
        ['Utilities', 'Utilities', '6120', 'General & Administrative', null, false, false, true],
        [null, 'Telephone & Internet', '6130', 'General & Administrative', null, false, false, true],
        ['Office supplies', 'Office Supplies', '6140', 'General & Administrative', null, false, false, true],
        ['Maintenance', 'Repairs & Maintenance', '6150', 'Direct Project Costs', '5000', true, true, true],
        [null, 'Travel', '6160', 'General & Administrative', null, false, false, true],
        [null, 'Marketing & Advertising', '6170', 'General & Administrative', null, false, false, true],
        [null, 'Bank Charges', '6180', 'General & Administrative', null, false, false, true],
        [null, 'Professional Fees', '6190', 'General & Administrative', null, false, false, true],
        [null, 'Transportation', '6220', 'Direct Project Costs', '5000', true, true, true],
        [null, 'Miscellaneous', '6900', 'General & Administrative', null, false, false, false],
        ['Home', 'Home', 'OWNER-HOME', 'Owner / Personal', '3100', false, false, false],
        ['Home Food', 'Home Food', 'OWNER-HOMEFOOD', 'Owner / Personal', '3100', false, false, false],
        ['VAT', 'VAT', 'TAX-VAT', 'Tax & VAT', '2100', false, false, false],
        ['Project', 'Project', 'DIRECT-PROJECT', 'Direct Project Costs', '5000', true, true, false],
    ];

    public function run(): void
    {
        $company = Company::where('vat_number', '314526094900003')->first();

        if (! $company) {
            $this->command?->warn('Dynamic Core Contracting Company not found — run RealCompanySeeder first.');

            return;
        }

        $ledger = app(LedgerPostingService::class);

        DB::transaction(function () use ($company, $ledger) {
            $groups = [];
            foreach (self::GROUPS as $name => $code) {
                $groups[$name] = ExpenseCategory::withoutGlobalScope('company')->firstOrCreate(
                    ['company_id' => $company->id, 'name' => $name],
                    ['code' => $code, 'is_active' => true]
                );

                if (! $groups[$name]->code) {
                    $groups[$name]->update(['code' => $code]);
                }
            }

            $repostCategoryIds = [];

            foreach (self::CATEGORIES as [$matchName, $finalName, $code, $groupName, $linkedCode, $isDirect, $requiresProject, $vatEligible]) {
                // Prefer the real, already-in-use row (by its old name);
                // fall back to one already renamed on an earlier run;
                // create it fresh only if neither exists.
                $category = ExpenseCategory::withoutGlobalScope('company')
                    ->where('company_id', $company->id)
                    ->when($matchName, fn ($q) => $q->whereIn('name', array_unique([$matchName, $finalName])), fn ($q) => $q->where('name', $finalName))
                    ->orderBy('id')
                    ->first()
                    ?? ExpenseCategory::create(['company_id' => $company->id, 'name' => $finalName]);

                $linkedAccount = $linkedCode
                    ? Account::where('company_id', $company->id)->where('code', $linkedCode)->first()
                    : null;

                $alreadyDone = $category->code === $code && $category->name === $finalName;

                $category->update([
                    'name' => $finalName,
                    'code' => $code,
                    'parent_category_id' => $groups[$groupName]->id,
                    'linked_account_id' => $linkedAccount?->id,
                    'is_direct_cost' => $isDirect,
                    'requires_project' => $requiresProject,
                    'vat_eligible' => $vatEligible,
                    'is_active' => true,
                ]);

                if (! $alreadyDone && $linkedAccount) {
                    $repostCategoryIds[] = $category->id;
                }
            }

            if (empty($repostCategoryIds)) {
                return;
            }

            $reposted = 0;
            Expense::where('company_id', $company->id)
                ->whereIn('expense_category_id', $repostCategoryIds)
                ->whereNull('account_id')
                ->get()
                ->each(function (Expense $expense) use ($ledger, $company, &$reposted) {
                    $ledger->deletePosting($company, 'expense', $expense->id);
                    $ledger->postExpense($expense->fresh(['account', 'category', 'bankAccount']));
                    $reposted++;
                });

            $this->command?->info("Expense categories coded; {$reposted} transaction(s) reposted to their category's new default account.");
        });
    }
}
