<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();
            // NULL company_id = Daftari's own platform-level gateway, used
            // to collect SUBSCRIPTION payments from companies. A row with a
            // company_id belongs to that company and is used to collect
            // payments on ITS OWN invoices from its clients — the money
            // goes to the company, not to Daftari, so each company needs
            // its own merchant credentials, never Daftari's.
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('provider'); // moyasar | hyperpay | tap | paytabs
            $table->string('mode')->default('test'); // test | live
            $table->boolean('is_enabled')->default(false);
            // Structured, provider-specific credentials (api keys, entity
            // ids, webhook secrets) — never plaintext at rest.
            $table->text('credentials')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('payment_gateway_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            // Polymorphic: Subscription (platform gateway) or Invoice
            // (company gateway) is the thing this payment is settling.
            $table->string('payable_type');
            $table->unsignedBigInteger('payable_id');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('SAR');
            $table->string('status')->default('pending'); // pending | paid | failed | cancelled
            $table->string('provider_reference')->nullable();
            $table->text('checkout_url')->nullable();
            $table->json('raw_response')->nullable();
            $table->json('raw_webhook')->nullable();
            $table->timestamps();

            $table->index(['payable_type', 'payable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('payment_gateways');
    }
};
