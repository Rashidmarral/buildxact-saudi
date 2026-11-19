<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "i need to add terms and conditions the same way added in this
 * quotation in bilingual on any template can we do this on creating
 * quotation in notes field also it accept 2000 characters but my terms
 * have more data" — a dedicated, bilingual, per-quotation Terms &
 * Conditions page (English + Arabic, numbered sections each with one or
 * more paragraphs), separate from the plain `notes` field (which stays
 * capped at 2000 characters, single language, no section structure).
 * Rendered once after every layout branch in both documents.print.pdf
 * (the mPDF download) and documents.print.body (the browser show/print
 * view), so it looks the same regardless of which template the company
 * has configured for its quotations.
 */
class QuotationTermsAndConditionsTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompanyWithClient(): array
    {
        $company = Company::create(['name' => 'Terms Co.', 'slug' => 'terms-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client A']);

        return [$company, $owner, $client];
    }

    private function basePayload(array $client, array $overrides = []): array
    {
        return array_merge([
            'client_id' => $client['id'],
            'type' => 'quotation',
            'issue_date' => now()->toDateString(),
            'expiry_date' => now()->addDays(30)->toDateString(),
            'items' => [['description' => 'Asphalt works', 'quantity' => 1, 'unit_price' => 49, 'vat_rate' => 15]],
        ], $overrides);
    }

    public function test_terms_sections_can_hold_far_more_than_the_2000_character_notes_cap(): void
    {
        [, $owner, $client] = $this->makeCompanyWithClient();
        $longText = str_repeat('Work shall commence following written acceptance of the quotation. ', 60);
        $this->assertGreaterThan(2000, strlen($longText));

        $response = $this->actingAs($owner)->post(route('app.quotations.store'), $this->basePayload(
            ['id' => $client->id],
            ['terms_sections' => [[
                'title_en' => 'General conditions',
                'title_ar' => 'الشروط العامة',
                'paragraphs' => [['heading_en' => '', 'heading_ar' => '', 'body_en' => $longText, 'body_ar' => 'تبدأ الأعمال بعد قبول العرض كتابياً.']],
            ]]]
        ));

        $response->assertSessionDoesntHaveErrors();

        $quotation = Quotation::latest('id')->first();
        $this->assertSame(trim($longText), $quotation->terms_sections[0]['paragraphs'][0]['body_en']);
    }

    public function test_the_plain_notes_field_still_rejects_more_than_2000_characters(): void
    {
        [, $owner, $client] = $this->makeCompanyWithClient();

        $response = $this->actingAs($owner)->post(route('app.quotations.store'), $this->basePayload(
            ['id' => $client->id],
            ['notes' => str_repeat('x', 2001)]
        ));

        $response->assertSessionHasErrors('notes');
    }

    public function test_empty_sections_and_paragraphs_are_stripped_before_saving(): void
    {
        [, $owner, $client] = $this->makeCompanyWithClient();

        $response = $this->actingAs($owner)->post(route('app.quotations.store'), $this->basePayload(
            ['id' => $client->id],
            ['terms_sections' => [
                ['title_en' => 'Payment terms', 'title_ar' => 'شروط الدفع', 'paragraphs' => [
                    ['heading_en' => '', 'heading_ar' => '', 'body_en' => '25% upon award.', 'body_ar' => ''],
                    ['heading_en' => '', 'heading_ar' => '', 'body_en' => '', 'body_ar' => ''], // blank — stripped
                ]],
                ['title_en' => '', 'title_ar' => '', 'paragraphs' => []], // entirely blank — stripped
            ]]
        ));

        $response->assertSessionDoesntHaveErrors();

        $quotation = Quotation::latest('id')->first();
        $this->assertCount(1, $quotation->terms_sections);
        $this->assertCount(1, $quotation->terms_sections[0]['paragraphs']);
        $this->assertSame('25% upon award.', $quotation->terms_sections[0]['paragraphs'][0]['body_en']);
    }

    public function test_no_terms_sections_submitted_saves_an_empty_array_without_error(): void
    {
        [, $owner, $client] = $this->makeCompanyWithClient();

        $response = $this->actingAs($owner)->post(route('app.quotations.store'), $this->basePayload(['id' => $client->id]));
        $response->assertSessionDoesntHaveErrors();

        $quotation = Quotation::latest('id')->first();
        $this->assertSame([], $quotation->terms_sections);
    }

    public function test_the_show_page_renders_numbered_bilingual_sections_and_sub_headings(): void
    {
        [, $owner, $client] = $this->makeCompanyWithClient();
        $this->actingAs($owner)->post(route('app.quotations.store'), $this->basePayload(
            ['id' => $client->id],
            ['terms_sections' => [
                ['title_en' => 'General conditions', 'title_ar' => 'الشروط العامة', 'paragraphs' => [
                    ['heading_en' => '', 'heading_ar' => '', 'body_en' => 'Work starts on acceptance.', 'body_ar' => 'تبدأ الأعمال عند القبول.'],
                ]],
                ['title_en' => 'Asphalt price adjustment terms', 'title_ar' => 'شروط تعديل الأسعار', 'paragraphs' => [
                    ['heading_en' => 'Bitumen Price Variation', 'heading_ar' => 'تغير سعر البيتومين', 'body_en' => 'Rates are based on a reference price.', 'body_ar' => 'الأسعار مبنية على سعر مرجعي.'],
                ]],
            ]]
        ));
        $quotation = Quotation::latest('id')->first();

        $show = $this->actingAs($owner)->get(route('app.quotations.show', $quotation));
        $show->assertOk()
            ->assertSee('Terms and Conditions')
            ->assertSee('1. General conditions')
            ->assertSeeInOrder(['1. General conditions', '2. Asphalt price adjustment terms'])
            ->assertSee('Bitumen Price Variation')
            ->assertSee('Work starts on acceptance.');
    }

    public function test_the_downloaded_pdf_renders_with_terms_sections(): void
    {
        [, $owner, $client] = $this->makeCompanyWithClient();
        $this->actingAs($owner)->post(route('app.quotations.store'), $this->basePayload(
            ['id' => $client->id],
            ['terms_sections' => [['title_en' => 'Payment terms', 'title_ar' => 'شروط الدفع', 'paragraphs' => [
                ['heading_en' => '', 'heading_ar' => '', 'body_en' => '25% upon award.', 'body_ar' => '٢٥٪ عند الترسية.'],
            ]]]]
        ));
        $quotation = Quotation::latest('id')->first();

        $pdf = $this->actingAs($owner)->get(route('app.quotations.pdf', $quotation));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_the_pdf_has_no_terms_page_at_all_when_none_were_entered(): void
    {
        [, $owner, $client] = $this->makeCompanyWithClient();
        $this->actingAs($owner)->post(route('app.quotations.store'), $this->basePayload(['id' => $client->id]));
        $quotation = Quotation::latest('id')->first();

        $pdf = $this->actingAs($owner)->get(route('app.quotations.pdf', $quotation));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $show = $this->actingAs($owner)->get(route('app.quotations.show', $quotation));
        $show->assertOk()->assertDontSee('Terms and Conditions');
    }

    public function test_editing_a_quotation_round_trips_its_terms_sections_unchanged(): void
    {
        [, $owner, $client] = $this->makeCompanyWithClient();
        $this->actingAs($owner)->post(route('app.quotations.store'), $this->basePayload(
            ['id' => $client->id],
            ['terms_sections' => [['title_en' => 'Payment terms', 'title_ar' => 'شروط الدفع', 'paragraphs' => [
                ['heading_en' => '', 'heading_ar' => '', 'body_en' => '25% upon award.', 'body_ar' => '٢٥٪ عند الترسية.'],
            ]]]]
        ));
        $quotation = Quotation::latest('id')->first();

        $edit = $this->actingAs($owner)->get(route('app.quotations.edit', $quotation));
        $edit->assertOk()->assertSee('const EXISTING_TERMS', false)->assertSee('Payment terms');

        // Re-submit the update with a second section added, confirming
        // the first one survives untouched.
        $this->actingAs($owner)->put(route('app.quotations.update', $quotation), $this->basePayload(
            ['id' => $client->id],
            ['terms_sections' => [
                ['title_en' => 'Payment terms', 'title_ar' => 'شروط الدفع', 'paragraphs' => [
                    ['heading_en' => '', 'heading_ar' => '', 'body_en' => '25% upon award.', 'body_ar' => '٢٥٪ عند الترسية.'],
                ]],
                ['title_en' => 'Warranty', 'title_ar' => 'الضمان', 'paragraphs' => [
                    ['heading_en' => '', 'heading_ar' => '', 'body_en' => 'One year warranty.', 'body_ar' => 'ضمان لمدة سنة.'],
                ]],
            ]]
        ))->assertSessionDoesntHaveErrors();

        $quotation->refresh();
        $this->assertCount(2, $quotation->terms_sections);
        $this->assertSame('Payment terms', $quotation->terms_sections[0]['title_en']);
        $this->assertSame('Warranty', $quotation->terms_sections[1]['title_en']);
    }
}
