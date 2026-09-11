<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('run_number', 30);
            $table->unsignedTinyInteger('period_month');
            $table->unsignedSmallInteger('period_year');
            $table->date('pay_date');
            $table->string('status', 20)->default('draft'); // draft, approved, paid, cancelled
            $table->decimal('total_gross', 14, 2)->default(0);
            $table->decimal('total_gosi_employee', 14, 2)->default(0);
            $table->decimal('total_gosi_employer', 14, 2)->default(0);
            $table->decimal('total_other_deductions', 14, 2)->default(0);
            $table->decimal('total_net', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            // Not a unique constraint on (company_id, period_month,
            // period_year): a cancelled run must be able to coexist with
            // its replacement for the same period. PayrollRunController
            // enforces "no draft/approved/paid run already exists for
            // this period" at the application level instead.
            $table->index(['company_id', 'period_year', 'period_month']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_runs');
    }
};
