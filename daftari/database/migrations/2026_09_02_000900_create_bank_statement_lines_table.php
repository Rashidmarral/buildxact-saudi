<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_statement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_reconciliation_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('description');
            $table->string('reference')->nullable();
            // Positive = money in (deposit), negative = money out
            // (withdrawal) — matches how the statement's own CSV export
            // reads, so no sign-flipping is needed on import.
            $table->decimal('amount', 14, 2);
            // 'receipt_voucher' | 'payment_voucher' | 'bank_transfer', with
            // matched_id pointing at that model's row — no FK constraint
            // since the target table varies.
            $table->string('matched_type', 20)->nullable();
            $table->unsignedBigInteger('matched_id')->nullable();
            $table->timestamps();

            $table->index(['matched_type', 'matched_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statement_lines');
    }
};
