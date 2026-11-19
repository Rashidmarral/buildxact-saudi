<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "please login and verify each and everything" (Hamly ERP) — Hamly
 * tracks post-dated cheques as their own sub-ledger (cheques received
 * held under collection, cheques issued not yet cleared) instead of
 * treating a cheque as cash the moment it's handed over. method='cheque'
 * already existed on both voucher types but posted straight to the bank
 * account like a same-day transfer — these three fields let
 * LedgerPostingService hold it in a dedicated account (1270/2240) until
 * someone explicitly marks it cleared (see
 * ReceiptVoucherController::markChequeCleared() /
 * PaymentVoucherController::markChequeCleared()).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['receipt_vouchers', 'payment_vouchers'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('cheque_number', 50)->nullable()->after('method');
                $blueprint->date('cheque_date')->nullable()->after('cheque_number');
                $blueprint->date('cheque_cleared_at')->nullable()->after('cheque_date');
            });
        }
    }

    public function down(): void
    {
        foreach (['receipt_vouchers', 'payment_vouchers'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn(['cheque_number', 'cheque_date', 'cheque_cleared_at']);
            });
        }
    }
};
