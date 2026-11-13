<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "add more... Chart of Accounts tree view toggle" — the chart of
 * accounts was a completely flat list (type only), with no way to
 * group a sub-account under a parent the way a real chart of accounts
 * does (e.g. "Accumulated Depreciation" nested under "Fixed Assets").
 * Self-referencing and nullable: an account with no parent stays a
 * root of its type group, exactly as every existing account already
 * behaves — purely additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->foreignId('parent_account_id')->nullable()->after('type')->constrained('accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_account_id');
        });
    }
};
