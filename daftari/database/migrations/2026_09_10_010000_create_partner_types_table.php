<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-defined categories of partner (e.g. "Accountant Partner",
 * "Reseller", "Referral Partner") with their own commission rule. Every
 * value here is set by the operator from Admin -> Partner Program — the
 * request explicitly asked that commission rates never be hard-coded, so
 * there is no seeded default row and no default commission percentage
 * anywhere in code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_types', function (Blueprint $table) {
            $table->id();
            $table->string('name_en');
            $table->string('name_ar')->nullable();
            $table->string('slug')->unique();
            $table->string('commission_type', 10)->default('percentage'); // percentage, fixed
            $table->decimal('commission_value', 10, 2);
            $table->boolean('is_recurring')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_types');
    }
};
