<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('asset_code');
            $table->string('name');
            $table->string('category')->nullable();
            $table->date('acquisition_date');
            $table->decimal('acquisition_cost', 14, 2);
            $table->decimal('salvage_value', 14, 2)->default(0);
            $table->unsignedSmallInteger('useful_life_years');
            // Straight-line only for v1 — the overwhelming majority of
            // small/mid Saudi businesses depreciate this way; reducing-
            // balance or units-of-production would need a real per-method
            // schedule engine, not a one-line rate.
            $table->string('depreciation_method', 20)->default('straight_line');
            $table->decimal('accumulated_depreciation', 14, 2)->default(0);
            $table->string('status', 20)->default('active'); // active, disposed
            $table->date('disposed_at')->nullable();
            $table->decimal('disposal_proceeds', 14, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'asset_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_assets');
    }
};
