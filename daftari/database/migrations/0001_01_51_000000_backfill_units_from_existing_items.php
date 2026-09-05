<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Every existing item still only has its old flat unit/unit_code
     * strings — nothing points at the new units table yet. This creates a
     * matching Unit row per company for each distinct unit/unit_code
     * combination already in use and points base_unit_id at it, so no
     * existing item is left without a base unit once the column is relied
     * on by the app.
     */
    public function up(): void
    {
        $items = DB::table('items')->select('id', 'company_id', 'unit', 'unit_code')->get();

        $unitIdsByKey = [];

        foreach ($items as $item) {
            $unitName = trim((string) $item->unit) !== '' ? $item->unit : 'Unit';
            $unitCode = trim((string) $item->unit_code) !== '' ? $item->unit_code : 'PCE';
            $key = $item->company_id.'|'.$unitName.'|'.$unitCode;

            if (! isset($unitIdsByKey[$key])) {
                $existing = DB::table('units')
                    ->where('company_id', $item->company_id)
                    ->where('name', $unitName)
                    ->where('code', $unitCode)
                    ->first();

                if ($existing) {
                    $unitIdsByKey[$key] = $existing->id;
                } else {
                    $unitIdsByKey[$key] = DB::table('units')->insertGetId([
                        'company_id' => $item->company_id,
                        'name' => $unitName,
                        'name_ar' => null,
                        'symbol' => null,
                        'code' => $unitCode,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::table('items')->where('id', $item->id)->update(['base_unit_id' => $unitIdsByKey[$key]]);
        }
    }

    public function down(): void
    {
        // Intentionally left as a no-op: reverting the schema (previous
        // migrations' down()) already drops base_unit_id/units, which is
        // enough to undo this backfill's effects.
    }
};
