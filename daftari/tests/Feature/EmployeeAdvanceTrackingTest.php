<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Employee;
use App\Models\JournalEntry;
use App\Models\PaymentVoucher;
use App\Models\ReceiptVoucher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit finding CASH-02: an employee cash advance (a Payment Voucher paid
 * to staff, outside the normal customer/supplier flow) had no way to be
 * tagged or tracked back to the employee, so nobody could tell who still
 * owed money against an advance. Adds an "Employee" party type to both
 * vouchers plus a running-balance report, without changing how any
 * existing manual/customer/supplier voucher behaves or posts.
 */
class EmployeeAdvanceTrackingTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $company = Company::create(['name' => 'Advance Co.', 'slug' => 'advance-co-'.uniqid()]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    private function makeBankAccount(Company $company): BankAccount
    {
        return BankAccount::create(['company_id' => $company->id, 'name' => 'Main Account', 'bank_name' => 'Al Rajhi Bank', 'type' => 'bank', 'is_active' => true]);
    }

    private function makeEmployee(Company $company, array $overrides = []): Employee
    {
        return Employee::create(array_merge([
            'company_id' => $company->id,
            'employee_number' => 'EMP-'.uniqid(),
            'full_name' => 'Khalid Al-Harbi',
            'hire_date' => now()->subYear()->toDateString(),
            'status' => 'active',
        ], $overrides));
    }

    public function test_a_payment_voucher_can_be_tagged_to_an_employee_and_posts_normally(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $account = $this->makeBankAccount($company);
        $employee = $this->makeEmployee($company);

        $this->actingAs($owner)->post(route('app.payment-vouchers.store'), [
            'party_type' => 'employee', 'employee_id' => $employee->id,
            'payee_name' => $employee->full_name, 'bank_account_id' => $account->id,
            'date' => now()->toDateString(), 'amount' => 2000, 'method' => 'cash',
        ])->assertRedirect();

        $voucher = PaymentVoucher::first();
        $this->assertSame($employee->id, $voucher->employee_id);
        $this->assertSame('issued', $voucher->status);
        $this->assertSame(1, JournalEntry::where('source_type', 'payment_voucher')->where('source_id', $voucher->id)->count());
    }

    public function test_a_receipt_voucher_can_be_tagged_to_an_employee_settling_an_advance(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $account = $this->makeBankAccount($company);
        $employee = $this->makeEmployee($company);

        $this->actingAs($owner)->post(route('app.receipt-vouchers.store'), [
            'party_type' => 'employee', 'employee_id' => $employee->id,
            'payer_name' => $employee->full_name, 'bank_account_id' => $account->id,
            'date' => now()->toDateString(), 'amount' => 800, 'method' => 'cash',
        ])->assertRedirect();

        $voucher = ReceiptVoucher::first();
        $this->assertSame($employee->id, $voucher->employee_id);
        $this->assertSame('issued', $voucher->status);
        $this->assertSame(1, JournalEntry::where('source_type', 'receipt_voucher')->where('source_id', $voucher->id)->count());
    }

    public function test_employee_model_computes_the_outstanding_advance_as_advanced_minus_settled(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $account = $this->makeBankAccount($company);
        $employee = $this->makeEmployee($company);

        $this->actingAs($owner)->post(route('app.payment-vouchers.store'), [
            'party_type' => 'employee', 'employee_id' => $employee->id,
            'payee_name' => $employee->full_name, 'bank_account_id' => $account->id,
            'date' => now()->toDateString(), 'amount' => 3000, 'method' => 'cash',
        ]);
        $this->actingAs($owner)->post(route('app.receipt-vouchers.store'), [
            'party_type' => 'employee', 'employee_id' => $employee->id,
            'payer_name' => $employee->full_name, 'bank_account_id' => $account->id,
            'date' => now()->toDateString(), 'amount' => 1200, 'method' => 'cash',
        ]);

        $employee->refresh();
        $this->assertSame(3000.0, $employee->totalAdvanced());
        $this->assertSame(1200.0, $employee->totalSettled());
        $this->assertSame(1800.0, $employee->outstandingAdvanceTotal());
    }

    public function test_employee_advances_report_lists_outstanding_balances_and_excludes_untouched_employees(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $account = $this->makeBankAccount($company);
        $employee = $this->makeEmployee($company, ['full_name' => 'Saeed Al-Qahtani']);
        $this->makeEmployee($company, ['full_name' => 'Untouched Employee', 'employee_number' => 'EMP-UNTOUCHED']);

        $this->actingAs($owner)->post(route('app.payment-vouchers.store'), [
            'party_type' => 'employee', 'employee_id' => $employee->id,
            'payee_name' => $employee->full_name, 'bank_account_id' => $account->id,
            'date' => now()->toDateString(), 'amount' => 2500, 'method' => 'cash',
        ]);

        $response = $this->actingAs($owner)->get(route('app.reports.employee-advances'));

        $response->assertOk();
        $response->assertSee('Saeed Al-Qahtani');
        $response->assertDontSee('Untouched Employee');
    }

    public function test_a_voucher_without_an_employee_selected_still_works_as_before(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $account = $this->makeBankAccount($company);

        $this->actingAs($owner)->post(route('app.payment-vouchers.store'), [
            'party_type' => 'manual', 'payee_name' => 'Walk-in Vendor', 'bank_account_id' => $account->id,
            'date' => now()->toDateString(), 'amount' => 400, 'method' => 'cash',
        ])->assertRedirect();

        $voucher = PaymentVoucher::first();
        $this->assertNull($voucher->employee_id);
        $this->assertSame('issued', $voucher->status);
    }
}
