<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Third-party audit findings SEC-01 (Critical) / SEC-02 (High): the
 * forgot-password flow passed a callback to PasswordBroker::sendResetLink()
 * that only captured the reset URL. Laravel's PasswordBroker returns
 * immediately after calling a supplied callback, so the real
 * sendPasswordResetNotification() call never ran (SEC-02) — and whenever
 * config('mail.default') === 'log' (Laravel's own out-of-box default, not
 * something unique to local dev), that captured URL was flashed straight
 * to the browser for ANY requested email, with no access to the victim's
 * mailbox required at all (SEC-01). Fixed so the real notification always
 * fires outside local/testing, and the token is never exposed to the
 * response outside those two environments regardless of mail config.
 */
class PasswordResetTokenDisclosureTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        $company = Company::create(['name' => 'Reset Co.', 'slug' => 'reset-co-'.uniqid()]);

        return User::factory()->create(['company_id' => $company->id, 'email' => 'victim@example.com', 'status' => 'active']);
    }

    public function test_a_production_request_sends_the_real_notification_and_never_exposes_the_token(): void
    {
        // Overriding app()->environment() also disables the test-harness
        // CSRF bypass (Laravel's ValidateCsrfToken middleware only skips
        // verification when runningUnitTests() reports env === 'testing'),
        // which is a side effect of this test setup, not the real app.
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->app['env'] = 'production';
        config(['mail.default' => 'log']);
        Notification::fake();

        $user = $this->makeUser();

        $response = $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
        $response->assertSessionMissing('dev_reset_url');
    }

    public function test_a_production_request_with_a_working_smtp_mailer_also_sends_the_real_notification(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->app['env'] = 'production';
        config(['mail.default' => 'smtp']);
        Notification::fake();

        $user = $this->makeUser();

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_an_unknown_email_gives_the_same_generic_response_in_production(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->app['env'] = 'production';
        config(['mail.default' => 'log']);
        Notification::fake();

        $response = $this->post(route('password.email'), ['email' => 'nobody@example.com']);

        Notification::assertNothingSent();
        $response->assertSessionMissing('dev_reset_url');
        $response->assertSessionHas('status');
    }

    public function test_the_testing_environment_still_exposes_the_dev_link_for_local_convenience(): void
    {
        config(['mail.default' => 'log']);
        Notification::fake();

        $user = $this->makeUser();

        $response = $this->post(route('password.email'), ['email' => $user->email]);

        $response->assertSessionHas('dev_reset_url');
        // Even the dev convenience path must still send the real
        // notification — SEC-02 must stay fixed in every environment.
        Notification::assertSentTo($user, ResetPassword::class);
    }
}
