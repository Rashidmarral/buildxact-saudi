<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('pipeline_stage', 20)->nullable()->after('last_contacted_at');
            $table->string('lost_reason', 255)->nullable()->after('pipeline_stage');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['pipeline_stage', 'lost_reason']);
        });
    }
};
