<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_account_id')->constrained();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_payment_id')->nullable()->constrained('invoice_payments')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('voucher_number');
            $table->date('date');
            $table->string('payer_name');
            $table->decimal('amount', 14, 2);
            $table->string('method', 20)->default('cash'); // cash, bank_transfer, card, cheque
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('issued'); // issued, void
            $table->timestamps();

            $table->unique(['company_id', 'voucher_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_vouchers');
    }
};
