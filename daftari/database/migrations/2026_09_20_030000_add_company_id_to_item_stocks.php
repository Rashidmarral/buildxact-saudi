<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Security audit finding M-06: item_stocks had no company_id of its own —
 * safe today only because every caller pre-scopes item_id/warehouse_id
 * through already company-scoped Item/Warehouse lookups first, so this is
 * defense-in-depth against a future direct query, not a fix for an active
 * leak. Left nullable rather than a hard NOT NULL constraint: this app has
 * no schema-modification tooling (doctrine/dbal) installed to alter an
 * existing column's nullability portably across MySQL and SQLite, and
 * every actual write path already runs authenticated (BelongsToCompany's
 * own creating() listener populates it), so the column is always filled
 * in practice regardless.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_stocks', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        DB::statement('
            update item_stocks
            set company_id = (select items.company_id from items where items.id = item_stocks.item_id)
        ');

        Schema::table('item_stocks', function (Blueprint $table) {
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::table('item_stocks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
        });
    }
};
