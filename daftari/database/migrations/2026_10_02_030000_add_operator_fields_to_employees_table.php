<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->boolean('is_operator')->default(false)->after('department');
            $table->string('license_number', 30)->nullable()->after('is_operator');
            $table->date('license_expiry_date')->nullable()->after('license_number');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['is_operator', 'license_number', 'license_expiry_date']);
        });
    }
};
