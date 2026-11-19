<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Support\Installer\InstallerLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Tests\TestCase;

/**
 * Security audit finding D-7: neither changing your password from Settings
 * nor resetting it via the forgot-password email flow used to touch any
 * other session for the account — a stolen session cookie would happily
 * keep working forever, even after the legitimate owner "secured" the
 * account by rotating the password. Both flows now purge stale sessions
 * from the `sessions` table (the only session driver that can be queried
 * and targeted this way).
 */
class PasswordChangeInvalidatesOtherSessionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // PrepareInstallerEnvironment forces the session driver back to
        // "file" whenever the app looks uninstalled, which it does by
        // default in tests — mark it installed so the database driver
        // this fix relies on actually stays in effect for these requests.
        InstallerLock::lock('admin@example.test');
        config(['session.driver' => 'database']);
    }

    protected function tearDown(): void
    {
        InstallerLock::unlock();
        parent::tearDown();
    }

    private function makeUser(): User
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);

        return User::factory()->create([
            'company_id' => $company->id,
            'role' => 'owner',
            'status' => 'active',
            'password' => Hash::make('old-password-123'),
        ]);
    }

    private function seedOtherSession(User $user): string
    {
        $sessionId = 'other-session-'.uniqid();

        DB::table('sessions')->insert([
            'id' => $sessionId,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Other Device',
            'payload' => base64_encode('x'),
            'last_activity' => now()->timestamp,
        ]);

        return $sessionId;
    }

    public function test_updating_password_from_settings_logs_out_other_sessions_but_keeps_the_current_one(): void
    {
        $user = $this->makeUser();
        $otherSessionId = $this->seedOtherSession($user);

        $response = $this->actingAs($user)->put(route('app.settings.password'), [
            'current_password' => 'old-password-123',
            'password' => 'new-password-456',
            'password_confirmation' => 'new-password-456',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseMissing('sessions', ['id' => $otherSessionId]);
        $this->assertTrue(Hash::check('new-password-456', $user->refresh()->password));
    }

    public function test_updating_password_does_not_delete_the_requesting_sessions_own_row(): void
    {
        $user = $this->makeUser();
        $otherSessionId = $this->seedOtherSession($user);

        $response = $this->actingAs($user)->put(route('app.settings.password'), [
            'current_password' => 'old-password-123',
            'password' => 'new-password-456',
            'password_confirmation' => 'new-password-456',
        ]);

        // The session manager is a singleton bound for the lifetime of the
        // test, so it still holds the id StartSession assigned to *this*
        // request — captured only now, since a fresh session is started
        // per test request and won't match whatever id existed beforehand.
        $currentSessionId = $this->app['session']->getId();

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseMissing('sessions', ['id' => $otherSessionId]);
        $this->assertDatabaseHas('sessions', ['id' => $currentSessionId, 'user_id' => $user->id]);
    }

    public function test_wrong_current_password_does_not_touch_any_session(): void
    {
        $user = $this->makeUser();
        $otherSessionId = $this->seedOtherSession($user);

        $response = $this->actingAs($user)->put(route('app.settings.password'), [
            'current_password' => 'totally-wrong',
            'password' => 'new-password-456',
            'password_confirmation' => 'new-password-456',
        ]);

        $response->assertSessionHasErrors('current_password');
        $this->assertDatabaseHas('sessions', ['id' => $otherSessionId]);
        $this->assertTrue(Hash::check('old-password-123', $user->refresh()->password));
    }

    public function test_manual_logout_other_sessions_action_still_works_unchanged(): void
    {
        $user = $this->makeUser();
        $otherSessionId = $this->seedOtherSession($user);

        $response = $this->actingAs($user)->post(route('app.settings.sessions.logout-others'), [
            'current_password' => 'old-password-123',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseMissing('sessions', ['id' => $otherSessionId]);
    }

    public function test_password_reset_via_email_link_deletes_every_session_including_ones_that_look_current(): void
    {
        $user = $this->makeUser();
        $otherSessionId = $this->seedOtherSession($user);

        $token = PasswordBroker::createToken($user);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'brand-new-password-789',
            'password_confirmation' => 'brand-new-password-789',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseMissing('sessions', ['id' => $otherSessionId]);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertTrue(Hash::check('brand-new-password-789', $user->refresh()->password));
    }

    public function test_a_failed_password_reset_attempt_does_not_touch_sessions(): void
    {
        $user = $this->makeUser();
        $otherSessionId = $this->seedOtherSession($user);

        $response = $this->post('/reset-password', [
            'token' => 'not-a-real-token',
            'email' => $user->email,
            'password' => 'brand-new-password-789',
            'password_confirmation' => 'brand-new-password-789',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseHas('sessions', ['id' => $otherSessionId]);
    }
}
