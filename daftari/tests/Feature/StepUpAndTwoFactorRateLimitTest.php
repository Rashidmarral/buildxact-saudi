<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Services\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit finding MEDIUM-17: two security-sensitive, already-authenticated
 * endpoints had no rate limiting at all — an attacker holding a hijacked
 * session could brute-force either one with unlimited attempts:
 *   - settings/two-factor/confirm (the TOTP code that finishes turning
 *     2FA on) — the login-time /two-factor-challenge was already
 *     throttled via the existing "two-factor" limiter, this one wasn't.
 *   - admin/confirm-password (the step-up password re-check every
 *     "password.confirm.admin"-guarded dangerous admin action funnels
 *     through) — completely unthrottled.
 * Both now use a dedicated 5/minute RateLimiter (AppServiceProvider),
 * keyed by the already-authenticated user (+ IP for the admin one),
 * matching the shape of the existing "login"/"two-factor" limiters.
 */
class StepUpAndTwoFactorRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithPendingTwoFactor(): User
    {
        $company = Company::create(['name' => 'Rate Limit Co.', 'slug' => 'ratelimit-'.uniqid()]);
        $user = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $user->update(['two_factor_secret' => Totp::generateSecret()]);

        return $user;
    }

    public function test_the_two_factor_confirm_endpoint_is_rate_limited_after_five_attempts(): void
    {
        $user = $this->makeUserWithPendingTwoFactor();

        for ($i = 0; $i < 5; $i++) {
            $response = $this->actingAs($user)->post(route('app.settings.two-factor.confirm'), ['code' => '000000']);
            $response->assertStatus(302);
        }

        $response = $this->actingAs($user)->post(route('app.settings.two-factor.confirm'), ['code' => '000000']);

        // ThrottleRequestsException is rendered app-wide (bootstrap/app.php)
        // as a friendly redirect-back-with-errors rather than a raw 429 for
        // non-JSON requests, matching how the login throttle already behaves.
        $response->assertStatus(302);
        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('Too many attempts', session('errors')->first('email'));
    }

    public function test_the_two_factor_confirm_rate_limit_is_scoped_per_user(): void
    {
        $userA = $this->makeUserWithPendingTwoFactor();
        $userB = $this->makeUserWithPendingTwoFactor();

        for ($i = 0; $i < 6; $i++) {
            $this->actingAs($userA)->post(route('app.settings.two-factor.confirm'), ['code' => '000000']);
        }

        $response = $this->actingAs($userB)->post(route('app.settings.two-factor.confirm'), ['code' => '000000']);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('code');
    }

    private function makeSuperAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'company_id' => null, 'status' => 'active']);
    }

    public function test_the_admin_password_confirm_endpoint_is_rate_limited_after_five_attempts(): void
    {
        $admin = $this->makeSuperAdmin();

        for ($i = 0; $i < 5; $i++) {
            $response = $this->actingAs($admin)->post(route('admin.password.confirm.store'), ['password' => 'wrong-password']);
            $response->assertStatus(302);
        }

        $response = $this->actingAs($admin)->post(route('admin.password.confirm.store'), ['password' => 'wrong-password']);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('Too many attempts', session('errors')->first('email'));
    }

    public function test_the_admin_password_confirm_rate_limit_is_scoped_per_admin(): void
    {
        $adminA = $this->makeSuperAdmin();
        $adminB = $this->makeSuperAdmin();

        for ($i = 0; $i < 6; $i++) {
            $this->actingAs($adminA)->post(route('admin.password.confirm.store'), ['password' => 'wrong-password']);
        }

        $response = $this->actingAs($adminB)->post(route('admin.password.confirm.store'), ['password' => 'wrong-password']);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('password');
    }
}
