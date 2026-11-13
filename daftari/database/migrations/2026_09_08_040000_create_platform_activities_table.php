<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Commercial Registration (CR) activity codes the platform operator
 * is actually registered under — shown on the admin Compliance Readiness
 * checklist so a super admin can see, at a glance, whether any registered
 * activity plausibly covers selling software/SaaS (see ComplianceController).
 * Single flat list, not per-company: this describes the SaaS operator's
 * own legal registration, not a tenant's.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_activities', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20);
            $table->string('description_ar', 500);
            $table->string('description_en', 500)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_activities');
    }
};
