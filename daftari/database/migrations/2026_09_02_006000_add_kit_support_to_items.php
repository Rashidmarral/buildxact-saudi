<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->boolean('is_kit')->default(false)->after('tracking_type');
        });

        Schema::create('item_kit_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kit_item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('component_item_id')->constrained('items')->cascadeOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->timestamps();

            $table->unique(['kit_item_id', 'component_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_kit_components');

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('is_kit');
        });
    }
};
