<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Lets a super admin point outgoing mail at their own SMTP account from
 * Platform Settings → Email instead of editing .env and redeploying —
 * see PlatformSettingsController::updateMail()/testMail() and
 * AppServiceProvider::configureMailDriver().
 */
class PlatformMailSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'company_id' => null]);
    }

    public function test_saving_smtp_settings_requires_a_freshly_confirmed_password(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->post(route('admin.settings.mail.update'), [
            'mail_smtp_enabled' => '1',
            'mail_smtp_host' => 'smtp.example.com',
        ]);

        $response->assertRedirect(route('admin.password.confirm'));
    }

    public function test_saving_smtp_settings_persists_and_encrypts_the_password(): void
    {
        $admin = $this->makeAdmin();
        session(['auth.password_confirmed_at' => time()]);

        $response = $this->actingAs($admin)->post(route('admin.settings.mail.update'), [
            'mail_smtp_enabled' => '1',
            'mail_smtp_host' => 'smtp.example.com',
            'mail_smtp_port' => '587',
            'mail_smtp_encryption' => 'tls',
            'mail_smtp_username' => 'billing@example.com',
            'mail_smtp_password' => 'super-secret',
            'mail_from_address' => 'billing@example.com',
            'mail_from_name' => 'Example Co.',
        ]);

        $response->assertRedirect();
        $this->assertTrue(Setting::getBool('mail_smtp_enabled'));
        $this->assertSame('smtp.example.com', Setting::get('mail_smtp_host'));
        $this->assertSame('super-secret', Setting::get('mail_smtp_password'));

        $rawColumn = \Illuminate\Support\Facades\DB::table('settings')->where('key', 'mail_smtp_password')->value('value');
        $this->assertNotSame('super-secret', $rawColumn);
    }

    public function test_leaving_the_password_field_blank_keeps_the_existing_password(): void
    {
        $admin = $this->makeAdmin();
        session(['auth.password_confirmed_at' => time()]);
        Setting::set('mail_smtp_password', 'original-secret', encrypted: true);

        $this->actingAs($admin)->post(route('admin.settings.mail.update'), [
            'mail_smtp_enabled' => '1',
            'mail_smtp_host' => 'smtp.example.com',
            'mail_smtp_port' => '587',
            'mail_smtp_encryption' => 'tls',
            'mail_from_address' => 'billing@example.com',
            'mail_from_name' => 'Example Co.',
        ]);

        $this->assertSame('original-secret', Setting::get('mail_smtp_password'));
    }

    public function test_configure_mail_driver_overrides_config_when_enabled_and_leaves_it_untouched_when_disabled(): void
    {
        Setting::set('mail_smtp_enabled', '0');
        (new AppServiceProvider($this->app))->boot();
        $this->assertNotSame('smtp.example.com', config('mail.mailers.smtp.host'));

        Setting::set('mail_smtp_enabled', '1');
        Setting::set('mail_smtp_host', 'smtp.example.com');
        Setting::set('mail_smtp_port', '2525');
        Setting::set('mail_smtp_encryption', 'ssl');
        Setting::set('mail_from_address', 'billing@example.com');
        Setting::set('mail_from_name', 'Example Co.');

        (new AppServiceProvider($this->app))->boot();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.example.com', config('mail.mailers.smtp.host'));
        $this->assertSame(2525, config('mail.mailers.smtp.port'));
        $this->assertSame('ssl', config('mail.mailers.smtp.encryption'));
        $this->assertSame('billing@example.com', config('mail.from.address'));
        $this->assertSame('Example Co.', config('mail.from.name'));
    }

    public function test_sending_a_test_email_requires_a_host_before_anything_is_saved(): void
    {
        $admin = $this->makeAdmin();
        session(['auth.password_confirmed_at' => time()]);

        $response = $this->actingAs($admin)->post(route('admin.settings.mail.test'), [
            'test_email' => 'owner@example.com',
        ]);

        $response->assertSessionHasErrors('mail_test');
    }

    public function test_sending_a_test_email_dispatches_mail_using_the_submitted_settings(): void
    {
        Mail::fake();
        $admin = $this->makeAdmin();
        session(['auth.password_confirmed_at' => time()]);

        $response = $this->actingAs($admin)->post(route('admin.settings.mail.test'), [
            'test_email' => 'owner@example.com',
            'mail_smtp_host' => 'smtp.example.com',
            'mail_smtp_port' => '587',
            'mail_smtp_encryption' => 'tls',
            'mail_smtp_username' => 'billing@example.com',
            'mail_smtp_password' => 'super-secret',
            'mail_from_address' => 'billing@example.com',
            'mail_from_name' => 'Example Co.',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status');
        Mail::assertSent(\App\Mail\SmtpTestMail::class, function ($mail) {
            return $mail->hasTo('owner@example.com') && $mail->platformName !== '';
        });
    }
}
