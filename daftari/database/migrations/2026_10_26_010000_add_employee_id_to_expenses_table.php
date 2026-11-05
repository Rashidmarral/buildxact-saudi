<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "i have register an expense like paid to khalid 10000 then i have
 * record expenses for that 10000 cash of khalid account" — with no way
 * to tag an Expense as "paid out of this employee's cash custody" (only
 * a real bank_account_id, or paid_by_owner), the operator was recording
 * the cash handed to the custodian AND the real purchases it later paid
 * for as two separate, indistinguishable Expense rows — double-counting
 * both the expense list and the GL. employee_id lets an expense settle
 * against the employee's own advance balance instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable()->after('bank_account_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employee_id');
        });
    }
};
