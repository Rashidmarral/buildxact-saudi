<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Security audit finding M-19: sku had no uniqueness constraint at all,
 * not even per company. Before adding the index, null out sku on every
 * row but the oldest for any (company_id, sku) pair that already has a
 * duplicate — otherwise the index creation itself would fail outright on
 * any company that already has one.
 */
return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('items')
            ->select('company_id', 'sku')
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->groupBy('company_id', 'sku')
            ->havingRaw('count(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            $ids = DB::table('items')
                ->where('company_id', $duplicate->company_id)
                ->where('sku', $duplicate->sku)
                ->orderBy('id')
                ->pluck('id')
                ->all();

            // Keep the oldest row's sku, null out every later duplicate —
            // the item itself is untouched, just no longer double-booked.
            DB::table('items')->whereIn('id', array_slice($ids, 1))->update(['sku' => null]);
        }

        Schema::table('items', function (Blueprint $table) {
            $table->unique(['company_id', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'sku']);
        });
    }
};
