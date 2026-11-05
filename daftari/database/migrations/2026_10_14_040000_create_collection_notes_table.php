<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A log of collection calls/follow-ups on a client chasing an overdue
 * balance, with an optional promise-to-pay — the automated email
 * dunning ladder (SendOverdueInvoiceReminders) covers the reminder
 * itself, but there was nowhere to record "spoke to them, they promised
 * 5,000 by the 20th" so the next person chasing the same client isn't
 * starting from zero.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->text('note');
            $table->date('promise_to_pay_date')->nullable();
            $table->decimal('promise_to_pay_amount', 12, 2)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_notes');
    }
};
