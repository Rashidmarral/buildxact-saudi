<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Income;
use App\Models\IncomeCategory;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "I need to add income as we add expense for the whole not in the
 * project flow only" — a general-purpose way to record money received
 * that isn't tied to an invoice, reachable from its own nav item (not
 * only from inside a project's Cash Flow page), mirroring Expense's own
 * category/financial-account/GL-account/project shape. See the Income
 * model's and migration's docblocks for the full reasoning.
 */
class IncomeTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): User
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    public function test_an_income_received_into_an_account_posts_debit_bank_credit_other_income(): void
    {
        $owner = $this->makeOwner();
        $account = BankAccount::create(['company_id' => $owner->company_id, 'name' => 'SNB Current Account', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true]);

        $response = $this->actingAs($owner)->post(route('app.incomes.store'), [
            'income_date' => now()->toDateString(), 'gross_amount' => 5000, 'tax_category' => 'zero_rated',
            'bank_account_id' => $account->id, 'payer_name' => 'Scrap buyer', 'description' => 'Sold scrap steel',
        ]);

        $response->assertRedirect(route('app.incomes.index'));
        $income = Income::first();
        $this->assertNotNull($income);
        $this->assertEquals(5000, $income->gross_amount);
        $this->assertEquals(0, $income->vat_amount);
        $this->assertEqualsWithDelta(5000, $account->fresh()->currentBalance(), 0.01);

        $entry = JournalEntry::where('source_type', 'income')->where('source_id', $income->id)->first();
        $this->assertNotNull($entry);
        $this->assertEquals(5000, (float) $entry->lines()->sum('debit'));
        $this->assertEquals(5000, (float) $entry->lines()->sum('credit'));

        $this->assertDatabaseHas('audit_logs', ['action' => 'income.create', 'subject_type' => Income::class, 'subject_id' => $income->id]);
    }

    public function test_vat_liable_income_splits_net_and_output_vat_correctly(): void
    {
        $owner = $this->makeOwner();
        $account = BankAccount::create(['company_id' => $owner->company_id, 'name' => 'SNB Current Account', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true]);

        $this->actingAs($owner)->post(route('app.incomes.store'), [
            'income_date' => now()->toDateString(), 'gross_amount' => 1150, 'tax_category' => 'standard_15',
            'bank_account_id' => $account->id, 'payer_name' => 'Equipment rental',
        ])->assertRedirect();

        $income = Income::first();
        $this->assertEqualsWithDelta(1000, (float) $income->amount, 0.01);
        $this->assertEqualsWithDelta(150, (float) $income->vat_amount, 0.01);

        $entry = JournalEntry::where('source_type', 'income')->where('source_id', $income->id)->first();
        $this->assertEqualsWithDelta(1150, (float) $entry->lines()->sum('debit'), 0.01);
        $this->assertEqualsWithDelta(1150, (float) $entry->lines()->sum('credit'), 0.01);
    }

    public function test_income_with_no_account_chosen_posts_to_accounts_receivable_instead_of_a_payable(): void
    {
        $owner = $this->makeOwner();

        $this->actingAs($owner)->post(route('app.incomes.store'), [
            'income_date' => now()->toDateString(), 'gross_amount' => 2000, 'tax_category' => 'zero_rated',
            'payer_name' => 'Pending reimbursement',
        ])->assertRedirect();

        $income = Income::first();
        $this->assertNull($income->bank_account_id);

        $ar = Account::where('company_id', $owner->company_id)->where('code', '1200')->first();
        $entry = JournalEntry::where('source_type', 'income')->where('source_id', $income->id)->first();
        $this->assertTrue($entry->lines->contains(fn ($line) => $line->account_id === $ar->id && (float) $line->debit === 2000.0));
    }

    public function test_editing_an_income_reposts_the_ledger_and_writes_an_audit_entry(): void
    {
        $owner = $this->makeOwner();
        $account = BankAccount::create(['company_id' => $owner->company_id, 'name' => 'SNB Current Account', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true]);
        $this->actingAs($owner)->post(route('app.incomes.store'), [
            'income_date' => now()->toDateString(), 'gross_amount' => 1000, 'tax_category' => 'zero_rated',
            'bank_account_id' => $account->id, 'payer_name' => 'Scrap buyer',
        ]);
        $income = Income::first();

        $this->actingAs($owner)->put(route('app.incomes.update', $income), [
            'income_date' => now()->toDateString(), 'gross_amount' => 3000, 'tax_category' => 'zero_rated',
            'bank_account_id' => $account->id, 'payer_name' => 'Scrap buyer',
        ])->assertRedirect();

        $this->assertSame(1, JournalEntry::where('source_type', 'income')->where('source_id', $income->id)->count());
        $this->assertEqualsWithDelta(3000, $account->fresh()->currentBalance(), 0.01);

        $log = AuditLog::where('action', 'income.update')->where('subject_id', $income->id)->first();
        $this->assertNotNull($log);
        $this->assertSame(1000.0, (float) $log->old_value['gross_amount']);
        $this->assertSame(3000.0, (float) $log->new_value['gross_amount']);
    }

    public function test_deleting_an_income_reverses_the_ledger_and_writes_an_audit_entry(): void
    {
        $owner = $this->makeOwner();
        $account = BankAccount::create(['company_id' => $owner->company_id, 'name' => 'SNB Current Account', 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true]);
        $this->actingAs($owner)->post(route('app.incomes.store'), [
            'income_date' => now()->toDateString(), 'gross_amount' => 1000, 'tax_category' => 'zero_rated',
            'bank_account_id' => $account->id, 'payer_name' => 'Scrap buyer',
        ]);
        $income = Income::first();
        $incomeId = $income->id;

        $this->actingAs($owner)->delete(route('app.incomes.destroy', $income))->assertRedirect();

        $this->assertNotNull(JournalEntry::where('source_type', 'income_reversal')->where('source_id', $incomeId)->first());
        $this->assertEqualsWithDelta(0, $account->fresh()->currentBalance(), 0.01);
        $this->assertSoftDeleted($income);

        $log = AuditLog::where('action', 'income.delete')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString((string) $incomeId, $log->description);
    }

    public function test_income_categories_can_be_added_and_deleted(): void
    {
        $owner = $this->makeOwner();

        $this->actingAs($owner)->post(route('app.income-categories.store'), ['name' => 'Scrap sales'])->assertRedirect();
        $category = IncomeCategory::first();
        $this->assertSame('Scrap sales', $category->name);

        $this->actingAs($owner)->delete(route('app.income-categories.destroy', $category))->assertRedirect();
        $this->assertSame(0, IncomeCategory::count());
    }

    public function test_the_income_nav_item_and_list_work_standalone_without_any_project(): void
    {
        $owner = $this->makeOwner();
        BankAccount::create(['company_id' => $owner->company_id, 'name' => 'Petty Cash', 'type' => 'cash', 'currency' => 'SAR', 'is_active' => true]);

        $this->actingAs($owner)->get(route('app.incomes.create'))
            ->assertOk()
            ->assertSee(__('New Income'))
            ->assertDontSee(__('Edit Income'));

        $this->actingAs($owner)->post(route('app.incomes.store'), [
            'income_date' => now()->toDateString(), 'gross_amount' => 500, 'tax_category' => 'zero_rated',
            'payer_name' => 'Walk-in customer',
        ])->assertRedirect();

        $this->actingAs($owner)->get(route('app.incomes.index'))
            ->assertOk()
            ->assertSee('Walk-in customer');
    }

    public function test_a_company_users_income_is_never_visible_to_another_company(): void
    {
        $ownerA = $this->makeOwner();
        $ownerB = $this->makeOwner();

        $this->actingAs($ownerA)->post(route('app.incomes.store'), [
            'income_date' => now()->toDateString(), 'gross_amount' => 777, 'tax_category' => 'zero_rated',
            'payer_name' => 'Company A income',
        ]);
        $income = Income::first();

        $this->actingAs($ownerB)->get(route('app.incomes.edit', $income))->assertNotFound();
        $this->actingAs($ownerB)->get(route('app.incomes.index'))->assertDontSee('Company A income');
    }
}
