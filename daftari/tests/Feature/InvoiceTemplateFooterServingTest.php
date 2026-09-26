<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\InvoiceTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Bug: a template's uploaded footer image was stored under 'footers/' on
 * the public disk, but FileServeController::PUBLIC_PREFIXES (the allowlist
 * that lets an image render without a matching Attachment/Item row) never
 * included that prefix — only 'logos/', 'stamps/' and 'letterheads/' did.
 * The <img> tag was correctly emitted in every print view, but the browser
 * request for its src always 404'd, so the footer silently never appeared
 * even though the letterhead/logo (same upload flow, different prefix)
 * worked fine. Same gap applies to the new watermark upload.
 */
class InvoiceTemplateFooterServingTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_uploaded_footer_image_is_actually_servable(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('footers/test-footer.png', 'fake-png-bytes');

        $company = Company::create(['name' => 'Footer Serve Co.', 'slug' => 'footer-serve-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Default', 'document_type' => 'all',
            'layout' => 'minimal', 'is_default' => true, 'footer_path' => 'footers/test-footer.png',
        ]);

        $this->actingAs($owner)
            ->get(route('files.show', ['filepath' => 'footers/test-footer.png']))
            ->assertOk();
    }

    public function test_an_uploaded_watermark_image_is_actually_servable(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('watermarks/test-watermark.png', 'fake-png-bytes');

        $company = Company::create(['name' => 'Watermark Serve Co.', 'slug' => 'watermark-serve-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Default', 'document_type' => 'all',
            'layout' => 'minimal', 'is_default' => true, 'watermark_path' => 'watermarks/test-watermark.png',
        ]);

        $this->actingAs($owner)
            ->get(route('files.show', ['filepath' => 'watermarks/test-watermark.png']))
            ->assertOk();
    }
}
