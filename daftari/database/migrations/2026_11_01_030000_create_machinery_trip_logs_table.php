<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who had a machine/vehicle out, where, and why — a day-by-day usage
 * record for a company running its own equipment on its own sites,
 * not a GPS/odometer trip tracker for a hauling fleet (no distance or
 * time-of-day fields; add them later if a real need for them shows up).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machinery_trip_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('machinery_asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operator_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->date('trip_date');
            $table->string('purpose', 255);
            $table->string('from_location', 255)->nullable();
            $table->string('to_location', 255)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'machinery_asset_id']);
            $table->index(['company_id', 'trip_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machinery_trip_logs');
    }
};
