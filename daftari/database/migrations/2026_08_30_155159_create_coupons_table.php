<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->text('description')->nullable();

            // 'percentage'/'fixed' = a one-time discount computed the
            // stated way; 'first_payment'/'recurring'/'limited_duration'
            // govern WHEN it re-applies across a company's payments (see
            // CouponService) — the amount is still computed from whichever
            // of percentage/fixed_amount below is set.
            $table->string('discount_type', 30); // percentage | fixed | first_payment | recurring | limited_duration
            $table->decimal('percentage', 5, 2)->nullable();
            $table->decimal('fixed_amount', 10, 2)->nullable();
            $table->string('currency', 3)->default('SAR');

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->unsignedInteger('max_uses')->nullable(); // null = unlimited
            $table->unsignedInteger('max_uses_per_company')->nullable(); // null = unlimited
            $table->decimal('min_subscription_amount', 10, 2)->nullable();

            // Only meaningful for discount_type = limited_duration: how
            // many of a company's payments this coupon discounts before it
            // stops applying on its own (separate from max_uses_per_company,
            // which is a hard ceiling regardless of type).
            $table->unsignedInteger('duration_in_cycles')->nullable();

            $table->boolean('new_customers_only')->default(false);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
