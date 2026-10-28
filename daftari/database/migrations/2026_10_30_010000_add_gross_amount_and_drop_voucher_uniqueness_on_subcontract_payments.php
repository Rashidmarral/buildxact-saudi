<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * amount is the net figure applied toward the contract/milestone
 * schedule — "i have paid the same amount with tax but i am paying my
 * supplier this amount without tax", so a voucher's real (VAT-
 * inclusive) transfer and what counts against a VAT-exclusive contract
 * value are deliberately allowed to differ. gross_amount is the real
 * cash figure for the receipt acknowledgement (defaults to amount when
 * there's no VAT difference to record). payment_voucher_id stops being
 * unique: "the payments i have made clear them as milestone total
 * milestone paid move to next" — one real payment can waterfall across
 * several milestones (see SubcontractController::allocateAcrossMilestones()),
 * which splits it into several rows sharing one voucher link, with the
 * gross recorded on exactly one of them so it isn't double-counted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subcontract_payments', function (Blueprint $table) {
            $table->decimal('gross_amount', 14, 2)->nullable()->after('amount');
            $table->dropUnique(['payment_voucher_id']);
            $table->index('payment_voucher_id');
        });
    }

    public function down(): void
    {
        Schema::table('subcontract_payments', function (Blueprint $table) {
            $table->dropIndex(['payment_voucher_id']);
            $table->unique('payment_voucher_id');
            $table->dropColumn('gross_amount');
        });
    }
};
