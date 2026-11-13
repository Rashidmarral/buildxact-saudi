<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "please login and verify each and everything" (Hamly ERP) — Hamly maps
 * every real bank/cash account to its own ledger line, instead of every
 * bank-type account posting to one shared "Bank" account and every
 * cash-type account posting to one shared "Cash" account (how this app
 * worked until now — see LedgerPostingService::bankOrCashAccount()).
 * Nullable: an unlinked BankAccount keeps using the shared Default
 * Bank/Default Cash account, so this never blocks a transaction waiting
 * on setup — see BankAccount::glAccount().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->after('type')->constrained('accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('account_id');
        });
    }
};
