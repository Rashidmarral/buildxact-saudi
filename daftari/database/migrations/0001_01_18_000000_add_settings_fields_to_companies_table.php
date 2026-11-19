<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('alternative_seller_id_type', 30)->nullable()->after('vat_number'); // commercial_registration, momra_license, mhrsd_license, passport_number, gcc_id, other_id
            $table->string('alternative_seller_id', 50)->nullable()->after('alternative_seller_id_type');
            $table->string('primary_customer_type', 20)->default('mixed')->after('alternative_seller_id'); // b2b, b2c, mixed
            $table->string('negative_number_format', 20)->default('minus')->after('primary_customer_type'); // minus, parentheses
            $table->foreignId('default_bank_account_id')->nullable()->after('default_branch_id')->constrained('bank_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_bank_account_id');
            $table->dropColumn(['alternative_seller_id_type', 'alternative_seller_id', 'primary_customer_type', 'negative_number_format']);
        });
    }
};
