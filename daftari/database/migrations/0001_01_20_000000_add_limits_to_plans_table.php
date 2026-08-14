<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('max_customers')->nullable()->after('max_invoices_per_month'); // null = unlimited
            $table->unsignedInteger('max_suppliers')->nullable()->after('max_customers'); // null = unlimited
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['max_customers', 'max_suppliers']);
        });
    }
};
