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
        Schema::table('subscriptions', function (Blueprint $table) {
            // Dunning-ladder timestamps: current_period_end already anchors
            // "when did this become past due", so only the two later stages
            // need their own timestamp column.
            $table->timestamp('grace_period_ends_at')->nullable()->after('cancelled_at');
            $table->timestamp('suspended_at')->nullable()->after('grace_period_ends_at');

            // Comp (complimentary/free) accounts granted by a Super Admin —
            // bypasses nothing structurally, just documents why a
            // subscription is active without a matching Payment record.
            $table->boolean('is_comp')->default(false)->after('suspended_at');
            $table->string('comp_reason', 255)->nullable()->after('is_comp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['grace_period_ends_at', 'suspended_at', 'is_comp', 'comp_reason']);
        });
    }
};
