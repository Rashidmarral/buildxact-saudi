<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('employee_number', 20);
            $table->string('full_name');
            $table->string('full_name_ar')->nullable();
            $table->string('national_id', 20)->nullable();
            $table->string('nationality')->nullable();
            // Drives which GOSI branches apply (see GosiCalculator) — Saudi
            // (and GCC-national, treated the same by GOSI) employees are
            // covered by the Annuities/Pensions branch, non-Saudi ones by
            // Occupational Hazards only.
            $table->boolean('is_saudi')->default(true);
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 10)->nullable();
            $table->string('mobile', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('job_title')->nullable();
            $table->string('department')->nullable();
            $table->date('hire_date');
            $table->date('termination_date')->nullable();
            $table->string('status', 20)->default('active'); // active, terminated, suspended
            $table->string('iban', 34)->nullable();
            $table->string('bank_name')->nullable();
            $table->string('gosi_subscription_number', 20)->nullable();
            $table->decimal('basic_salary', 14, 2)->default(0);
            $table->decimal('housing_allowance', 14, 2)->default(0);
            $table->decimal('transport_allowance', 14, 2)->default(0);
            $table->decimal('other_allowance', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'employee_number']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'full_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
