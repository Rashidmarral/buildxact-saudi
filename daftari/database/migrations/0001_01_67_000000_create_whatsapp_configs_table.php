<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_configs', function (Blueprint $table) {
            $table->id();
            // One row per company — each company sends invoices/quotations
            // from ITS OWN WhatsApp Business number, same as each company
            // configures its own payment gateway credentials.
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('phone_number_id');
            $table->text('access_token');
            // WhatsApp's Cloud API can only send free-form text within a
            // 24-hour customer-service window; anything business-initiated
            // (like an invoice notification) legally requires a template
            // pre-approved in the company's own Meta Business account —
            // Daftari has no way to create or approve templates on a
            // company's behalf, so the company names its own template here.
            $table->string('template_name')->default('invoice_notification');
            $table->string('template_language', 10)->default('en_US');
            $table->boolean('is_enabled')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_configs');
    }
};
