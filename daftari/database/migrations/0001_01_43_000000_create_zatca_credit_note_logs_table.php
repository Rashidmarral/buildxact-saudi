<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors zatca_invoice_logs but for Credit Notes. Kept as a separate table
 * (rather than widening zatca_invoice_logs' invoice_id to nullable) so the
 * existing invoice log table and its NOT NULL foreign key stay untouched.
 * Credit notes are still submitted through the same clearance/reporting
 * ZATCA endpoints as invoices — only the storage is split.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zatca_credit_note_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('credit_note_id')->constrained()->cascadeOnDelete();
            $table->string('environment', 20);
            $table->string('invoice_type', 10);
            $table->string('direction', 20);
            $table->string('status', 20)->default('queued');
            $table->uuid('request_uuid');
            $table->string('invoice_hash', 128)->nullable();
            $table->string('previous_invoice_hash', 128)->nullable();
            $table->longText('xml_payload')->nullable();
            $table->longText('cryptographic_stamp')->nullable();
            $table->longText('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('cleared_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'environment']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zatca_credit_note_logs');
    }
};
