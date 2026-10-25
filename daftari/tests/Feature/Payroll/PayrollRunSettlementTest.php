<?php

namespace Tests\Feature\Payroll;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Employee;
use App\Models\JournalEntry;
use App\Models\PayrollRun;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit finding PAYROLL-01: marking a payroll run "paid" only flipped its
 * status column — it never settled SALARIES_PAYABLE (credited when the
 * run was approved, see postPayrollRun()) against the bank/cash account
 * salaries were actually paid from. The liability sat on the books
 * forever as if salaries were never actually paid.
 */
class PayrollRunSettlementTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompanyWithPayroll(): Company
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000,
            'is_active' => true, 'has_payroll' => true,
        ]);

        $company = Company::create(['name' => 'Acme', 'slug' => 'acme-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        Subscription::create([
            'company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
        ]);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    private function makeEmployee(Company $company): Employee
    {
        return Employee::create([
            'company_id' => $company->id, 'employee_number' => $company->nextEmployeeNumber(),
            'full_name' => 'Ahmed Ali', 'is_saudi' => true, 'hire_date' => now()->subYears(2),
            'status' => 'active', 'basic_salary' => 8000, 'housing_allowance' => 2000,
        ]);
    }

    private function makeApprovedRun(Company $company, User $owner): PayrollRun
    {
        $this->makeEmployee($company);

        $this->actingAs($owner)->post(route('app.payroll.store'), [
            'period_month' => now()->month, 'period_year' => now()->year, 'pay_date' => now()->toDateString(),
        ]);
        $run = PayrollRun::first();
        $this->actingAs($owner)->post(route('app.payroll.approve', $run));

        return $run->refresh();
    }

    public function test_marking_a_run_as_paid_settles_salaries_payable_against_the_chosen_bank_account(): void
    {
        $company = $this->makeCompanyWithPayroll();
        $owner = $this->makeOwner($company);
        $bankAccount = BankAccount::create(['company_id' => $company->id, 'name' => 'Main Account', 'bank_name' => 'Al Rajhi Bank', 'type' => 'bank', 'is_active' => true]);
        $run = $this->makeApprovedRun($company, $owner);

        $response = $this->actingAs($owner)->post(route('app.payroll.mark-paid', $run), ['bank_account_id' => $bankAccount->id]);

        $response->assertRedirect();
        $run->refresh();
        $this->assertSame('paid', $run->status);
        $this->assertSame($bankAccount->id, $run->bank_account_id);

        $salariesPayable = AccountMapping::resolve($company->id, 'SALARIES_PAYABLE');
        $bank = AccountMapping::resolve($company->id, 'DEFAULT_BANK');
        $entry = JournalEntry::where('company_id', $company->id)->where('source_type', 'payroll_run_settlement')->where('source_id', $run->id)->first();

        $this->assertNotNull($entry, 'Marking a run paid must post a settlement journal entry.');
        $this->assertSame((float) $run->total_net, (float) $entry->lines()->where('account_id', $salariesPayable->id)->sum('debit'));
        $this->assertSame((float) $run->total_net, (float) $entry->lines()->where('account_id', $bank->id)->sum('credit'));
    }

    public function test_marking_a_run_as_paid_from_a_cash_account_credits_the_cash_account(): void
    {
        $company = $this->makeCompanyWithPayroll();
        $owner = $this->makeOwner($company);
        $cashAccount = BankAccount::create(['company_id' => $company->id, 'name' => 'Petty Cash', 'type' => 'cash', 'is_active' => true]);
        $run = $this->makeApprovedRun($company, $owner);

        $this->actingAs($owner)->post(route('app.payroll.mark-paid', $run), ['bank_account_id' => $cashAccount->id])->assertRedirect();

        $cash = AccountMapping::resolve($company->id, 'DEFAULT_CASH');
        $entry = JournalEntry::where('company_id', $company->id)->where('source_type', 'payroll_run_settlement')->where('source_id', $run->id)->first();

        $this->assertSame((float) $run->total_net, (float) $entry->lines()->where('account_id', $cash->id)->sum('credit'));
    }

    public function test_marking_a_run_as_paid_without_a_bank_account_is_rejected(): void
    {
        $company = $this->makeCompanyWithPayroll();
        $owner = $this->makeOwner($company);
        $run = $this->makeApprovedRun($company, $owner);

        $response = $this->actingAs($owner)->post(route('app.payroll.mark-paid', $run), []);

        $response->assertSessionHasErrors('bank_account_id');
        $this->assertSame('approved', $run->fresh()->status);
        $this->assertSame(0, JournalEntry::where('source_type', 'payroll_run_settlement')->count());
    }

    public function test_a_bank_account_belonging_to_another_company_is_rejected(): void
    {
        $company = $this->makeCompanyWithPayroll();
        $owner = $this->makeOwner($company);
        $run = $this->makeApprovedRun($company, $owner);

        $otherCompany = Company::create(['name' => 'Other Co.', 'slug' => 'other-'.uniqid()]);
        $otherAccount = BankAccount::create(['company_id' => $otherCompany->id, 'name' => 'Other Bank', 'type' => 'bank', 'is_active' => true]);

        $response = $this->actingAs($owner)->post(route('app.payroll.mark-paid', $run), ['bank_account_id' => $otherAccount->id]);

        $response->assertSessionHasErrors('bank_account_id');
        $this->assertSame('approved', $run->fresh()->status);
    }
}
