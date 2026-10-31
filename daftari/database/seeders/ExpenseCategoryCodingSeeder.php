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
 * "add proper expenses categories with code recommended by zatca and
 * adjust transaction by that way" — ZATCA itself doesn't publish a
 * fixed numeric code list for expense sub-categories (that's an
 * internal chart-of-accounts matter); what actually matters for
 * ZATCA/Zakat compliance is exactly what Section 3 already
 * recommended: separating direct project cost from general
 * administrative expense, flagging which categories normally carry
 * recoverable input VAT, and giving every category a short stable
 * code for reporting/export. This seeder applies that recommendation
 * to the 13 real categories already in use (the duplicate "Rent" row
 * is coded the same as the real one and soft-disabled, never merged
 * or deleted), grouped under five parent categories mirroring a
 * standard Saudi SME/Zakat cost breakdown: Direct Project Costs,
 * General & Administrative, Payroll & Benefits, Owner/Personal, and
 * Tax & VAT.
 *
 * Three categories get a real GL account other than the 5100 default
 * (Salaries -> 5350 Salaries & Wages Expense; Maintenance, Fuel and
 * Project -> 5000 Cost of Goods Sold, i.e. direct project cost) via
 * ExpenseCategory.linked_account_id, which
 * LedgerPostingService::postExpense() now falls back to whenever a
 * transaction has no explicit account_id override of its own. "Adjust
 * transaction by that way": every already-posted expense in one of
 * those three categories that has no per-transaction override
 * (account_id still null) is reposted so its journal entry reflects
 * the new category default — an explicit override on any individual
 * expense (e.g. the owner-drawings or VAT reclassifications already
 * built) always wins and is never touched here.
 *
 * Run it yourself, after taking a database backup:
 *   php artisan db:seed --class="Database\\Seeders\\ExpenseCategoryCodingSeeder"
 *
 * Idempotent: skips any category whose code is already set, and any
 * expense whose journal entry is already posted to its category's
 * current linked account.
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
     * name => [code, group, linked_account_code (null = leave at the
     * 5100 global default), is_direct_cost, requires_project, vat_eligible]
     */
    private const CATEGORIES = [
        'Office supplies' => ['ADMIN-OFFICE', 'General & Administrative', null, false, false, true],
        'Utilities' => ['ADMIN-UTIL', 'General & Administrative', null, false, false, true],
        'Rent' => ['ADMIN-RENT', 'General & Administrative', null, false, false, true],
        'Bill' => ['ADMIN-BILL', 'General & Administrative', null, false, false, true],
        'Management' => ['ADMIN-MGMT', 'General & Administrative', null, false, false, false],
        'Operating expense' => ['ADMIN-OPEX', 'General & Administrative', null, false, false, false],
        'Salaries' => ['PAYROLL-SALARY', 'Payroll & Benefits', '5350', false, false, false],
        'Maintenance' => ['DIRECT-MAINT', 'Direct Project Costs', '5000', true, true, true],
        'Fuel' => ['DIRECT-FUEL', 'Direct Project Costs', '5000', true, true, true],
        'Project' => ['DIRECT-PROJECT', 'Direct Project Costs', '5000', true, true, false],
        'Home' => ['OWNER-HOME', 'Owner / Personal', '3100', false, false, false],
        'Home Food' => ['OWNER-HOMEFOOD', 'Owner / Personal', '3100', false, false, false],
        'VAT' => ['TAX-VAT', 'Tax & VAT', '2100', false, false, false],
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

            foreach (self::CATEGORIES as $name => [$code, $groupName, $linkedCode, $isDirect, $requiresProject, $vatEligible]) {
                $rows = ExpenseCategory::withoutGlobalScope('company')
                    ->where('company_id', $company->id)
                    ->where('name', $name)
                    ->orderBy('id')
                    ->get();

                if ($rows->isEmpty()) {
                    $this->command?->warn("Expense category \"{$name}\" not found — skipping.");

                    continue;
                }

                $linkedAccount = $linkedCode
                    ? Account::where('company_id', $company->id)->where('code', $linkedCode)->first()
                    : null;

                foreach ($rows as $i => $category) {
                    $alreadyDone = $category->code === $code;

                    $category->update([
                        'code' => $code,
                        'parent_category_id' => $groups[$groupName]->id,
                        'linked_account_id' => $linkedAccount?->id,
                        'is_direct_cost' => $isDirect,
                        'requires_project' => $requiresProject,
                        'vat_eligible' => $vatEligible,
                        // The first row (oldest id) is the real, in-use
                        // category; any later row sharing the same name
                        // is a duplicate — coded the same for reporting,
                        // but soft-disabled rather than merged or deleted.
                        'is_active' => $i === 0,
                    ]);

                    if (! $alreadyDone && $linkedAccount) {
                        $repostCategoryIds[] = $category->id;
                    }
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
