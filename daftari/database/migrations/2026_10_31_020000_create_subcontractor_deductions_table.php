<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "purchase return we have done in a wrong sense" — crediting a
 * subcontractor's bill for materials the company bought from a third
 * party and supplied to him isn't a Purchase Return: nothing was ever
 * bought from the subcontractor to return to him. It's its own kind
 * of document — a Subcontractor Deduction — reusing PurchaseReturn
 * only because it happened to post the right debits and credits, not
 * because it's the right label. This gives it a real, correctly-named
 * table instead.
 *
 * No VAT fields at all (unlike PurchaseReturn): a deduction against a
 * subcontractor's own bill is never itself a VAT-bearing document —
 * the VAT belongs to the real third-party supplier's own bill, already
 * captured there.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subcontractor_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bill_id')->constrained();
            $table->foreignId('supplier_id')->constrained();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('deduction_number');
            $table->date('issue_date');
            $table->string('reason');
            $table->string('status', 20)->default('issued'); // issued, void
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('SAR');
            $table->decimal('exchange_rate', 12, 6)->default(1);
            $table->timestamps();

            $table->unique(['company_id', 'deduction_number']);
            $table->index(['company_id', 'bill_id']);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->string('subcontractor_deduction_prefix', 10)->default('SD')->after('purchase_return_prefix');
            $table->unsignedInteger('next_subcontractor_deduction_number')->default(1)->after('next_purchase_return_number');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['subcontractor_deduction_prefix', 'next_subcontractor_deduction_number']);
        });

        Schema::dropIfExists('subcontractor_deductions');
    }
};
