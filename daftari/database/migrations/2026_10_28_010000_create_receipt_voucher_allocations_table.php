<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "how can i allocate same invoice for different receipt voucher" /
 * "i need to show the exact amount i have received... there is one
 * transfer reference" — a ReceiptVoucher could only ever point at one
 * invoice (invoice_id/invoice_payment_id), so settling one real bank
 * transfer across several invoices meant recording it as several
 * separate vouchers — each with its own slice of the amount, none of
 * them matching what the client actually sent, or the one real
 * reference on their bank statement. A voucher can now carry a list of
 * allocations (one row per invoice it's settling, plus how much of
 * this one payment goes to each), while the voucher itself still
 * records the real, whole amount and its one real reference.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_voucher_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('receipt_voucher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained();
            // Null until this allocation is actually posted (e.g. a
            // voucher still pending approval) — mirrors receipt_vouchers.
            // invoice_payment_id's own null-until-settled convention.
            $table->foreignId('invoice_payment_id')->nullable()->constrained('invoice_payments')->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_voucher_allocations');
    }
};
