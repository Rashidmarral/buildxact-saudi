<?php

namespace Tests\Feature;

use App\Mail\ContactFormMail;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Go-live audit finding: the public /contact form validated its input
 * and showed a success message but never actually delivered the
 * message anywhere — every lead from a prospective customer silently
 * vanished. It's now emailed to the configured support_email, and
 * (being a public, unauthenticated form) throttled against spam.
 */
class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_submitting_the_contact_form_emails_the_configured_support_address(): void
    {
        Mail::fake();
        Setting::set('support_email', 'support@example.test');

        $response = $this->post(route('contact.submit'), [
            'name' => 'Prospective Customer',
            'email' => 'prospect@example.test',
            'message' => 'Do you support multi-branch VAT reporting?',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        Mail::assertQueued(ContactFormMail::class, function ($mail) {
            return $mail->hasTo('support@example.test')
                && $mail->fromName === 'Prospective Customer'
                && $mail->fromEmail === 'prospect@example.test';
        });
    }

    public function test_the_contact_form_requires_valid_input(): void
    {
        Mail::fake();

        $response = $this->post(route('contact.submit'), [
            'name' => '',
            'email' => 'not-an-email',
            'message' => '',
        ]);

        $response->assertSessionHasErrors(['name', 'email', 'message']);
        Mail::assertNothingSent();
    }

    public function test_the_contact_form_is_rate_limited_against_spam(): void
    {
        Mail::fake();

        $payload = ['name' => 'Spammer', 'email' => 'spam@example.test', 'message' => 'x'];

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('contact.submit'), $payload);
        }

        $response = $this->post(route('contact.submit'), $payload);

        // ThrottleRequestsException is rendered app-wide (bootstrap/app.php)
        // as a friendly redirect-back-with-errors rather than a raw 429 for
        // non-JSON requests, matching how the login throttle already behaves.
        $response->assertStatus(302);
        $response->assertSessionHasErrors('email');
    }
}
