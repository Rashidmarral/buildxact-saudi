<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->string('slug')->unique();
            $table->string('vat_number', 32)->nullable();
            $table->string('cr_number', 32)->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('invoice_prefix', 10)->default('INV');
            $table->unsignedInteger('next_invoice_number')->default(1);
            $table->string('quotation_prefix', 10)->default('QTN');
            $table->unsignedInteger('next_quotation_number')->default(1);
            $table->string('proforma_prefix', 10)->default('PRO');
            $table->unsignedInteger('next_proforma_number')->default(1);
            $table->string('receipt_prefix', 10)->default('RV');
            $table->unsignedInteger('next_receipt_number')->default(1);
            $table->string('payment_voucher_prefix', 10)->default('PV');
            $table->unsignedInteger('next_payment_voucher_number')->default(1);
            $table->string('currency', 3)->default('SAR');
            $table->string('locale', 2)->default('en');
            $table->string('status', 20)->default('active'); // active, suspended
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
