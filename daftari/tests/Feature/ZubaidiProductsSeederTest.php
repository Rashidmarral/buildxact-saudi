<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Item;
use Database\Seeders\ZubaidiProductsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression for a real production failure: `php artisan db:seed
 * --class=ZubaidiProductsSeeder` against MySQL threw "Data too long for
 * column 'name'" (SQLSTATE 22001) because two rows in the source export
 * (SKUs 234, 235) carry a 300+ character work-order writeup in the Name
 * column — past items.name's varchar(255) limit. SQLite (this test's own
 * database) never enforces that limit, which is exactly why the bug
 * shipped unnoticed until it hit a real MySQL install; these assertions
 * check the actual stored values instead of relying on the DB to reject
 * an oversized string.
 */
class ZubaidiProductsSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedZubaidiCompany(): Company
    {
        return Company::create([
            'name' => 'Zubaidi Test Co.',
            'slug' => 'zubaidi-test-'.uniqid(),
            'vat_number' => '310464560600003',
        ]);
    }

    public function test_every_seeded_item_name_fits_the_varchar_255_column(): void
    {
        $this->seedZubaidiCompany();

        (new ZubaidiProductsSeeder)->run();

        $tooLong = Item::query()->get()->first(fn (Item $item) => mb_strlen($item->name) > 255);

        $this->assertNull($tooLong, 'An item name exceeds the items.name varchar(255) column limit.');
    }

    public function test_a_name_too_long_to_fit_is_truncated_with_the_full_text_kept_as_description(): void
    {
        $this->seedZubaidiCompany();

        (new ZubaidiProductsSeeder)->run();

        $item = Item::where('sku', '234')->first();

        $this->assertNotNull($item);
        $this->assertLessThanOrEqual(255, mb_strlen($item->name));
        $this->assertStringContainsString('as per Purchase Order Mentioned Below', $item->description);
        $this->assertStringStartsWith('All Sliding Big doors', $item->name);
    }

    public function test_reseeding_does_not_duplicate_the_overflowed_description(): void
    {
        $this->seedZubaidiCompany();

        (new ZubaidiProductsSeeder)->run();
        (new ZubaidiProductsSeeder)->run();

        $item = Item::where('sku', '234')->first();

        $this->assertSame(1, Item::where('sku', '234')->count());
        $this->assertSame(300, mb_strlen($item->description));
    }

    /**
     * Bug report: after fixing the disabled-unit-picker bug once (for
     * Dynamic Core), the same symptom reappeared for Zubaidi — this seeder
     * writes Item rows directly via Item::updateOrCreate(), bypassing
     * ItemController entirely, and the real Zubaidi company (seeded by
     * RealCompanySeeder, mirrored by seedZubaidiCompany() above) has no
     * Units at all until something creates them. Item::booted() now
     * guarantees every item gets a real base_unit_id regardless of write
     * path — this asserts it holds for the actual seeder and company
     * shape behind the original report, not just a synthetic case.
     */
    public function test_every_seeded_item_gets_a_usable_base_unit_so_the_line_item_unit_picker_is_never_disabled(): void
    {
        $company = $this->seedZubaidiCompany();
        $this->assertSame(0, \App\Models\Unit::where('company_id', $company->id)->count());

        (new ZubaidiProductsSeeder)->run();

        $withoutBaseUnit = Item::where('company_id', $company->id)->whereNull('base_unit_id')->count();
        $this->assertSame(0, $withoutBaseUnit, 'every Zubaidi catalog item must have a base_unit_id or its line-item unit picker stays permanently disabled');

        $sample = Item::where('company_id', $company->id)->where('sku', '3')->first();
        $this->assertNotNull($sample->baseUnit, 'baseUnit relation must resolve to a real Unit row, not just a non-null id');
    }
}
