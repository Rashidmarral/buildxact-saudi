<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deliberately NOT the generic polymorphic Attachment model (used for
 * invoices/bills/company documents, which store on the PUBLIC disk and
 * link with a direct, unauthenticated Storage::url()). Ticket attachments
 * cross the company/platform boundary by design and can carry sensitive
 * support content, so this module gives them their own storage on the
 * PRIVATE 'local' disk plus a controlled, authorization-checked download
 * route (see TicketController::downloadAttachment in both User and Admin
 * namespaces) instead of a guessable public URL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_reply_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->unsignedBigInteger('size');
            $table->string('mime_type', 100);
            // Mirrors the parent reply's is_internal_note — an attachment
            // on an internal note is itself internal and must never be
            // downloadable by the company (see downloadAttachment()).
            $table->boolean('is_internal')->default(false);
            $table->timestamps();

            $table->index(['ticket_id', 'is_internal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_attachments');
    }
};
