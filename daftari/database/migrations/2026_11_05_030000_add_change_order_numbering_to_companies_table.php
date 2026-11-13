<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('change_order_prefix', 10)->default('CO')->after('pr_prefix');
            $table->unsignedInteger('next_change_order_number')->default(1)->after('next_pr_number');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['change_order_prefix', 'next_change_order_number']);
        });
    }
};
