<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Period-end revaluation of open foreign-currency AR/AP balances — the
 * missing piece next to the realized FX gain/loss that already posts when
 * a foreign-currency invoice/bill is paid (see LedgerPostingService::
 * appendFxDifference). Each run always measures against a document's own
 * booked exchange_rate, never a previous run's rate, so reversed_at marks
 * a run superseded by a newer one rather than letting adjustments compound.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fx_revaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->date('as_of_date');
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'as_of_date']);
        });

        Schema::create('fx_revaluation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fx_revaluation_id')->constrained()->cascadeOnDelete();
            $table->string('document_type'); // invoice | bill
            $table->unsignedBigInteger('document_id');
            $table->string('document_number');
            $table->string('party_name');
            $table->string('currency', 3);
            $table->decimal('foreign_balance', 14, 2);
            $table->decimal('booked_rate', 12, 6);
            $table->decimal('revaluation_rate', 12, 6);
            $table->decimal('booked_base_amount', 14, 2);
            $table->decimal('revalued_base_amount', 14, 2);
            $table->decimal('unrealized_gain_loss', 14, 2);
            $table->timestamps();

            $table->index(['fx_revaluation_id', 'document_type', 'document_id'], 'fx_revaluation_lines_document_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fx_revaluation_lines');
        Schema::dropIfExists('fx_revaluations');
    }
};
