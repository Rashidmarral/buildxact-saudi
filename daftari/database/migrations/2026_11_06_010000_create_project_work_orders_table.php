<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hamly's Work Orders: a dispatchable job distinct from a Purchase
 * Order or Quotation — kind (preventive/breakdown/installation/
 * inspection/other), priority, an optional machinery asset it's against
 * (this business's equivalent of Hamly's "customer asset" — the asset
 * worked on is one of the company's own machines, not a client's),
 * a technician to assign, and a schedule the Dispatch Board reads/writes.
 * status auto-advances open -> assigned the moment a technician is set
 * (see WorkOrderController).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_work_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('work_order_number', 30);
            $table->string('kind', 20)->default('other');
            $table->string('priority', 10)->default('medium');
            $table->string('title', 255);
            $table->foreignId('machinery_asset_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description')->nullable();
            $table->date('raised_date');
            $table->dateTime('scheduled_at')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->foreignId('technician_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('site_address', 255)->nullable();
            $table->boolean('billable')->default(false);
            $table->decimal('bill_amount', 14, 2)->nullable();
            $table->string('status', 20)->default('open');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'scheduled_at']);
            $table->index(['project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_work_orders');
    }
};
