<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bug report: deleting a payment voucher that a subcontract payment was
 * linked to crashed the subcontract's show page with "Attempt to read
 * property 'voucher_number' on null". Root cause: payment_vouchers uses
 * SoftDeletes, so deleting one never actually removes the row — it just
 * sets deleted_at — which means the subcontract_payments.payment_voucher_id
 * foreign key's nullOnDelete() (a real-DELETE-only trigger) never fires.
 * The row is left pointing at a voucher id that Eloquent's default
 * (non-trashed) query can no longer find, so ->paymentVoucher resolves to
 * null while payment_voucher_id stays set.
 *
 * PaymentVoucherController::destroy() now nulls this link itself going
 * forward. This migration is the one-time catch-up for subcontract
 * payments that were already left dangling by a voucher deleted before
 * that fix existed — no voucher or subcontract payment is deleted here,
 * only the stale reference is cleared, same as the FK was always meant
 * to do.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('subcontract_payments')
            ->whereNotNull('subcontract_payments.payment_voucher_id')
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('payment_vouchers')
                    ->whereColumn('payment_vouchers.id', 'subcontract_payments.payment_voucher_id')
                    ->whereNull('payment_vouchers.deleted_at');
            })
            ->update(['payment_voucher_id' => null]);
    }

    public function down(): void
    {
        // Intentionally irreversible — the original (dangling, broken)
        // reference isn't worth restoring.
    }
};
