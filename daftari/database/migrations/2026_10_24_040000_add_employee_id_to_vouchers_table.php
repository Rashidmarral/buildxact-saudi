<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit finding CASH-02: an employee cash advance could only be recorded
 * as party_type='manual' free text — no link to the Employee model, so
 * there was no way to answer "which employees currently owe us money."
 * Adds a real 'employee' party type + employee_id, alongside the
 * existing manual/customer/supplier options.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_vouchers', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable()->after('supplier_id')->constrained()->nullOnDelete();
        });

        Schema::table('receipt_vouchers', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable()->after('supplier_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payment_vouchers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employee_id');
        });

        Schema::table('receipt_vouchers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employee_id');
        });
    }
};
