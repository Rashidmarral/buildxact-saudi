<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Security audit finding D-6: the /admin route group only ever checked
 * ['auth', 'role:super_admin,admin_staff'] — nothing verified 2FA was
 * enabled, even though these accounts can reach every tenant's financial
 * data (and, for super_admin, impersonate any customer). Fixed by
 * EnsureAdminTwoFactorEnabled, applied to the whole /admin route group.
 *
 * UserFactory now auto-enables 2FA for any factory-created super_admin/
 * admin_staff user (so the hundreds of other admin-panel tests don't all
 * need their own 2FA boilerplate) — these tests explicitly undo that via
 * forceFill() to exercise the gate itself.
 */
class RequireTwoFactorForAdminsTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdminWithoutTwoFactor(string $role = 'super_admin'): User
    {
        $user = User::factory()->create(['role' => $role, 'company_id' => null]);
        $user->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();

        return $user;
    }

    /**
     * App\Services\Totp has no public "give me a currently-valid code"
     * method (by design — nothing in the app itself needs one outside a
     * real authenticator app), so this duplicates the standard RFC 6238
     * computation locally, the same way any TOTP-consuming test would.
     */
    private function currentTotpCode(string $base32Secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = strtoupper((string) preg_replace('/[^A-Z2-7]/i', '', $base32Secret));
        $bits = '';
        foreach (str_split($secret) as $char) {
            $position = strpos($alphabet, $char);
            if ($position === false) {
                continue;
            }
            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }
        $key = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $key .= chr(bindec($byte));
            }
        }

        $counter = (int) floor(time() / 30);
        $binaryCounter = pack('N*', 0, $counter);
        $hash = hash_hmac('sha1', $binaryCounter, $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $truncated = (
            ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF)
        );

        return str_pad((string) ($truncated % 1000000), 6, '0', STR_PAD_LEFT);
    }

    public function test_a_super_admin_without_two_factor_is_redirected_to_set_it_up(): void
    {
        $admin = $this->makeAdminWithoutTwoFactor('super_admin');

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertRedirect(route('admin.settings.two-factor'));
    }

    public function test_an_admin_staff_without_two_factor_is_redirected_to_set_it_up(): void
    {
        $admin = $this->makeAdminWithoutTwoFactor('admin_staff');

        $response = $this->actingAs($admin)->get(route('admin.companies.index'));

        $response->assertRedirect(route('admin.settings.two-factor'));
    }

    public function test_the_two_factor_setup_screen_itself_is_reachable_without_two_factor_enabled(): void
    {
        $admin = $this->makeAdminWithoutTwoFactor();

        $response = $this->actingAs($admin)->get(route('admin.settings.two-factor'));

        $response->assertOk();
    }

    public function test_a_super_admin_can_complete_two_factor_setup_and_then_reach_the_dashboard(): void
    {
        $admin = $this->makeAdminWithoutTwoFactor();

        // Generates and stores a pending secret on the user.
        $this->actingAs($admin)->get(route('admin.settings.two-factor'));
        $admin->refresh();
        $this->assertNotNull($admin->two_factor_secret);

        $code = $this->currentTotpCode($admin->two_factor_secret);
        $this->actingAs($admin)->post(route('admin.settings.two-factor.confirm'), ['code' => $code])
            ->assertOk();

        $admin->refresh();
        $this->assertTrue($admin->hasTwoFactorEnabled());

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    }

    public function test_an_admin_with_two_factor_enabled_reaches_the_admin_panel_normally(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'company_id' => null]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
    }

    public function test_a_company_owner_is_unaffected_by_the_admin_two_factor_requirement(): void
    {
        $company = \App\Models\Company::create(['name' => 'Acme', 'slug' => 'acme-'.uniqid(), 'status' => 'active']);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $owner->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();

        $response = $this->actingAs($owner)->get(route('app.dashboard'));

        $response->assertOk();
    }
}
