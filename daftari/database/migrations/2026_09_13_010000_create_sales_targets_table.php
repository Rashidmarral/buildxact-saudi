<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per week of the operator's own 90-day new-customer sales plan
 * (Admin -> Sales Center), holding editable weekly goals only — actual
 * progress is never stored here, it's computed live from Lead/Partner/
 * Payment each time the plan page renders, so the two can never drift.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_targets', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('week_number')->unique();
            $table->unsignedInteger('new_leads_target')->default(0);
            $table->unsignedInteger('demos_target')->default(0);
            $table->unsignedInteger('won_target')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_targets');
    }
};
