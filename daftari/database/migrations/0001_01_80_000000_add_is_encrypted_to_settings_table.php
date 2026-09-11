<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            // Lets a handful of platform settings (S3 secret key, etc.) be
            // stored encrypted-at-rest through the same flat key/value
            // table, rather than needing a separate table just for secrets.
            // Existing rows default to false — they were never encrypted,
            // so nothing about their stored values needs to change.
            $table->boolean('is_encrypted')->default(false)->after('value');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('is_encrypted');
        });
    }
};
