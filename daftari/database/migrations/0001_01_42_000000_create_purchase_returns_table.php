<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Purchase Return" — the buyer-side mirror of a Credit Note, used when
     * we return goods to a supplier or they owe us a reduction. Deliberately
     * NOT a ZATCA "Debit Note": ZATCA e-invoicing only regulates documents a
     * business issues as the seller (Invoices, Credit Notes, and any Debit
     * Notes it issues to raise what a customer owes). A purchase return we
     * make against our own supplier is our supplier's obligation to report,
     * not ours — this stays purely an internal accounting document.
     */
    public function up(): void
    {
        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bill_id')->constrained();
            $table->foreignId('supplier_id')->constrained();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('return_number');
            $table->date('issue_date');
            $table->string('reason')->nullable();
            $table->string('status', 20)->default('issued'); // issued, void
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('vat_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('currency', 3)->default('SAR');
            $table->timestamps();

            $table->unique(['company_id', 'return_number']);
            $table->index(['company_id', 'bill_id']);
        });

        Schema::create('purchase_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bill_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('vat_rate', 5, 2)->default(15.00);
            $table->decimal('vat_amount', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_return_items');
        Schema::dropIfExists('purchase_returns');
    }
};
