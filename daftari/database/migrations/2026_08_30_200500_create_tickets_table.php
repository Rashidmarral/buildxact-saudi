<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            // Nullable at the DB level only for the brief instant between
            // insert and Ticket::booted()'s created() callback, which
            // always backfills it from the row's own new id — see that
            // callback for why a DB-side counter isn't used instead.
            $table->string('ticket_number', 20)->nullable()->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject');
            $table->text('description');
            $table->string('priority', 10)->default('normal');
            $table->string('status', 20)->default('open');
            $table->foreignId('assigned_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_reply_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['status', 'priority']);
            $table->index('assigned_admin_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
