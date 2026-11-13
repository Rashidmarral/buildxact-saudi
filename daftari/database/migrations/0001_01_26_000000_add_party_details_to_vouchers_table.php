<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipt_vouchers', function (Blueprint $table) {
            $table->string('party_type', 10)->default('manual')->after('bank_account_id'); // manual, customer, supplier
            $table->foreignId('supplier_id')->nullable()->after('client_id')->constrained()->nullOnDelete();
            $table->foreignId('counter_account_id')->nullable()->after('supplier_id')->constrained('accounts')->nullOnDelete();
            $table->string('party_name_ar')->nullable()->after('payer_name');
            $table->string('party_vat_number', 32)->nullable()->after('party_name_ar');
            $table->string('party_phone', 30)->nullable()->after('party_vat_number');
            $table->string('party_email')->nullable()->after('party_phone');
            $table->string('party_address')->nullable()->after('party_email');
        });

        Schema::table('payment_vouchers', function (Blueprint $table) {
            $table->string('party_type', 10)->default('manual')->after('bank_account_id'); // manual, customer, supplier
            $table->foreignId('client_id')->nullable()->after('expense_id')->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->after('client_id')->constrained()->nullOnDelete();
            $table->foreignId('counter_account_id')->nullable()->after('bill_payment_id')->constrained('accounts')->nullOnDelete();
            $table->string('party_name_ar')->nullable()->after('payee_name');
            $table->string('party_vat_number', 32)->nullable()->after('party_name_ar');
            $table->string('party_phone', 30)->nullable()->after('party_vat_number');
            $table->string('party_email')->nullable()->after('party_phone');
            $table->string('party_address')->nullable()->after('party_email');
        });
    }

    public function down(): void
    {
        Schema::table('payment_vouchers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('counter_account_id');
            $table->dropConstrainedForeignId('supplier_id');
            $table->dropConstrainedForeignId('client_id');
            $table->dropColumn(['party_type', 'party_name_ar', 'party_vat_number', 'party_phone', 'party_email', 'party_address']);
        });

        Schema::table('receipt_vouchers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('counter_account_id');
            $table->dropConstrainedForeignId('supplier_id');
            $table->dropColumn(['party_type', 'party_name_ar', 'party_vat_number', 'party_phone', 'party_email', 'party_address']);
        });
    }
};
