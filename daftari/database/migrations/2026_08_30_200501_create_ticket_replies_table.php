<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One shared thread table for both customer-visible replies and internal
 * (staff-only) notes, distinguished by is_internal_note — matches how real
 * helpdesk systems (Zendesk, Intercom) keep both on one timeline. Every
 * query that renders this to a company user MUST filter
 * is_internal_note = false; see Ticket::publicReplies().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->boolean('is_internal_note')->default(false);
            $table->timestamps();

            $table->index(['ticket_id', 'is_internal_note']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_replies');
    }
};
