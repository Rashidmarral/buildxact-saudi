<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\InvoiceTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "i have set invoice layout different and quotation layout different i
 * don't have option to change quotation layout to new template ... user
 * choose the layout type and then user have multiple choice selection
 * where it is applies and also user have option to deselect." Each
 * InvoiceTemplate row is still scoped to exactly one document_type
 * (Company::defaultTemplateFor() is untouched), but the editor now lets
 * one design apply to several types at once via a shared design_group —
 * these tests cover the controller logic driving that: creating with
 * several types, growing/shrinking the set on save, and deleting the
 * whole design together.
 */
class InvoiceTemplateDesignGroupTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): User
    {
        $company = Company::create(['name' => 'Design Co.', 'slug' => 'design-'.uniqid()]);

        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    public function test_creating_a_template_with_several_document_types_makes_one_row_per_type_sharing_a_design_group(): void
    {
        $owner = $this->makeOwner();

        $response = $this->actingAs($owner)->post(route('app.invoice-templates.store'), [
            'name' => 'Bold Branded',
            'document_types' => ['invoice', 'quotation', 'proforma'],
        ]);

        $response->assertRedirect();

        $rows = InvoiceTemplate::where('company_id', $owner->company_id)->get();
        $this->assertCount(3, $rows);
        $this->assertSame(['invoice', 'proforma', 'quotation'], $rows->pluck('document_type')->sort()->values()->all());
        $this->assertCount(1, $rows->pluck('design_group')->unique(), 'every row must share the same design_group');
        $this->assertTrue($rows->every(fn ($r) => $r->is_default), 'applies to means active for — every selected type should be the active one immediately');
    }

    public function test_checking_a_type_already_active_on_another_design_switches_it_over(): void
    {
        $owner = $this->makeOwner();
        $company = $owner->company;

        $existingQuotationTemplate = InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Old Quotation Look', 'document_type' => 'quotation',
            'layout' => 'minimal', 'is_default' => true,
        ]);

        $this->actingAs($owner)->post(route('app.invoice-templates.store'), [
            'name' => 'New Design',
            'document_types' => ['quotation'],
        ])->assertRedirect();

        $this->assertFalse($existingQuotationTemplate->fresh()->is_default, 'the old design must stop being active for quotations once a new one claims that type');
        $newRow = InvoiceTemplate::where('name', 'New Design')->first();
        $this->assertTrue($newRow->is_default);
    }

    public function test_updating_a_design_to_add_a_document_type_creates_a_new_row_in_the_same_group(): void
    {
        $owner = $this->makeOwner();
        $template = InvoiceTemplate::create([
            'company_id' => $owner->company_id, 'name' => 'My Design', 'document_type' => 'invoice',
            'layout' => 'minimal', 'is_default' => true,
        ]);
        $groupId = $template->design_group;
        $this->assertNotNull($groupId);

        $response = $this->actingAs($owner)->put(route('app.invoice-templates.update', $template), $this->fullPayload([
            'document_types' => ['invoice', 'quotation'],
        ]));

        $response->assertRedirect();
        $rows = InvoiceTemplate::where('design_group', $groupId)->get();
        $this->assertCount(2, $rows);
        $this->assertSame(['invoice', 'quotation'], $rows->pluck('document_type')->sort()->values()->all());
        $this->assertTrue($rows->every(fn ($r) => $r->is_default));
    }

    public function test_updating_a_design_to_remove_a_document_type_deletes_that_row_and_leaves_the_others(): void
    {
        $owner = $this->makeOwner();
        $groupId = (string) \Illuminate\Support\Str::uuid();
        $invoiceRow = InvoiceTemplate::create([
            'company_id' => $owner->company_id, 'name' => 'Two Types', 'document_type' => 'invoice',
            'design_group' => $groupId, 'layout' => 'minimal', 'is_default' => true,
        ]);
        InvoiceTemplate::create([
            'company_id' => $owner->company_id, 'name' => 'Two Types', 'document_type' => 'quotation',
            'design_group' => $groupId, 'layout' => 'minimal', 'is_default' => true,
        ]);

        $response = $this->actingAs($owner)->put(route('app.invoice-templates.update', $invoiceRow), $this->fullPayload([
            'document_types' => ['invoice'],
        ]));

        $response->assertRedirect();
        $this->assertSame(1, InvoiceTemplate::where('design_group', $groupId)->count());
        $this->assertNull(InvoiceTemplate::where('design_group', $groupId)->where('document_type', 'quotation')->first());
    }

    public function test_updating_a_design_applies_shared_style_fields_to_every_row_in_the_group(): void
    {
        $owner = $this->makeOwner();
        $groupId = (string) \Illuminate\Support\Str::uuid();
        $invoiceRow = InvoiceTemplate::create([
            'company_id' => $owner->company_id, 'name' => 'Shared Style', 'document_type' => 'invoice',
            'design_group' => $groupId, 'layout' => 'minimal', 'accent_color' => '#000000', 'is_default' => true,
        ]);
        InvoiceTemplate::create([
            'company_id' => $owner->company_id, 'name' => 'Shared Style', 'document_type' => 'quotation',
            'design_group' => $groupId, 'layout' => 'minimal', 'accent_color' => '#000000', 'is_default' => true,
        ]);

        $this->actingAs($owner)->put(route('app.invoice-templates.update', $invoiceRow), $this->fullPayload([
            'document_types' => ['invoice', 'quotation'],
            'accent_color' => '#ff0000',
        ]))->assertRedirect();

        $rows = InvoiceTemplate::where('design_group', $groupId)->get();
        $this->assertTrue($rows->every(fn ($r) => $r->accent_color === '#ff0000'), 'every row in a design must carry the same style — it is one design applied to several types');
    }

    public function test_deleting_a_design_removes_every_row_in_its_group(): void
    {
        $owner = $this->makeOwner();
        $groupId = (string) \Illuminate\Support\Str::uuid();
        $invoiceRow = InvoiceTemplate::create([
            'company_id' => $owner->company_id, 'name' => 'Delete Me', 'document_type' => 'invoice',
            'design_group' => $groupId, 'layout' => 'minimal', 'is_default' => true,
        ]);
        InvoiceTemplate::create([
            'company_id' => $owner->company_id, 'name' => 'Delete Me', 'document_type' => 'quotation',
            'design_group' => $groupId, 'layout' => 'minimal', 'is_default' => true,
        ]);

        $this->actingAs($owner)->delete(route('app.invoice-templates.destroy', $invoiceRow))->assertRedirect();

        $this->assertSame(0, InvoiceTemplate::where('design_group', $groupId)->count());
    }

    public function test_make_default_reactivates_every_row_in_the_group_at_once(): void
    {
        $owner = $this->makeOwner();
        $company = $owner->company;
        $groupId = (string) \Illuminate\Support\Str::uuid();

        $otherInvoiceDefault = InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Currently Active', 'document_type' => 'invoice',
            'layout' => 'minimal', 'is_default' => true,
        ]);
        $thisInvoiceRow = InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Inactive Design', 'document_type' => 'invoice',
            'design_group' => $groupId, 'layout' => 'minimal', 'is_default' => false,
        ]);
        InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Inactive Design', 'document_type' => 'quotation',
            'design_group' => $groupId, 'layout' => 'minimal', 'is_default' => false,
        ]);

        $this->actingAs($owner)->post(route('app.invoice-templates.make-default', $thisInvoiceRow))->assertRedirect();

        $this->assertFalse($otherInvoiceDefault->fresh()->is_default);
        $this->assertTrue(InvoiceTemplate::where('design_group', $groupId)->get()->every(fn ($r) => $r->is_default));
    }

    public function test_the_index_page_groups_rows_into_designs_and_lists_every_applies_to_type_even_when_filtered(): void
    {
        $owner = $this->makeOwner();
        $groupId = (string) \Illuminate\Support\Str::uuid();
        InvoiceTemplate::create([
            'company_id' => $owner->company_id, 'name' => 'Multi Type Design', 'document_type' => 'invoice',
            'design_group' => $groupId, 'layout' => 'minimal', 'is_default' => true,
        ]);
        InvoiceTemplate::create([
            'company_id' => $owner->company_id, 'name' => 'Multi Type Design', 'document_type' => 'quotation',
            'design_group' => $groupId, 'layout' => 'minimal', 'is_default' => true,
        ]);

        $response = $this->actingAs($owner)->get(route('app.invoice-templates.index', ['type' => 'quotation']));

        $response->assertOk();
        $response->assertSee('Multi Type Design');
        // The filter narrows which designs are listed, but a design that
        // matches must still show every type it applies to, not just the
        // one that matched the filter.
        $response->assertSee(__('Invoices'));
        $response->assertSee(__('Quotations'));
    }

    public function test_using_a_starter_preset_from_the_modal_can_also_target_several_document_types(): void
    {
        $owner = $this->makeOwner();

        $response = $this->actingAs($owner)->post(route('app.invoice-templates.use'), [
            'preset' => array_key_first(\App\Support\InvoiceTemplatePresets::all()),
            'document_types' => ['invoice', 'bill'],
        ]);

        $response->assertRedirect();
        $rows = InvoiceTemplate::where('company_id', $owner->company_id)->get();
        $this->assertCount(2, $rows);
        $this->assertSame(['bill', 'invoice'], $rows->pluck('document_type')->sort()->values()->all());
        $this->assertCount(1, $rows->pluck('design_group')->unique());
        $this->assertTrue($rows->every(fn ($r) => $r->is_default));
    }

    private function fullPayload(array $overrides): array
    {
        return array_merge([
            'name' => 'Updated Name',
            'accent_color' => '#0f766e',
            'layout' => 'minimal',
            'language_mode' => 'bilingual',
            'table_direction' => 'ltr',
        ], $overrides);
    }
}
