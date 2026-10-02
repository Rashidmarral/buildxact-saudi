<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The reverse of machinery_rental_contracts: the company HIRES equipment
 * IN from an external supplier (it does not own this equipment, so there
 * is no machinery_asset_id) — e.g. hiring an MC1 spray tanker + driver
 * from another contractor, billed per square metre of completed work.
 * Real costs (fuel the company pays directly, the supplier's billed
 * amount) are recorded through the ordinary Expense pipeline (see
 * expenses.machinery_hire_in_contract_id) — this table only tracks the
 * agreement's terms and status, not the money itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machinery_hire_in_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('supplier_name')->nullable();
            $table->string('supplier_cr_number', 30)->nullable();
            $table->string('supplier_phone', 30)->nullable();
            $table->string('contract_number', 30);
            $table->string('equipment_description');
            $table->string('equipment_category')->nullable();
            $table->string('plate_or_chassis_number', 60)->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('rate', 12, 2);
            $table->string('rate_type', 20); // daily, weekly, monthly, per_unit
            $table->string('rate_unit_label', 60)->nullable(); // e.g. "sq. meter" — only when rate_type = per_unit
            $table->boolean('operator_included')->default(true);
            $table->string('operator_name')->nullable(); // the SUPPLIER's own driver — free text, not an Employee
            $table->string('fuel_responsibility', 20)->default('supplier'); // supplier, company
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->text('delivery_condition_notes')->nullable();
            $table->text('return_condition_notes')->nullable();
            $table->string('status', 20)->default('active'); // active, completed, terminated
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'contract_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machinery_hire_in_contracts');
    }
};
