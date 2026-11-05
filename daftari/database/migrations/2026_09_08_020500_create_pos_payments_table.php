<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pos_sale_id')->constrained()->cascadeOnDelete();
            $table->string('method', 20); // cash, card, other
            $table->decimal('amount', 14, 2);
            $table->string('reference')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'pos_sale_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_payments');
    }
};
