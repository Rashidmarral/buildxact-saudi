<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Machinery & Equipment module: the asset registry. A machine's cost,
 * depreciation, and eventual disposal live on the linked FixedAsset row
 * (see FixedAssetLifecycleService) — this table only carries the
 * machinery-specific facts (make/model/serial, rental defaults, operator,
 * registration/insurance expiry) plus a status a rental/deployment/sale
 * action can move between.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machinery_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fixed_asset_id')->nullable()->constrained()->nullOnDelete();
            $table->string('asset_code', 30);
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->string('category', 100)->nullable();
            $table->string('make')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('plate_or_chassis_number')->nullable();
            $table->unsignedSmallInteger('year_of_manufacture')->nullable();
            $table->string('status', 20)->default('available'); // available, rented_out, deployed, maintenance, sold, retired
            $table->decimal('default_rental_rate', 12, 2)->nullable();
            $table->string('rental_rate_type', 20)->nullable(); // daily, weekly, monthly
            $table->foreignId('operator_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->date('registration_expiry_date')->nullable();
            $table->date('insurance_expiry_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'asset_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machinery_assets');
    }
};
