<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('rate', 5, 2);
            // standard = normal VAT (e.g. 15%), zero_rated = 0% but still a
            // taxable supply (exports, some medicines...), exempt = outside
            // the VAT system entirely (e.g. residential rent, some
            // financial services). Zero-rated and exempt both charge 0 SAR
            // of VAT but are legally different categories — ZATCA's e-invoice
            // XML tags them with different tax category codes (Z vs E).
            $table->string('type', 20)->default('standard');
            $table->boolean('is_active')->default(true);
            // Which rate pre-fills a new invoice/bill/quotation line — at
            // most one per company; enforced in application code the same
            // way Currency::makeDefault() enforces exactly one default
            // currency.
            $table->boolean('is_default')->default(false);
            // Null = effective immediately with no end date (the common
            // case). Set when a company needs to record that a rate only
            // applied from a certain date (e.g. Saudi Arabia's 2020 VAT
            // rate change from 5% to 15%), without losing history on
            // documents already issued under the old rate.
            $table->date('effective_date')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
    }
};
