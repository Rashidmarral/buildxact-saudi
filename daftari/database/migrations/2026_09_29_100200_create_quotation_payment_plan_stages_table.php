<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_payment_plan_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('description');
            // A share of the quotation's VAT-inclusive total, not the
            // subtotal — matches how a payment schedule is normally quoted
            // ("20% advance", "30% on completion of X") in these contracts.
            $table->decimal('percentage', 5, 2);
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->timestamp('invoiced_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'quotation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_payment_plan_stages');
    }
};
