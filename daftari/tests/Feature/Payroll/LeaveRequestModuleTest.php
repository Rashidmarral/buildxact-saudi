<?php

namespace Tests\Feature\Payroll;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Core accounting audit finding: payroll had GOSI, End-of-Service, and
 * WPS but no leave/annual-leave accrual or balance tracking at all. This
 * exercises the persisted half (LeaveRequestService, on top of
 * LeaveAccrualCalculatorTest's pure-calculator coverage): a request can't
 * be submitted or approved for more annual leave than the balance
 * actually covers, sick/unpaid leave don't touch the balance, and the
 * whole thing is company-scoped like everything else in this app.
 */
class LeaveRequestModuleTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(bool $withPayroll = true): Company
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000,
            'is_active' => true, 'has_payroll' => $withPayroll,
        ]);

        $company = Company::create(['name' => 'Leave Co.', 'slug' => 'leave-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
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

    private function makeEmployee(Company $company, string $hireDate): Employee
    {
        return Employee::create([
            'company_id' => $company->id, 'employee_number' => 'EMP-'.uniqid(), 'full_name' => 'Waleed Al-Otaibi',
            'hire_date' => $hireDate, 'status' => 'active', 'basic_salary' => 5000,
        ]);
    }

    public function test_a_company_without_payroll_is_blocked_from_leave_requests(): void
    {
        $company = $this->makeCompany(withPayroll: false);
        $owner = $this->makeOwner($company);

        $this->actingAs($owner)->get(route('app.leave-requests.index'))
            ->assertRedirect(route('app.dashboard'));
    }

    public function test_the_index_page_shows_the_accrued_balance_for_an_employee_hired_a_year_ago(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $this->makeEmployee($company, now()->subYear()->toDateString());

        $this->actingAs($owner)->get(route('app.leave-requests.index'))
            ->assertOk()->assertSee('21.00');
    }

    public function test_a_request_within_the_balance_can_be_submitted_and_approved(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $employee = $this->makeEmployee($company, now()->subYear()->toDateString());

        $this->actingAs($owner)->post(route('app.leave-requests.store'), [
            'employee_id' => $employee->id, 'type' => 'annual',
            'start_date' => now()->addWeek()->toDateString(), 'end_date' => now()->addWeek()->addDays(4)->toDateString(),
        ])->assertRedirect();

        $leaveRequest = LeaveRequest::first();
        $this->assertSame('pending', $leaveRequest->status);
        $this->assertSame(5.0, (float) $leaveRequest->days);

        $this->actingAs($owner)->post(route('app.leave-requests.approve', $leaveRequest))->assertRedirect();
        $this->assertSame('approved', $leaveRequest->fresh()->status);
    }

    public function test_a_request_exceeding_the_balance_is_rejected_with_a_clear_error(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        // Hired one month ago: ~1.75 days accrued — nowhere near 21.
        $employee = $this->makeEmployee($company, now()->subMonth()->toDateString());

        $response = $this->actingAs($owner)->post(route('app.leave-requests.store'), [
            'employee_id' => $employee->id, 'type' => 'annual',
            'start_date' => now()->toDateString(), 'end_date' => now()->addDays(20)->toDateString(),
        ]);

        $response->assertSessionHasErrors('leave');
        $this->assertSame(0, LeaveRequest::count());
    }

    public function test_sick_and_unpaid_leave_do_not_draw_down_the_annual_balance(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        // Hired one month ago: too little annual balance for 10 days, but
        // sick/unpaid leave aren't checked against it at all.
        $employee = $this->makeEmployee($company, now()->subMonth()->toDateString());

        $this->actingAs($owner)->post(route('app.leave-requests.store'), [
            'employee_id' => $employee->id, 'type' => 'sick',
            'start_date' => now()->toDateString(), 'end_date' => now()->addDays(9)->toDateString(),
        ])->assertRedirect();

        $this->assertSame(1, LeaveRequest::count());
        $this->assertSame('pending', LeaveRequest::first()->status);
    }

    public function test_two_approvals_together_cannot_overdraw_the_balance(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $employee = $this->makeEmployee($company, now()->subYear()->toDateString()); // 21 days accrued

        $this->actingAs($owner)->post(route('app.leave-requests.store'), [
            'employee_id' => $employee->id, 'type' => 'annual',
            'start_date' => now()->addWeek()->toDateString(), 'end_date' => now()->addWeek()->addDays(13)->toDateString(), // 14 days
        ]);
        $this->actingAs($owner)->post(route('app.leave-requests.store'), [
            'employee_id' => $employee->id, 'type' => 'annual',
            'start_date' => now()->addMonth()->toDateString(), 'end_date' => now()->addMonth()->addDays(9)->toDateString(), // 10 days
        ]);

        [$first, $second] = LeaveRequest::orderBy('id')->get();

        $this->actingAs($owner)->post(route('app.leave-requests.approve', $first))->assertRedirect();
        $this->assertSame('approved', $first->fresh()->status);

        // 14 already taken, only 7 left — approving the second (10 days) must fail.
        $response = $this->actingAs($owner)->post(route('app.leave-requests.approve', $second));
        $response->assertSessionHasErrors('leave');
        $this->assertSame('pending', $second->fresh()->status);
    }

    public function test_a_request_can_be_rejected(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $employee = $this->makeEmployee($company, now()->subYear()->toDateString());

        $this->actingAs($owner)->post(route('app.leave-requests.store'), [
            'employee_id' => $employee->id, 'type' => 'annual',
            'start_date' => now()->addWeek()->toDateString(), 'end_date' => now()->addWeek()->addDays(2)->toDateString(),
        ]);
        $leaveRequest = LeaveRequest::first();

        $this->actingAs($owner)->post(route('app.leave-requests.reject', $leaveRequest))->assertRedirect();
        $this->assertSame('rejected', $leaveRequest->fresh()->status);

        // A rejected request's days don't count against the balance.
        $this->actingAs($owner)->get(route('app.leave-requests.index'))->assertOk()->assertSee('21.00');
    }

    public function test_a_company_cannot_approve_another_companys_leave_request(): void
    {
        $companyA = $this->makeCompany();
        $companyB = $this->makeCompany();
        $ownerA = $this->makeOwner($companyA);
        $employeeB = $this->makeEmployee($companyB, now()->subYear()->toDateString());
        $this->actingAs($this->makeOwner($companyB))->post(route('app.leave-requests.store'), [
            'employee_id' => $employeeB->id, 'type' => 'annual',
            'start_date' => now()->addWeek()->toDateString(), 'end_date' => now()->addWeek()->addDays(2)->toDateString(),
        ]);
        $requestB = LeaveRequest::withoutGlobalScopes()->where('company_id', $companyB->id)->first();

        $this->actingAs($ownerA)->post(route('app.leave-requests.approve', $requestB))->assertNotFound();
    }

    public function test_the_leave_page_renders_in_arabic(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $this->makeEmployee($company, now()->subYear()->toDateString());
        $this->actingAs($owner)->get(route('locale.switch', 'ar'));

        $this->actingAs($owner)->get(route('app.leave-requests.index'))
            ->assertOk()->assertSee(__('Annual leave balances'))->assertSee(__('Leave requests'));
    }

    public function test_a_company_cannot_submit_a_request_for_another_companys_employee(): void
    {
        $companyA = $this->makeCompany();
        $companyB = $this->makeCompany();
        $ownerA = $this->makeOwner($companyA);
        $employeeB = $this->makeEmployee($companyB, now()->subYear()->toDateString());

        $this->actingAs($ownerA)->post(route('app.leave-requests.store'), [
            'employee_id' => $employeeB->id, 'type' => 'annual',
            'start_date' => now()->addWeek()->toDateString(), 'end_date' => now()->addWeek()->addDays(2)->toDateString(),
        ])->assertSessionHasErrors('employee_id');
        $this->assertSame(0, LeaveRequest::withoutGlobalScopes()->count());
    }
}
