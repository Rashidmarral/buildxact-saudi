<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repair_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('job_number', 30);
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_description', 255); // e.g. "iPhone 13 Pro" or "Toyota Camry 2019"
            $table->string('brand', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('year', 10)->nullable();
            $table->string('serial_or_plate', 60)->nullable();
            $table->text('issue_description')->nullable();
            $table->text('diagnosis_notes')->nullable();
            $table->string('status', 20)->default('received'); // received, diagnosing, awaiting_approval, in_repair, ready, collected, cancelled
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('pos_sale_id')->nullable()->constrained('pos_sales')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancel_reason', 255)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'job_number']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_jobs');
    }
};
