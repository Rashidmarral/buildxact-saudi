<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A sales-side Debit Note — the mirror image of a Credit Note: it raises
 * what a customer owes on top of an already-issued invoice (e.g. an
 * under-billed charge, a price correction), rather than reducing it. Like
 * Credit Notes it must reference the original invoice (ZATCA's BR-KSA-56
 * requires a BillingReference on both credit and debit notes) and is a
 * real ZATCA-regulated document — unlike Purchase Return, which is a
 * buyer-side adjustment ZATCA never regulates.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('debit_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained();
            $table->foreignId('client_id')->constrained();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('debit_note_number');
            $table->date('issue_date');
            $table->string('reason')->nullable();
            $table->string('status', 20)->default('issued'); // issued, void
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('vat_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('currency', 3)->default('SAR');
            $table->text('qr_code')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'debit_note_number']);
            $table->index(['company_id', 'invoice_id']);
        });

        Schema::create('debit_note_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('debit_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tax_rate_id')->nullable()->constrained()->nullOnDelete();
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
        Schema::dropIfExists('debit_note_items');
        Schema::dropIfExists('debit_notes');
    }
};
