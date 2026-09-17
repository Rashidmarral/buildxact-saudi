<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item 9 of the Sales, Compliance & Business Growth request: "Done-For-You
 * Setup Package — configurable paid packages". A package is a productized
 * professional service (e.g. "we configure your ZATCA onboarding and
 * chart of accounts for you") the operator defines and prices here —
 * nothing is seeded, no price is hard-coded anywhere in code. price is
 * nullable so a package can be listed as "contact us for pricing"
 * instead of a fixed fee.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setup_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name_en');
            $table->string('name_ar')->nullable();
            $table->string('slug')->unique();
            $table->text('description_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->json('features_en')->nullable();
            $table->json('features_ar')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setup_packages');
    }
};
