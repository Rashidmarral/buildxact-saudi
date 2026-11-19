<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_timesheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('task_id')->nullable()->constrained('project_tasks')->nullOnDelete();
            $table->date('work_date');
            $table->string('description', 255)->nullable();
            $table->decimal('hours', 5, 2);
            $table->decimal('overtime_hours', 5, 2)->default(0);
            $table->boolean('billable')->default(true);
            $table->decimal('bill_rate', 10, 2)->nullable();
            $table->decimal('cost_rate', 10, 2)->nullable();
            $table->string('status', 20)->default('draft');
            $table->foreignId('project_cost_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_timesheets');
    }
};
