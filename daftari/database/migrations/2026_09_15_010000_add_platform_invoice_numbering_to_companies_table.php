<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A separate, independent numbering sequence for the real ZATCA tax
 * invoices PlatformInvoiceService generates for subscription payments —
 * requested directly by the operator, who runs a second real business
 * (asphalt paving/machine rental) through the same company account and
 * doesn't want subscription invoices interleaved with that business's
 * own invoice numbers. Mirrors the existing next_credit_note_number/
 * next_debit_note_number pattern: its own counter, so neither series
 * ever skips a number because of the other. The prefix itself lives in
 * Setting ('platform_billing_invoice_prefix', default 'SUB') alongside
 * platform_billing_company_id, since it's an operator/platform-level
 * choice, not something an ordinary tenant user edits.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->unsignedInteger('next_platform_invoice_number')->default(1)->after('next_invoice_number');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('next_platform_invoice_number');
        });
    }
};
