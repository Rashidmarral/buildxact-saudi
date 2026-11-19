<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Go-live audit finding: X-Frame-Options/X-Content-Type-Options only
 * existed as a copy-pasteable nginx snippet, so a buyer on a different
 * web server or a CDN that strips upstream headers would ship without
 * them. App\Http\Middleware\SetSecurityHeaders sets them on every
 * response regardless of web server; HSTS is added only over an actual
 * https request in production.
 */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_response_carries_the_baseline_security_headers(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_hsts_is_not_sent_over_plain_http_in_testing(): void
    {
        $response = $this->get('/');

        $response->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_hsts_is_sent_over_https_in_production_only(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $response = $this->get('/', ['X-Forwarded-Proto' => 'https']);

        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }
}
