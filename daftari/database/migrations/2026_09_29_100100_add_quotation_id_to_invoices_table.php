<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Nullable and non-unique, unlike quotations.converted_invoice_id
            // (a single scalar FK for the "whole quotation -> one invoice"
            // path) — a staged quotation links several invoices back to the
            // same quotation_id, one per payment-plan stage.
            $table->foreignId('quotation_id')->nullable()->after('client_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('quotation_id');
        });
    }
};
