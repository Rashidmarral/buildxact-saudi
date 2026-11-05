<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit finding GL-4: Company::accounting_lock_date (one rolling date,
 * checked by LedgerPostingService::post()/postManual()) is a real,
 * working control — enforcement is intentionally left untouched here,
 * since it's already battle-tested. What it lacked was a formal,
 * structured history: who closed which period, when, and — critically —
 * why it was ever reopened. This table is that history, additive only;
 * accounting_lock_date remains the single value the posting engine
 * actually checks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('period_closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('action', 10); // close, reopen
            $table->date('lock_date')->nullable(); // the accounting_lock_date value that resulted from this action (null = fully reopened)
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable(); // required on reopen, optional on close
            $table->timestamps();

            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('period_closures');
    }
};
