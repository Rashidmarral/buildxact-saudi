<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            // How many of this alternate unit equal one base unit of the
            // item, e.g. item base unit is "Ton", alt unit "Bag" with
            // conversion_factor 40 means 1 Ton = 40 Bags.
            $table->decimal('conversion_factor', 12, 4)->default(1);
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->timestamps();

            $table->unique(['item_id', 'unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_units');
    }
};
