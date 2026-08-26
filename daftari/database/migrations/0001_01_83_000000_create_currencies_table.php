<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-wide currency catalog (not company-scoped — the set of
 * available currencies, and how each one formats, is the same fact for
 * every company; a company just picks one via its own `currency` column).
 * Super-admin managed, matching Plans/Payment Gateways.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique();
            $table->string('name');
            $table->string('symbol', 10);
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $table->string('decimal_separator', 1)->default('.');
            $table->string('thousands_separator', 1)->default(',');
            $table->string('symbol_position', 10)->default('before');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
