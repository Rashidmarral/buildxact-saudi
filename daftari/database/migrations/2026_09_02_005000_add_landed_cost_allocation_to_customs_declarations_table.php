<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customs_declarations', function (Blueprint $table) {
            $table->timestamp('landed_cost_allocated_at')->nullable()->after('vat_amount');
        });
    }

    public function down(): void
    {
        Schema::table('customs_declarations', function (Blueprint $table) {
            $table->dropColumn('landed_cost_allocated_at');
        });
    }
};
