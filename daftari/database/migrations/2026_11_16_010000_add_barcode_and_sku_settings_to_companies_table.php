<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * sku_prefix/sku_next_number follow the exact same running-sequence
 * pattern as invoice_prefix/next_invoice_number elsewhere on this table
 * — see ItemSettingsController::update() and ItemController::store() for
 * where sku_auto_generate actually assembles the two into a real SKU.
 * barcode_length controls ItemController::generateBarcode()'s digit
 * count instead of the hardcoded 12 it used before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('sku_prefix', 20)->nullable()->after('pos_auto_print_receipt');
            $table->boolean('sku_auto_generate')->default(false)->after('sku_prefix');
            $table->unsignedInteger('sku_next_number')->default(1)->after('sku_auto_generate');
            $table->unsignedTinyInteger('barcode_length')->default(12)->after('sku_next_number');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['sku_prefix', 'sku_auto_generate', 'sku_next_number', 'barcode_length']);
        });
    }
};
