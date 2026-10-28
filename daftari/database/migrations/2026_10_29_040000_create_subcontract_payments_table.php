<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The trackable payment ledger for a subcontract. payment_voucher_id
 * links back to a real, already-posted Payment Voucher — "i have
 * already create payment vouchers against subcontractor can i add
 * payments records in module" — so this table never re-posts the GL;
 * it's a reporting/tracking layer on top of money that already moved. A
 * row with no voucher link is a manual entry for a payment made before
 * this module existed or outside the voucher system.
 *
 * amount is the net figure applied toward the contract/milestone
 * schedule — "i have paid the same amount with tax but i am paying my
 * supplier this amount without tax", so a voucher's real (VAT-
 * inclusive) transfer and what counts against a VAT-exclusive contract
 * value are deliberately allowed to differ. gross_amount is the real
 * cash figure for the receipt acknowledgement (defaults to amount when
 * there's no VAT difference to record). payment_voucher_id is not
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
        Schema::create('subcontract_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->foreignId('subcontract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('milestone_id')->nullable()->constrained('subcontract_milestones')->nullOnDelete();
            $table->foreignId('payment_voucher_id')->nullable()->constrained('payment_vouchers')->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->decimal('gross_amount', 14, 2)->nullable();
            $table->date('paid_at');
            $table->string('method', 30)->nullable();
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'subcontract_id']);
            $table->index('payment_voucher_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subcontract_payments');
    }
};
