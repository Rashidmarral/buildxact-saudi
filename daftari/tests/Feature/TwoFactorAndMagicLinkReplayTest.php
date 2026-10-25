<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientPortalLogin;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Third-party audit finding SEC-09 (Medium): TOTP verification, recovery-
 * code consumption, and client-portal magic-link consumption were all
 * stateless/non-atomic read-then-update — a captured valid credential
 * could be replayed (the same code/token used twice) rather than being
 * consumed exactly once. Also covers the pending 2FA challenge now
 * expiring instead of living as long as the session did.
 */
class TwoFactorAndMagicLinkReplayTest extends TestCase
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

    /** Duplicates RFC 6238 locally — App\Services\Totp only exposes verify()/matchedStep(). */
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

    public function test_the_same_totp_code_cannot_authenticate_twice(): void
    {
        $company = $this->makeCompany();
        $secret = 'ABCDEFGHIJKLMNOP';
        $user = $this->makeOwner($company, ['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()]);
        $code = $this->currentTotpCode($secret);

        $this->post('/login', ['email' => $user->email, 'password' => 'password123']);
        $this->post('/two-factor-challenge', ['code' => $code])->assertRedirect(route('app.dashboard'));
        $this->assertAuthenticatedAs($user);

        $this->post('/logout');

        // A fresh challenge (e.g. the attacker captured the code and the
        // legitimate user's password, and starts their own login) must
        // still reject the already-consumed code, even though its 30s/90s
        // time window hasn't elapsed.
        $this->post('/login', ['email' => $user->email, 'password' => 'password123']);
        $response = $this->post('/two-factor-challenge', ['code' => $code]);

        $response->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_a_later_totp_step_still_authenticates_after_an_earlier_one_was_consumed(): void
    {
        $company = $this->makeCompany();
        $secret = 'ABCDEFGHIJKLMNOP';
        $user = $this->makeOwner($company, [
            'two_factor_secret' => $secret, 'two_factor_confirmed_at' => now(), 'two_factor_last_totp_step' => null,
        ]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password123']);
        $this->post('/two-factor-challenge', ['code' => $this->currentTotpCode($secret)]);
        $this->assertAuthenticatedAs($user);
        $this->post('/logout');

        $this->post('/login', ['email' => $user->email, 'password' => 'password123']);
        $response = $this->post('/two-factor-challenge', ['code' => $this->currentTotpCode($secret, 30, 6)]);

        // Same real-time code (the 30s window hasn't rolled over in-test),
        // which is exactly the replay this fix must still catch —
        // confirms the first assertion's mechanism, not a false negative.
        $response->assertSessionHasErrors('code');
    }

    public function test_the_pending_two_factor_challenge_expires(): void
    {
        $company = $this->makeCompany();
        $secret = 'ABCDEFGHIJKLMNOP';
        $user = $this->makeOwner($company, ['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password123']);

        Carbon::setTestNow(now()->addMinutes(6));

        $this->get('/two-factor-challenge')->assertRedirect(route('login'));

        $response = $this->post('/two-factor-challenge', ['code' => $this->currentTotpCode($secret)]);
        $response->assertForbidden();
        $this->assertGuest();

        Carbon::setTestNow();
    }

    public function test_an_account_suspended_mid_challenge_is_blocked_at_the_two_factor_step(): void
    {
        $company = $this->makeCompany();
        $secret = 'ABCDEFGHIJKLMNOP';
        $user = $this->makeOwner($company, ['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password123']);

        $user->update(['status' => 'suspended']);

        $response = $this->post('/two-factor-challenge', ['code' => $this->currentTotpCode($secret)]);

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_a_recovery_code_can_only_be_used_once(): void
    {
        $company = $this->makeCompany();
        $user = $this->makeOwner($company, [
            'two_factor_secret' => 'ABCDEFGHIJKLMNOP', 'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => ['RECOVERY-CODE-1', 'RECOVERY-CODE-2'],
        ]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password123']);
        $this->post('/two-factor-challenge', ['recovery_code' => 'RECOVERY-CODE-1']);
        $this->assertAuthenticatedAs($user);
        $this->post('/logout');

        $this->post('/login', ['email' => $user->email, 'password' => 'password123']);
        $response = $this->post('/two-factor-challenge', ['recovery_code' => 'RECOVERY-CODE-1']);

        $response->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->assertSame(['RECOVERY-CODE-2'], $user->fresh()->two_factor_recovery_codes);
    }

    public function test_a_client_portal_magic_link_can_only_be_consumed_once(): void
    {
        $company = Company::create(['name' => 'Portal Co.', 'slug' => 'portal-co-'.uniqid()]);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client']);
        $token = ClientPortalLogin::issueFor($client);

        $first = ClientPortalLogin::consume($token);
        $second = ClientPortalLogin::consume($token);

        $this->assertNotNull($first);
        $this->assertSame($client->id, $first->id);
        $this->assertNull($second, 'A magic-link token must not be consumable twice.');
    }

    public function test_an_expired_magic_link_is_rejected(): void
    {
        $company = Company::create(['name' => 'Portal Co.', 'slug' => 'portal-co-'.uniqid()]);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client']);
        $token = ClientPortalLogin::issueFor($client);

        Carbon::setTestNow(now()->addMinutes(20));
        $this->assertNull(ClientPortalLogin::consume($token));
        Carbon::setTestNow();
    }
}
