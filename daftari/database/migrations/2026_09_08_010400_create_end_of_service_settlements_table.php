<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('end_of_service_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('hire_date');
            $table->date('termination_date');
            // resignation | termination | end_of_contract | retirement | death | disability
            $table->string('reason', 20);
            $table->decimal('years_of_service', 6, 2);
            $table->decimal('last_basic_salary', 14, 2);
            $table->decimal('gratuity_days', 8, 2);
            $table->decimal('gratuity_amount', 14, 2);
            // 0, 1/3, or 1 — the resignation-tier multiplier applied (see
            // EndOfServiceCalculator); always 1 for non-resignation reasons.
            $table->decimal('entitlement_fraction', 4, 3)->default(1);
            $table->string('status', 20)->default('calculated'); // calculated, paid
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('end_of_service_settlements');
    }
};
