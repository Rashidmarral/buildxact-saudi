<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit finding PAYROLL-01: marking a payroll run "paid" only flipped its
 * status — it never settled SALARIES_PAYABLE (credited at approval,
 * postPayrollRun()) against the bank/cash account salaries were actually
 * paid from, the same way an Expense or Bill payment already settles
 * ACCOUNTS_PAYABLE. Records which account funded the payment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->foreignId('bank_account_id')->nullable()->after('pay_date')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_account_id');
        });
    }
};
