<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Go-live audit follow-up: hitting an error (404, etc.) showed Laravel's
 * bare framework fallback ("no default error page available") instead of
 * a branded page, because resources/views/errors/ only had maintenance.blade.php
 * and registration-closed.blade.php — no numbered status-code views, which
 * is what Laravel's exception handler looks for first. Each of these now
 * renders through the shared <x-error-page> component and must do so
 * without throwing, including with no authenticated user and no database
 * state — a 500 can mean the database itself is unreachable, so the error
 * page rendering must never depend on it.
 */
class CustomErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, array{0: string}> */
    public static function statusCodes(): array
    {
        return [['401'], ['403'], ['404'], ['419'], ['429'], ['500'], ['503']];
    }

    #[DataProvider('statusCodes')]
    public function test_the_error_view_renders_without_a_logged_in_user_or_any_database_dependency(string $code): void
    {
        $html = (string) view('errors.'.$code)->render();

        $this->assertStringContainsString($code, $html);
        $this->assertStringContainsString('Daftari', $html);
    }

    public function test_a_genuinely_unmatched_route_renders_the_custom_404_page_not_the_framework_default(): void
    {
        $response = $this->get('/this-route-does-not-exist-'.uniqid());

        $response->assertNotFound();
        $response->assertSee('404');
        $response->assertDontSee('Symfony');
    }
}
