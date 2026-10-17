<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->string('account_holder_name')->nullable()->after('bank_name');
            $table->string('bank_phone', 30)->nullable()->after('iban');
            $table->string('bank_address')->nullable()->after('bank_phone');
            $table->date('opening_balance_date')->nullable()->after('opening_balance');
            $table->string('opening_balance_reference')->nullable()->after('opening_balance_date');
        });
    }

    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropColumn(['account_holder_name', 'bank_phone', 'bank_address', 'opening_balance_date', 'opening_balance_reference']);
        });
    }
};
