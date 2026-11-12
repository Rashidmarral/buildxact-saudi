<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "clean... test client... linked with quotation invoice or bill that
 * will be deleted also" — the super-admin company data reset tool needs
 * to be able to delete a Client even when an Invoice or Quotation still
 * references it, since Invoices are deliberately the one document type
 * that reset never deletes. Both columns were previously a plain
 * required, no-action foreign key, which blocked the delete outright.
 *
 * An Invoice already has exactly the mechanism built for this —
 * party_snapshot (see Invoice::capturePartySnapshot()/partyForDocument())
 * freezes the client's name/VAT/address the moment the invoice is
 * issued, specifically so its own display/PDF/ZATCA XML never depends on
 * the live Client record surviving. Quotations have no such snapshot, so
 * a quotation whose client gets deleted this way falls back to a plain
 * "Deleted client" placeholder instead (see Invoice::displayClientName()
 * and the matching helper added to Quotation).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('client_id')->nullable()->change();
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreign('client_id')->references('id')->on('clients')->nullOnDelete();
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
        });
        Schema::table('quotations', function (Blueprint $table) {
            $table->unsignedBigInteger('client_id')->nullable()->change();
        });
        Schema::table('quotations', function (Blueprint $table) {
            $table->foreign('client_id')->references('id')->on('clients')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('client_id')->nullable(false)->change();
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreign('client_id')->references('id')->on('clients');
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
        });
        Schema::table('quotations', function (Blueprint $table) {
            $table->unsignedBigInteger('client_id')->nullable(false)->change();
        });
        Schema::table('quotations', function (Blueprint $table) {
            $table->foreign('client_id')->references('id')->on('clients');
        });
    }
};
