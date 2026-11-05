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
 * Audit finding MEDIUM-3: dispose() built its journal lines inline and
 * silently dropped the Fixed Assets leg entirely when that account mapping
 * was missing, instead of failing with a clear message the way every
 * postXxx() method does via requireAccounts().
 */
class FixedAssetDisposalTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): User
    {
        $company = Company::create(['name' => 'Asset Co.', 'slug' => 'asset-'.uniqid()]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    private function makeAsset(User $owner): FixedAsset
    {
        return FixedAsset::create([
            'company_id' => $owner->company_id, 'asset_code' => 'FA-001', 'name' => 'Delivery Van',
            'acquisition_date' => now()->subYear()->toDateString(), 'acquisition_cost' => 50000,
            'useful_life_years' => 5, 'accumulated_depreciation' => 10000, 'status' => 'active',
        ]);
    }

    public function test_disposal_is_refused_with_a_clear_error_when_the_fixed_assets_mapping_is_missing(): void
    {
        $owner = $this->makeOwner();
        $asset = $this->makeAsset($owner);

        // resolve() falls back to the account with the mapping's
        // default_code when no explicit AccountMapping row exists, so the
        // mapping is only really "missing" once that fallback account is
        // gone too.
        AccountMapping::withoutGlobalScope('company')->where('company_id', $owner->company_id)->where('key', 'FIXED_ASSETS_DEFAULT')->delete();
        Account::withoutGlobalScope('company')->where('company_id', $owner->company_id)->where('code', '1500')->delete();

        $response = $this->actingAs($owner)->post(route('app.fixed-assets.dispose', $asset), [
            'disposed_at' => now()->toDateString(), 'disposal_proceeds' => 30000,
        ]);

        $response->assertSessionHasErrors('disposal');
        $asset->refresh();
        $this->assertSame('active', $asset->status);
        $this->assertSame(0, JournalEntry::where('source_type', 'fixed_asset_disposal')->where('source_id', $asset->id)->count());
    }

    public function test_disposal_posts_correctly_when_mappings_are_configured(): void
    {
        $owner = $this->makeOwner();
        $asset = $this->makeAsset($owner);

        $response = $this->actingAs($owner)->post(route('app.fixed-assets.dispose', $asset), [
            'disposed_at' => now()->toDateString(), 'disposal_proceeds' => 30000,
        ]);

        $response->assertRedirect();
        $asset->refresh();
        $this->assertSame('disposed', $asset->status);

        $entry = JournalEntry::where('source_type', 'fixed_asset_disposal')->where('source_id', $asset->id)->first();
        $this->assertNotNull($entry);
        $this->assertEquals((float) $entry->lines()->sum('debit'), (float) $entry->lines()->sum('credit'));
    }
}
