<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Company;
use App\Models\Item;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bug report: /app/items/create 500'd with "Undefined variable
 * $unitOptionsData". Root cause was a Blade compiler hazard, not a missing
 * variable: BladeCompiler::storePhpBlocks() pairs the FIRST @php token in a
 * file with the FIRST @endphp that follows it, regardless of whether that
 * @php was the inline `@php(expr)` form or a block `@php ... @endphp`. Two
 * inline `@php($defaultVatRate = ...)` / `@php($variantList = ...)`
 * directives sat earlier in this file than the block-form @php...@endphp
 * sections defining $unitOptionsData and $kitCandidatesData, so the regex
 * paired the first inline @php with the first real @endphp and silently
 * swallowed everything in between (including the $unitOptionsData
 * assignment) as inert, unprocessed text. Fixed by converting the inline
 * directives to their own self-contained @php ... @endphp blocks.
 */
class ItemFormPageRendersTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): User
    {
        $company = Company::create(['name' => 'Form Co.', 'slug' => 'form-'.uniqid()]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    public function test_the_create_item_page_loads(): void
    {
        $owner = $this->makeOwner();
        Unit::create(['company_id' => $owner->company_id, 'name' => 'Piece', 'abbreviation' => 'pc']);

        $response = $this->actingAs($owner)->get(route('app.items.create'));

        $response->assertOk();
        $response->assertSee(__('Alternate units'));
    }

    public function test_the_edit_item_page_loads_and_exposes_kit_and_variant_data(): void
    {
        $owner = $this->makeOwner();
        $component = Item::create(['company_id' => $owner->company_id, 'name' => 'Component', 'item_type' => 'physical', 'unit_price' => 1, 'track_inventory' => true]);
        $kit = Item::create(['company_id' => $owner->company_id, 'name' => 'Kit', 'item_type' => 'physical', 'unit_price' => 10, 'is_kit' => true]);
        $kit->kitComponents()->create(['company_id' => $owner->company_id, 'component_item_id' => $component->id, 'quantity' => 2]);

        $response = $this->actingAs($owner)->get(route('app.items.edit', $kit));

        $response->assertOk();
        $response->assertSee(__('Kit components'));
        $response->assertSee($component->name);
    }

    /**
     * Bug report: editing an item and clicking Save did nothing. Root
     * cause was invalid nested HTML: the "Add variant" mini-form (only
     * rendered for an existing, non-kit item) sat inside the main item
     * edit <form>. A real browser's HTML parser refuses to open a second
     * <form> while one is already in scope (the open tag is simply
     * dropped), so the inner form's own </form> tag closes the OUTER form
     * early instead — leaving the real Save button, and everything after
     * the Variants block, outside of any form. Clicking it then fires no
     * request at all.
     *
     * PHP's DOMDocument/libxml HTML parser does NOT reproduce that
     * browser behavior — it happily nests a second <form> inside the
     * first, so an xpath-based "which form owns the Save button" check
     * passes even against the broken markup and would never have caught
     * this. This instead walks the raw HTML token-by-token counting
     * <form>/</form> the way a browser's tokenizer does, and asserts the
     * nesting depth never exceeds 1 — i.e. no <form> is ever still open
     * when another <form> tag begins.
     */
    public function test_the_edit_page_never_nests_a_form_inside_another_form(): void
    {
        $owner = $this->makeOwner();
        $item = Item::create(['company_id' => $owner->company_id, 'name' => 'Standalone Item', 'item_type' => 'physical', 'unit_price' => 10]);

        $response = $this->actingAs($owner)->get(route('app.items.edit', $item));
        $response->assertOk();

        preg_match_all('/<\/?form\b[^>]*>/i', $response->getContent(), $matches);
        $this->assertNotEmpty($matches[0], 'Expected to find at least one <form> tag on the edit page.');

        $depth = 0;
        foreach ($matches[0] as $tag) {
            if (str_starts_with($tag, '</')) {
                $depth--;
            } else {
                $depth++;
                $this->assertLessThanOrEqual(1, $depth, "A <form> tag opened while another was still open: {$tag}");
            }
        }

        $this->assertSame(0, $depth, 'A <form> tag was left unclosed.');
    }
}
