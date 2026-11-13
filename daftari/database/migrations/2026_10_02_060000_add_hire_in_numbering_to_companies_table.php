<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('hire_in_contract_prefix', 10)->default('HC');
            $table->unsignedInteger('next_hire_in_contract_number')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['hire_in_contract_prefix', 'next_hire_in_contract_number']);
        });
    }
};
