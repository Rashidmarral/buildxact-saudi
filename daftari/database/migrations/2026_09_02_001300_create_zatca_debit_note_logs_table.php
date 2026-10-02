<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors zatca_credit_note_logs but for Debit Notes — its own table so
 * neither existing log table's NOT NULL foreign key needs widening.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zatca_debit_note_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('debit_note_id')->constrained()->cascadeOnDelete();
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
        Schema::dropIfExists('zatca_debit_note_logs');
    }
};
