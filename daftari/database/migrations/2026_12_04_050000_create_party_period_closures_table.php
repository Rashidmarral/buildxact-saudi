<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The per-party mirror of period_closures — a structured history of
 * close/reopen actions against a single client's or supplier's own
 * lock_date column (see the sibling add_lock_date migration), not the
 * enforcement mechanism itself (that's LedgerPostingService's job).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('party_period_closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('party_type', 10); // client, supplier
            $table->unsignedBigInteger('party_id');
            $table->string('action', 10); // close, reopen
            $table->date('lock_date')->nullable(); // the resulting lock_date (null = fully reopened)
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable(); // required on reopen, optional on close
            $table->timestamps();

            $table->index(['company_id', 'party_type', 'party_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('party_period_closures');
    }
};
