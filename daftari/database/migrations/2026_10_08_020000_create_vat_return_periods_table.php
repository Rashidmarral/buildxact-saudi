<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "we have submit vat returns for 2 quarters and third we have paid now
 * so we have already some advance vat ... how to tackle this" — the
 * on-demand VAT report (Reports > VAT) recomputes output/input tax fresh
 * for whatever date range is picked, with no memory of the period before
 * it, so a company whose input VAT exceeded output VAT (a recoverable
 * credit, not a loss) has nowhere to record "this much is still owed to
 * me, carry it into next quarter". This table is a persisted record per
 * filed period — the figures frozen at creation time (same "computed
 * once, saved, never silently recalculated later" pattern as
 * ZakatCalculation) — chained by credit_brought_forward /
 * credit_carried_forward so each quarter picks up exactly where the last
 * one left off.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vat_return_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('output_tax', 12, 2);
            $table->decimal('input_tax_purchases', 12, 2);
            $table->decimal('expense_tax', 12, 2);
            $table->decimal('net_recoverable_input_tax', 12, 2);
            $table->decimal('credit_brought_forward', 12, 2)->default(0);
            $table->decimal('amount_payable', 12, 2);
            $table->decimal('credit_carried_forward', 12, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'period_start', 'period_end']);
            $table->index(['company_id', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vat_return_periods');
    }
};
