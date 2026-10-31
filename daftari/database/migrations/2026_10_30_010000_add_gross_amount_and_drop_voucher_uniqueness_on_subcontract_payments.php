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
 *
 * Every step is guarded with an existence check: on MySQL this first
 * failed partway through on the operator's real database (error 1553 —
 * dropping the unique index while it was still the only index covering
 * the payment_voucher_id foreign key), which left gross_amount already
 * added but the index swap undone, and — since MySQL DDL isn't
 * transactional — the migration unrecorded as run. Re-running it from
 * scratch has to tolerate that exact half-applied state as cleanly as a
 * fully fresh table.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('subcontract_payments', 'gross_amount')) {
            Schema::table('subcontract_payments', function (Blueprint $table) {
                $table->decimal('gross_amount', 14, 2)->nullable()->after('amount');
            });
        }

        // The replacement plain index must exist BEFORE the unique one
        // is dropped — MySQL requires some index covering a foreign key
        // column at all times, and each of these runs as its own
        // separate ALTER TABLE statement, not one combined statement.
        if (! Schema::hasIndex('subcontract_payments', 'subcontract_payments_payment_voucher_id_index')) {
            Schema::table('subcontract_payments', function (Blueprint $table) {
                $table->index('payment_voucher_id', 'subcontract_payments_payment_voucher_id_index');
            });
        }

        if (Schema::hasIndex('subcontract_payments', 'subcontract_payments_payment_voucher_id_unique')) {
            Schema::table('subcontract_payments', function (Blueprint $table) {
                $table->dropUnique('subcontract_payments_payment_voucher_id_unique');
            });
        }
    }

    public function down(): void
    {
        // Same ordering constraint in reverse: add the unique index
        // before dropping the plain one, so the FK always has coverage.
        if (! Schema::hasIndex('subcontract_payments', 'subcontract_payments_payment_voucher_id_unique')) {
            Schema::table('subcontract_payments', function (Blueprint $table) {
                $table->unique('payment_voucher_id');
            });
        }

        if (Schema::hasIndex('subcontract_payments', 'subcontract_payments_payment_voucher_id_index')) {
            Schema::table('subcontract_payments', function (Blueprint $table) {
                $table->dropIndex('subcontract_payments_payment_voucher_id_index');
            });
        }

        if (Schema::hasColumn('subcontract_payments', 'gross_amount')) {
            Schema::table('subcontract_payments', function (Blueprint $table) {
                $table->dropColumn('gross_amount');
            });
        }
    }
};
