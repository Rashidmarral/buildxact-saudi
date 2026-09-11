<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('employee_prefix', 10)->default('EMP')->after('next_project_number');
            $table->unsignedInteger('next_employee_number')->default(1)->after('employee_prefix');
            $table->string('payroll_run_prefix', 10)->default('PR-RUN')->after('next_employee_number');
            $table->unsignedInteger('next_payroll_run_number')->default(1)->after('payroll_run_prefix');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['employee_prefix', 'next_employee_number', 'payroll_run_prefix', 'next_payroll_run_number']);
        });
    }
};
