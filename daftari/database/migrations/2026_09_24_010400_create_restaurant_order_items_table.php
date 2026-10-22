<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('restaurant_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->string('description', 255);
            $table->decimal('quantity', 10, 3)->default(1);
            $table->decimal('unit_price', 12, 4)->default(0);
            $table->decimal('vat_rate', 6, 2)->default(0);
            $table->string('notes', 255)->nullable();
            $table->string('kitchen_status', 15)->default('pending'); // pending, preparing, ready, served
            $table->timestamps();

            $table->index(['company_id', 'restaurant_order_id']);
            $table->index(['company_id', 'kitchen_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_order_items');
    }
};
