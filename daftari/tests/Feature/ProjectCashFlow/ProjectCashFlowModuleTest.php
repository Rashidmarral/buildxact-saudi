<?php

namespace Tests\Feature\ProjectCashFlow;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\BankAccount;
use App\Models\BankTransfer;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyOverride;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\PaymentVoucher;
use App\Models\Plan;
use App\Models\Project;
use App\Models\ReceiptVoucher;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Project Cash Flow: a paid module layered on top of the existing,
 * ungated Cash & Banks records — closes the gap between "a project has
 * invoiced/billed amounts" (Project::revenue()/costs(), already existed)
 * and "how much cash has this project actually received/paid/moved"
 * (Project::cashReceived()/cashPaid()/cashTransferredOut(), new). The
 * underlying ReceiptVoucher/PaymentVoucher/BankTransfer CRUD must keep
 * working exactly as before for a company that hasn't bought this module.
 */
class ProjectCashFlowModuleTest extends \Tests\TestCase
{
    use RefreshDatabase;

    private function makeCompany(bool $withModule): Company
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000,
            'is_active' => true, 'has_project_cash_flow' => $withModule,
        ]);

        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
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

    private function makeBankAccount(Company $company, string $name = 'SNB Current Account'): BankAccount
    {
        return BankAccount::create(['company_id' => $company->id, 'name' => $name, 'type' => 'bank', 'currency' => 'SAR', 'is_active' => true]);
    }

    // ------------------------------------------------------------------
    // Module gating
    // ------------------------------------------------------------------

    public function test_a_company_without_the_module_is_blocked_from_every_project_cash_flow_route(): void
    {
        $company = $this->makeCompany(withModule: false);
        $owner = $this->makeOwner($company);
        $project = Project::create(['company_id' => $company->id, 'code' => 'PRJ-JAMUM', 'name' => 'Jamum', 'status' => 'active']);
        $account = $this->makeBankAccount($company);

        $this->actingAs($owner)->get(route('app.project-cash-flow.index'))->assertRedirect(route('app.dashboard'));
        $this->actingAs($owner)->get(route('app.project-cash-flow.show', $project))->assertRedirect(route('app.dashboard'));
        $this->actingAs($owner)->get(route('app.project-cash-flow.pdf', $project))->assertRedirect(route('app.dashboard'));
        $this->actingAs($owner)->get(route('app.project-cash-flow.bank-account.show', $account))->assertRedirect(route('app.dashboard'));
        $this->actingAs($owner)->get(route('app.project-cash-flow.bank-account.pdf', $account))->assertRedirect(route('app.dashboard'));
    }

    public function test_a_company_whose_plan_includes_the_module_can_access_the_statement(): void
    {
        $company = $this->makeCompany(withModule: true);
        $owner = $this->makeOwner($company);
        $project = Project::create(['company_id' => $company->id, 'code' => 'PRJ-JAMUM', 'name' => 'Jamum', 'status' => 'active']);

        $this->actingAs($owner)->get(route('app.project-cash-flow.index'))->assertOk();
        $this->actingAs($owner)->get(route('app.project-cash-flow.show', $project))->assertOk();
    }

    public function test_an_admin_override_installs_the_module_for_one_company_without_a_plan_change(): void
    {
        $company = $this->makeCompany(withModule: false);
        $owner = $this->makeOwner($company);
        CompanyOverride::create(['company_id' => $company->id, 'type' => 'feature', 'key' => 'project_cash_flow', 'value' => '1', 'reason' => 'test']);

        $this->actingAs($owner)->get(route('app.project-cash-flow.index'))->assertOk();
    }

    /**
     * The critical regression check: the existing, ungated Cash & Banks
     * CRUD must be completely unaffected for a company that hasn't bought
     * this module — the new nullable project_id columns and the new
     * gated module must coexist with it invisibly.
     */
    public function test_existing_cash_and_banks_crud_is_completely_unaffected_without_the_module(): void
    {
        $company = $this->makeCompany(withModule: false);
        $owner = $this->makeOwner($company);
        $accountA = $this->makeBankAccount($company, 'Main Account');
        $accountB = $this->makeBankAccount($company, 'Petty Cash');
        $accountB->update(['type' => 'cash']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Sada Al Jeul Construction']);

        $this->actingAs($owner)->post(route('app.receipt-vouchers.store'), [
            'bank_account_id' => $accountA->id, 'party_type' => 'customer', 'client_id' => $client->id,
            'date' => now()->toDateString(), 'payer_name' => $client->name, 'amount' => 1000, 'method' => 'bank_transfer',
        ])->assertRedirect();
        $this->assertSame(1, ReceiptVoucher::count());

        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Steel Supplier']);
        $this->actingAs($owner)->post(route('app.payment-vouchers.store'), [
            'bank_account_id' => $accountA->id, 'party_type' => 'supplier', 'supplier_id' => $supplier->id,
            'date' => now()->toDateString(), 'payee_name' => $supplier->name, 'amount' => 300, 'method' => 'bank_transfer',
        ])->assertRedirect();
        $this->assertSame(1, PaymentVoucher::count());

        $this->actingAs($owner)->post(route('app.bank-transfers.store'), [
            'from_bank_account_id' => $accountA->id, 'to_bank_account_id' => $accountB->id,
            'amount' => 200, 'date' => now()->toDateString(),
        ])->assertRedirect();
        $this->assertSame(1, BankTransfer::count());

        $this->actingAs($owner)->get(route('app.bank-transactions.index'))->assertOk();
    }

    public function test_the_project_dropdown_is_hidden_from_voucher_and_transfer_forms_without_the_module(): void
    {
        $company = $this->makeCompany(withModule: false);
        $owner = $this->makeOwner($company);

        $this->actingAs($owner)->get(route('app.receipt-vouchers.create'))->assertDontSee('name="project_id"', false);
        $this->actingAs($owner)->get(route('app.payment-vouchers.create'))->assertDontSee('name="project_id"', false);
        $this->actingAs($owner)->get(route('app.bank-transfers.create'))->assertDontSee('name="project_id"', false);
    }

    public function test_the_project_dropdown_is_shown_on_voucher_and_transfer_forms_with_the_module(): void
    {
        $company = $this->makeCompany(withModule: true);
        $owner = $this->makeOwner($company);

        $this->actingAs($owner)->get(route('app.receipt-vouchers.create'))->assertSee('name="project_id"', false);
        $this->actingAs($owner)->get(route('app.payment-vouchers.create'))->assertSee('name="project_id"', false);
        $this->actingAs($owner)->get(route('app.bank-transfers.create'))->assertSee('name="project_id"', false);
    }

    // ------------------------------------------------------------------
    // Statement correctness — the real-world "Jamum" scenario
    // ------------------------------------------------------------------

    public function test_the_project_cash_flow_statement_matches_a_seeded_construction_project_scenario(): void
    {
        $company = $this->makeCompany(withModule: true);
        $owner = $this->makeOwner($company);
        $project = Project::create(['company_id' => $company->id, 'code' => 'PRJ-JAMUM', 'name' => 'Jamum', 'status' => 'active']);
        $account = $this->makeBankAccount($company, 'SNB Current Account');
        $otherAccount = $this->makeBankAccount($company, 'Al Rajhi Account');

        // The 20% advance the real quotation/PO describes.
        ReceiptVoucher::create([
            'company_id' => $company->id, 'bank_account_id' => $account->id, 'project_id' => $project->id,
            'party_type' => 'manual', 'voucher_number' => 'RV-1', 'date' => now()->toDateString(),
            'payer_name' => 'Sada Al Jeul Construction', 'amount' => 105007.07, 'method' => 'bank_transfer', 'status' => 'issued',
        ]);

        // A site expense paid from the same account.
        PaymentVoucher::create([
            'company_id' => $company->id, 'bank_account_id' => $account->id, 'project_id' => $project->id,
            'party_type' => 'manual', 'voucher_number' => 'PV-1', 'date' => now()->toDateString(),
            'payee_name' => 'Asphalt Supplier', 'amount' => 20000, 'method' => 'bank_transfer', 'status' => 'issued',
        ]);

        // A transfer of part of the balance to another of the company's
        // own accounts, tagged to the same project.
        BankTransfer::create([
            'company_id' => $company->id, 'from_bank_account_id' => $account->id, 'to_bank_account_id' => $otherAccount->id,
            'project_id' => $project->id, 'amount' => 15000, 'date' => now()->toDateString(),
        ]);

        // A voided receipt tagged to the same project — must be excluded
        // from every total, mirroring BankAccount::currentBalance()'s own
        // status='issued' filter.
        ReceiptVoucher::create([
            'company_id' => $company->id, 'bank_account_id' => $account->id, 'project_id' => $project->id,
            'party_type' => 'manual', 'voucher_number' => 'RV-2', 'date' => now()->toDateString(),
            'payer_name' => 'Should not count', 'amount' => 999999, 'method' => 'cash', 'status' => 'void',
        ]);

        $this->assertEqualsWithDelta(105007.07, $project->cashReceived(), 0.01);
        $this->assertEqualsWithDelta(20000, $project->cashPaid(), 0.01);
        $this->assertEqualsWithDelta(15000, $project->cashTransferredOut(), 0.01);
        // netCashPosition() deliberately excludes cashTransferredOut(): a
        // transfer just relocates money between the company's own
        // accounts (e.g. bank → petty cash) — it isn't spent, so it must
        // not also reduce the project's total alongside whatever is later
        // actually paid out of the destination account.
        $this->assertEqualsWithDelta(105007.07 - 20000, $project->netCashPosition(), 0.01);

        $response = $this->actingAs($owner)->get(route('app.project-cash-flow.show', $project));
        $response->assertOk();
        $response->assertSee(\App\Support\Money::format(105007.07));
        $response->assertSee(\App\Support\Money::format(20000));
        $response->assertSee(\App\Support\Money::format(15000));
        $response->assertDontSee('999,999');

        // Running balance after all three counted rows, in date order:
        // the transfer's own -15,000 still shows on its row (it really did
        // leave this account) but does not reduce the total, since nothing
        // has been paid out of the destination account in this scenario —
        // 105007.07 - 20000 = 85007.07, not 70007.07.
        $response->assertSee(\App\Support\Money::format(85007.07));
    }

    public function test_a_bank_account_statement_shows_the_opening_and_running_balance(): void
    {
        $company = $this->makeCompany(withModule: true);
        $owner = $this->makeOwner($company);
        $account = BankAccount::create([
            'company_id' => $company->id, 'name' => 'SNB Current Account', 'type' => 'bank', 'currency' => 'SAR',
            'is_active' => true, 'opening_balance' => 5000,
        ]);

        ReceiptVoucher::create([
            'company_id' => $company->id, 'bank_account_id' => $account->id,
            'party_type' => 'manual', 'voucher_number' => 'RV-1', 'date' => now()->toDateString(),
            'payer_name' => 'Client', 'amount' => 10000, 'method' => 'bank_transfer', 'status' => 'issued',
        ]);

        $response = $this->actingAs($owner)->get(route('app.project-cash-flow.bank-account.show', $account));
        $response->assertOk();
        $response->assertSee(\App\Support\Money::format(5000));
        $response->assertSee(\App\Support\Money::format(15000));
    }

    // ------------------------------------------------------------------
    // PDF export
    // ------------------------------------------------------------------

    public function test_the_project_pdf_export_returns_a_pdf_for_a_company_that_owns_the_module(): void
    {
        $company = $this->makeCompany(withModule: true);
        $owner = $this->makeOwner($company);
        $project = Project::create(['company_id' => $company->id, 'code' => 'PRJ-JAMUM', 'name' => 'Jamum', 'status' => 'active']);
        $account = $this->makeBankAccount($company);

        ReceiptVoucher::create([
            'company_id' => $company->id, 'bank_account_id' => $account->id, 'project_id' => $project->id,
            'party_type' => 'manual', 'voucher_number' => 'RV-1', 'date' => now()->toDateString(),
            'payer_name' => 'Client', 'amount' => 1000, 'method' => 'cash', 'status' => 'issued',
        ]);

        $response = $this->actingAs($owner)->get(route('app.project-cash-flow.pdf', $project));
        $response->assertOk();
        $this->assertStringStartsWith('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_the_bank_account_pdf_export_returns_a_pdf_for_a_company_that_owns_the_module(): void
    {
        $company = $this->makeCompany(withModule: true);
        $owner = $this->makeOwner($company);
        $account = $this->makeBankAccount($company);

        $response = $this->actingAs($owner)->get(route('app.project-cash-flow.bank-account.pdf', $account));
        $response->assertOk();
        $this->assertStringStartsWith('application/pdf', $response->headers->get('Content-Type'));
    }

    // ------------------------------------------------------------------
    // Withdrawal / deposit tracking — how much cash was taken out, how
    // much remains, and (via the destination cash account's own
    // statement) exactly what it was later spent on.
    // ------------------------------------------------------------------

    public function test_a_bank_to_cash_transfer_is_labeled_a_withdrawal_and_a_cash_to_bank_transfer_a_deposit(): void
    {
        $company = $this->makeCompany(withModule: true);
        $owner = $this->makeOwner($company);
        $bank = $this->makeBankAccount($company, 'SNB Current Account');
        $cash = BankAccount::create(['company_id' => $company->id, 'name' => 'Petty Cash', 'type' => 'cash', 'currency' => 'SAR', 'is_active' => true]);

        BankTransfer::create([
            'company_id' => $company->id, 'from_bank_account_id' => $bank->id, 'to_bank_account_id' => $cash->id,
            'amount' => 5000, 'date' => now()->toDateString(), 'created_by' => $owner->id,
        ]);

        $response = $this->actingAs($owner)->get(route('app.project-cash-flow.bank-account.show', $bank));
        $response->assertOk()->assertSee(__('Withdrawal'));

        $response = $this->actingAs($owner)->get(route('app.project-cash-flow.bank-account.show', $cash));
        $response->assertOk()->assertSee(__('Withdrawal'));

        BankTransfer::create([
            'company_id' => $company->id, 'from_bank_account_id' => $cash->id, 'to_bank_account_id' => $bank->id,
            'amount' => 1000, 'date' => now()->toDateString(), 'created_by' => $owner->id,
        ]);

        $this->actingAs($owner)->get(route('app.project-cash-flow.bank-account.show', $cash))
            ->assertOk()->assertSee(__('Deposit'));
    }

    /**
     * The real end-to-end scenario: withdraw cash from the bank, see how
     * much of it remains in the cash account after spending part of it,
     * and see exactly where it went — all from the cash account's own
     * statement, discoverable from the picker's inline balance.
     */
    public function test_withdrawn_cash_remaining_balance_and_its_spend_are_all_visible_on_the_cash_accounts_statement(): void
    {
        $company = $this->makeCompany(withModule: true);
        $owner = $this->makeOwner($company);
        $bank = $this->makeBankAccount($company, 'SNB Current Account');
        $cash = BankAccount::create(['company_id' => $company->id, 'name' => 'Petty Cash', 'type' => 'cash', 'currency' => 'SAR', 'is_active' => true]);

        // Withdraw 5,000 SAR from the bank into petty cash.
        BankTransfer::create([
            'company_id' => $company->id, 'from_bank_account_id' => $bank->id, 'to_bank_account_id' => $cash->id,
            'amount' => 5000, 'date' => now()->toDateString(), 'created_by' => $owner->id,
        ]);

        // Spend part of that cash on a site expense.
        PaymentVoucher::create([
            'company_id' => $company->id, 'bank_account_id' => $cash->id, 'party_type' => 'manual',
            'voucher_number' => 'PV-CASH-1', 'date' => now()->toDateString(), 'payee_name' => 'Site labourers',
            'amount' => 1800, 'method' => 'cash', 'status' => 'issued',
        ]);

        $this->assertEqualsWithDelta(3200.0, $cash->currentBalance(), 0.01);

        // The picker shows the cash account's remaining balance inline.
        $this->actingAs($owner)->get(route('app.project-cash-flow.index'))
            ->assertOk()
            ->assertSee('Petty Cash')
            ->assertSee(\App\Support\Money::format(3200));

        // Its own statement shows the withdrawal in, the spend out, and
        // exactly who it was paid to.
        $response = $this->actingAs($owner)->get(route('app.project-cash-flow.bank-account.show', $cash));
        $response->assertOk()
            ->assertSee(__('Withdrawal'))
            ->assertSee(__('Payment'))
            ->assertSee('Site labourers')
            ->assertSee(\App\Support\Money::format(3200));
    }

    /**
     * Regression: a same-day withdrawal followed by a payment out of that
     * same cash must sort in the order they actually happened (withdrawal
     * first), not by this ledger's internal receipts/payments/transfers
     * concatenation order — the latter would show the payment first and
     * dip the running balance negative before the withdrawal "arrives".
     */
    public function test_a_same_day_withdrawal_and_payment_sort_in_creation_order_not_balance_negative(): void
    {
        $company = $this->makeCompany(withModule: true);
        $owner = $this->makeOwner($company);
        $bank = $this->makeBankAccount($company, 'SNB Current Account');
        $cash = BankAccount::create(['company_id' => $company->id, 'name' => 'Petty Cash', 'type' => 'cash', 'currency' => 'SAR', 'is_active' => true]);
        $today = now()->toDateString();

        $transfer = BankTransfer::create([
            'company_id' => $company->id, 'from_bank_account_id' => $bank->id, 'to_bank_account_id' => $cash->id,
            'amount' => 20000, 'date' => $today, 'created_by' => $owner->id,
        ]);
        $payment = PaymentVoucher::create([
            'company_id' => $company->id, 'bank_account_id' => $cash->id, 'party_type' => 'manual',
            'voucher_number' => 'PV-CASH-2', 'date' => $today, 'payee_name' => 'Daily labourers',
            'amount' => 7500, 'method' => 'cash', 'status' => 'issued',
        ]);
        // created_at only stores whole-second precision, so two rows
        // created moments apart in real use (or, as forced here, on
        // purpose) routinely land on the exact same stored timestamp —
        // this must still resolve to the withdrawal first, since money
        // can't fund a payment before it arrives.
        $payment->created_at = $transfer->created_at;
        $payment->save();

        $response = $this->actingAs($owner)->get(route('app.project-cash-flow.bank-account.show', $cash));
        $response->assertOk();

        $content = $response->getContent();
        $withdrawalPosition = strpos($content, __('Withdrawal'));
        $paymentPosition = strpos($content, 'Daily labourers');
        $this->assertNotFalse($withdrawalPosition);
        $this->assertNotFalse($paymentPosition);
        $this->assertLessThan($paymentPosition, $withdrawalPosition, 'The withdrawal row must render before the payment it funded.');

        // No intermediate negative balance: the lowest running balance on
        // this two-row statement is the final 12,500, never -7,500.
        $response->assertDontSee('-7,500.00');
        $response->assertSee(\App\Support\Money::format(12500));
    }

    /**
     * Regression: an Expense paid directly out of an account (its
     * "Financial account" set to something other than "Unpaid") posts to
     * the GL via LedgerPostingService::postExpense() with no Payment
     * Voucher ever created — before this fix, BankAccount::currentBalance()
     * and the Cash Flow ledger only read ReceiptVoucher/PaymentVoucher/
     * BankTransfer, so this real cash-out was silently invisible on both.
     */
    public function test_a_directly_paid_expense_reduces_the_cash_account_balance_and_appears_on_the_ledger(): void
    {
        $company = $this->makeCompany(withModule: true);
        $owner = $this->makeOwner($company);
        $bank = $this->makeBankAccount($company, 'SNB Current Account');
        $cash = BankAccount::create(['company_id' => $company->id, 'name' => 'Petty Cash', 'type' => 'cash', 'currency' => 'SAR', 'is_active' => true]);
        $project = Project::create(['company_id' => $company->id, 'code' => 'PRJ-JAMUM', 'name' => 'Jamum', 'status' => 'active']);

        BankTransfer::create([
            'company_id' => $company->id, 'from_bank_account_id' => $bank->id, 'to_bank_account_id' => $cash->id,
            'amount' => 70000, 'date' => now()->toDateString(), 'created_by' => $owner->id, 'project_id' => $project->id,
        ]);

        $partsExpense = Expense::create([
            'company_id' => $company->id, 'bank_account_id' => $cash->id, 'project_id' => $project->id,
            'vendor_name' => 'Al Jeul Building Materials', 'description' => 'Steel fixing parts',
            'amount' => 20000, 'gross_amount' => 20000, 'vat_amount' => 0, 'tax_category' => 'zero_rated',
            'expense_date' => now()->toDateString(), 'status' => 'approved',
        ]);

        // An unpaid expense (no bank_account_id) is a payable, not yet a
        // cash movement — it must not touch the balance until it's later
        // settled by a Payment Voucher.
        Expense::create([
            'company_id' => $company->id, 'project_id' => $project->id,
            'vendor_name' => 'Unrelated unpaid bill', 'description' => 'Not yet paid',
            'amount' => 99999, 'gross_amount' => 99999, 'vat_amount' => 0, 'tax_category' => 'zero_rated',
            'expense_date' => now()->toDateString(), 'status' => 'approved',
        ]);

        // A pending-approval expense hasn't posted to the ledger yet
        // either, even though it already names Petty Cash as its account.
        Expense::create([
            'company_id' => $company->id, 'bank_account_id' => $cash->id, 'project_id' => $project->id,
            'vendor_name' => 'Awaiting approval', 'description' => 'Not yet approved',
            'amount' => 55555, 'gross_amount' => 55555, 'vat_amount' => 0, 'tax_category' => 'zero_rated',
            'expense_date' => now()->toDateString(), 'status' => 'pending_approval',
        ]);

        $this->assertEquals(50000, $cash->currentBalance());
        $this->assertEquals(20000, $project->fresh()->cashPaid());

        $response = $this->actingAs($owner)->get(route('app.project-cash-flow.bank-account.show', $cash));
        $response->assertOk()
            ->assertSee(__('Expense'))
            ->assertSee('Al Jeul Building Materials')
            ->assertDontSee('Unrelated unpaid bill')
            ->assertDontSee('Awaiting approval')
            ->assertSee(\App\Support\Money::format(50000));

        // Labour salary paid from the same withdrawn cash, tagged the same
        // way — both categories of spend end up on the one statement.
        Expense::create([
            'company_id' => $company->id, 'bank_account_id' => $cash->id, 'project_id' => $project->id,
            'vendor_name' => 'Site labour salaries', 'amount' => 15000, 'gross_amount' => 15000,
            'vat_amount' => 0, 'tax_category' => 'zero_rated', 'expense_date' => now()->toDateString(), 'status' => 'approved',
        ]);

        $this->assertEquals(35000, $cash->fresh()->currentBalance());

        // Regression: the 70,000 withdrawal (BankTransfer, tagged to this
        // project) must not ALSO count as project spend on top of the two
        // Expenses paid out of the cash it funded — that would double the
        // real 35,000 spent into a nonsensical -105,000 net position.
        $project->refresh();
        $this->assertEquals(70000, $project->cashTransferredOut());
        $this->assertEquals(35000, $project->cashPaid());
        $this->assertEquals(-35000, $project->netCashPosition());

        $projectResponse = $this->actingAs($owner)->get(route('app.project-cash-flow.show', $project));
        $projectResponse->assertOk()
            ->assertSee('Al Jeul Building Materials')
            ->assertSee('Site labour salaries')
            ->assertSee(\App\Support\Money::format(-35000))
            ->assertDontSee(\App\Support\Money::format(-105000));
    }

    /**
     * Reported bug: a real client payment, recorded on the invoice via
     * "Record payment", never showed up on that project's Cash Flow page
     * at all and didn't move the bank account's own balance — because
     * buildLedger()/currentBalance()/cashReceived() only ever read
     * ReceiptVoucher, never InvoicePayment. Fixed by letting a payment
     * optionally carry the real bank_account_id it was received into.
     */
    public function test_an_invoice_payment_tagged_to_a_bank_account_appears_on_both_statements_and_moves_the_balance(): void
    {
        $company = $this->makeCompany(withModule: true);
        $owner = $this->makeOwner($company);
        $project = Project::create(['company_id' => $company->id, 'code' => 'PRJ-JAMUM', 'name' => 'Jamum', 'status' => 'active']);
        $account = $this->makeBankAccount($company, 'SNB Current Account');
        $client = Client::create(['company_id' => $company->id, 'name' => 'Sada Al Jeul Construction']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'project_id' => $project->id,
            'invoice_number' => 'INV-1', 'status' => 'sent', 'issue_date' => now(), 'due_date' => now()->addDays(30),
            'subtotal' => 91310.50, 'vat_total' => 13696.58, 'total' => 105007.08, 'currency' => 'SAR',
        ]);

        $this->actingAs($owner)->post(route('app.invoices.payments.store', $invoice), [
            'amount' => 105007.08, 'paid_at' => now()->toDateString(), 'method' => 'bank_transfer',
            'bank_account_id' => $account->id,
        ])->assertSessionDoesntHaveErrors();

        $this->assertEqualsWithDelta(105007.08, $account->fresh()->currentBalance(), 0.01);
        $this->assertEqualsWithDelta(105007.08, $project->fresh()->cashReceived(), 0.01);

        $this->actingAs($owner)->get(route('app.project-cash-flow.show', $project))
            ->assertOk()
            ->assertSee('INV-1')
            ->assertSee(\App\Support\Money::format(105007.08));

        $this->actingAs($owner)->get(route('app.project-cash-flow.bank-account.show', $account))
            ->assertOk()
            ->assertSee('INV-1')
            ->assertSee(\App\Support\Money::format(105007.08));
    }

    /**
     * Backward compatibility: a payment recorded without picking an
     * account (the field is optional, and every payment recorded before
     * this feature existed has none) must keep working exactly as before
     * — it just stays invisible on the per-account/per-project statements,
     * same as it always was.
     */
    public function test_an_invoice_payment_without_a_bank_account_still_records_but_stays_off_both_statements(): void
    {
        $company = $this->makeCompany(withModule: true);
        $owner = $this->makeOwner($company);
        $project = Project::create(['company_id' => $company->id, 'code' => 'PRJ-JAMUM', 'name' => 'Jamum', 'status' => 'active']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co.']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'project_id' => $project->id,
            'invoice_number' => 'INV-2', 'status' => 'sent', 'issue_date' => now(), 'due_date' => now()->addDays(30),
            'subtotal' => 1000, 'vat_total' => 150, 'total' => 1150, 'currency' => 'SAR',
        ]);

        $this->actingAs($owner)->post(route('app.invoices.payments.store', $invoice), [
            'amount' => 1150, 'paid_at' => now()->toDateString(), 'method' => 'bank_transfer',
        ])->assertSessionDoesntHaveErrors();

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertEqualsWithDelta(0.0, $project->fresh()->cashReceived(), 0.01);
    }
}
