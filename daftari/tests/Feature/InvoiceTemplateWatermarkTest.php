<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Feature request: a company can now upload a background watermark image
 * for its invoice/document template — shown faintly behind the whole
 * document, at a configurable opacity — in addition to the existing
 * letterhead (header) and footer images.
 */
class InvoiceTemplateWatermarkTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $company = Company::create(['name' => 'Watermark Co.', 'slug' => 'watermark-'.uniqid()]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    private function tinyPng(): string
    {
        $image = imagecreatetruecolor(4, 4);
        ob_start();
        imagepng($image);
        $contents = ob_get_clean();
        imagedestroy($image);

        return $contents;
    }

    public function test_a_company_can_upload_set_opacity_and_remove_a_watermark(): void
    {
        Storage::fake('public');

        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $template = InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Default', 'document_type' => 'all',
            'layout' => 'minimal', 'is_default' => true,
        ]);

        $response = $this->actingAs($owner)->put(route('app.invoice-templates.update', $template), [
            'name' => 'Default', 'document_type' => 'all', 'accent_color' => '#0f766e',
            'layout' => 'minimal', 'language_mode' => 'bilingual', 'table_direction' => 'ltr',
            'watermark' => UploadedFile::fake()->image('watermark.png', 800, 800),
            'watermark_opacity' => 25,
        ]);

        $response->assertRedirect();
        $template->refresh();
        $this->assertNotNull($template->watermark_path);
        $this->assertSame(25, $template->watermark_opacity);
        Storage::disk('public')->assertExists($template->watermark_path);
        $this->assertStringStartsWith('watermarks/', $template->watermark_path);

        $removal = $this->actingAs($owner)->put(route('app.invoice-templates.update', $template), [
            'name' => 'Default', 'document_type' => 'all', 'accent_color' => '#0f766e',
            'layout' => 'minimal', 'language_mode' => 'bilingual', 'table_direction' => 'ltr',
            'remove_watermark' => '1',
        ]);

        $removal->assertRedirect();
        $template->refresh();
        $this->assertNull($template->watermark_path);
    }

    public function test_a_templates_watermark_image_renders_on_the_invoice_show_page_and_is_servable(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('watermarks/co-seal.png', $this->tinyPng());

        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Default', 'document_type' => 'all',
            'layout' => 'minimal', 'is_default' => true,
            'watermark_path' => 'watermarks/co-seal.png', 'watermark_opacity' => 15,
        ]);

        $client = Client::create(['company_id' => $company->id, 'name' => 'Watermark Client']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-WM-1',
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(),
            'currency' => $company->currency, 'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id, 'description' => 'Test item', 'quantity' => 1,
            'unit_price' => 100, 'vat_rate' => 15, 'vat_amount' => 15, 'line_total' => 115,
        ]);

        $show = $this->actingAs($owner)->get(route('app.invoices.show', $invoice));
        $show->assertOk();
        $show->assertSee('co-seal.png', false);
        $show->assertSee('opacity: 0.15', false);

        $this->actingAs($owner)
            ->get(route('files.show', ['filepath' => 'watermarks/co-seal.png']))
            ->assertOk();
    }

    public function test_the_invoice_pdf_still_downloads_when_a_watermark_is_set(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('watermarks/co-seal.png', $this->tinyPng());

        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Default', 'document_type' => 'all',
            'layout' => 'minimal', 'is_default' => true,
            'watermark_path' => 'watermarks/co-seal.png', 'watermark_opacity' => 20,
        ]);

        $client = Client::create(['company_id' => $company->id, 'name' => 'Watermark PDF Client']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-WM-2',
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(),
            'currency' => $company->currency, 'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id, 'description' => 'Test item', 'quantity' => 1,
            'unit_price' => 100, 'vat_rate' => 15, 'vat_amount' => 15, 'line_total' => 115,
        ]);

        $response = $this->actingAs($owner)->get(route('app.invoices.pdf', $invoice));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }
}
