<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "add more... Card/POS Settlement workflow" — a card/POS sale posts
 * its gross amount into POS_CARD_CLEARING immediately (see
 * LedgerPostingService::postPosSale()), since the money isn't really
 * in the bank yet. This table records the processor actually settling:
 * the bank fee (MDR) + VAT on that fee, netted down to what was really
 * deposited, clearing that balance out of POS_CARD_CLEARING (or
 * whichever account the settled payment mode is mapped to).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_mode_id')->nullable()->constrained('payment_modes')->nullOnDelete();
            $table->foreignId('bank_account_id')->constrained()->cascadeOnDelete();
            $table->date('settlement_date');
            $table->decimal('gross_amount', 14, 2);
            $table->decimal('fee_amount', 14, 2)->default(0);
            $table->decimal('fee_vat_amount', 14, 2)->default(0);
            $table->decimal('net_amount', 14, 2);
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('posted');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'settlement_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_settlements');
    }
};
