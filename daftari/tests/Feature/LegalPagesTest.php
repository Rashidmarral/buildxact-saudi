<?php

namespace Tests\Feature;

use Database\Seeders\LegalDocumentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Go-live audit finding: the public Terms of Service and Privacy Policy
 * pages literally said "This placeholder ... Replace this text with
 * terms reviewed by your legal counsel before going live." — lawyer-
 * unreviewed placeholder copy shipping to real paying customers. Both
 * pages now carry real, substantive content tailored to what Daftari
 * actually does (subscriptions, ZATCA e-invoicing, PDPL), rendered from
 * the admin-editable LegalDocument table (LegalDocumentSeeder) rather
 * than hardcoded in the view.
 */
class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LegalDocumentSeeder::class);
    }

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

    /**
     * Bug report: legal documents (Refund Policy included) were fully built
     * in Admin — CRUD, content, the public /legal/{slug} route — but the
     * site footer never linked to any of them, so a visitor had no way to
     * find them without already knowing the exact URL. The footer now
     * lists every LegalDocument, published or still in review.
     */
    public function test_the_site_footer_links_to_every_legal_document_including_refund_policy(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee(route('legal', 'refund-policy'), false);
        $response->assertSee(route('legal', 'terms'), false);
        $response->assertSee(route('legal', 'cookie-policy'), false);
    }
}
