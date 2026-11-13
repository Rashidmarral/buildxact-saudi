<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('lead_source')->nullable()->after('notes');
            $table->date('next_follow_up_date')->nullable()->after('lead_source');
            $table->timestamp('last_contacted_at')->nullable()->after('next_follow_up_date');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('lead_source')->nullable()->after('notes');
            $table->date('next_follow_up_date')->nullable()->after('lead_source');
            $table->timestamp('last_contacted_at')->nullable()->after('next_follow_up_date');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['lead_source', 'next_follow_up_date', 'last_contacted_at']);
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['lead_source', 'next_follow_up_date', 'last_contacted_at']);
        });
    }
};
