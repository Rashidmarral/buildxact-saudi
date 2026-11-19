<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fulfillment tracking for one purchase-intent request against a
 * SetupPackage. The sales conversation itself (notes, follow-ups, who's
 * assigned) lives on the linked Lead — see Lead::SOURCES = 'setup_package'
 * and Admin\LeadController::show(), which surfaces this row alongside
 * the Lead's own timeline rather than duplicating a second CRM.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setup_package_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('setup_package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('requested'); // requested, in_progress, completed, cancelled
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setup_package_requests');
    }
};
