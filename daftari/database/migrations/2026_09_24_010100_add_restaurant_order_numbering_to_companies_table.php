<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('restaurant_order_prefix', 10)->default('ORD')->after('next_pos_sale_number');
            $table->unsignedInteger('next_restaurant_order_number')->default(1)->after('restaurant_order_prefix');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['restaurant_order_prefix', 'next_restaurant_order_number']);
        });
    }
};
