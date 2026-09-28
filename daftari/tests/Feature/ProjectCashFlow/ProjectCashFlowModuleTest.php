<?php

namespace Tests\Feature\ProjectCashFlow;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\BankAccount;
use App\Models\BankTransfer;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyOverride;
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
        $this->assertEqualsWithDelta(105007.07 - 20000 - 15000, $project->netCashPosition(), 0.01);

        $response = $this->actingAs($owner)->get(route('app.project-cash-flow.show', $project));
        $response->assertOk();
        $response->assertSee(\App\Support\Money::format(105007.07));
        $response->assertSee(\App\Support\Money::format(20000));
        $response->assertSee(\App\Support\Money::format(15000));
        $response->assertDontSee('999,999');

        // Running balance after all three counted rows, in date order:
        // 105007.07 - 20000 - 15000 = 70007.07.
        $response->assertSee(\App\Support\Money::format(70007.07));
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
}
