<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('barcode')->nullable()->after('sku');
            $table->string('category')->nullable()->after('barcode');
            $table->date('expiry_date')->nullable()->after('category');
            $table->string('item_type', 20)->default('service')->after('expiry_date');
            $table->decimal('purchase_price', 12, 2)->nullable()->after('unit_price');
            $table->string('unit_code', 10)->default('PCE')->after('unit');
            $table->string('image_path')->nullable()->after('unit_code');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['barcode', 'category', 'expiry_date', 'item_type', 'purchase_price', 'unit_code', 'image_path']);
        });
    }
};
