<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Security audit finding D-5: invoices, expenses, quotations, payment/
 * receipt vouchers, and customs declarations were all permanently
 * destroyed by their controllers' destroy() actions — for expenses and
 * customs declarations, even ones already posted to the ledger, whose
 * reversing entries would be left referencing a source document that no
 * longer exists anywhere, with no way to recover it after an accidental
 * click or a compromised account.
 *
 * Deliberately limited to these six: every other financial document
 * (bills, credit/debit notes, purchase orders, manual journal entries,
 * purchase returns) has no delete path in the app at all today, so
 * there's nothing here for SoftDeletes to protect.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['invoices', 'expenses', 'quotations', 'payment_vouchers', 'receipt_vouchers', 'customs_declarations'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        foreach (['invoices', 'expenses', 'quotations', 'payment_vouchers', 'receipt_vouchers', 'customs_declarations'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
