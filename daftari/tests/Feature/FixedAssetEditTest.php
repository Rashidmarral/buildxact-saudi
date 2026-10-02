<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Company;
use App\Models\FixedAsset;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "please make it or update the form to support edits later through the
 * app" — a machine was registered with a placeholder acquisition cost
 * (real price not known yet), and the Fixed Assets module had no edit
 * screen at all. This covers the new edit/update flow: correcting the
 * cost rebuilds the acquisition journal entry instead of leaving the old
 * (wrong) one and a new one both on the books, and the correction is
 * refused once something downstream (depreciation, disposal) already
 * relied on the original numbers.
 */
class FixedAssetEditTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): User
    {
        $company = Company::create(['name' => 'Asset Co.', 'slug' => 'asset-'.uniqid()]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    public function test_editing_a_fresh_asset_rebuilds_the_acquisition_journal_with_the_corrected_cost(): void
    {
        $owner = $this->makeOwner();

        $create = $this->actingAs($owner)->post(route('app.fixed-assets.store'), [
            'name' => 'Road Roller (Compactor)', 'category' => 'Road Roller',
            'acquisition_date' => '2026-08-24', 'acquisition_cost' => 1.00,
            'useful_life_years' => 10,
        ]);
        $asset = FixedAsset::first();
        $create->assertRedirect(route('app.fixed-assets.show', $asset));

        $response = $this->actingAs($owner)->put(route('app.fixed-assets.update', $asset), [
            'name' => 'Road Roller (Compactor)', 'category' => 'Road Roller',
            'acquisition_date' => '2026-08-24', 'acquisition_cost' => 185000.00,
            'useful_life_years' => 10,
        ]);

        $response->assertRedirect(route('app.fixed-assets.show', $asset));
        $asset->refresh();
        $this->assertEquals(185000.00, (float) $asset->acquisition_cost);

        $entries = JournalEntry::where('source_type', 'fixed_asset')->where('source_id', $asset->id)->get();
        $this->assertCount(1, $entries, 'the old placeholder journal entry should be replaced, not kept alongside the new one');
        $this->assertEquals(185000.00, (float) $entries->first()->lines()->sum('debit'));
    }

    public function test_editing_is_refused_once_depreciation_has_been_posted(): void
    {
        $owner = $this->makeOwner();
        $asset = FixedAsset::create([
            'company_id' => $owner->company_id, 'asset_code' => 'FA-001', 'name' => 'Delivery Van',
            'acquisition_date' => now()->subYear()->toDateString(), 'acquisition_cost' => 50000,
            'useful_life_years' => 5, 'accumulated_depreciation' => 1000, 'status' => 'active',
        ]);

        $response = $this->actingAs($owner)->put(route('app.fixed-assets.update', $asset), [
            'name' => 'Delivery Van', 'acquisition_date' => now()->subYear()->toDateString(),
            'acquisition_cost' => 60000, 'useful_life_years' => 5,
        ]);

        $response->assertSessionHasErrors('asset');
        $this->assertEquals(50000, (float) $asset->refresh()->acquisition_cost);
    }

    public function test_editing_is_refused_once_disposed(): void
    {
        $owner = $this->makeOwner();
        $asset = FixedAsset::create([
            'company_id' => $owner->company_id, 'asset_code' => 'FA-002', 'name' => 'Old Compressor',
            'acquisition_date' => now()->subYears(2)->toDateString(), 'acquisition_cost' => 20000,
            'useful_life_years' => 5, 'status' => 'disposed', 'disposed_at' => now()->toDateString(),
        ]);

        $response = $this->actingAs($owner)->put(route('app.fixed-assets.update', $asset), [
            'name' => 'Old Compressor', 'acquisition_date' => now()->subYears(2)->toDateString(),
            'acquisition_cost' => 25000, 'useful_life_years' => 5,
        ]);

        $response->assertSessionHasErrors('asset');
    }

    public function test_edit_link_is_hidden_once_depreciation_has_posted(): void
    {
        $owner = $this->makeOwner();
        $asset = FixedAsset::create([
            'company_id' => $owner->company_id, 'asset_code' => 'FA-003', 'name' => 'Generator',
            'acquisition_date' => now()->subYear()->toDateString(), 'acquisition_cost' => 15000,
            'useful_life_years' => 5, 'accumulated_depreciation' => 250, 'status' => 'active',
        ]);

        $response = $this->actingAs($owner)->get(route('app.fixed-assets.show', $asset));

        $response->assertOk();
        $response->assertDontSee(route('app.fixed-assets.edit', $asset), false);
    }
}
