<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A machine rented OUT to a third party (a Client, or an ad-hoc renter
 * with no Client record — mirrors ReceiptVoucher's manual/customer party
 * pattern). Revenue is recorded through the ordinary Invoice pipeline
 * (see invoices.machinery_asset_id) — this table only tracks the rental
 * period, terms, and status, not the money itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machinery_rental_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('machinery_asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('renter_name')->nullable();
            $table->string('renter_phone', 30)->nullable();
            $table->string('contract_number', 30);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('rate', 12, 2);
            $table->string('rate_type', 20); // daily, weekly, monthly
            $table->decimal('deposit_amount', 12, 2)->nullable();
            $table->boolean('operator_included')->default(false);
            $table->foreignId('operator_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('fuel_responsibility', 20)->default('owner'); // owner, renter
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
        Schema::dropIfExists('machinery_rental_contracts');
    }
};
