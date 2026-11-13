<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Links a gateway-originated payment back to the transaction
            // that produced it, so the detail page can show the real
            // gateway response (raw_response/raw_webhook) instead of only
            // Daftari's own summary fields. Null for bank-transfer/manual
            // payments, which never go through PaymentCheckoutService.
            $table->foreignId('payment_transaction_id')->nullable()->after('method')->constrained()->nullOnDelete();

            // Partial-refund tracking — status alone can't express "SAR 30
            // of a SAR 100 payment was refunded".
            $table->decimal('refunded_amount', 10, 2)->nullable()->after('paid_at');
            $table->string('refund_reason', 255)->nullable()->after('refunded_amount');
            $table->timestamp('refunded_at')->nullable()->after('refund_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_transaction_id');
            $table->dropColumn(['refunded_amount', 'refund_reason', 'refunded_at']);
        });
    }
};
