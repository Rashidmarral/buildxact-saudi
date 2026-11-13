<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional bill-of-quantities breakdown behind a subcontract's lump-sum
 * contract_value — mirrors the "Description / Quantity / Unit / Rate /
 * Amount" table the user's own reference payment-calculation sheet used
 * (e.g. 11,200 m² Subbase & Base Course @ 20.00). Purely documentation:
 * contract_value stays the authoritative figure the schedule and
 * retention are calculated against, recalculated from these lines only
 * when the user explicitly asks the form to total them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subcontract_boq_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subcontract_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->decimal('quantity', 14, 3)->nullable();
            $table->string('unit', 30)->nullable();
            $table->decimal('rate', 14, 2)->nullable();
            $table->decimal('amount', 14, 2);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subcontract_boq_items');
    }
};
