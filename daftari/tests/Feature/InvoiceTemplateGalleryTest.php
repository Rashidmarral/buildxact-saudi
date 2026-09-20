<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceTemplate;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Support\InvoiceTemplatePresets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature request: a visual, one-click "gallery" of built-in invoice
 * layouts (mirroring a competitor's Invoice Settings screen) — activating
 * a card applies that look everywhere at once (every document type, on
 * screen and in every downloaded/emailed PDF), rather than the existing
 * per-document-type advanced editor's sidebar+form flow.
 */
class InvoiceTemplateGalleryTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        return Company::create(['name' => 'Gallery Co.', 'slug' => 'gallery-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    public function test_the_gallery_lists_the_default_card_and_every_preset(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->get(route('app.invoice-templates.gallery'));

        $response->assertOk();
        $response->assertSee(__('Default'));
        foreach (InvoiceTemplatePresets::all() as $preset) {
            $response->assertSee($preset['name']);
        }
        $response->assertSee(__('Activate invoice layout'));
    }

    public function test_the_default_card_is_active_when_no_global_template_exists(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->get(route('app.invoice-templates.gallery'));

        $response->assertSeeInOrder([__('Default'), '✓ '.__('Active invoice layout')]);
    }

    public function test_every_preset_and_the_default_card_render_a_live_preview(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->get(route('app.invoice-templates.gallery.preview', 'default'));
        $response->assertOk();
        $response->assertSee('INV-0001');
        $response->assertSee('Nolwa Private Limited');

        foreach (array_keys(InvoiceTemplatePresets::all()) as $key) {
            $this->actingAs($owner)->get(route('app.invoice-templates.gallery.preview', $key))->assertOk();
        }
    }

    public function test_a_nonexistent_preset_key_404s(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);

        $this->actingAs($owner)->get(route('app.invoice-templates.gallery.preview', 'not-a-real-preset'))->assertNotFound();
        $this->actingAs($owner)->post(route('app.invoice-templates.gallery.activate', 'not-a-real-preset'))->assertNotFound();
    }

    public function test_activating_a_preset_creates_a_global_default_template_and_marks_it_active(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->post(route('app.invoice-templates.gallery.activate', 'bold_branded'));

        $response->assertRedirect(route('app.invoice-templates.gallery'));
        $this->assertDatabaseHas('invoice_templates', [
            'company_id' => $company->id, 'preset_key' => 'bold_branded', 'document_type' => 'all', 'is_default' => true,
        ]);

        $gallery = $this->actingAs($owner)->get(route('app.invoice-templates.gallery'));
        $gallery->assertSeeInOrder(['Bold Branded', '✓ '.__('Active invoice layout')]);
    }

    public function test_activating_a_preset_clears_is_default_on_every_other_template(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $custom = InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Custom Invoice Only', 'document_type' => 'invoice',
            'accent_color' => '#000000', 'layout' => 'minimal', 'is_default' => true,
        ]);

        $this->actingAs($owner)->post(route('app.invoice-templates.gallery.activate', 'modern_minimal'));

        $this->assertFalse($custom->fresh()->is_default);
        $this->assertTrue(
            InvoiceTemplate::where('company_id', $company->id)->where('preset_key', 'modern_minimal')->value('is_default')
        );
    }

    public function test_reactivating_the_same_preset_updates_the_existing_row_instead_of_duplicating_it(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);

        $this->actingAs($owner)->post(route('app.invoice-templates.gallery.activate', 'corporate_gray'));
        $this->actingAs($owner)->post(route('app.invoice-templates.gallery.activate', 'corporate_gray'));

        $this->assertSame(1, InvoiceTemplate::where('company_id', $company->id)->where('preset_key', 'corporate_gray')->count());
    }

    public function test_activating_default_reverts_to_the_hardcoded_look(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $this->actingAs($owner)->post(route('app.invoice-templates.gallery.activate', 'compact_commercial'));

        $response = $this->actingAs($owner)->post(route('app.invoice-templates.gallery.activate', 'default'));

        $response->assertRedirect(route('app.invoice-templates.gallery'));
        $this->assertSame(0, InvoiceTemplate::where('company_id', $company->id)->where('is_default', true)->count());
    }

    public function test_an_activated_preset_is_actually_used_when_showing_a_real_invoice(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client A']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-0001', 'type' => 'standard', 'status' => 'draft',
            'issue_date' => now(), 'subtotal' => 100, 'vat_total' => 15, 'total' => 115, 'currency' => 'SAR',
        ]);

        $this->actingAs($owner)->post(route('app.invoice-templates.gallery.activate', 'classic_professional'));

        $response = $this->actingAs($owner)->get(route('app.invoices.show', $invoice));

        $response->assertOk();
        $response->assertViewHas('template', fn ($template) => $template && $template->preset_key === 'classic_professional');
    }

    public function test_activating_a_preset_is_blocked_once_the_plan_template_limit_is_reached(): void
    {
        $plan = Plan::create([
            'name' => 'Starter', 'slug' => 'starter-'.uniqid(), 'price_monthly' => 50, 'price_yearly' => 500,
            'is_active' => true, 'max_invoice_templates' => 1,
        ]);
        $company = $this->makeCompany();
        Subscription::create([
            'company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
        ]);
        $owner = $this->makeOwner($company);
        InvoiceTemplate::create(['company_id' => $company->id, 'name' => 'Existing', 'document_type' => 'invoice', 'accent_color' => '#000000', 'layout' => 'minimal']);

        $response = $this->actingAs($owner)->post(route('app.invoice-templates.gallery.activate', 'arabic_executive'));

        $response->assertSessionHasErrors('plan_limit');
        $this->assertDatabaseMissing('invoice_templates', ['company_id' => $company->id, 'preset_key' => 'arabic_executive']);
    }
}
