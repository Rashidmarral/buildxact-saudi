<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "add income as we add expense for the whole not in the project flow
 * only" — a general-purpose way to record money received that isn't
 * tied to an invoice (e.g. scrap sale, bank interest, a miscellaneous
 * reimbursement), mirroring the Expense module: a category, which
 * account the money landed in, which GL income account it's booked to,
 * and an optional project tag — but reachable from its own page, not
 * only from inside Project Cash Flow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('income_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('payer_name')->nullable();
            $table->string('description')->nullable();
            $table->decimal('amount', 12, 2);
            $table->decimal('gross_amount', 12, 2);
            $table->decimal('vat_amount', 12, 2)->default(0);
            $table->string('tax_category', 20)->default('zero_rated');
            $table->string('reference')->nullable();
            $table->date('income_date');
            $table->string('status', 20)->default('received');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'income_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incomes');
    }
};
