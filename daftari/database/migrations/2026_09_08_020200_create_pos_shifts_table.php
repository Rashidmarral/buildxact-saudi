<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('register_id')->constrained('pos_registers')->cascadeOnDelete();
            $table->foreignId('opened_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('opened_at');
            $table->decimal('opening_cash', 14, 2)->default(0);
            $table->string('status', 20)->default('open'); // open, closed
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            // Cash actually counted in the drawer at close time, vs what
            // opening_cash + cash sales during the shift implies should be
            // there (expected_cash) — the gap is cash_difference, the
            // Z-report's whole point.
            $table->decimal('counted_cash', 14, 2)->nullable();
            $table->decimal('expected_cash', 14, 2)->nullable();
            $table->decimal('cash_difference', 14, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'register_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_shifts');
    }
};
