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
        Schema::create('payment_gateway_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->foreignId('payment_gateway_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_transaction_id')->nullable()->constrained()->nullOnDelete();
            // sha256(provider + raw body/query) — the same delivery retried
            // by the gateway (or replayed by an attacker who captured a
            // valid request) fingerprints identically, so a second hit is
            // detected as a duplicate before anything is re-processed.
            $table->string('fingerprint', 64);
            $table->string('status', 20)->default('received'); // received | verified | rejected | duplicate | processed
            $table->json('payload')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['provider', 'fingerprint']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_webhook_events');
    }
};
