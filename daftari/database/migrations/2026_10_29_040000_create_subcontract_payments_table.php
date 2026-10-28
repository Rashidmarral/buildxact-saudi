<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The trackable payment ledger for a subcontract. payment_voucher_id
 * (nullable, unique) links back to a real, already-posted Payment
 * Voucher — "i have already create payment vouchers against
 * subcontractor can i add payments records in module" — so this table
 * never re-posts the GL; it's a reporting/tracking layer on top of
 * money that already moved. A row with no voucher link is a manual
 * entry for a payment made before this module existed or outside the
 * voucher system, kept separate from milestone_id's progress tracking.
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
            $table->foreignId('payment_voucher_id')->nullable()->unique()->constrained('payment_vouchers')->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('paid_at');
            $table->string('method', 30)->nullable();
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'subcontract_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subcontract_payments');
    }
};
