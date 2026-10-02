<?php

use App\Models\Item;
use Illuminate\Database\Migrations\Migration;

/**
 * Bug report: a company (Zubaidi) still had a permanently-disabled unit
 * picker on invoice/quotation line items after the earlier fix — because
 * that fix (ItemController::validated(), plus the one-off
 * 2026_09_01_000600 migration) only covered items that existed at the
 * time, or were created through the item form. ZubaidiProductsSeeder
 * (~380 real catalog rows) and DemoSeeder's six starter items both write
 * Item rows directly and were seeded after that migration ran, so their
 * base_unit_id stayed null. Item now guarantees this at the model level
 * for every future write (see Item::booted()) — this migration is the
 * one-time catch-up for rows that predate that guarantee, covering every
 * company at once instead of one at a time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Item::whereNull('base_unit_id')->chunkById(200, function ($items) {
            foreach ($items as $item) {
                $unitId = Item::resolveDefaultBaseUnitId($item->company_id, $item->unit_code);

                if ($unitId) {
                    $item->update(['base_unit_id' => $unitId]);
                }
            }
        });
    }

    public function down(): void
    {
        // Deliberately irreversible — see 2026_09_01_000600_backfill_default_units_and_item_base_units.php.
    }
};
