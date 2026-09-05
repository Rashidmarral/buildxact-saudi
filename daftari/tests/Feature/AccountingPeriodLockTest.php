<?php

namespace Tests\Feature;

use App\Exceptions\PeriodLockedException;
use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit finding: nothing stopped a transaction from being posted (or an
 * approved expense from being edited and reposted) with a date inside a
 * period that had already been closed and reported — a bookkeeper could
 * quietly alter a filed VAT return. LedgerPostingService::post() now
 * refuses anything dated on or before the company's lock date.
 */
class AccountingPeriodLockTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $company = Company::create(['name' => 'Locked Co.', 'slug' => 'locked-'.uniqid()]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return $company;
    }

    public function test_posting_inside_a_locked_period_throws(): void
    {
        $company = $this->makeCompany();
        $company->update(['accounting_lock_date' => '2026-06-30']);

        $expense = Expense::create([
            'company_id' => $company->id, 'gross_amount' => 115, 'amount' => 100, 'vat_amount' => 15,
            'tax_category' => 'standard_15', 'expense_date' => '2026-06-15', 'status' => 'approved',
        ]);

        $this->expectException(PeriodLockedException::class);
        app(LedgerPostingService::class)->postExpense($expense);
    }

    public function test_posting_on_the_lock_date_itself_still_throws(): void
    {
        $company = $this->makeCompany();
        $company->update(['accounting_lock_date' => '2026-06-30']);

        $expense = Expense::create([
            'company_id' => $company->id, 'gross_amount' => 115, 'amount' => 100, 'vat_amount' => 15,
            'tax_category' => 'standard_15', 'expense_date' => '2026-06-30', 'status' => 'approved',
        ]);

        $this->expectException(PeriodLockedException::class);
        app(LedgerPostingService::class)->postExpense($expense);
    }

    public function test_posting_after_the_lock_date_still_works(): void
    {
        $company = $this->makeCompany();
        $company->update(['accounting_lock_date' => '2026-06-30']);

        $expense = Expense::create([
            'company_id' => $company->id, 'gross_amount' => 115, 'amount' => 100, 'vat_amount' => 15,
            'tax_category' => 'standard_15', 'expense_date' => '2026-07-01', 'status' => 'approved',
        ]);

        $entry = app(LedgerPostingService::class)->postExpense($expense);

        $this->assertNotNull($entry);
    }

    public function test_no_lock_date_never_blocks_posting(): void
    {
        $company = $this->makeCompany();

        $expense = Expense::create([
            'company_id' => $company->id, 'gross_amount' => 115, 'amount' => 100, 'vat_amount' => 15,
            'tax_category' => 'standard_15', 'expense_date' => '2020-01-01', 'status' => 'approved',
        ]);

        $entry = app(LedgerPostingService::class)->postExpense($expense);

        $this->assertNotNull($entry);
    }

    public function test_the_settings_screen_rejects_locked_period_postings_with_a_friendly_error(): void
    {
        $company = $this->makeCompany();
        $company->update(['accounting_lock_date' => '2026-06-30']);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $response = $this->actingAs($owner)->post(route('app.expenses.store'), [
            'gross_amount' => 115, 'tax_category' => 'standard_15', 'expense_date' => '2026-06-15',
        ]);

        $response->assertSessionHasErrors('period');
        $this->assertSame(0, JournalEntry::where('source_type', 'expense')->count());
    }

    public function test_owner_can_set_and_clear_the_lock_date_from_settings(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $this->actingAs($owner)->post(route('app.settings.approvals.lock-date'), [
            'accounting_lock_date' => '2026-06-30',
        ])->assertSessionDoesntHaveErrors();

        $this->assertSame('2026-06-30', $owner->company->fresh()->accounting_lock_date->toDateString());
        $this->assertNotNull(AuditLog::where('action', 'company.lock_date_update')->first());

        $this->actingAs($owner)->post(route('app.settings.approvals.lock-date'), [
            'accounting_lock_date' => '',
        ])->assertSessionDoesntHaveErrors();

        $this->assertNull($owner->company->fresh()->accounting_lock_date);
    }
}
