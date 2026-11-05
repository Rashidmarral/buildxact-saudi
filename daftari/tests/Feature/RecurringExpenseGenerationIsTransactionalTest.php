<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Company;
use App\Models\Expense;
use App\Models\Plan;
use App\Models\RecurringExpense;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Security audit finding M-22: RecurringExpense::generateExpense() ran
 * with no shared transaction — a failure partway through (most
 * realistically the ledger posting) left a real Expense already created
 * but next_run_date never advanced, so the next scheduled run would
 * generate a second expense for the same period on top of the first.
 */
class RecurringExpenseGenerationIsTransactionalTest extends TestCase
{
    use RefreshDatabase;

    private function makeOperationalCompany(): Company
    {
        $plan = Plan::create(['name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000, 'is_active' => true]);
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Subscription::create([
            'company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
        ]);

        return $company;
    }

    public function test_a_ledger_posting_failure_leaves_no_partial_state(): void
    {
        $company = $this->makeOperationalCompany();
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        // A locked accounting period covering the expense's date makes
        // LedgerPostingService::post() throw a PeriodLockedException —
        // a realistic failure mode partway through generateExpense().
        $company->update(['accounting_lock_date' => now()]);

        $recurring = RecurringExpense::create([
            'company_id' => $company->id, 'title' => 'Rent', 'gross_amount' => 1150,
            'tax_category' => 'standard_15', 'frequency' => 'monthly',
            'start_date' => now()->subMonth(), 'next_run_date' => now()->subDay(), 'status' => 'active',
        ]);

        try {
            $recurring->generateExpense();
            $this->fail('Expected postExpense() to throw when no account mapping exists.');
        } catch (\Throwable $e) {
            // expected
        }

        $this->assertSame(0, Expense::where('company_id', $company->id)->count());
        $recurring->refresh();
        $this->assertSame(0, $recurring->generated_count);
        $this->assertTrue($recurring->next_run_date->isYesterday());
    }

    public function test_the_console_command_still_processes_other_companies_after_one_fails(): void
    {
        $failingCompany = $this->makeOperationalCompany();
        Account::seedSystemAccounts($failingCompany->id);
        AccountMapping::seedDefaults($failingCompany->id);
        $failingCompany->update(['accounting_lock_date' => now()]);
        $failingRecurring = RecurringExpense::create([
            'company_id' => $failingCompany->id, 'title' => 'Rent', 'gross_amount' => 1150,
            'tax_category' => 'standard_15', 'frequency' => 'monthly',
            'start_date' => now()->subMonth(), 'next_run_date' => now()->subDay(), 'status' => 'active',
        ]);

        $healthyCompany = $this->makeOperationalCompany();
        Account::seedSystemAccounts($healthyCompany->id);
        AccountMapping::seedDefaults($healthyCompany->id);
        $healthyRecurring = RecurringExpense::create([
            'company_id' => $healthyCompany->id, 'title' => 'Utilities', 'gross_amount' => 230,
            'tax_category' => 'standard_15', 'frequency' => 'monthly',
            'start_date' => now()->subMonth(), 'next_run_date' => now()->subDay(), 'status' => 'active',
        ]);

        $this->artisan('expenses:generate-recurring')->assertSuccessful();

        $this->assertSame(0, Expense::where('company_id', $failingCompany->id)->count());
        $this->assertSame(1, Expense::where('company_id', $healthyCompany->id)->count());
        $healthyRecurring->refresh();
        $this->assertSame(1, $healthyRecurring->generated_count);
    }
}
