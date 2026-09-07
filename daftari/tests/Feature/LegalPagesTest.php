<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Go-live audit finding: the public Terms of Service and Privacy Policy
 * pages literally said "This placeholder ... Replace this text with
 * terms reviewed by your legal counsel before going live." — lawyer-
 * unreviewed placeholder copy shipping to real paying customers. Both
 * pages now carry real, substantive content tailored to what Daftari
 * actually does (subscriptions, ZATCA e-invoicing, PDPL).
 */
class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public static function pages(): array
    {
        return [['terms'], ['privacy']];
    }

    #[DataProvider('pages')]
    public function test_the_legal_page_no_longer_contains_placeholder_language(string $page): void
    {
        $response = $this->get(route('legal', $page));

        $response->assertOk();
        $response->assertDontSee('placeholder', false);
        $response->assertDontSee('Replace this text');
    }

    public function test_the_terms_page_covers_subscriptions_and_zatca(): void
    {
        $response = $this->get(route('legal', 'terms'));

        $response->assertOk();
        $response->assertSee('Subscriptions and billing');
        $response->assertSee('ZATCA', false);
        $response->assertSee('Kingdom of Saudi Arabia', false);
    }

    public function test_the_privacy_page_covers_pdpl_and_data_rights(): void
    {
        $response = $this->get(route('legal', 'privacy'));

        $response->assertOk();
        $response->assertSee('Personal Data Protection Law', false);
        $response->assertSee('Your rights');
    }

    public function test_an_unknown_legal_page_slug_404s(): void
    {
        $response = $this->get('/legal/refund');

        $response->assertNotFound();
    }
}
