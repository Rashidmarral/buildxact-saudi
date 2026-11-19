<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('bank_account_id')->nullable()->after('expense_category_id')->constrained()->nullOnDelete();
            $table->foreignId('account_id')->nullable()->after('bank_account_id')->constrained()->nullOnDelete();
            $table->decimal('gross_amount', 12, 2)->nullable()->after('amount');
            $table->string('tax_category', 20)->default('standard_15')->after('vat_amount');
            $table->string('reference')->nullable()->after('tax_category');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_account_id');
            $table->dropConstrainedForeignId('account_id');
            $table->dropColumn(['gross_amount', 'tax_category', 'reference']);
        });
    }
};
