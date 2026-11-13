<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_letters', function (Blueprint $table) {
            $table->foreignId('machinery_hire_in_contract_id')->nullable()->after('machinery_rental_contract_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('company_letters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('machinery_hire_in_contract_id');
        });
    }
};
