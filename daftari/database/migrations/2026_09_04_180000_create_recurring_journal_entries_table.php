<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('frequency', 20)->default('monthly'); // weekly, monthly, quarterly, yearly
            $table->date('start_date');
            $table->date('next_run_date');
            $table->date('end_date')->nullable();
            $table->string('status', 20)->default('active'); // active, paused, completed
            $table->timestamp('last_generated_at')->nullable();
            $table->unsignedInteger('generated_count')->default(0);
            $table->timestamps();

            $table->index(['company_id', 'status', 'next_run_date']);
        });

        Schema::create('recurring_journal_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recurring_journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->string('memo')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_journal_entry_lines');
        Schema::dropIfExists('recurring_journal_entries');
    }
};
