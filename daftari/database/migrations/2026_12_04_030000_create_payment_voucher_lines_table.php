<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "can we add multiple entries on single transaction like we have
     * made a payment to supplier like 789.43 with that bank have charged
     * us it's service charges and vat ... how we can mange this" — a
     * voucher's existing `amount`/`counter_account_id` pair (unchanged,
     * still "amount paid to the main counterparty") stays the source of
     * truth for bill settlement, subcontract linkage and WHT — none of
     * that cares about an incidental bank fee. These rows are purely
     * EXTRA debit lines riding along in the same bank transaction (bank
     * charges, VAT on those charges, ...), each against its own GL
     * account — see PaymentVoucher::totalAmount()/extraLinesTotal() and
     * LedgerPostingService::postPaymentVoucher().
     */
    public function up(): void
    {
        Schema::create('payment_voucher_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_voucher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained();
            $table->decimal('amount', 14, 2);
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_voucher_lines');
    }
};
