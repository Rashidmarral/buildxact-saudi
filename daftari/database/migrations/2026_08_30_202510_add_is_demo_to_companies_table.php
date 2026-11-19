<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks a company as a safe demo/sandbox account (Module 23). Distinct
 * from Company::status ('active'/'suspended', a billing/access state) —
 * this flags a company whose data is deliberately shared/sample data, so
 * destructive actions, real payment processing, and real ZATCA
 * submissions are refused for it regardless of status. See
 * App\Support\DemoMode and App\Http\Middleware\PreventDemoDestruction.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('is_demo');
        });
    }
};
