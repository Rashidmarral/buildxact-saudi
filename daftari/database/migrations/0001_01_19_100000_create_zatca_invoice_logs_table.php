<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zatca_invoice_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('environment', 20); // developer, simulation, production
            $table->string('invoice_type', 10); // b2b (standard/clearance), b2c (simplified/reporting)
            $table->string('direction', 20); // clearance, reporting
            $table->string('status', 20)->default('queued'); // queued, cleared, reported, failed
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
        Schema::dropIfExists('zatca_invoice_logs');
    }
};
