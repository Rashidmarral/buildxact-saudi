<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->boolean('is_personal')->default(false)->after('type');
            $table->string('personal_owner_name')->nullable()->after('is_personal');
        });
    }

    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropColumn(['is_personal', 'personal_owner_name']);
        });
    }
};
