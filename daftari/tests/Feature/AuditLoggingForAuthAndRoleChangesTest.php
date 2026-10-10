<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Security audit findings D-3 (auth events) and D-4 (role/permission
 * changes): neither was logged anywhere before this — a compromised
 * account could log in, grant itself broad permissions via a custom
 * role, and there would be no trace on the company's own Activity page.
 */
class AuditLoggingForAuthAndRoleChangesTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        return Company::create(['name' => 'Acme', 'slug' => 'acme-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
    }

    private function makeOwner(Company $company, array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'company_id' => $company->id, 'role' => 'owner', 'status' => 'active', 'password' => bcrypt('password123'),
        ], $overrides));
    }

    // -----------------------------------------------------------------
    // D-3: auth events
    // -----------------------------------------------------------------

    public function test_a_successful_login_is_logged(): void
    {
        $company = $this->makeCompany();
        $user = $this->makeOwner($company);

        $this->post('/login', ['email' => $user->email, 'password' => 'password123']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.login', 'subject_type' => User::class, 'subject_id' => $user->id, 'admin_user_id' => $user->id,
        ]);
    }

    public function test_a_failed_login_is_logged_against_the_targeted_account(): void
    {
        $company = $this->makeCompany();
        $user = $this->makeOwner($company);

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.login_failed', 'subject_type' => User::class, 'subject_id' => $user->id, 'company_id' => $company->id,
        ]);
    }

    public function test_a_failed_login_for_a_nonexistent_email_is_still_logged_without_a_subject(): void
    {
        $this->post('/login', ['email' => 'nobody@example.test', 'password' => 'whatever']);

        $log = AuditLog::where('action', 'auth.login_failed')->first();
        $this->assertNotNull($log);
        $this->assertNull($log->subject_id);
        $this->assertStringContainsString('nobody@example.test', $log->description);
    }

    public function test_login_for_an_inactive_account_is_logged_as_blocked(): void
    {
        $company = $this->makeCompany();
        $user = $this->makeOwner($company, ['status' => 'invited']);

        $this->post('/login', ['email' => $user->email, 'password' => 'password123']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.login_blocked', 'subject_type' => User::class, 'subject_id' => $user->id,
        ]);
    }

    public function test_logout_is_logged(): void
    {
        $company = $this->makeCompany();
        $user = $this->makeOwner($company);

        $this->actingAs($user)->post('/logout');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.logout', 'subject_type' => User::class, 'subject_id' => $user->id,
        ]);
    }

    public function test_changing_password_from_settings_is_logged(): void
    {
        $company = $this->makeCompany();
        $user = $this->makeOwner($company);

        $this->actingAs($user)->put(route('app.settings.password'), [
            'current_password' => 'password123',
            'password' => 'new-password-456',
            'password_confirmation' => 'new-password-456',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.password_changed', 'subject_type' => User::class, 'subject_id' => $user->id,
        ]);
    }

    public function test_resetting_password_via_emailed_link_is_logged(): void
    {
        $company = $this->makeCompany();
        $user = $this->makeOwner($company);
        $token = \Illuminate\Support\Facades\Password::createToken($user);

        $this->post('/reset-password', [
            'token' => $token, 'email' => $user->email,
            'password' => 'brand-new-789', 'password_confirmation' => 'brand-new-789',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.password_reset', 'subject_type' => User::class, 'subject_id' => $user->id, 'admin_user_id' => $user->id,
        ]);
    }

    public function test_a_failed_two_factor_code_at_login_is_logged(): void
    {
        $company = $this->makeCompany();
        $user = $this->makeOwner($company, [
            'two_factor_secret' => 'ABCDEFGHIJKLMNOP',
            'two_factor_confirmed_at' => now(),
        ]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password123']);
        $this->post('/two-factor-challenge', ['code' => '000000']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.two_factor_failed', 'subject_type' => User::class, 'subject_id' => $user->id, 'admin_user_id' => $user->id,
        ]);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'auth.login', 'subject_id' => $user->id]);
    }

    public function test_a_successful_two_factor_login_is_logged(): void
    {
        $company = $this->makeCompany();
        $secret = 'ABCDEFGHIJKLMNOP';
        $user = $this->makeOwner($company, [
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password123']);
        $this->post('/two-factor-challenge', ['code' => $this->currentTotpCode($secret)]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.login', 'subject_type' => User::class, 'subject_id' => $user->id,
        ]);
    }

    /**
     * Duplicates the standard RFC 6238 computation locally, the same way
     * any TOTP-consuming test would — App\Services\Totp exposes verify()
     * only, not "give me a currently valid code".
     */
    private function currentTotpCode(string $secret, int $window = 30, int $digits = 6): string
    {
        $key = $this->base32Decode($secret);
        $counter = intdiv(time(), $window);
        $binaryCounter = pack('N*', 0, $counter);
        $hash = hash_hmac('sha1', $binaryCounter, $key, true);
        $offset = ord($hash[19]) & 0xf;
        $truncated = (
            ((ord($hash[$offset]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        );

        return str_pad((string) ($truncated % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split(strtoupper($secret)) as $char) {
            $bits .= str_pad(base_convert((string) strpos($alphabet, $char), 10, 2), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $bytes .= chr((int) base_convert($byte, 2, 10));
            }
        }

        return $bytes;
    }

    // -----------------------------------------------------------------
    // D-4: role/permission changes
    // -----------------------------------------------------------------

    public function test_creating_a_custom_role_is_logged(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);

        $this->actingAs($owner)->post(route('app.roles.store'), [
            'name' => 'Accountant', 'permissions' => ['invoices', 'quotations'],
        ]);

        $role = Role::where('company_id', $company->id)->where('name', 'Accountant')->first();
        $this->assertNotNull($role);

        $log = AuditLog::where('action', 'role.create')->where('subject_id', $role->id)->first();
        $this->assertNotNull($log);
        $this->assertSame(['name' => 'Accountant', 'permissions' => ['invoices', 'quotations']], $log->new_value);
    }

    public function test_updating_a_custom_roles_permissions_is_logged_with_before_and_after(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $role = Role::create([
            'company_id' => $company->id, 'name' => 'Accountant', 'slug' => 'accountant-'.uniqid(),
            'permissions' => ['invoices'], 'is_system' => false,
        ]);

        $this->actingAs($owner)->put(route('app.roles.update', $role), [
            'name' => 'Senior Accountant', 'permissions' => ['invoices', 'quotations', 'purchases'],
        ]);

        $log = AuditLog::where('action', 'role.update')->where('subject_id', $role->id)->first();
        $this->assertNotNull($log);
        $this->assertSame(['name' => 'Accountant', 'permissions' => ['invoices']], $log->old_value);
        $this->assertSame(['name' => 'Senior Accountant', 'permissions' => ['invoices', 'quotations', 'purchases']], $log->new_value);
    }

    public function test_deleting_a_custom_role_is_logged(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $role = Role::create([
            'company_id' => $company->id, 'name' => 'Temp Role', 'slug' => 'temp-role-'.uniqid(),
            'permissions' => ['invoices'], 'is_system' => false,
        ]);

        $this->actingAs($owner)->delete(route('app.roles.destroy', $role));

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
        $log = AuditLog::where('action', 'role.delete')->where('company_id', $company->id)->first();
        $this->assertNotNull($log);
        $this->assertSame('Temp Role', $log->old_value['name']);
    }

    public function test_a_system_role_cannot_be_deleted_and_nothing_is_logged(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        Role::seedSystemRoles($company->id);
        $systemRole = Role::where('company_id', $company->id)->where('is_system', true)->first();

        $response = $this->actingAs($owner)->delete(route('app.roles.destroy', $systemRole));

        $response->assertStatus(403);
        $this->assertDatabaseHas('roles', ['id' => $systemRole->id]);
        $this->assertSame(0, AuditLog::where('action', 'role.delete')->count());
    }
}
