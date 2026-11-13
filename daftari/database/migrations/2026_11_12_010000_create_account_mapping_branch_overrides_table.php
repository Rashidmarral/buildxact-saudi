<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "please login and verify each and everything" (Hamly ERP) — Hamly's
 * account-mapping settings page repeats per business location/branch,
 * each one able to override the company-wide default for any key,
 * falling back to that default for anything left unset. Lives in a
 * separate table (rather than a nullable branch_id on account_mappings
 * itself) so the company-wide default keeps its existing, simple
 * unique(company_id, key) — a nullable branch_id in the same unique
 * index wouldn't reliably enforce "exactly one default row per key"
 * since SQL treats every NULL as distinct for uniqueness purposes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_mapping_branch_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('key', 50);
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'branch_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_mapping_branch_overrides');
    }
};
