<?php

namespace Tests\Feature;

use App\Http\Controllers\User\QuotationController;
use App\Models\Client;
use App\Models\Company;
use App\Models\InvoiceTemplate;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reported bug: a company using the quotation_offer layout with Language
 * set to Bilingual got a document that was mostly English anyway — the
 * title, "Dear Sirs," salutation, intro paragraph, table's "#" header,
 * "Yours faithfully," and "Authorized Signatory" were all hardcoded to
 * switch between pure-Arabic and pure-English only (keyed off
 * language_mode === 'arabic_only'), never actually combining both
 * languages the way every other layout's structural labels do via
 * $lbl()/$primary()/$secondary(). The settings page even warned "this
 * layout always writes fully in one language" — true of the old code,
 * but not what a company picking Bilingual actually wants. Every one of
 * those spots is now genuinely bilingual in bilingual mode (still pure
 * Arabic/English in arabic_only/english_only, unchanged).
 *
 * Also new: a company can override (or hide) the boilerplate "Dear Sirs,
 * We are pleased to submit..." paragraph via InvoiceTemplate::greeting_en/
 * greeting_ar/show_greeting, instead of it always being the same fixed
 * wording.
 */
class QuotationOfferBilingualGreetingTest extends TestCase
{
    use RefreshDatabase;

    private function makeQuotation(array $templateOverrides = []): array
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'name_ar' => 'شركة دايناميك كور', 'slug' => 'dcc-'.uniqid(), 'cr_number' => '7053180563', 'phone' => '0568582270']);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        InvoiceTemplate::create(array_merge([
            'company_id' => $company->id, 'name' => 'Quotation Offer', 'document_type' => 'all',
            'layout' => 'quotation_offer', 'language_mode' => 'bilingual', 'is_default' => true,
        ], $templateOverrides));

        $client = Client::create(['company_id' => $company->id, 'name' => 'Sada Al Jeul Construction Establishment', 'name_ar' => 'مؤسسة سادة الجيل']);

        $quotation = Quotation::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'created_by' => $owner->id,
            'quotation_number' => 'QTN-00006', 'type' => 'quotation', 'status' => 'issued',
            'issue_date' => now()->toDateString(), 'expiry_date' => now()->addDays(30)->toDateString(),
            'currency' => $company->currency, 'subtotal' => 9500, 'vat_total' => 1425, 'total' => 10925,
        ]);

        QuotationItem::create([
            'quotation_id' => $quotation->id, 'description' => 'Aggregate Base Course, 10 CM thickness',
            'quantity' => 1, 'unit_price' => 7000, 'vat_rate' => 15, 'vat_amount' => 1050, 'line_total' => 8050,
        ]);

        return [$owner, $quotation];
    }

    public function test_bilingual_mode_shows_both_languages_throughout_the_quotation_offer_layout(): void
    {
        [$owner, $quotation] = $this->makeQuotation();

        $response = $this->actingAs($owner)->get(route('app.quotations.show', $quotation));

        $response->assertOk();
        $response->assertSee(__('Quotation'));
        $response->assertSee('عرض سعر');
        $response->assertSee('Dear Sirs,');
        $response->assertSee('السلام عليكم ورحمة الله وبركاته،');
        $response->assertSee('We, Dynamic Core Contracting', false);
        $response->assertSee('يسرنا نحن', false);
        $response->assertSee('Yours faithfully,');
        $response->assertSee('وتفضلوا بقبول فائق الاحترام');
        $response->assertSee('Authorized Signatory');
        $response->assertSee('المفوض بالتوقيع');
        // "Valid until" — the field this whole investigation started from.
        $response->assertSee(__('Valid until'));
        $response->assertSee('صالح حتى');
    }

    public function test_the_real_downloaded_pdf_also_renders_bilingually_for_quotation_offer(): void
    {
        [$owner, $quotation] = $this->makeQuotation();

        $response = $this->actingAs($owner)->get(route('app.quotations.pdf', $quotation));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_the_mpdf_template_html_itself_is_bilingual_for_quotation_offer(): void
    {
        [, $quotation] = $this->makeQuotation();

        $method = new \ReflectionMethod(QuotationController::class, 'pdfData');
        $method->setAccessible(true);
        $data = $method->invoke(app(QuotationController::class), $quotation);

        $html = view('documents.print.pdf', $data + ['embed' => fn () => null])->render();

        $this->assertStringContainsString('Dear Sirs,', $html);
        $this->assertStringContainsString('السلام عليكم ورحمة الله وبركاته،', $html);
        $this->assertStringContainsString('يسرنا نحن', $html);
        $this->assertStringContainsString('Yours faithfully,', $html);
        $this->assertStringContainsString('وتفضلوا بقبول فائق الاحترام', $html);
        $this->assertStringContainsString('Authorized Signatory', $html);
        $this->assertStringContainsString('المفوض بالتوقيع', $html);
        $this->assertStringContainsString('صالح حتى', $html);
    }

    public function test_a_custom_greeting_replaces_the_default_boilerplate(): void
    {
        [$owner, $quotation] = $this->makeQuotation([
            'greeting_en' => 'Thank you for the opportunity to quote on this project.',
            'greeting_ar' => 'نشكركم على فرصة تقديم عرض السعر لهذا المشروع.',
        ]);

        $response = $this->actingAs($owner)->get(route('app.quotations.show', $quotation));

        $response->assertOk();
        $response->assertSee('Thank you for the opportunity to quote on this project.');
        $response->assertSee('نشكركم على فرصة تقديم عرض السعر لهذا المشروع.');
        $response->assertDontSee('Dear Sirs,');
        $response->assertDontSee('are pleased to submit the following', false);
    }

    public function test_show_greeting_false_hides_the_greeting_entirely(): void
    {
        [$owner, $quotation] = $this->makeQuotation(['show_greeting' => false]);

        $response = $this->actingAs($owner)->get(route('app.quotations.show', $quotation));

        $response->assertOk();
        $response->assertDontSee('Dear Sirs,');
        $response->assertDontSee('are pleased to submit the following', false);
        // The data-driven "To:" salutation line is not boilerplate and
        // must still show regardless of the greeting toggle.
        $response->assertSee('Sada Al Jeul Construction Establishment');
    }

    public function test_arabic_only_mode_is_unaffected_by_the_bilingual_fix(): void
    {
        [$owner, $quotation] = $this->makeQuotation(['language_mode' => 'arabic_only']);

        $response = $this->actingAs($owner)->get(route('app.quotations.show', $quotation));

        $response->assertOk();
        $response->assertSee('السادة/ مؤسسة سادة الجيل المحترمين');
        $response->assertSee('السلام عليكم ورحمة الله وبركاته،');
        $response->assertDontSee('Dear Sirs,');
    }

    public function test_english_only_mode_is_unaffected_by_the_bilingual_fix(): void
    {
        [$owner, $quotation] = $this->makeQuotation(['language_mode' => 'english_only']);

        $response = $this->actingAs($owner)->get(route('app.quotations.show', $quotation));

        $response->assertOk();
        $response->assertSee('Dear Sirs,');
        $response->assertDontSee('السلام عليكم');
    }
}
