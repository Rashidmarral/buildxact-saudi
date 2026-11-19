<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('wo_prefix', 10)->default('WO')->after('change_order_prefix');
            $table->unsignedInteger('next_wo_number')->default(1)->after('next_change_order_number');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['wo_prefix', 'next_wo_number']);
        });
    }
};
