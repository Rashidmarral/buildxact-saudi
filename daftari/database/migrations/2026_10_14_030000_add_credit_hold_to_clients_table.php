<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->boolean('credit_hold')->default(false)->after('next_follow_up_date');
            $table->string('credit_hold_reason', 255)->nullable()->after('credit_hold');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['credit_hold', 'credit_hold_reason']);
        });
    }
};
