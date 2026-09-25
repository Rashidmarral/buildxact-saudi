<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            // annual is the only type that draws down the accrued balance
            // (LeaveAccrualCalculator) — sick/unpaid are recorded for HR's
            // own record-keeping but don't touch it.
            $table->enum('type', ['annual', 'sick', 'unpaid']);
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('days', 6, 2);
            $table->string('status', 20)->default('pending'); // pending, approved, rejected
            $table->string('reason', 255)->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
