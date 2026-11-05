<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repair_job_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('repair_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->string('description', 255);
            $table->decimal('quantity', 10, 3)->default(1);
            $table->decimal('unit_price', 12, 4)->default(0);
            $table->decimal('vat_rate', 6, 2)->default(0);
            // Credit given for a customer's traded-in old part — nets off
            // this line's price at checkout (see RepairJobService::checkout(),
            // which maps it straight onto PosSaleService's existing
            // per-line discount_amount rather than inventing a parallel
            // mechanism).
            $table->decimal('core_exchange_credit', 12, 2)->default(0);
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['company_id', 'repair_job_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_job_items');
    }
};
