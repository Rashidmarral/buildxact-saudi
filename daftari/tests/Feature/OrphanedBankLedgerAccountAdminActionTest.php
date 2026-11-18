<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "i can't run artisan command on my server please make a mechanism
 * that i can do it from admin panel interface" — the same orphaned
 * bank-ledger-line cleanup the accounts:deactivate-orphaned-bank-lines
 * console command does, reachable instead as a button on the super-admin
 * company page, for a host (shared hosting, SSH disabled) where artisan
 * isn't reachable at all.
 */
class OrphanedBankLedgerAccountAdminActionTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'company_id' => null, 'status' => 'active']);
    }

    private function makeCompanyWithOwner(): array
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        return [$company, $owner];
    }

    private function asConfirmedAdmin(User $admin)
    {
        return $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => now()->timestamp]);
    }

    public function test_the_company_page_lists_orphaned_bank_ledger_lines(): void
    {
        [$company, $owner] = $this->makeCompanyWithOwner();
        $this->actingAs($owner)->post(route('app.bank-accounts.store'), ['name' => 'Nasir', 'type' => 'bank', 'currency' => 'SAR']);
        $bankAccount = BankAccount::where('company_id', $company->id)->where('name', 'Nasir')->firstOrFail();
        $accountId = $bankAccount->account_id;
        $bankAccount->delete(); // bypasses the controller's own deactivation, simulating one deleted before this fix

        $response = $this->asConfirmedAdmin($this->makeAdmin())->get(route('admin.companies.show', $company));
        $response->assertOk();
        $orphans = $response->viewData('orphanedBankAccounts');
        $this->assertTrue($orphans->contains('id', $accountId));
    }

    public function test_the_admin_can_deactivate_orphaned_bank_ledger_lines_from_the_company_page(): void
    {
        [$company, $owner] = $this->makeCompanyWithOwner();
        $this->actingAs($owner)->post(route('app.bank-accounts.store'), ['name' => 'Khalid', 'type' => 'bank', 'currency' => 'SAR']);
        $bankAccount = BankAccount::where('company_id', $company->id)->where('name', 'Khalid')->firstOrFail();
        $accountId = $bankAccount->account_id;
        $bankAccount->delete();

        $admin = $this->makeAdmin();
        $this->asConfirmedAdmin($admin)
            ->post(route('admin.companies.deactivate-orphaned-bank-accounts', $company))
            ->assertRedirect();

        $this->assertFalse(Account::withoutGlobalScope('company')->find($accountId)->is_active);
        $this->assertSame(1, AuditLog::where('action', 'company.deactivate_orphaned_bank_accounts')->count());
    }

    public function test_a_plain_owner_cannot_reach_the_admin_action(): void
    {
        [$company, $owner] = $this->makeCompanyWithOwner();

        $this->actingAs($owner)
            ->post(route('admin.companies.deactivate-orphaned-bank-accounts', $company))
            ->assertStatus(403);
    }

    public function test_an_unconfirmed_admin_session_is_redirected_to_confirm_password_instead_of_acting(): void
    {
        [$company] = $this->makeCompanyWithOwner();
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->post(route('admin.companies.deactivate-orphaned-bank-accounts', $company))
            ->assertRedirect(route('admin.password.confirm'));
    }
}
