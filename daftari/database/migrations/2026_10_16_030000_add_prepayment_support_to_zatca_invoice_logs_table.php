<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prepayment invoices (InvoiceTypeCode 386) submit to ZATCA through the
 * exact same clearance/reporting pipeline a regular invoice does — same
 * ICV counter, same PIH hash chain (ZatcaSyncService::nextIcv() already
 * counts every row in this table regardless of document type) — so
 * rather than stand up a whole parallel zatca_prepayment_invoice_logs
 * table/model (as CreditNote/DebitNote each got), this table is reused
 * directly: invoice_id becomes nullable and a sibling nullable
 * prepayment_invoice_id is added. Exactly one of the two is ever set on
 * a given row — enforced in ZatcaSyncService, not at the DB level,
 * since cross-column "exactly one of" constraints aren't portable
 * across this app's supported database drivers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zatca_invoice_logs', function (Blueprint $table) {
            $table->foreignId('prepayment_invoice_id')->nullable()->after('invoice_id')->constrained()->cascadeOnDelete();
        });

        // SQLite (used in tests) can't ALTER a column to drop NOT NULL
        // via a plain Blueprint change() the way MySQL can without the
        // doctrine/dbal package this app doesn't carry — recreate the
        // column nullable through a native statement instead.
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('zatca_invoice_logs', function (Blueprint $table) {
                $table->unsignedBigInteger('invoice_id')->nullable()->change();
            });
        } else {
            Schema::table('zatca_invoice_logs', function (Blueprint $table) {
                $table->foreignId('invoice_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        Schema::table('zatca_invoice_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('prepayment_invoice_id');
        });
    }
};
