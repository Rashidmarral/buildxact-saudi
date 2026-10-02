<?php

use App\Models\Company;
use App\Models\Item;
use App\Models\Unit;
use Illuminate\Database\Migrations\Migration;

/**
 * Units didn't exist yet when earlier companies signed up, so their items
 * never got a base_unit_id — and the unit picker on invoice/quotation/
 * bill/purchase-order forms only enables once the selected item has at
 * least one unit, so it stays permanently disabled for every one of their
 * items. Seeds Unit::seedDefaults() for any company that still has zero
 * units (never touches one that already set up its own), then backfills
 * base_unit_id on items that don't have one — matching the item's legacy
 * unit_code text against a seeded unit's code where possible, falling
 * back to Piece (PCE) so every item ends up usable on those forms.
 */
return new class extends Migration
{
    public function up(): void
    {
        Company::query()->pluck('id')->each(function (int $companyId) {
            if (! Unit::where('company_id', $companyId)->exists()) {
                Unit::seedDefaults($companyId);
            }
        });

        Item::whereNull('base_unit_id')->chunkById(200, function ($items) {
            foreach ($items as $item) {
                $unit = null;

                if ($item->unit_code) {
                    $unit = Unit::where('company_id', $item->company_id)
                        ->whereRaw('UPPER(code) = ?', [strtoupper($item->unit_code)])
                        ->first();
                }

                $unit ??= Unit::where('company_id', $item->company_id)->where('code', 'PCE')->first();

                if ($unit) {
                    $item->update(['base_unit_id' => $unit->id]);
                }
            }
        });
    }

    public function down(): void
    {
        // Deliberately irreversible — undoing this would require knowing
        // which units/base_unit_id values existed before the backfill,
        // which isn't recoverable once real invoices reference them.
    }
};
