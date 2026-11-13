<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('pos_sale_prefix', 10)->default('POS')->after('next_payroll_run_number');
            $table->unsignedInteger('next_pos_sale_number')->default(1)->after('pos_sale_prefix');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['pos_sale_prefix', 'next_pos_sale_number']);
        });
    }
};
