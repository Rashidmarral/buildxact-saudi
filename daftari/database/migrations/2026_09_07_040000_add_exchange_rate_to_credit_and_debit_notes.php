<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Same shape as invoices.exchange_rate / bills.exchange_rate: "1
        // unit of document currency = X units of the company's base
        // currency" — LedgerPostingService::postCreditNote()/postDebitNote()
        // previously posted subtotal/vat_total/total straight to the base-
        // currency ledger with no conversion at all, which is only correct
        // when the note's currency happens to equal the base currency.
        // Credit/debit notes are always created against a parent invoice,
        // so this is backfilled from it once and never recalculated later
        // (same immutable-snapshot convention as currency).
        Schema::table('credit_notes', function (Blueprint $table) {
            $table->decimal('exchange_rate', 12, 6)->default(1)->after('currency');
        });

        Schema::table('debit_notes', function (Blueprint $table) {
            $table->decimal('exchange_rate', 12, 6)->default(1)->after('currency');
        });

        // Same gap, same fix, on the purchase side: PurchaseReturn mirrors
        // CreditNote against a Bill instead of an Invoice.
        Schema::table('purchase_returns', function (Blueprint $table) {
            $table->decimal('exchange_rate', 12, 6)->default(1)->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('credit_notes', function (Blueprint $table) {
            $table->dropColumn('exchange_rate');
        });

        Schema::table('debit_notes', function (Blueprint $table) {
            $table->dropColumn('exchange_rate');
        });

        Schema::table('purchase_returns', function (Blueprint $table) {
            $table->dropColumn('exchange_rate');
        });
    }
};
