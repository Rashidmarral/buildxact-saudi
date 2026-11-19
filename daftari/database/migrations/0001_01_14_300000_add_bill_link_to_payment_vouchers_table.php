<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_vouchers', function (Blueprint $table) {
            $table->foreignId('bill_id')->nullable()->after('expense_id')->constrained()->nullOnDelete();
            $table->foreignId('bill_payment_id')->nullable()->after('bill_id')->constrained('bill_payments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payment_vouchers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bill_payment_id');
            $table->dropConstrainedForeignId('bill_id');
        });
    }
};
